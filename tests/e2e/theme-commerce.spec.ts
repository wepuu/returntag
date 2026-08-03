import AxeBuilder from '@axe-core/playwright';
import type { Browser, Page } from '@playwright/test';

import { adminAuthStatePath } from './auth-state';
import { expect, test } from './fixtures';

type CommerceFixture = {
	cartUrl: string;
	checkoutUrl: string;
	productIds: number[];
	productName: string;
	productUrl: string;
	shopUrl: string;
};

const getNonce = async ( page: Page ): Promise< string > => {
	await page.goto( '/wp-admin/', { waitUntil: 'domcontentloaded' } );

	return page.evaluate( () => {
		const settings = (
			window as typeof window & { wpApiSettings?: { nonce?: string } }
		 ).wpApiSettings;
		if ( ! settings?.nonce ) {
			throw new Error( 'WordPress REST nonce is unavailable.' );
		}

		return settings.nonce;
	} );
};

const createCommerceFixture = async (
	page: Page,
	projectName: string
): Promise< CommerceFixture > => {
	const nonce = await getNonce( page );
	const suffix = `${ projectName.replace(
		/[^a-z0-9]+/gi,
		'-'
	) }-${ Date.now() }`.toLowerCase();

	return page.evaluate(
		async ( { fixtureNonce, fixtureSuffix } ) => {
			const headers = {
				'Content-Type': 'application/json',
				'X-WP-Nonce': fixtureNonce,
			};
			const pageLinks: Record< string, string > = {};

			for ( const slug of [ 'shop', 'cart', 'checkout' ] ) {
				const response = await fetch(
					`/wp-json/wp/v2/pages?slug=${ slug }&context=edit`,
					{ headers }
				);
				const pages = ( await response.json() ) as Array< {
					link: string;
				} >;
				if ( ! response.ok || ! pages[ 0 ]?.link ) {
					throw new Error(
						`WooCommerce ${ slug } page is unavailable: ${ response.status }`
					);
				}
				pageLinks[ slug ] = pages[ 0 ].link;
			}

			const definitions = [
				{
					name: `ForgeTag Classic Tag ${ fixtureSuffix }`,
					regular_price: '29.00',
					short_description:
						'A durable tag with an independent QR recovery path.',
					stock_status: 'instock',
				},
				{
					name: `ForgeTag Sticker ${ fixtureSuffix }`,
					regular_price: '12.00',
					short_description:
						'A low-profile format for everyday belongings.',
					stock_status: 'instock',
				},
				{
					name: `ForgeTag Smart Tag ${ fixtureSuffix }`,
					regular_price: '39.00',
					short_description:
						'Smart finding guidance and QR recovery remain separate systems.',
					stock_status: 'outofstock',
				},
			];
			const products: Array< {
				id: number;
				name: string;
				permalink: string;
			} > = [];

			for ( const definition of definitions ) {
				const response = await fetch( '/wp-json/wc/v3/products', {
					method: 'POST',
					headers,
					body: JSON.stringify( {
						...definition,
						status: 'publish',
						type: 'simple',
					} ),
				} );
				if ( ! response.ok ) {
					throw new Error(
						`Could not create WooCommerce product: ${ response.status }`
					);
				}
				products.push( await response.json() );
			}

			return {
				cartUrl: pageLinks.cart,
				checkoutUrl: pageLinks.checkout,
				productIds: products.map( ( product ) => product.id ),
				productName: products[ 0 ].name,
				productUrl: products[ 0 ].permalink,
				shopUrl: pageLinks.shop,
			};
		},
		{ fixtureNonce: nonce, fixtureSuffix: suffix }
	);
};

const removeCommerceFixture = async (
	browser: Browser,
	baseURL: string | undefined,
	fixture: CommerceFixture | undefined
): Promise< void > => {
	if ( ! fixture ) {
		return;
	}

	const context = await browser.newContext( {
		baseURL,
		storageState: adminAuthStatePath,
	} );
	const page = await context.newPage();

	try {
		const nonce = await getNonce( page );
		await page.evaluate(
			async ( { fixtureNonce, productIds } ) => {
				await Promise.all(
					productIds.map( async ( productId ) => {
						const response = await fetch(
							`/wp-json/wc/v3/products/${ productId }?force=true`,
							{
								method: 'DELETE',
								headers: { 'X-WP-Nonce': fixtureNonce },
							}
						);
						if ( ! response.ok ) {
							throw new Error(
								`Could not delete WooCommerce product ${ productId }: ${ response.status }`
							);
						}
					} )
				);
			},
			{ fixtureNonce: nonce, productIds: fixture.productIds }
		);
	} finally {
		await context.close();
	}
};

test.describe( 'RT-314 ForgeTag commerce baseline', () => {
	let fixture: CommerceFixture | undefined;

	test.beforeAll( async ( { browser, baseURL }, testInfo ) => {
		const context = await browser.newContext( {
			baseURL,
			storageState: adminAuthStatePath,
		} );
		const page = await context.newPage();

		try {
			fixture = await createCommerceFixture(
				page,
				testInfo.project.name
			);
		} finally {
			await context.close();
		}
	} );

	test.afterAll( async ( { browser, baseURL } ) => {
		await removeCommerceFixture( browser, baseURL, fixture );
	} );

	test( 'renders catalog and product templates from the Theme', async ( {
		page,
	} ) => {
		if ( ! fixture ) {
			throw new Error( 'Commerce fixture was not created.' );
		}

		const origin = new URL( fixture.shopUrl ).origin;
		const externalRequests = new Set< string >();
		page.on( 'request', ( request ) => {
			const url = new URL( request.url() );
			if ( url.origin !== origin ) {
				externalRequests.add( url.href );
			}
		} );

		const shopResponse = await page.goto( fixture.shopUrl, {
			waitUntil: 'domcontentloaded',
		} );
		expect( shopResponse?.status() ).toBe( 200 );
		await expect(
			page.locator( 'main.forge-commerce--catalog' )
		).toBeVisible();
		await expect(
			page.getByRole( 'link', { name: fixture.productName } )
		).toBeVisible();
		expect(
			await page
				.locator( '.wp-block-woocommerce-product-template > li' )
				.count()
		).toBeGreaterThanOrEqual( 3 );

		const productResponse = await page.goto( fixture.productUrl, {
			waitUntil: 'domcontentloaded',
		} );
		expect( productResponse?.status() ).toBe( 200 );
		await expect(
			page.locator( 'main.forge-commerce--product' )
		).toBeVisible();
		await expect(
			page.getByRole( 'heading', { level: 1, name: fixture.productName } )
		).toBeVisible();
		await expect(
			page.locator( '.wp-block-woocommerce-add-to-cart-form' )
		).toBeVisible();
		expect( [ ...externalRequests ] ).toEqual( [] );

		const accessibility = await new AxeBuilder( { page } )
			.include( 'main.forge-commerce--product' )
			.analyze();
		expect( accessibility.violations ).toEqual( [] );
	} );

	test( 'keeps assigned Cart and Checkout page content authoritative', async ( {
		page,
	} ) => {
		if ( ! fixture ) {
			throw new Error( 'Commerce fixture was not created.' );
		}

		const cartUrl = new URL( fixture.cartUrl );
		cartUrl.searchParams.set(
			'add-to-cart',
			String( fixture.productIds[ 0 ] )
		);
		await page.goto( cartUrl.href, { waitUntil: 'domcontentloaded' } );
		await page.goto( fixture.cartUrl, { waitUntil: 'domcontentloaded' } );
		await expect(
			page.locator( 'main.forge-commerce--cart' )
		).toBeVisible();
		await expect(
			page.locator( '.wp-block-woocommerce-cart' )
		).toBeVisible();
		await expect( page.getByText( fixture.productName ) ).toBeVisible();

		await page.goto( fixture.checkoutUrl, {
			waitUntil: 'domcontentloaded',
		} );
		await expect(
			page.locator( 'main.forge-commerce--checkout' )
		).toBeVisible();
		await expect(
			page.locator( '.wp-block-woocommerce-checkout' )
		).toBeVisible();
	} );

	test( 'remains usable at 320px and 200 percent text', async ( {
		page,
	} ) => {
		if ( ! fixture ) {
			throw new Error( 'Commerce fixture was not created.' );
		}

		await page.setViewportSize( { width: 320, height: 720 } );
		await page.goto( fixture.shopUrl, { waitUntil: 'domcontentloaded' } );
		await page.evaluate( () => {
			document.documentElement.style.fontSize = '200%';
		} );

		await expect(
			page.locator( 'main.forge-commerce--catalog' )
		).toBeVisible();
		const layout = await page.evaluate( () => ( {
			overflowingElements: [
				...document.querySelectorAll< HTMLElement >( 'body *' ),
			]
				.filter(
					( element ) => ! element.closest( '[aria-hidden="true"]' )
				)
				.map( ( element ) => {
					const bounds = element.getBoundingClientRect();
					return {
						className: element.className.toString(),
						right: Math.round( bounds.right ),
						tagName: element.tagName.toLowerCase(),
					};
				} )
				.filter( ( element ) => element.right > window.innerWidth + 1 )
				.slice( 0, 10 ),
			scrollWidth: document.documentElement.scrollWidth,
			viewportWidth: window.innerWidth,
		} ) );
		expect(
			layout.scrollWidth,
			JSON.stringify( layout )
		).toBeLessThanOrEqual( layout.viewportWidth );
	} );
} );
