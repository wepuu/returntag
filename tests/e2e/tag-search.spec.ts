import { adminTest as test, expect } from './fixtures';

test( 'an authorized operator performs a read-only exact Tag search', async ( {
	page,
} ) => {
	await page.route(
		'**/wp-json/tagcore/v1/admin/tags/search*',
		async ( route ) => {
			expect( route.request().method() ).toBe( 'POST' );
			expect( route.request().postDataJSON() ).toMatchObject( {
				mode: 'tag_id',
				tag_id: '23-45 67',
			} );
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
							finder_report_count: 0,
							conversation_count: 0,
						},
					],
					next_cursor: null,
				},
			} );
		}
	);

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
	await expect(
		page.getByText( 'unregistered', { exact: true } )
	).toBeVisible();
	await expect(
		page.getByText( 'classic_tag', { exact: true } )
	).toBeVisible();
	await expect(
		page.getByRole( 'button', { name: /edit|delete|activate/i } )
	).toHaveCount( 0 );
} );

test( 'a new search clears stale results while the request is pending', async ( {
	page,
} ) => {
	let requestCount = 0;

	await page.route(
		'**/wp-json/tagcore/v1/admin/tags/search*',
		async ( route ) => {
			requestCount += 1;
			expect( route.request().method() ).toBe( 'POST' );

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
							finder_report_count: 0,
							conversation_count: 0,
						},
					],
					next_cursor: null,
				},
			} );
		}
	);

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
		page.getByText( 'Loading secure results…', { exact: true } )
	).toBeVisible();
	await expect( page.getByText( '234568', { exact: true } ) ).toBeVisible();
} );
