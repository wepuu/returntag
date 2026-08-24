import AxeBuilder from '@axe-core/playwright';

import { expect, test } from './fixtures';

test.describe( 'RT-314 ForgeTag homepage', () => {
	test( 'renders the brand shell and keeps product entry inside TagCore', async ( {
		baseURL,
		page,
	}, testInfo ) => {
		const externalRequests = new Set< string >();
		const consoleErrors: string[] = [];
		const expectedOrigin = new URL( baseURL ?? 'http://localhost:8888' )
			.origin;
		page.on( 'request', ( request ) => {
			const url = new URL( request.url() );
			if ( url.origin !== expectedOrigin ) {
				externalRequests.add( url.href );
			}
		} );
		page.on( 'console', ( message ) => {
			if ( message.type() === 'error' ) {
				consoleErrors.push( message.text() );
			}
		} );

		await page.setViewportSize( { width: 1440, height: 900 } );
		const response = await page.goto( '/', { waitUntil: 'networkidle' } );
		expect( response?.status() ).toBe( 200 );
		await page.evaluate( () => document.fonts.ready );
		await expect( page ).toHaveTitle( 'ForgeTag' );

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
			page.getByRole( 'heading', {
				name: 'From a brand built on travel security',
			} )
		).toBeVisible();
		await expect(
			page.getByRole( 'img', {
				name: 'Three black Forge travel locks with combination, cable, and keyed closures',
			} )
		).toBeVisible();
		await expect(
			page.getByText(
				'Since 2015, Forge has helped travelers protect what matters with TSA locks trusted by customers across Amazon and beyond. ForgeTag brings that same security mindset to item recovery and tracking.',
				{ exact: true }
			)
		).toBeVisible();

		const brandProofs = page.locator( '.forge-home-brand-story__proofs' );
		await expect( brandProofs ).toHaveAttribute( 'role', 'list' );
		await expect(
			brandProofs.locator( '.forge-home-brand-story__proof' )
		).toHaveCount( 3 );
		await expect( brandProofs.getByText( '2015' ) ).toBeVisible();
		await expect( brandProofs.getByText( 'Millions' ) ).toBeVisible();
		await expect( brandProofs.getByText( 'Trusted' ) ).toBeVisible();
		await expect(
			page.getByRole( 'heading', {
				name: 'Private by design, clear in the moment',
			} )
		).toBeVisible();

		const confidenceCards = page.locator( '.forge-home-confidence__card' );
		await expect( confidenceCards ).toHaveCount( 3 );
		await expect( confidenceCards.locator( 'h3' ) ).toHaveCount( 3 );
		await expect(
			confidenceCards.locator( 'img[aria-hidden="true"]' )
		).toHaveCount( 3 );
		await expect(
			confidenceCards.getByText( 'Contact details stay private', {
				exact: true,
			} )
		).toBeVisible();
		await expect(
			page.getByRole( 'heading', { name: 'Customer stories' } )
		).toBeVisible();
		await expect(
			page.getByText( 'Verified Buyer', { exact: false } )
		).toHaveCount( 3 );
		await expect(
			page.getByText( 'Demo content · development environment', {
				exact: true,
			} )
		).toHaveCount( 2 );
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
		await expect( productImages ).toHaveCount( 4 );
		await productImages.last().scrollIntoViewIfNeeded();
		await expect
			.poll( () =>
				productImages.evaluateAll( ( images ) =>
					images.every(
						( image ) =>
							image instanceof HTMLImageElement &&
							image.complete &&
							image.naturalWidth === 1254 &&
							image.naturalHeight === 1254
					)
				)
			)
			.toBe( true );

		const brandHeritageImage = page.locator(
			'.forge-home-brand-story__media img'
		);
		await brandHeritageImage.scrollIntoViewIfNeeded();
		await expect
			.poll( () =>
				brandHeritageImage.evaluate( ( image: HTMLImageElement ) => ( {
					complete: image.complete,
					height: image.naturalHeight,
					width: image.naturalWidth,
				} ) )
			)
			.toEqual( { complete: true, height: 2424, width: 3377 } );

		const brandProofMetrics = await page
			.locator( '.forge-home-brand-story__proof' )
			.evaluateAll( ( proofs ) =>
				proofs.map( ( proof ) => {
					const value = proof.querySelector(
						'strong'
					) as HTMLElement;
					const label = proof.querySelector(
						'.forge-home-brand-story__proof-copy span'
					) as HTMLElement;

					return {
						labelFits: label.scrollWidth <= label.clientWidth,
						valueFits: value.scrollWidth <= value.clientWidth,
					};
				} )
			);
		expect( brandProofMetrics ).toEqual( [
			{ labelFits: true, valueFits: true },
			{ labelFits: true, valueFits: true },
			{ labelFits: true, valueFits: true },
		] );
		const confidenceHeights = await confidenceCards.evaluateAll(
			( cards ) =>
				cards.map( ( card ) => card.getBoundingClientRect().height )
		);
		expect(
			Math.max( ...confidenceHeights ) - Math.min( ...confidenceHeights )
		).toBeLessThanOrEqual( 1 );

		expect(
			await page.getByRole( 'link', { name: 'Activate my tag' } ).count()
		).toBe( 2 );
		expect(
			await page.locator( '[data-returntag-tag-entry="report"]' ).count()
		).toBe( 2 );
		const entryRoots = page.locator( '[data-returntag-tag-entry]' );
		await expect( entryRoots ).toHaveCount( 4 );
		const entryContracts = await entryRoots.evaluateAll( ( roots ) =>
			roots.map( ( root ) => {
				const trigger = root.querySelector< HTMLAnchorElement >(
					'[data-returntag-tag-entry-trigger]'
				);
				const dialog = root.querySelector< HTMLDialogElement >(
					'[data-returntag-tag-entry-dialog]'
				);

				return {
					controls: trigger?.getAttribute( 'aria-controls' ) ?? '',
					dialogId: dialog?.id ?? '',
					intent: root.getAttribute( 'data-returntag-tag-entry' ),
					path: trigger ? new URL( trigger.href ).pathname : '',
					sameOrigin: trigger
						? new URL( trigger.href ).origin ===
						  window.location.origin
						: false,
				};
			} )
		);
		expect(
			new Set( entryContracts.map( ( entry ) => entry.dialogId ) ).size
		).toBe( 4 );
		for ( const entry of entryContracts ) {
			expect( entry.controls ).toBe( entry.dialogId );
			expect( entry.sameOrigin ).toBe( true );
			expect( entry.path ).toBe(
				entry.intent === 'activate' ? '/tag/activate/' : '/tag/report/'
			);
		}
		expect( await page.locator( 'main input:visible' ).count() ).toBe( 0 );
		expect( [ ...externalRequests ] ).toEqual( [] );

		const hero = page.locator( '.forge-home-hero' );
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
			for ( const root of await entryRoots.all() ) {
				const trigger = root.locator(
					'[data-returntag-tag-entry-trigger]'
				);
				const dialogId = await trigger.getAttribute( 'aria-controls' );
				expect( dialogId ).not.toBeNull();
				await trigger.click();
				const dialog = page.locator( `#${ dialogId }` );
				await expect( dialog ).toBeVisible();
				await expect( dialog.getByLabel( 'Tag ID' ) ).toBeFocused();
				await page.keyboard.press( 'Escape' );
				await expect( dialog ).toBeHidden();
				await expect( trigger ).toBeFocused();
			}
		}

		const accessibility = await new AxeBuilder( { page } )
			.exclude( 'dialog:not([open])' )
			.exclude( '.wc-block-mini-cart__drawer[aria-hidden="true"]' )
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
				brandColumns: columns( '.forge-home-brand-story__layout' ),
				brandProofColumns: columns( '.forge-home-brand-story__proofs' ),
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
				confidenceColumns: columns( '.forge-home-confidence__grid' ),
			};
		} );

		expect( layout.brandColumns ).toBe( 2 );
		expect( layout.brandProofColumns ).toBe( 3 );
		expect( layout.heroColumns ).toBe( 2 );
		expect( layout.productColumns ).toBe( 2 );
		expect( layout.confidenceColumns ).toBe( 3 );
		expect( layout.productRows[ 0 ] ).toBe( layout.productRows[ 1 ] );
		expect( layout.productRows[ 2 ] ).toBeGreaterThan(
			layout.productRows[ 0 ]
		);
		expect( layout.productWidths[ 2 ] ).toBeGreaterThan(
			layout.productWidths[ 0 ] * 1.8
		);
		expect( layout.documentHeight ).toBeLessThan( 7600 );
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
				)
				?.getBoundingClientRect();
			const activate = document
				.querySelector< HTMLElement >( '.forge-site-header__activate' )!
				.getBoundingClientRect();
			const useCases = getComputedStyle(
				document.querySelector< HTMLElement >(
					'.forge-home-use-cases__grid'
				)!
			).gridTemplateColumns.split( ' ' ).length;
			const brandStory = getComputedStyle(
				document.querySelector< HTMLElement >(
					'.forge-home-brand-story__layout'
				)!
			).gridTemplateColumns.split( ' ' ).length;
			const confidence = getComputedStyle(
				document.querySelector< HTMLElement >(
					'.forge-home-confidence__grid'
				)!
			).gridTemplateColumns.split( ' ' ).length;
			const brandProofs = getComputedStyle(
				document.querySelector< HTMLElement >(
					'.forge-home-brand-story__proofs'
				)!
			).gridTemplateColumns.split( ' ' ).length;

			return {
				accountLeft: account ? Math.round( account.left ) : null,
				accountTop: account ? Math.round( account.top ) : null,
				activateLeft: Math.round( activate.left ),
				activateTop: Math.round( activate.top ),
				brandStory,
				brandProofs,
				brandLeft: Math.round( brand.left ),
				brandTop: Math.round( brand.top ),
				confidence,
				useCases,
			};
		} );
		if (
			compactLayout.accountLeft !== null &&
			compactLayout.accountTop !== null
		) {
			expect( compactLayout.brandLeft ).toBeLessThan(
				compactLayout.accountLeft
			);
			expect(
				Math.abs( compactLayout.accountTop - compactLayout.brandTop )
			).toBeLessThan( 16 );
		}
		expect(
			Math.abs( compactLayout.activateTop - compactLayout.brandTop )
		).toBeLessThan( 16 );
		expect( compactLayout.useCases ).toBe( 2 );
		expect( compactLayout.brandStory ).toBe( 1 );
		expect( compactLayout.brandProofs ).toBe( 1 );
		expect( compactLayout.confidence ).toBe( 1 );

		const overflowBeforeZoom = await page.evaluate( () =>
			[ ...document.querySelectorAll< HTMLElement >( 'body *' ) ]
				.filter( ( element ) => {
					if ( element.closest( '[aria-hidden="true"]' ) ) {
						return false;
					}
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
					if ( element.closest( '[aria-hidden="true"]' ) ) {
						return false;
					}
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

	test( 'switches from full-screen navigation to dialog at 768px', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 767, height: 900 } );
		await page.goto( '/', { waitUntil: 'domcontentloaded' } );
		await page
			.locator( '.forge-home-hero' )
			.getByRole( 'link', { name: 'Activate my tag' } )
			.click();
		await expect( page ).toHaveURL( /\/tag\/activate\/$/ );

		await page.setViewportSize( { width: 768, height: 900 } );
		await page.goto( '/', { waitUntil: 'domcontentloaded' } );
		const trigger = page
			.locator( '.forge-home-hero' )
			.getByRole( 'link', { name: 'Activate my tag' } );
		await trigger.click();
		const dialog = page.getByRole( 'dialog', {
			name: 'Activate your ForgeTag',
		} );
		await expect( dialog ).toBeVisible();
		await expect( page ).toHaveURL( /\/$/ );
		await page.keyboard.press( 'Escape' );
		await expect( trigger ).toBeFocused();
	} );
} );
