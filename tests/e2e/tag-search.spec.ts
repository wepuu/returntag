import { expect, test } from '@playwright/test';

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

test( 'an authorized operator performs a read-only exact Tag search', async ( {
	page,
} ) => {
	await page.route( '**/wp-json/tagcore/v1/tags?*', async ( route ) => {
		await route.fulfill( {
			json: {
				items: [
					{
						tag_id: '234567',
						batch_id: 7,
						batch_code: 'RT-209-E2E',
						batch_status: 'voided',
						batch_activation_enabled: false,
						activation_availability: 'blocked_batch_voided',
						tag_type: 'classic_tag',
						model_code: 'CLASSIC-01',
						tag_status: 'unregistered',
						lost_mode: false,
						activated_at: null,
						created_at: '2026-07-29T08:00:00+00:00',
						updated_at: '2026-07-29T08:00:00+00:00',
					},
				],
				next_cursor: null,
				context: {
					global_activation_enabled: true,
				},
			},
		} );
	} );

	await logIn( page );
	await page.goto( '/wp-admin/admin.php?page=tagcore-tags', {
		waitUntil: 'domcontentloaded',
	} );
	await expect( page.getByRole( 'heading', { name: 'Tags' } ) ).toBeVisible();
	await page
		.getByRole( 'textbox', { name: 'Tag ID', exact: true } )
		.fill( '23-45 67' );
	await page.getByRole( 'button', { name: 'Search tags' } ).click();

	await expect( page.getByText( '234567', { exact: true } ) ).toBeVisible();
	await expect(
		page.getByText( 'RT-209-E2E', { exact: true } )
	).toBeVisible();
	await expect( page.getByText( 'Voided', { exact: true } ) ).toBeVisible();
	await expect(
		page.getByText( 'Permanently blocked — Batch voided', {
			exact: true,
		} )
	).toBeVisible();
	await expect(
		page.getByText( 'Never activated', { exact: true } )
	).toBeVisible();
	await expect(
		page.getByRole( 'link', { name: 'RT-209-E2E', exact: true } )
	).toHaveAttribute(
		'href',
		/http:\/\/localhost:8888\/wp-admin\/admin\.php\?page=tagcore-batches&view=detail&batch_id=7/
	);
	await expect(
		page.getByRole( 'button', { name: /edit|delete|activate/i } )
	).toHaveCount( 0 );
} );

test( 'a new search clears stale results while the request is pending', async ( {
	page,
} ) => {
	let requestCount = 0;

	await page.route( '**/wp-json/tagcore/v1/tags?*', async ( route ) => {
		requestCount += 1;

		if ( requestCount > 1 ) {
			await new Promise( ( resolve ) => setTimeout( resolve, 500 ) );
		}

		await route.fulfill( {
			json: {
				items: [
					{
						tag_id: requestCount === 1 ? '234567' : '234568',
						batch_id: 8,
						batch_code: 'RT-209-LOADING',
						batch_status: 'released',
						batch_activation_enabled: true,
						activation_availability: 'eligible',
						tag_type: 'classic_tag',
						model_code: null,
						tag_status: 'unregistered',
						lost_mode: false,
						activated_at: null,
						created_at: '2026-07-29T08:00:00+00:00',
						updated_at: '2026-07-29T08:00:00+00:00',
					},
				],
				next_cursor: null,
				context: {
					global_activation_enabled: true,
				},
			},
		} );
	} );

	await logIn( page );
	await page.goto( '/wp-admin/admin.php?page=tagcore-tags', {
		waitUntil: 'domcontentloaded',
	} );
	const tagId = page.getByRole( 'textbox', {
		name: 'Tag ID',
		exact: true,
	} );

	await tagId.fill( '234567' );
	await page.getByRole( 'button', { name: 'Search tags' } ).click();
	await expect( page.getByText( '234567', { exact: true } ) ).toBeVisible();

	await tagId.fill( '234568' );
	await page.getByRole( 'button', { name: 'Search tags' } ).click();
	await expect( page.getByText( '234567', { exact: true } ) ).toHaveCount(
		0
	);
	await expect(
		page.getByText( 'Searching Tags…', { exact: true } )
	).toBeVisible();
	await expect( page.getByText( '234568', { exact: true } ) ).toBeVisible();
} );
