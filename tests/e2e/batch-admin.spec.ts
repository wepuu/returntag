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

async function fillRequiredBatchFields(
	page: import('@playwright/test').Page,
	batchCode: string
) {
	await page.getByLabel( /Batch Code/ ).fill( batchCode );
	await page.getByLabel( 'Smart tag' ).check();
	await page.getByLabel( /Model code/ ).fill( 'SMART-01' );
	await page.getByLabel( /Smart network/ ).selectOption( 'apple_find_my' );
	await page.getByLabel( /Requested quantity/ ).fill( '2500' );
	await page.getByLabel( /Manufacturer/ ).fill( 'Northstar Manufacturing' );
	await page.getByLabel( /Notes/ ).fill( 'Automated RT-201 test fixture.' );
}

test( 'an authorized operator creates a disabled draft Batch', async ( {
	page,
} ) => {
	await logIn( page );
	await page.goto( '/wp-admin/admin.php?page=tagcore-batches&view=create', {
		waitUntil: 'domcontentloaded',
	} );

	await expect(
		page.getByRole( 'heading', { name: 'Create batch' } )
	).toBeVisible();
	await expect(
		page.getByText( 'Activation', { exact: true } )
	).toBeVisible();
	await expect( page.getByText( 'Disabled', { exact: true } ) ).toBeVisible();

	const batchCode = `RT-E2E-${ Date.now() }`;
	await fillRequiredBatchFields( page, batchCode );
	await page.getByRole( 'button', { name: 'Create draft batch' } ).click();

	await expect(
		page.getByRole( 'heading', { name: 'Batch created' } )
	).toBeVisible();
	await expect( page.getByText( batchCode, { exact: true } ) ).toBeVisible();
	await expect( page.getByText( 'Draft', { exact: true } ) ).toBeVisible();
	await expect( page.getByText( 'Disabled', { exact: true } ) ).toBeVisible();

	await page.getByRole( 'button', { name: 'Create another' } ).click();
	await fillRequiredBatchFields( page, batchCode );
	await page.getByRole( 'button', { name: 'Create draft batch' } ).click();

	await expect(
		page.getByText( 'This Batch Code is already in use.', {
			exact: true,
		} )
	).toBeVisible();
	await expect( page.getByLabel( 'Batch validation errors' ) ).toBeFocused();
} );
