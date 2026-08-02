import AxeBuilder from '@axe-core/playwright';

import { expect, test } from './fixtures';

test.describe( 'RT-314 ForgeTag homepage', () => {
	test( 'renders the brand shell and keeps product entry inside TagCore', async ( {
		page,
	}, testInfo ) => {
		const externalRequests = new Set< string >();
		const consoleErrors: string[] = [];
		page.on( 'request', ( request ) => {
			const url = new URL( request.url() );
			if ( url.origin !== 'http://localhost:8888' ) {
				externalRequests.add( url.href );
			}
		} );
		page.on( 'console', ( message ) => {
			if ( message.type() === 'error' ) {
				consoleErrors.push( message.text() );
			}
		} );

		const response = await page.goto( '/', { waitUntil: 'networkidle' } );
		expect( response?.status() ).toBe( 200 );
		await page.evaluate( () => document.fonts.ready );

		await expect(
			page.getByRole( 'heading', {
				level: 1,
				name: 'Help what matters find its way back.',
			} )
		).toBeVisible();
		await expect(
			page.getByRole( 'img', { name: 'ForgeTag', exact: true } )
		).toBeVisible();
		await expect(
			page.getByRole( 'navigation', { name: 'Primary navigation' } )
		).toBeVisible();
		await expect(
			page.getByRole( 'navigation', { name: 'Footer navigation' } )
		).toBeAttached();
		await expect(
			page.getByRole( 'heading', { name: 'How ForgeTag works' } )
		).toBeVisible();
		await expect(
			page.getByRole( 'heading', { name: 'Sticker' } )
		).toBeVisible();
		await expect(
			page.getByRole( 'heading', { name: 'Classic Tag' } )
		).toBeVisible();
		await expect(
			page.getByRole( 'heading', { name: 'Smart Tag' } )
		).toBeVisible();
		await expect(
			page.getByRole( 'img', {
				name: 'ForgeTag Sticker product sheet',
			} )
		).toBeVisible();
		await expect(
			page.getByRole( 'img', { name: 'Black ForgeTag Smart Tag' } )
		).toBeVisible();

		const productImages = page.locator(
			'main img[src*="/assets/images/product-"]'
		);
		expect( await productImages.count() ).toBe( 4 );
		expect(
			await productImages.evaluateAll( ( images ) =>
				images.every(
					( image ) =>
						image instanceof HTMLImageElement &&
						image.complete &&
						image.naturalWidth === 1254 &&
						image.naturalHeight === 1254
				)
			)
		).toBe( true );

		expect(
			await page.getByRole( 'link', { name: 'Activate my tag' } ).count()
		).toBe( 2 );
		expect(
			await page.locator( '[data-returntag-tag-entry="report"]' ).count()
		).toBe( 2 );
		expect( await page.locator( 'main input:visible' ).count() ).toBe( 0 );
		expect( [ ...externalRequests ] ).toEqual( [] );

		const hero = page.locator( '.forge-home-hero' );
		const activate = hero.getByRole( 'link', { name: 'Activate my tag' } );
		const report = hero.getByRole( 'link', { name: 'Report a found tag' } );
		const reportStyles = await report.evaluate( ( node ) => {
			const style = getComputedStyle( node );
			return {
				background: style.backgroundColor,
				borderWidth: style.borderTopWidth,
				color: style.color,
			};
		} );
		expect( reportStyles ).toEqual( {
			background: 'rgb(255, 255, 255)',
			borderWidth: '1px',
			color: 'rgb(21, 23, 26)',
		} );

		if ( testInfo.project.name === 'chromium' ) {
			await activate.click();
			const dialog = page.getByRole( 'dialog', {
				name: 'Activate your ForgeTag',
			} );
			await expect( dialog ).toBeVisible();
			await expect( dialog.getByLabel( 'Tag ID' ) ).toBeFocused();
			await page.keyboard.press( 'Escape' );
			await expect( dialog ).toBeHidden();
			await expect( activate ).toBeFocused();
		}

		const accessibility = await new AxeBuilder( { page } )
			.exclude( 'dialog:not([open])' )
			.analyze();
		expect( accessibility.violations ).toEqual( [] );
		expect( consoleErrors ).toEqual( [] );
	} );

	test( 'keeps the 816px composition dense without collapsing every section', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 816, height: 900 } );
		await page.goto( '/', { waitUntil: 'domcontentloaded' } );
		await page.evaluate( () => document.fonts.ready );

		const layout = await page.evaluate( () => {
			const columns = ( selector: string ) =>
				getComputedStyle(
					document.querySelector< HTMLElement >( selector )!
				).gridTemplateColumns.split( ' ' ).length;
			const cards = [
				...document.querySelectorAll< HTMLElement >(
					'.forge-home-product'
				),
			].map( ( card ) => card.getBoundingClientRect() );

			return {
				documentHeight: document.documentElement.scrollHeight,
				heroColumns: columns( '.forge-home-hero__layout' ),
				productColumns: columns( '.forge-home-products__grid' ),
				productRows: [
					Math.round( cards[ 0 ].top ),
					Math.round( cards[ 1 ].top ),
					Math.round( cards[ 2 ].top ),
				],
				productWidths: cards.map( ( card ) =>
					Math.round( card.width )
				),
			};
		} );

		expect( layout.heroColumns ).toBe( 2 );
		expect( layout.productColumns ).toBe( 2 );
		expect( layout.productRows[ 0 ] ).toBe( layout.productRows[ 1 ] );
		expect( layout.productRows[ 2 ] ).toBeGreaterThan(
			layout.productRows[ 0 ]
		);
		expect( layout.productWidths[ 2 ] ).toBeGreaterThan(
			layout.productWidths[ 0 ] * 1.8
		);
		expect( layout.documentHeight ).toBeLessThan( 5500 );
	} );

	test( 'uses the available width for desktop process and privacy content', async ( {
		page,
	} ) => {
		for ( const width of [ 1440, 1920 ] ) {
			await page.setViewportSize( { width, height: 1080 } );
			await page.goto( '/', { waitUntil: 'domcontentloaded' } );
			await page.evaluate( () => document.fonts.ready );

			const layout = await page.evaluate( () => {
				const processPanel = document.querySelector< HTMLElement >(
					'.forge-home-process__panel'
				)!;
				const processGrid = document.querySelector< HTMLElement >(
					'.forge-return-route'
				)!;
				const privacyInner = document.querySelector< HTMLElement >(
					'.forge-home-privacy__inner'
				)!;
				const privacyTitle = document.querySelector< HTMLElement >(
					'.forge-home-privacy .forge-home-section-title'
				)!;
				const privacyGrid = document.querySelector< HTMLElement >(
					'.forge-home-privacy__grid'
				)!;
				const panelStyle = getComputedStyle( processPanel );
				const panelContentWidth =
					processPanel.getBoundingClientRect().width -
					parseFloat( panelStyle.paddingLeft ) -
					parseFloat( panelStyle.paddingRight );
				const privacyInnerRect = privacyInner.getBoundingClientRect();

				return {
					bodyOverflow:
						document.documentElement.scrollWidth -
						window.innerWidth,
					privacyColumns:
						getComputedStyle(
							privacyGrid
						).gridTemplateColumns.split( ' ' ).length,
					privacyRatio:
						privacyGrid.getBoundingClientRect().width /
						privacyInnerRect.width,
					privacyTitleOffset:
						privacyTitle.getBoundingClientRect().left -
						privacyInnerRect.left,
					processColumns:
						getComputedStyle(
							processGrid
						).gridTemplateColumns.split( ' ' ).length,
					processExpectedWidth: Math.min(
						panelContentWidth,
						parseFloat( getComputedStyle( processGrid ).maxWidth )
					),
					processWidth: processGrid.getBoundingClientRect().width,
				};
			} );

			expect( layout.processColumns ).toBe( 3 );
			expect(
				Math.abs( layout.processWidth - layout.processExpectedWidth )
			).toBeLessThan( 2 );
			expect( layout.privacyColumns ).toBe( 3 );
			expect( layout.privacyRatio ).toBeGreaterThanOrEqual( 0.95 );
			expect( Math.abs( layout.privacyTitleOffset ) ).toBeLessThan( 2 );
			expect( layout.bodyOverflow ).toBeLessThanOrEqual( 0 );
		}
	} );

	test( 'keeps the mobile hierarchy usable at 320px and 200 percent text', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 320, height: 720 } );
		await page.goto( '/', { waitUntil: 'domcontentloaded' } );

		await expect(
			page.locator( '.forge-site-header__brand' )
		).toBeVisible();
		await expect(
			page.locator( '.forge-site-header__activate' )
		).toBeVisible();
		await expect(
			page.locator( '.forge-site-header__report' )
		).toBeHidden();
		await expect(
			page.locator( '.forge-home-hero' ).getByRole( 'link', {
				name: 'Report a found tag',
			} )
		).toBeVisible();

		const compactLayout = await page.evaluate( () => {
			const brand = document
				.querySelector< HTMLElement >( '.forge-site-header__brand' )!
				.getBoundingClientRect();
			const account = document
				.querySelector< HTMLElement >(
					'.wp-block-woocommerce-customer-account'
				)!
				.getBoundingClientRect();
			const activate = document
				.querySelector< HTMLElement >( '.forge-site-header__activate' )!
				.getBoundingClientRect();
			const useCases = getComputedStyle(
				document.querySelector< HTMLElement >(
					'.forge-home-use-cases__grid'
				)!
			).gridTemplateColumns.split( ' ' ).length;

			return {
				accountLeft: Math.round( account.left ),
				accountTop: Math.round( account.top ),
				activateLeft: Math.round( activate.left ),
				activateTop: Math.round( activate.top ),
				brandLeft: Math.round( brand.left ),
				brandTop: Math.round( brand.top ),
				useCases,
			};
		} );
		expect( compactLayout.brandLeft ).toBeLessThan(
			compactLayout.accountLeft
		);
		expect(
			Math.abs( compactLayout.accountTop - compactLayout.brandTop )
		).toBeLessThan( 16 );
		expect( compactLayout.activateTop ).toBeGreaterThan(
			compactLayout.brandTop
		);
		expect( compactLayout.useCases ).toBe( 2 );

		const overflowBeforeZoom = await page.evaluate( () =>
			[ ...document.querySelectorAll< HTMLElement >( 'body *' ) ]
				.filter( ( element ) => {
					const rect = element.getBoundingClientRect();
					return rect.right > window.innerWidth + 1 || rect.left < -1;
				} )
				.map( ( element ) => ( {
					className: element.className,
					left: Math.round( element.getBoundingClientRect().left ),
					right: Math.round( element.getBoundingClientRect().right ),
					tagName: element.tagName,
					width: Math.round( element.getBoundingClientRect().width ),
				} ) )
		);
		expect( overflowBeforeZoom ).toEqual( [] );

		await page.evaluate( () => {
			document.documentElement.style.fontSize = '200%';
		} );
		const overflowAtTwoHundredPercent = await page.evaluate( () =>
			[ ...document.querySelectorAll< HTMLElement >( 'body *' ) ]
				.filter( ( element ) => {
					const rect = element.getBoundingClientRect();
					return rect.right > window.innerWidth + 1 || rect.left < -1;
				} )
				.map( ( element ) => ( {
					className: element.className,
					left: Math.round( element.getBoundingClientRect().left ),
					right: Math.round( element.getBoundingClientRect().right ),
					tagName: element.tagName,
					width: Math.round( element.getBoundingClientRect().width ),
				} ) )
		);
		expect( overflowAtTwoHundredPercent ).toEqual( [] );
	} );

	test( 'uses the TagCore full-screen report entry below 768px', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( '/', { waitUntil: 'domcontentloaded' } );
		await page
			.locator( '.forge-home-hero' )
			.getByRole( 'link', { name: 'Report a found tag' } )
			.click();

		await expect( page ).toHaveURL( /\/tag\/report\/$/ );
		await expect(
			page.getByRole( 'heading', { name: 'Report a found ForgeTag' } )
		).toBeVisible();
	} );
} );
