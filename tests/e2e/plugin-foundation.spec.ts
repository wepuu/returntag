import { expect, test } from '@playwright/test';

test( 'TagCore is visible and active in WordPress', async ( { page } ) => {
	await page.goto( '/wp-login.php', { waitUntil: 'domcontentloaded' } );
	await page.getByLabel( 'Username or Email Address' ).fill( 'admin' );
	await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );
	await Promise.all( [
		page.waitForURL( /\/wp-admin(?:\/|$)/, {
			waitUntil: 'domcontentloaded',
		} ),
		page.getByRole( 'button', { name: 'Log In' } ).click(),
	] );

	await page.goto( '/wp-admin/plugins.php', {
		waitUntil: 'domcontentloaded',
	} );

	const pluginRow = page.locator( 'tr[data-slug="tagcore"]' );
	await expect( pluginRow ).toContainText( 'TagCore' );
	await expect( pluginRow ).toHaveClass( /active/ );
} );
