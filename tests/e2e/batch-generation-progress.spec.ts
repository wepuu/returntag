import AxeBuilder from '@axe-core/playwright';
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
	await page.getByLabel( /Requested quantity/ ).fill( '2' );
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
		name: 'Generate 2 Tag IDs',
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
		'of 2 Tag IDs generated'
	);
	await expect(
		page.getByText( 'Failed IDs', { exact: true } )
	).toBeVisible();
	await expect(
		page.getByText( 'Activation', { exact: true } )
	).toBeVisible();
	await expect( page.getByText( 'Disabled', { exact: true } ) ).toBeVisible();

	const accessibilityScanResults = await new AxeBuilder( { page } )
		.include( '.returntag-admin' )
		.analyze();
	expect( accessibilityScanResults.violations ).toEqual( [] );
} );
