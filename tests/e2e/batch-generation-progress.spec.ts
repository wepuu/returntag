import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const tagAlphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

const inventoryItems = Array.from( { length: 51 }, ( _, index ) => ( {
	tag_id: `2222${ tagAlphabet[ Math.floor( index / 32 ) ] }${
		tagAlphabet[ index % 32 ]
	}`,
	tag_status: 'unregistered',
	created_at: '2026-07-27T09:00:00+00:00',
} ) );

async function logIn( page: import('@playwright/test').Page ) {
	await page.goto( '/wp-login.php', { waitUntil: 'domcontentloaded' } );
	await page.getByLabel( 'Username or Email Address' ).fill( 'admin' );
	await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );
	await Promise.all( [
		page.waitForURL( /\/wp-admin(?:\/|$)/, {
			waitUntil: 'domcontentloaded',
		} ),
		page.getByRole( 'button', { name: 'Log In' } ).click(),
	] );
}

async function createSmallBatch(
	page: import('@playwright/test').Page,
	batchCode: string
) {
	await page.goto( '/wp-admin/admin.php?page=tagcore-batches&view=create', {
		waitUntil: 'domcontentloaded',
	} );
	await page.getByLabel( /Batch Code/ ).fill( batchCode );
	await page.getByLabel( 'Classic tag' ).check();
	await page.getByLabel( /Model code/ ).fill( 'RT205-CLASSIC' );
	await page.getByLabel( /Requested quantity/ ).fill( '51' );
	const [ createResponse ] = await Promise.all( [
		page.waitForResponse(
			( response ) =>
				response.request().method() === 'POST' &&
				response.url().includes( '/wp-json/tagcore/v1/batches' ),
			{ timeout: 30_000 }
		),
		page.getByRole( 'button', { name: 'Create draft batch' } ).click(),
	] );
	expect( createResponse.status() ).toBe( 201 );
	await expect(
		page.getByRole( 'heading', { name: 'Batch created' } )
	).toBeVisible();
	await Promise.all( [
		page.waitForURL( /view=detail&batch_id=\d+/, {
			waitUntil: 'domcontentloaded',
			timeout: 30_000,
		} ),
		page.getByRole( 'link', { name: 'Review and generate IDs' } ).click(),
	] );
	await expect(
		page.getByRole( 'heading', { name: batchCode } )
	).toBeVisible( { timeout: 30_000 } );
}

test( 'an operator confirms generation and sees committed progress', async ( {
	page,
} ) => {
	test.slow();
	await logIn( page );
	const batchCode = `RT-205-E2E-${ Date.now() }`;
	await createSmallBatch( page, batchCode );

	await page.route(
		/\/wp-json\/tagcore\/v1\/batches\/\d+\/generation(?:\?.*)?$/,
		async ( route ) => {
			if ( route.request().method() === 'POST' ) {
				await route.fulfill( {
					status: 202,
					contentType: 'application/json',
					body: JSON.stringify( {
						batch_id: 1,
						batch_status: 'generating',
						generated_quantity: 0,
						queue_status: 'queued',
					} ),
				} );
				return;
			}

			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( {
					batch_id: 1,
					batch_status: 'generated',
					requested_quantity: 51,
					generated_quantity: 51,
					remaining_quantity: 0,
					failed_quantity: 0,
					progress_percent: 100,
					started_at: '2026-07-27T08:59:00+00:00',
					completed_at: '2026-07-27T09:00:00+00:00',
					last_progress_at: '2026-07-27T09:00:00+00:00',
					queue_state: 'complete',
					can_start: false,
					can_retry: false,
					poll_after_ms: 0,
				} ),
			} );
		}
	);
	await page.route(
		/\/wp-json\/tagcore\/v1\/batches\/\d+\/tags(?:\?.*)?$/,
		async ( route ) => {
			const cursor = new URL( route.request().url() ).searchParams.get(
				'cursor'
			);
			const items = cursor
				? inventoryItems.slice( 50 )
				: inventoryItems.slice( 0, 50 );

			await route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( {
					items,
					next_cursor: cursor ? null : 'mock-next',
				} ),
			} );
		}
	);

	const trigger = page.getByRole( 'button', { name: 'Generate Tag IDs' } );
	await trigger.focus();
	await expect( trigger ).toBeFocused();
	await page.keyboard.press( 'Enter' );

	const dialog = page.getByRole( 'dialog', {
		name: 'Confirm Tag ID generation',
	} );
	await expect( dialog ).toBeVisible();
	await expect(
		dialog.getByText( 'This creates permanent public Tag IDs.' )
	).toBeVisible();
	await expect( dialog.getByText( 'Remains disabled' ) ).toBeVisible();

	const cancelButton = dialog.getByRole( 'button', { name: 'Cancel' } );
	await cancelButton.focus();
	await page.keyboard.press( 'Enter' );
	await expect( dialog ).toBeHidden();
	await expect( trigger ).toBeFocused();
	await expect( page.getByText( 'Draft', { exact: true } ) ).toBeVisible();

	await page.keyboard.press( 'Enter' );
	const confirmButton = dialog.getByRole( 'button', {
		name: 'Generate 51 Tag IDs',
	} );
	await confirmButton.focus();
	const [ generationResponse ] = await Promise.all( [
		page.waitForResponse(
			( response ) =>
				response.request().method() === 'POST' &&
				/\/wp-json\/tagcore\/v1\/batches\/\d+\/generation/.test(
					response.url()
				),
			{ timeout: 30_000 }
		),
		page.keyboard.press( 'Enter' ),
	] );
	expect( generationResponse.status() ).toBe( 202 );

	await expect(
		page
			.locator( '.returntag-admin' )
			.getByText( 'Tag ID generation was scheduled successfully.' )
	).toBeVisible( { timeout: 30_000 } );
	await expect(
		page.getByRole( 'heading', { name: 'Tag ID generation' } )
	).toBeVisible();
	await expect( page.locator( '.returntag-generation-count' ) ).toContainText(
		'of 51 Tag IDs generated'
	);
	await expect(
		page.getByText( 'Failed IDs', { exact: true } )
	).toBeVisible();
	await expect(
		page.getByText( 'Activation', { exact: true } )
	).toBeVisible();
	await expect( page.getByText( 'Disabled', { exact: true } ) ).toBeVisible();

	await expect(
		page.getByRole( 'heading', { name: 'Generated Tag IDs' } )
	).toBeVisible( { timeout: 60_000 } );
	const inventoryRows = page.locator( '.returntag-inventory-table tbody tr' );
	await expect( inventoryRows ).toHaveCount( 50 );
	await expect(
		page.getByText( '50 of 51 loaded', { exact: true } )
	).toBeVisible();

	const loadMore = page.getByRole( 'button', {
		name: 'Load more Tag IDs',
	} );
	await loadMore.focus();
	await page.keyboard.press( 'Enter' );
	await expect( inventoryRows ).toHaveCount( 51 );
	await expect( inventoryRows.nth( 50 ) ).toBeFocused();
	await expect(
		page.getByText( '51 of 51 loaded', { exact: true } )
	).toBeVisible();

	const accessibilityScanResults = await new AxeBuilder( { page } )
		.include( '.returntag-admin' )
		.analyze();
	expect( accessibilityScanResults.violations ).toEqual( [] );
} );
