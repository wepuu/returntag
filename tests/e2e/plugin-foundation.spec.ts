import { adminTest as test, expect } from './fixtures';

test( 'TagCore is visible and active in WordPress', async ( { page } ) => {
	await page.goto( '/wp-admin/plugins.php', {
		waitUntil: 'domcontentloaded',
	} );

	const pluginRow = page.locator( 'tr[data-slug="tagcore"]' );
	await expect( pluginRow ).toContainText( 'TagCore' );
	await expect( pluginRow ).toHaveClass( /active/ );
} );
