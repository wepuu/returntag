import { expect, test } from '@playwright/test';

test.describe( 'RT-317 Owner Account safety boundary', () => {
	test( 'fails closed while the Owner Account feature flag is disabled', async ( {
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

		const response = await page.goto( '/account/sign-in/', {
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
		expect( response?.headers()[ 'content-security-policy' ] ).toContain(
			"default-src 'none'"
		);
		await expect(
			page.getByRole( 'heading', { name: 'Account unavailable' } )
		).toBeVisible();
		await expect( page.getByLabel( 'Email address' ) ).toHaveCount( 0 );

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
	} );

	test( 'keeps the Conversation browser unavailable without minting access', async ( {
		page,
	} ) => {
		const response = await page.goto( '/account/conversations/', {
			waitUntil: 'domcontentloaded',
		} );

		expect( response ).not.toBeNull();
		expect( response?.status() ).toBe( 503 );
		expect( response?.headers()[ 'cache-control' ] ).toBe(
			'no-store, private'
		);
		await expect(
			page.getByRole( 'heading', { name: 'Account unavailable' } )
		).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: 'Continue securely' } )
		).toHaveCount( 0 );
		expect( page.url() ).not.toContain( 'token' );
		expect( page.url() ).not.toContain( 'session' );
	} );

	test( 'rejects mutations outside the sign-in route', async ( {
		request,
	} ) => {
		const response = await request.post( '/account/', {
			maxRedirects: 0,
		} );

		expect( response.status() ).toBe( 405 );
		expect( response.headers().location ).toBeUndefined();
		expect( response.headers()[ 'cache-control' ] ).toContain( 'no-store' );
		expect( response.headers()[ 'cache-control' ] ).toContain( 'private' );
	} );
} );
