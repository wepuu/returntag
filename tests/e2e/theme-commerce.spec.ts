import AxeBuilder from '@axe-core/playwright';
import type { Browser, Page } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';

import { adminAuthStatePath } from './auth-state';
import { expect, test } from './fixtures';

type CommerceFixture = {
	cartUrl: string;
	checkoutUrl: string;
	mediaIds: number[];
	productIds: number[];
	productName: string;
	productNames: string[];
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
	const imageFiles = [
		'product-classic-family-safe.png',
		'product-sticker-safe.png',
		'product-smart-tag.png',
	];
	const fixtureMediaIds: number[] = [];

	for ( const imageFile of imageFiles ) {
		const response = await page.request.post( '/wp-json/wp/v2/media', {
			data: await readFile(
				join(
					process.cwd(),
					'theme',
					'forge-tag',
					'assets',
					'images',
					imageFile
				)
			),
			headers: {
				'Content-Disposition': `attachment; filename="${ imageFile }"`,
				'Content-Type': 'image/png',
				'X-WP-Nonce': nonce,
			},
		} );
		if ( ! response.ok() ) {
			throw new Error(
				`Could not create fixture media: ${ response.status() }`
			);
		}
		fixtureMediaIds.push(
			( ( await response.json() ) as { id: number } ).id
		);
	}

	return page.evaluate(
		async ( { fixtureMedia, fixtureNonce, fixtureSuffix } ) => {
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
					imageId: fixtureMedia[ 0 ],
					name: `ForgeTag Classic Tag ${ fixtureSuffix }`,
					regular_price: '29.00',
					slug: `forge-tag-classic-tag-${ fixtureSuffix }`,
					short_description:
						'A durable tag with an independent QR recovery path.',
					stock_status: 'instock',
				},
				{
					imageId: fixtureMedia[ 1 ],
					name: `ForgeTag Sticker ${ fixtureSuffix }`,
					regular_price: '12.00',
					slug: `forge-tag-sticker-${ fixtureSuffix }`,
					short_description:
						'A low-profile format for everyday belongings.',
					stock_status: 'instock',
				},
				{
					imageId: fixtureMedia[ 2 ],
					name: `ForgeTag Smart Tag ${ fixtureSuffix }`,
					regular_price: '39.00',
					slug: `forge-tag-smart-tag-${ fixtureSuffix }`,
					short_description:
						'Smart finding guidance and QR recovery remain separate systems.',
					stock_status: 'outofstock',
				},
			];
			type FixtureProduct = {
				id: number;
				images: Array< { id: number } >;
				name: string;
				permalink: string;
			};
			const productResponse = await fetch(
				'/wp-json/wc/v3/products/batch',
				{
					method: 'POST',
					headers,
					body: JSON.stringify( {
						create: definitions.map(
							( { imageId, ...definition } ) => ( {
								...definition,
								images: [ { id: imageId } ],
								status: 'publish',
								type: 'simple',
							} )
						),
					} ),
				}
			);
			if ( ! productResponse.ok ) {
				throw new Error(
					`Could not create WooCommerce products: ${ productResponse.status }`
				);
			}
			const productBatch = ( await productResponse.json() ) as {
				create: FixtureProduct[];
			};
			const products = productBatch.create;

			const reviewResponse = await fetch(
				'/wp-json/wc/v3/products/reviews',
				{
					method: 'POST',
					headers,
					body: JSON.stringify( {
						product_id: products[ 0 ].id,
						rating: 5,
						review: 'A clear, practical presentation for this local storefront demo.',
						reviewer: 'Local Demo Reviewer',
						reviewer_email: 'demo-reviewer@example.test',
					} ),
				}
			);
			if ( ! reviewResponse.ok ) {
				throw new Error(
					`Could not create WooCommerce review: ${ reviewResponse.status }`
				);
			}

			return {
				cartUrl: pageLinks.cart,
				checkoutUrl: pageLinks.checkout,
				mediaIds: products.flatMap( ( product ) =>
					product.images.map( ( image ) => image.id )
				),
				productIds: products.map( ( product ) => product.id ),
				productName: products[ 0 ].name,
				productNames: products.map( ( product ) => product.name ),
				productUrl: products[ 0 ].permalink,
				shopUrl: pageLinks.shop,
			};
		},
		{
			fixtureMedia: fixtureMediaIds,
			fixtureNonce: nonce,
			fixtureSuffix: suffix,
		}
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
			async ( { fixtureNonce, mediaIds, productIds } ) => {
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
				await Promise.all(
					mediaIds.map( async ( mediaId ) => {
						const response = await fetch(
							`/wp-json/wp/v2/media/${ mediaId }?force=true`,
							{
								method: 'DELETE',
								headers: { 'X-WP-Nonce': fixtureNonce },
							}
						);
						if ( ! response.ok && response.status !== 404 ) {
							throw new Error(
								`Could not delete fixture media ${ mediaId }: ${ response.status }`
							);
						}
					} )
				);
			},
			{
				fixtureNonce: nonce,
				mediaIds: fixture.mediaIds,
				productIds: fixture.productIds,
			}
		);
	} finally {
		await context.close();
	}
};

test.describe( 'RT-321 ForgeTag commerce presentation', () => {
	test.describe.configure( { timeout: 240_000 } );

	let fixture: CommerceFixture | undefined;

	test.beforeAll( async ( { browser, baseURL }, testInfo ) => {
		testInfo.setTimeout( 240_000 );
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

	test.afterAll( async ( { browser, baseURL }, testInfo ) => {
		testInfo.setTimeout( 240_000 );
		await removeCommerceFixture( browser, baseURL, fixture );
	} );

	test( 'renders the catalog and product journey with real local media', async ( {
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
		const firstProductLink = page
			.locator( '.wp-block-post-title a' )
			.filter( { hasText: fixture.productName } )
			.first();
		await expect( firstProductLink ).toBeVisible();
		await firstProductLink.focus();
		expect(
			await firstProductLink.evaluate(
				( link ) => getComputedStyle( link ).outlineStyle
			)
		).not.toBe( 'none' );
		await expect(
			page.locator( '.forge-commerce__catalog-grid' )
		).toBeVisible();
		expect(
			await page
				.locator( '.wp-block-woocommerce-product-template > li' )
				.count()
		).toBeGreaterThanOrEqual( 3 );
		const catalogImageLocator = page.locator(
			'.wp-block-woocommerce-product-image img'
		);
		expect( await catalogImageLocator.count() ).toBeGreaterThanOrEqual( 3 );
		const catalogImages = await catalogImageLocator.evaluateAll(
			( images ) =>
				images.map( ( image ) => ( {
					complete: ( image as HTMLImageElement ).complete,
					naturalWidth: ( image as HTMLImageElement ).naturalWidth,
				} ) )
		);
		expect(
			catalogImages.every(
				( image ) => image.complete && image.naturalWidth > 0
			)
		).toBe( true );

		const shopAccessibility = await new AxeBuilder( { page } )
			.include( 'main.forge-commerce--catalog' )
			.analyze();
		expect( shopAccessibility.violations ).toEqual( [] );

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
		await expect(
			page.locator( '.wp-block-woocommerce-product-rating' )
		).toHaveCount( 1 );
		await expect(
			page.getByRole( 'img', { name: 'Rated 5 out of 5' } )
		).toBeVisible();
		await expect( page.getByText( 'Local Demo Reviewer' ) ).toBeVisible();
		await expect(
			page.locator( '.forge-commerce__product-media .wp-post-image' )
		).toBeVisible();
		const unexpectedExternalRequests = [ ...externalRequests ].filter(
			( url ) =>
				! url.startsWith( 'https://s.w.org/images/core/emoji/' ) &&
				! url.startsWith( 'https://secure.gravatar.com/avatar/' )
		);
		expect( unexpectedExternalRequests ).toEqual( [] );

		const accessibility = await new AxeBuilder( { page } )
			.include( 'main.forge-commerce--product' )
			.exclude( '.zoomImg' )
			.analyze();
		expect( accessibility.violations ).toEqual( [] );
	} );

	test( 'keeps populated Cart and Checkout content authoritative', async ( {
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
			page.getByRole( 'heading', { level: 1, name: 'Cart' } )
		).toBeVisible();
		await expect(
			page.locator( '.wp-block-woocommerce-cart' )
		).toBeVisible();
		await expect( page.getByText( fixture.productName ) ).toBeVisible();
		const cartLayout = await page.evaluate( () => ( {
			scrollWidth: document.documentElement.scrollWidth,
			viewportWidth: window.innerWidth,
		} ) );
		expect(
			cartLayout.scrollWidth,
			`Unexpected Cart overflow: ${ JSON.stringify( cartLayout ) }`
		).toBeLessThanOrEqual( cartLayout.viewportWidth );

		await page.goto( fixture.checkoutUrl, {
			waitUntil: 'domcontentloaded',
		} );
		await expect(
			page.locator( 'main.forge-commerce--checkout' )
		).toBeVisible();
		await expect(
			page.getByRole( 'heading', { level: 1, name: 'Checkout' } )
		).toBeVisible();
		await expect(
			page.locator( '.wp-block-woocommerce-checkout' )
		).toBeVisible();
		await expect( page.getByRole( 'banner' ) ).toBeVisible();
		await expect( page.getByRole( 'contentinfo' ) ).toBeVisible();
		await expect( page.getByLabel( /First name/i ).first() ).toBeVisible();
		await expect(
			page.getByText( fixture.productName ).first()
		).toBeVisible();
		await expect(
			page.locator( '.wc-block-components-skeleton__element' )
		).toHaveCount( 0, { timeout: 30_000 } );
		const checkoutLayout = await page.evaluate( () => ( {
			scrollWidth: document.documentElement.scrollWidth,
			viewportWidth: window.innerWidth,
		} ) );
		expect(
			checkoutLayout.scrollWidth,
			`Unexpected Checkout overflow: ${ JSON.stringify(
				checkoutLayout
			) }`
		).toBeLessThanOrEqual( checkoutLayout.viewportWidth );

		const accessibility = await new AxeBuilder( { page } )
			.include( 'main.forge-commerce--checkout' )
			.analyze();
		const themeActionableViolations = accessibility.violations.filter(
			( violation ) => violation.id !== 'autocomplete-valid'
		);
		expect( themeActionableViolations ).toEqual( [] );
	} );

	test( 'presents a useful empty Cart recovery state', async ( { page } ) => {
		if ( ! fixture ) {
			throw new Error( 'Commerce fixture was not created.' );
		}

		await page.goto( fixture.cartUrl, { waitUntil: 'domcontentloaded' } );
		await expect(
			page.locator( 'main.forge-commerce--cart' )
		).toBeVisible();
		await expect(
			page.locator( '.wp-block-woocommerce-empty-cart-block' )
		).toBeVisible();
		await expect(
			page.getByRole( 'link', { name: /browse|shop|products/i } ).first()
		).toBeVisible();
	} );

	test( 'reflows at the RT-319 breakpoints and 200 percent text', async ( {
		page,
	} ) => {
		if ( ! fixture ) {
			throw new Error( 'Commerce fixture was not created.' );
		}

		for ( const width of [ 1440, 1024, 816, 390, 320 ] ) {
			await page.setViewportSize( {
				width,
				height: width > 800 ? 900 : 760,
			} );
			await page.goto( fixture.shopUrl, {
				waitUntil: 'domcontentloaded',
			} );
			await expect(
				page.locator( 'main.forge-commerce--catalog' )
			).toBeVisible();
			const layout = await page.evaluate( () => ( {
				scrollWidth: document.documentElement.scrollWidth,
				viewportWidth: window.innerWidth,
			} ) );
			expect(
				layout.scrollWidth,
				`Unexpected overflow at ${ width }px: ${ JSON.stringify(
					layout
				) }`
			).toBeLessThanOrEqual( layout.viewportWidth );
		}

		await page.setViewportSize( { width: 720, height: 900 } );
		await page.goto( fixture.shopUrl, { waitUntil: 'domcontentloaded' } );
		await page.evaluate( () => {
			document.documentElement.style.fontSize = '200%';
		} );
		const zoomLayout = await page.evaluate( () => ( {
			scrollWidth: document.documentElement.scrollWidth,
			viewportWidth: window.innerWidth,
		} ) );
		expect(
			zoomLayout.scrollWidth,
			`Unexpected overflow at 200% text: ${ JSON.stringify(
				zoomLayout
			) }`
		).toBeLessThanOrEqual( zoomLayout.viewportWidth );
	} );
} );
