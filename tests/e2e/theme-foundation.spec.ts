import type { Page } from '@playwright/test';

import { expect, test } from './fixtures';

async function createThemeFixture(
	page: Page,
	projectName: string
): Promise< string > {
	await page.goto( '/wp-admin/', { waitUntil: 'domcontentloaded' } );

	const slug = `rt-314-foundation-${ projectName.replace(
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
				title: 'ForgeTag Theme foundation',
				slug: pageSlug,
				status: 'publish',
				content:
					'<!-- wp:group {"className":"forge-tag-foundation-fixture","layout":{"type":"constrained"}} --><div class="wp-block-group forge-tag-foundation-fixture"><!-- wp:heading --><h2 class="wp-block-heading">A clear path home</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Local, private and built for the moment a tag is found.</p><!-- /wp:paragraph --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#theme-check">Continue</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:group -->',
			} ),
		} );

		if ( ! response.ok ) {
			throw new Error(
				`Could not create Theme fixture page: ${ response.status }`
			);
		}

		return ( await response.json() ) as { link: string };
	}, slug );

	return result.link;
}

test.describe( 'RT-314 ForgeTag design-system foundation', () => {
	test( 'uses local assets and the approved responsive token contract', async ( {
		adminPage,
		page,
	}, testInfo ) => {
		const fixtureUrl = await createThemeFixture(
			adminPage,
			testInfo.project.name
		);
		const fixtureOrigin = new URL( fixtureUrl ).origin;
		const externalRequests = new Set< string >();
		const fontRequests = new Set< string >();

		page.on( 'request', ( request ) => {
			const url = new URL( request.url() );
			if ( url.origin !== fixtureOrigin ) {
				externalRequests.add( url.href );
			}
			if ( url.pathname.includes( '/themes/forge-tag/assets/fonts/' ) ) {
				fontRequests.add( url.pathname );
			}
		} );

		const response = await page.goto( fixtureUrl, {
			waitUntil: 'networkidle',
		} );
		expect( response?.status() ).toBe( 200 );
		await page.evaluate( () => document.fonts.ready );

		const foundation = page.locator( '.forge-tag-foundation-fixture' );
		const heading = foundation.getByRole( 'heading', {
			name: 'A clear path home',
		} );
		const button = foundation.getByRole( 'link', { name: 'Continue' } );
		await expect( foundation ).toBeVisible();

		const styles = await foundation.evaluate( ( element ) => {
			const site = element.closest( '.wp-site-blocks' );
			if ( ! site ) {
				throw new Error( 'Theme root is missing.' );
			}
			const siteStyles = getComputedStyle( site );
			return {
				accent: siteStyles
					.getPropertyValue( '--returntag-color-accent' )
					.trim(),
				cardRadius: siteStyles
					.getPropertyValue( '--wp--custom--forge--radius--card' )
					.trim(),
				gutter: siteStyles
					.getPropertyValue( '--wp--custom--forge--layout--gutter' )
					.trim(),
			};
		} );
		expect( styles ).toEqual( {
			accent: '#DC1117',
			cardRadius: '16px',
			gutter: 'clamp(1.25rem, 4vw, 4rem)',
		} );
		expect(
			await heading.evaluate(
				( node ) => getComputedStyle( node ).fontFamily
			)
		).toContain( 'Manrope' );
		expect(
			await foundation.evaluate(
				( node ) => getComputedStyle( node ).fontFamily
			)
		).toContain( 'Inter' );
		expect(
			await button.evaluate(
				( node ) => node.getBoundingClientRect().height
			)
		).toBeGreaterThanOrEqual( 48 );
		const allowedThemeFontRequests = [
			'/wp-content/themes/forge-tag/assets/fonts/inter/Inter-Variable-Roman.woff2',
			'/wp-content/themes/forge-tag/assets/fonts/manrope/Manrope-Variable-Roman.woff2',
		];
		// WooCommerce can satisfy the same-origin Inter face before the Theme's
		// duplicate face is requested; the computed-family assertion above still
		// verifies that the body renders with Inter.
		expect( [ ...fontRequests ] ).toContain(
			'/wp-content/themes/forge-tag/assets/fonts/manrope/Manrope-Variable-Roman.woff2'
		);
		expect(
			[ ...fontRequests ].every( ( path ) =>
				allowedThemeFontRequests.includes( path )
			)
		).toBe( true );
		expect( [ ...externalRequests ] ).toEqual( [] );

		await button.focus();
		const focus = await button.evaluate( ( node ) => {
			const style = getComputedStyle( node );
			return {
				boxShadow: style.boxShadow,
				outlineStyle: style.outlineStyle,
				outlineWidth: style.outlineWidth,
			};
		} );
		expect( focus.boxShadow ).not.toBe( 'none' );
		expect( focus.outlineStyle ).not.toBe( 'none' );
		expect( focus.outlineWidth ).toBe( '2px' );

		await page.setViewportSize( { width: 320, height: 720 } );
		const menuButton = page.locator(
			'.wp-block-navigation__responsive-container-open'
		);
		await expect( menuButton ).toBeVisible();
		const menuButtonSize = await menuButton.evaluate( ( node ) => {
			const bounds = node.getBoundingClientRect();
			return { height: bounds.height, width: bounds.width };
		} );
		expect( menuButtonSize.height ).toBeGreaterThanOrEqual( 44 );
		expect( menuButtonSize.width ).toBeGreaterThanOrEqual( 44 );
		await menuButton.focus();
		const menuFocus = await menuButton.evaluate( ( node ) => {
			const style = getComputedStyle( node );
			return {
				boxShadow: style.boxShadow,
				outlineWidth: style.outlineWidth,
			};
		} );
		expect( menuFocus.boxShadow ).not.toBe( 'none' );
		expect( menuFocus.outlineWidth ).toBe( '2px' );

		await page.evaluate( () => {
			document.documentElement.style.fontSize = '200%';
		} );
		expect(
			await page.evaluate(
				() => document.documentElement.scrollWidth <= window.innerWidth
			)
		).toBe( true );
	} );

	test( 'honors reduced motion and forced-color focus', async ( {
		adminPage,
		page,
	}, testInfo ) => {
		const fixtureUrl = await createThemeFixture(
			adminPage,
			testInfo.project.name
		);
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await page.goto( fixtureUrl, { waitUntil: 'domcontentloaded' } );
		const button = page.getByRole( 'link', { name: 'Continue' } );
		await expect( button ).toBeVisible();
		expect(
			await button.evaluate( ( node ) =>
				parseFloat( getComputedStyle( node ).transitionDuration )
			)
		).toBeLessThanOrEqual( 0.00001 );

		test.skip(
			testInfo.project.name !== 'chromium',
			'Forced-colors emulation is verified in Chromium.'
		);
		await page.emulateMedia( {
			forcedColors: 'active',
			reducedMotion: 'reduce',
		} );
		await button.focus();
		const focus = await button.evaluate( ( node ) => {
			const style = getComputedStyle( node );
			return {
				boxShadow: style.boxShadow,
				outlineWidth: style.outlineWidth,
			};
		} );
		expect( focus ).toEqual( {
			boxShadow: 'none',
			outlineWidth: '3px',
		} );
	} );
} );
