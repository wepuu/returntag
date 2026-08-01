import AxeBuilder from '@axe-core/playwright';
import { adminTest as test, expect } from './fixtures';

async function installApiMock(
	page: import('@playwright/test').Page,
	batchCode: string,
	checksum: string,
	csv: string
) {
	await page.addInitScript(
		( fixture ) => {
			let exported = false;
			const nativeFetch = window.fetch.bind( window );
			const json = ( body: unknown, status = 200 ) =>
				new Response( JSON.stringify( body ), {
					status,
					headers: { 'Content-Type': 'application/json' },
				} );

			window.fetch = async (
				input: RequestInfo | URL,
				init?: RequestInit
			) => {
				const request = input instanceof Request ? input : null;
				const url = new URL(
					request?.url ?? input.toString(),
					window.location.origin
				);
				if (
					/^\/wp-json\/tagcore\/v1\/batches\/\d+$/.test(
						url.pathname
					)
				) {
					return json( {
						batch_id: 1,
						batch_code: fixture.batchCode,
						tag_type: 'classic_tag',
						model_code: 'RT207-CLASSIC',
						smart_network: 'none',
						manufacturer: null,
						sales_channel: 'direct',
						requested_quantity: 2,
						generated_quantity: 2,
						batch_status: exported ? 'exported' : 'generated',
						activation_enabled: false,
						notes: null,
						created_by: 1,
						created_at: '2026-07-28T10:00:00+00:00',
						updated_at: '2026-07-28T10:00:00+00:00',
					} );
				}

				if ( url.pathname.endsWith( '/generation' ) ) {
					return json( {
						batch_id: 1,
						batch_status: exported ? 'exported' : 'generated',
						requested_quantity: 2,
						generated_quantity: 2,
						remaining_quantity: 0,
						failed_quantity: 0,
						progress_percent: 100,
						started_at: '2026-07-28T09:59:00+00:00',
						completed_at: '2026-07-28T10:00:00+00:00',
						last_progress_at: '2026-07-28T10:00:00+00:00',
						queue_state: 'complete',
						can_start: false,
						can_retry: false,
						poll_after_ms: 0,
					} );
				}

				if ( url.pathname.endsWith( '/tags' ) ) {
					return json( {
						items: [
							{
								tag_id: '234567',
								tag_status: 'unregistered',
								created_at: '2026-07-28T10:00:00+00:00',
							},
							{
								tag_id: '234568',
								tag_status: 'unregistered',
								created_at: '2026-07-28T10:00:00+00:00',
							},
						],
						next_cursor: null,
					} );
				}

				if ( url.pathname.endsWith( '/lifecycle' ) ) {
					return json( {
						batch_id: 1,
						batch_code: fixture.batchCode,
						batch_status: exported ? 'exported' : 'generated',
						activation_enabled: false,
						global_activation_enabled: true,
						effective_activation_enabled: false,
						release_ready: exported,
						tag_counts: {
							total: 2,
							unregistered: 2,
							active: 0,
							suspended: 0,
							retired: 0,
						},
						updated_at: '2026-07-28T10:00:00+00:00',
						changed: false,
					} );
				}

				if ( url.pathname.endsWith( '/exports' ) ) {
					const method = (
						init?.method ??
						request?.method ??
						'GET'
					).toUpperCase();

					if ( method === 'POST' ) {
						exported = true;
						return new Response( fixture.csv, {
							status: 200,
							headers: {
								'Content-Type': 'text/csv; charset=UTF-8',
								'Content-Disposition': `attachment; filename="tagcore-${ fixture.batchCode }-v1.csv"`,
								'X-ReturnTag-Export-Version': '1',
								'X-ReturnTag-Row-Count': '2',
								'X-ReturnTag-SHA256': fixture.checksum,
								'X-ReturnTag-Created-At':
									'2026-07-28T10:05:00+00:00',
								'X-ReturnTag-Batch-Status': 'exported',
							},
						} );
					}

					return json( {
						items: exported
							? [
									{
										export_version: 1,
										row_count: 2,
										file_format: 'csv',
										file_checksum: fixture.checksum,
										created_by: 1,
										created_by_name: 'admin',
										created_at: '2026-07-28T10:05:00+00:00',
									},
							  ]
							: [],
						next_cursor: null,
					} );
				}

				return nativeFetch( input, init );
			};
		},
		{ batchCode, checksum, csv }
	);
}

test( 'an operator confirms an audited CSV export and sees its history', async ( {
	page,
} ) => {
	test.slow();
	const batchCode = `RT-207-E2E-${ Date.now() }`;
	const checksum = 'a'.repeat( 64 );
	const csv = [
		'sequence_no,batch_code,tag_id,tag_type,model_code,smart_network,qr_url',
		`1,${ batchCode },234567,classic_tag,RT207-CLASSIC,,https://returntag.com/t/234567`,
		`2,${ batchCode },234568,classic_tag,RT207-CLASSIC,,https://returntag.com/t/234568`,
		'',
	].join( '\r\n' );
	await installApiMock( page, batchCode, checksum, csv );
	await page.goto(
		'/wp-admin/admin.php?page=tagcore-batches&view=detail&batch_id=1',
		{ waitUntil: 'domcontentloaded' }
	);

	const exportButton = page.getByRole( 'button', { name: 'Export CSV' } );
	await expect( exportButton ).toBeVisible( { timeout: 45_000 } );
	await exportButton.focus();
	await page.keyboard.press( 'Enter' );

	const dialog = page.getByRole( 'dialog', {
		name: 'Confirm CSV export',
	} );
	await expect( dialog ).toBeVisible();
	await expect(
		dialog.getByText( 'This marks the Batch as exported.' )
	).toBeVisible();

	const confirmExport = dialog.getByRole( 'button', {
		name: 'Export CSV',
	} );
	await expect( confirmExport ).toBeEnabled();
	await confirmExport.focus();
	await page.keyboard.press( 'Enter' );

	await expect(
		page.getByText(
			'CSV version 1 was prepared with 2 Tag IDs and the download has started.',
			{ exact: true }
		)
	).toBeVisible();
	await expect(
		page.getByRole( 'button', { name: 'Re-export CSV' } )
	).toBeVisible();
	await expect(
		page.locator( '.returntag-export-table tbody tr' )
	).toHaveCount( 1 );
	await expect(
		page.locator( '.returntag-export-table' ).getByText( checksum )
	).toBeVisible();

	const accessibilityScanResults = await new AxeBuilder( { page } )
		.include( '.returntag-admin' )
		.analyze();
	expect( accessibilityScanResults.violations ).toEqual( [] );
} );
