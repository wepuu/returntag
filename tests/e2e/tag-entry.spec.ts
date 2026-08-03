import type { Page } from '@playwright/test';

import { expect, test } from './fixtures';

async function createEntryPage(
	page: Page,
	projectName: string
): Promise< string > {
	await page.goto( '/wp-admin/', { waitUntil: 'domcontentloaded' } );

	const slug = `rt-312-entry-${ projectName.replace(
		/[^a-z0-9]+/g,
		'-'
	) }-${ Date.now() }`;
	const result = await page.evaluate( async ( pageSlug ) => {
		const settings = (
			window as typeof window & { wpApiSettings?: { nonce?: string } }
		 ).wpApiSettings;
		const nonce = settings?.nonce;

		if ( ! nonce ) {
			throw new Error( 'WordPress REST nonce is unavailable.' );
		}

		const response = await fetch( '/wp-json/wp/v2/pages', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce,
			},
			body: JSON.stringify( {
				title: 'RT-312 entry adapter',
				slug: pageSlug,
				status: 'publish',
				content:
					'<!-- wp:tagcore/tag-entry-link {"intent":"activate"} /--><!-- wp:tagcore/tag-entry-link {"intent":"report"} /-->',
			} ),
		} );

		if ( ! response.ok ) {
			throw new Error(
				`Could not create fixture page: ${ response.status }`
			);
		}

		return ( await response.json() ) as { link: string };
	}, slug );

	return result.link;
}

test.describe( 'RT-312 TagCore entry adapter', () => {
	test( 'standalone entry page is private, responsive, and canonicalizes a valid ID', async ( {
		page,
	} ) => {
		const response = await page.goto( '/tag/activate/', {
			waitUntil: 'domcontentloaded',
		} );

		expect( response?.status() ).toBe( 200 );
		expect( response?.headers()[ 'cache-control' ] ).toBe(
			'no-store, private'
		);
		expect( response?.headers()[ 'referrer-policy' ] ).toBe(
			'no-referrer'
		);
		await expect(
			page.getByRole( 'heading', { name: 'Activate your ForgeTag' } )
		).toBeVisible();

		const input = page.getByLabel( 'Tag ID' );
		await input.fill( 'a7-r2 w9' );
		await Promise.all( [
			page.waitForURL( /\/t\/A7R2W9\/?$/, {
				waitUntil: 'domcontentloaded',
			} ),
			page.getByRole( 'button', { name: 'Continue' } ).click(),
		] );

		await expect(
			page.getByRole( 'heading', {
				name: 'We could not find this ReturnTag',
			} )
		).toBeVisible();
	} );

	test( 'desktop link opens an accessible dialog and restores focus', async ( {
		adminPage,
		page,
	}, testInfo ) => {
		test.skip( testInfo.project.name.startsWith( 'mobile-' ) );
		const fixtureUrl = await createEntryPage(
			adminPage,
			testInfo.project.name
		);
		await page.goto( fixtureUrl, { waitUntil: 'domcontentloaded' } );

		const trigger = page
			.getByRole( 'main' )
			.getByRole( 'link', { name: 'Activate my tag' } );
		await trigger.click();

		const dialog = page.getByRole( 'dialog', {
			name: 'Activate your ForgeTag',
		} );
		await expect( dialog ).toBeVisible();
		await expect( dialog.getByLabel( 'Tag ID' ) ).toBeFocused();
		await dialog.getByLabel( 'Tag ID' ).fill( 'not-valid' );
		await dialog.getByRole( 'button', { name: 'Continue' } ).click();
		await expect( dialog ).toBeVisible();
		await expect( dialog.getByLabel( 'Tag ID' ) ).toBeFocused();

		await page.keyboard.press( 'Escape' );
		await expect( dialog ).toBeHidden();
		await expect( trigger ).toBeFocused();
	} );

	test( 'mobile link and JavaScript-free link use the standalone fallback', async ( {
		adminPage,
		browser,
		page,
	}, testInfo ) => {
		const fixtureUrl = await createEntryPage(
			adminPage,
			testInfo.project.name
		);

		if ( testInfo.project.name.startsWith( 'mobile-' ) ) {
			await page.goto( fixtureUrl, { waitUntil: 'domcontentloaded' } );
			await page
				.getByRole( 'main' )
				.getByRole( 'link', { name: 'Report a found tag' } )
				.click();
			await expect( page ).toHaveURL( /\/tag\/report\/$/ );
			await expect(
				page.getByRole( 'heading', {
					name: 'Report a found ForgeTag',
				} )
			).toBeVisible();
			return;
		}

		const context = await browser.newContext( {
			javaScriptEnabled: false,
		} );
		const noScriptPage = await context.newPage();
		await noScriptPage.goto( fixtureUrl, {
			waitUntil: 'domcontentloaded',
		} );
		await noScriptPage
			.getByRole( 'main' )
			.getByRole( 'link', { name: 'Activate my tag' } )
			.click();
		await expect( noScriptPage ).toHaveURL( /\/tag\/activate\/$/ );
		await expect(
			noScriptPage.getByRole( 'heading', {
				name: 'Activate your ForgeTag',
			} )
		).toBeVisible();
		await context.close();
	} );
} );
