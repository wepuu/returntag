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

function waitForBatchCreationResponse( page: import('@playwright/test').Page ) {
	return page.waitForResponse(
		( response ) => {
			const url = new URL( response.url() );

			return (
				url.pathname === '/wp-json/tagcore/v1/batches' &&
				response.request().method() === 'POST'
			);
		},
		{ timeout: 60_000 }
	);
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
	await expect( page.getByLabel( /Requested quantity/ ) ).toHaveAttribute(
		'max',
		'100000'
	);
	await expect(
		page.getByText( 'Between 1 and 100,000 Tag IDs per Batch.', {
			exact: true,
		} )
	).toBeVisible();

	const batchCode = `RT-E2E-${ Date.now() }`;
	await fillRequiredBatchFields( page, batchCode );
	const [ creationResponse ] = await Promise.all( [
		waitForBatchCreationResponse( page ),
		page.getByRole( 'button', { name: 'Create draft batch' } ).click(),
	] );
	expect( creationResponse.status() ).toBe( 201 );

	await expect(
		page.getByRole( 'heading', { name: 'Batch created' } )
	).toBeVisible();
	await expect( page.getByText( batchCode, { exact: true } ) ).toBeVisible();
	await expect( page.getByText( 'Draft', { exact: true } ) ).toBeVisible();
	await expect( page.getByText( 'Disabled', { exact: true } ) ).toBeVisible();

	await page.getByRole( 'button', { name: 'Create another' } ).click();
	await fillRequiredBatchFields( page, batchCode );
	const [ duplicateResponse ] = await Promise.all( [
		waitForBatchCreationResponse( page ),
		page.getByRole( 'button', { name: 'Create draft batch' } ).click(),
	] );
	expect( duplicateResponse.status() ).toBe( 409 );

	await expect(
		page.getByText( 'This Batch Code is already in use.', {
			exact: true,
		} )
	).toBeVisible();
	await expect( page.getByLabel( 'Batch validation errors' ) ).toBeFocused();
} );
