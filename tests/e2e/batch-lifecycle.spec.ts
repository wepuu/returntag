import AxeBuilder from '@axe-core/playwright';
import { adminTest as test, expect } from './fixtures';

async function installLifecycleMock(
	page: import('@playwright/test').Page,
	batchCode: string
) {
	await page.addInitScript(
		( fixture ) => {
			let status = 'exported';
			let activationEnabled = false;
			const nativeFetch = window.fetch.bind( window );
			const json = ( body: unknown, responseStatus = 200 ) =>
				new Response( JSON.stringify( body ), {
					status: responseStatus,
					headers: { 'Content-Type': 'application/json' },
				} );
			const lifecycle = ( changed = false ) => ( {
				batch_id: 1,
				batch_code: fixture.batchCode,
				batch_status: status,
				activation_enabled: activationEnabled,
				global_activation_enabled: true,
				effective_activation_enabled:
					status === 'released' && activationEnabled,
				release_ready: true,
				tag_counts: {
					total: 2,
					unregistered: 1,
					active: 1,
					suspended: 0,
					retired: 0,
				},
				updated_at: '2026-07-28T10:10:00+00:00',
				changed,
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
						model_code: 'RT208-CLASSIC',
						smart_network: 'none',
						manufacturer: null,
						sales_channel: 'direct',
						requested_quantity: 2,
						generated_quantity: 2,
						batch_status: status,
						activation_enabled: activationEnabled,
						notes: null,
						created_by: 1,
						created_at: '2026-07-28T10:00:00+00:00',
						updated_at: '2026-07-28T10:10:00+00:00',
					} );
				}

				if ( url.pathname.endsWith( '/generation' ) ) {
					return json( {
						batch_id: 1,
						batch_status: status,
						requested_quantity: 2,
						generated_quantity: 2,
						remaining_quantity: 0,
						failed_quantity: 0,
						progress_percent: 100,
						started_at: '2026-07-28T09:59:00+00:00',
						completed_at: '2026-07-28T10:00:00+00:00',
						last_progress_at: '2026-07-28T10:10:00+00:00',
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
								tag_status: 'active',
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

				if ( url.pathname.endsWith( '/exports' ) ) {
					return json( {
						items: [
							{
								export_version: 1,
								row_count: 2,
								file_format: 'csv',
								file_checksum: 'a'.repeat( 64 ),
								created_by: 1,
								created_by_name: 'admin',
								created_at: '2026-07-28T10:05:00+00:00',
							},
						],
						next_cursor: null,
					} );
				}

				if ( url.pathname.endsWith( '/lifecycle' ) ) {
					return json( lifecycle() );
				}

				if ( url.pathname.endsWith( '/release' ) ) {
					status = 'released';
					activationEnabled = true;
					return json( lifecycle( true ) );
				}

				if ( url.pathname.endsWith( '/suspend' ) ) {
					status = 'suspended';
					activationEnabled = false;
					return json( lifecycle( true ) );
				}

				if ( url.pathname.endsWith( '/void' ) ) {
					status = 'voided';
					activationEnabled = false;
					return json( lifecycle( true ) );
				}

				return nativeFetch( input, init );
			};
		},
		{ batchCode }
	);
}

test( 'an operator releases, suspends, and permanently voids a Batch', async ( {
	page,
} ) => {
	test.slow();
	const batchCode = `RT-208-E2E-${ Date.now() }`;
	await installLifecycleMock( page, batchCode );
	await page.goto(
		'/wp-admin/admin.php?page=tagcore-batches&view=detail&batch_id=1',
		{ waitUntil: 'domcontentloaded' }
	);

	const release = page.getByRole( 'button', { name: 'Release Batch' } );
	await expect( release ).toBeVisible( { timeout: 45_000 } );
	await release.click();
	await page
		.getByRole( 'dialog', { name: 'Release Batch' } )
		.getByRole( 'button', { name: 'Release Batch' } )
		.click();
	await expect( page.getByText( 'Activation available' ) ).toBeVisible();

	await page.getByRole( 'button', { name: 'Suspend Batch' } ).click();
	const suspendDialog = page.getByRole( 'dialog', {
		name: 'Suspend Batch',
	} );
	await expect(
		suspendDialog.getByText(
			'Already active owners keep access. Generated Tag IDs and export history remain retained.'
		)
	).toBeVisible();
	await suspendDialog
		.getByRole( 'button', { name: 'Suspend Batch' } )
		.click();
	await expect( page.getByText( 'Activation unavailable' ) ).toBeVisible();

	await page.getByRole( 'button', { name: 'Void Batch' } ).click();
	const voidDialog = page.getByRole( 'dialog', {
		name: 'Void Batch permanently',
	} );
	const voidButton = voidDialog.getByRole( 'button', {
		name: 'Void Batch',
	} );
	await expect( voidButton ).toBeDisabled();
	await voidDialog
		.getByLabel( `Enter ${ batchCode } to confirm permanent void` )
		.fill( batchCode );
	await expect( voidButton ).toBeEnabled();
	await voidButton.click();

	await expect(
		page.getByText(
			'This Batch is permanently voided. Its Tag IDs remain retained and can never be reused.'
		)
	).toBeVisible();
	const activeFact = page
		.locator( '.returntag-lifecycle-facts > div' )
		.filter( {
			has: page.getByText( 'Active', { exact: true } ),
		} );
	await expect( activeFact.locator( 'dd' ) ).toHaveText( '1' );

	const accessibilityScanResults = await new AxeBuilder( { page } )
		.include( '.returntag-admin' )
		.analyze();
	expect( accessibilityScanResults.violations ).toEqual( [] );
} );
