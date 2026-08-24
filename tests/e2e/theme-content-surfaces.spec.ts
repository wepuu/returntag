import AxeBuilder from '@axe-core/playwright';

import { expect, test } from './fixtures';

test.describe( 'RT-320 ForgeTag global content surfaces', () => {
	test( 'renders generic Page, Search results, Search empty, and 404 states', async ( {
		adminPage,
		page,
	}, testInfo ) => {
		await adminPage.goto( '/wp-admin/', { waitUntil: 'domcontentloaded' } );
		const slug = `rt-320-recovery-guide-${ testInfo.project.name.replace(
			/[^a-z0-9]+/g,
			'-'
		) }-${ Date.now() }`;
		const pageTitle = `Recovery guide ${ slug }`;
		const pageUrl = await adminPage.evaluate( async ( pageSlug ) => {
			const settings = (
				window as typeof window & {
					wpApiSettings?: { nonce?: string };
				}
			 ).wpApiSettings;
			if ( ! settings?.nonce ) {
				throw new Error( 'WordPress REST nonce is unavailable.' );
			}

			const response = await fetch( '/wp-json/wp/v2/pages', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': settings.nonce,
				},
				body: JSON.stringify( {
					content:
						'<!-- wp:paragraph --><p>A practical RT-320 recovery guide for everyday belongings.</p><!-- /wp:paragraph -->',
					slug: pageSlug,
					status: 'publish',
					title: `Recovery guide ${ pageSlug }`,
				} ),
			} );
			if ( ! response.ok ) {
				throw new Error(
					`Could not create Theme Page fixture: ${ response.status }`
				);
			}

			return ( ( await response.json() ) as { link: string } ).link;
		}, slug );

		await page.setViewportSize( { width: 1440, height: 900 } );
		const genericResponse = await page.goto( pageUrl, {
			waitUntil: 'networkidle',
		} );
		expect( genericResponse?.status() ).toBe( 200 );
		await expect( page ).toHaveTitle( `${ pageTitle } – ForgeTag` );
		await expect(
			page.getByRole( 'heading', { level: 1, name: pageTitle } )
		).toBeVisible();
		await expect(
			page.getByText(
				'A practical RT-320 recovery guide for everyday belongings.'
			)
		).toBeVisible();

		const searchResponse = await page.goto( `/?s=${ slug }`, {
			waitUntil: 'networkidle',
		} );
		expect( searchResponse?.status() ).toBe( 200 );
		await expect( page ).toHaveTitle( /ForgeTag$/ );
		await expect( page.locator( 'main h1' ) ).toContainText( slug );
		await expect(
			page.locator( '.forge-search-result' ).getByRole( 'heading', {
				level: 2,
				name: pageTitle,
			} )
		).toBeVisible();

		const emptyQuery = `no-match-${ Date.now() }`;
		await page.goto( `/?s=${ emptyQuery }`, { waitUntil: 'networkidle' } );
		await expect( page.locator( 'main h1' ) ).toContainText( emptyQuery );
		await expect(
			page.getByRole( 'heading', { level: 2, name: 'No matching pages' } )
		).toBeVisible();
		await expect(
			page.getByRole( 'searchbox', { name: 'Search again' } )
		).toBeVisible();
		await expect(
			page.getByRole( 'link', { name: 'Return to homepage' } )
		).toBeVisible();
		await page.setViewportSize( { width: 390, height: 844 } );
		expect(
			await page.evaluate(
				() => document.documentElement.scrollWidth <= window.innerWidth
			)
		).toBe( true );

		const notFoundResponse = await page.goto(
			`/rt-320-missing-${ Date.now() }/`,
			{ waitUntil: 'networkidle' }
		);
		expect( notFoundResponse?.status() ).toBe( 404 );
		await expect( page ).toHaveTitle( 'Page not found – ForgeTag' );
		await expect(
			page.getByRole( 'heading', {
				level: 1,
				name: 'This page has moved or does not exist',
			} )
		).toBeVisible();
		await expect(
			page.getByRole( 'link', { name: 'Return to homepage' } )
		).toBeVisible();
		await expect(
			page
				.locator( 'main' )
				.getByRole( 'link', { name: 'Activate my tag' } )
		).toBeVisible();
		await expect(
			page.locator( 'main' ).getByRole( 'link', {
				name: 'Report a found tag',
			} )
		).toBeVisible();
		await expect( page.getByText( 'ReturnTag' ) ).toHaveCount( 0 );

		const accessibility = await new AxeBuilder( { page } )
			.exclude( 'dialog:not([open])' )
			.analyze();
		expect( accessibility.violations ).toEqual( [] );

		await page.setViewportSize( { width: 320, height: 720 } );
		await page.reload( { waitUntil: 'domcontentloaded' } );
		expect(
			await page.evaluate(
				() => document.documentElement.scrollWidth <= window.innerWidth
			)
		).toBe( true );
	} );
} );
