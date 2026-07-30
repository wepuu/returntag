import { expect, test } from '@playwright/test';

test.describe( 'RT-302 public Tag ID normalization', () => {
	test( 'fails closed with a private theme-independent page', async ( {
		browserName,
		page,
	} ) => {
		const consoleErrors: string[] = [];
		const pageErrors: string[] = [];
		const requestOrigins = new Set< string >();

		page.on( 'console', ( message ) => {
			if ( message.type() === 'error' ) {
				consoleErrors.push( message.text() );
			}
		} );
		page.on( 'pageerror', ( error ) => pageErrors.push( error.message ) );
		page.on( 'request', ( request ) => {
			requestOrigins.add( new URL( request.url() ).origin );
		} );

		const response = await page.goto( '/t/A7R2W9', {
			waitUntil: 'domcontentloaded',
		} );

		expect( response ).not.toBeNull();
		expect( response?.status() ).toBe( 503 );
		expect( response?.headers()[ 'cache-control' ] ).toBe(
			'no-store, private'
		);
		expect( response?.headers()[ 'referrer-policy' ] ).toBe(
			'no-referrer'
		);
		expect( response?.headers()[ 'x-robots-tag' ] ).toBe(
			'noindex, nofollow, noarchive'
		);

		await expect(
			page.getByRole( 'heading', {
				name: 'Tag service is temporarily unavailable',
			} )
		).toBeVisible();
		await expect(
			page.getByRole( 'link', { name: 'Return to homepage' } )
		).toHaveAttribute( 'href', /\/$/ );
		await expect( page.locator( 'body' ) ).not.toContainText( 'A7R2W9' );
		await expect( page.locator( 'body' ) ).toHaveClass(
			/returntag-public--unavailable/
		);

		const homeLink = page.getByRole( 'link', {
			name: 'Return to homepage',
		} );

		if ( browserName === 'webkit' ) {
			await homeLink.focus();
		} else {
			await page.keyboard.press( 'Tab' );
		}

		await expect( homeLink ).toBeFocused();

		const focusStyle = await homeLink.evaluate( ( element ) => {
			const style = window.getComputedStyle( element );

			return {
				outlineStyle: style.outlineStyle,
				outlineWidth: style.outlineWidth,
			};
		} );
		expect( focusStyle.outlineStyle ).not.toBe( 'none' );
		expect(
			Number.parseFloat( focusStyle.outlineWidth )
		).toBeGreaterThanOrEqual( 1 );

		const hasHorizontalOverflow = await page.evaluate(
			() => document.documentElement.scrollWidth > window.innerWidth
		);
		expect( hasHorizontalOverflow ).toBe( false );
		expect(
			consoleErrors.filter(
				( message ) =>
					message !==
					'Failed to load resource: the server responded with a status of 503 (Service Unavailable)'
			)
		).toEqual( [] );
		expect( pageErrors ).toEqual( [] );
		expect( [ ...requestOrigins ] ).toEqual( [
			new URL( page.url() ).origin,
		] );

		await homeLink.click();
		await expect( page ).toHaveURL( /\/$/ );
	} );

	test( 'rejects mutation methods', async ( { request } ) => {
		const response = await request.post( '/t/a7-r2w9', {
			maxRedirects: 0,
		} );

		expect( response.status() ).toBe( 405 );
		expect( response.headers().allow ).toBe( 'GET, HEAD' );
		expect( response.headers().location ).toBeUndefined();
	} );

	test( 'redirects normalizable read input to the canonical URL', async ( {
		page,
		request,
	} ) => {
		const redirect = await request.get( '/t/a7-r2%20w9', {
			maxRedirects: 0,
		} );

		expect( redirect.status() ).toBe( 301 );
		expect( redirect.headers().location ).toBe(
			new URL( '/t/A7R2W9', redirect.url() ).toString()
		);
		expect( redirect.headers()[ 'cache-control' ] ).toBe(
			'no-store, private'
		);

		const headRedirect = await request.head( '/t/a7-r2%20w9', {
			maxRedirects: 0,
		} );

		expect( headRedirect.status() ).toBe( 301 );
		expect( headRedirect.headers().location ).toBe(
			new URL( '/t/A7R2W9', headRedirect.url() ).toString()
		);

		const response = await page.goto( '/t/a7-r2%20w9', {
			waitUntil: 'domcontentloaded',
		} );

		expect( response ).not.toBeNull();
		expect( response?.status() ).toBe( 503 );
		await expect( page ).toHaveURL( /\/t\/A7R2W9$/ );
		await expect( page.locator( 'body' ) ).not.toContainText( 'A7R2W9' );

		const canonical = await request.get( '/t/A7R2W9', {
			maxRedirects: 0,
		} );

		expect( canonical.status() ).toBe( 503 );
		expect( canonical.headers().location ).toBeUndefined();
	} );

	test( 'keeps invalid input on the generic fail-closed response', async ( {
		page,
	} ) => {
		const response = await page.goto( '/t/A7R2W0', {
			waitUntil: 'domcontentloaded',
		} );

		expect( response ).not.toBeNull();
		expect( response?.status() ).toBe( 503 );
		await expect( page ).toHaveURL( /\/t\/A7R2W0$/ );
		expect( response?.headers().location ).toBeUndefined();
		await expect(
			page.getByRole( 'heading', {
				name: 'Tag service is temporarily unavailable',
			} )
		).toBeVisible();
		await expect( page.locator( 'body' ) ).not.toContainText( 'A7R2W0' );
	} );
} );
