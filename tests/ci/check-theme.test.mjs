import assert from 'node:assert/strict';
import { cp, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

import { validateTheme } from '../../scripts/check-theme.mjs';

const repositoryRoot = dirname(
	dirname( dirname( fileURLToPath( import.meta.url ) ) )
);
const sourceThemeRoot = join( repositoryRoot, 'theme/forge-tag' );

const withThemeCopy = async ( mutate ) => {
	const temporaryRoot = await mkdtemp( join( tmpdir(), 'returntag-theme-' ) );
	const themeRoot = join( temporaryRoot, 'forge-tag' );

	try {
		await cp( sourceThemeRoot, themeRoot, { recursive: true } );
		await mutate( themeRoot );
		return await validateTheme( { repositoryRoot, themeRoot } );
	} finally {
		await rm( temporaryRoot, { recursive: true, force: true } );
	}
};

const assertFailure = ( result, pattern ) => {
	assert.ok(
		result.failures.some( ( failure ) => pattern.test( failure ) ),
		`Expected ${ pattern }, received:\n${ result.failures.join( '\n' ) }`
	);
};

describe( 'ForgeTag Theme contract', () => {
	it( 'accepts the source-controlled Stage 4 baseline', async () => {
		const result = await validateTheme( { repositoryRoot } );

		assert.deepEqual( result.failures, [] );
	} );

	it( 'rejects a missing WooCommerce template', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			await rm( join( themeRoot, 'templates/page-cart.html' ) );
		} );

		assertFailure( result, /templates\/page-cart\.html is missing/ );
	} );

	it( 'rejects bypassing assigned Cart page content', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'templates/page-cart.html' );
			const template = await readFile( path, 'utf8' );
			await writeFile(
				path,
				template.replace(
					'<!-- wp:post-content {"align":"wide","className":"forge-commerce__page-content"} /-->',
					'<!-- wp:woocommerce/cart /-->'
				)
			);
		} );

		assertFailure(
			result,
			/must render the assigned WooCommerce page content/
		);
		assertFailure( result, /must not replace assigned page content/ );
	} );

	it( 'rejects an incomplete commerce shell or transaction heading', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'templates/page-checkout.html' );
			const template = await readFile( path, 'utf8' );
			await writeFile(
				path,
				template
					.replace(
						'<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->',
						''
					)
					.replace( '<!-- wp:post-title {"level":1} /-->', '' )
			);
		} );

		assertFailure( result, /must retain the ForgeTag footer presentation/ );
		assertFailure(
			result,
			/must render the assigned WooCommerce page content/
		);
	} );

	it( 'rejects an incomplete responsive commerce presentation contract', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'assets/css/commerce.css' );
			const stylesheet = await readFile( path, 'utf8' );
			await writeFile(
				path,
				stylesheet.replaceAll(
					'.wp-block-woocommerce-checkout',
					'.wp-block-checkout-removed'
				)
			);
		} );

		assertFailure(
			result,
			/missing the RT-321 presentation contract: \.wp-block-woocommerce-checkout/
		);
	} );

	it( 'rejects commerce code that crosses Theme boundaries', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			await writeFile(
				join( themeRoot, 'templates/archive-product.html' ),
				'<main class="forge-commerce"><input name="tag_id"></main>'
			);
			await writeFile(
				join( themeRoot, 'assets/css/commerce.css' ),
				'.woocommerce .wc-block-components-button { color: red; }'
			);
		} );

		assertFailure( result, /contains forbidden custom behavior/ );
		assertFailure( result, /crosses the TagCore data boundary/ );
		assertFailure( result, /must not depend on WooCommerce internal/ );
	} );

	it( 'rejects missing third-party license evidence', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			await rm( join( themeRoot, 'assets/licenses/Inter-OFL-1.1.txt' ) );
		} );

		assertFailure( result, /Inter license is missing/ );
	} );

	it( 'rejects a changed Theme identity', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'style.css' );
			const stylesheet = await readFile( path, 'utf8' );
			await writeFile(
				path,
				stylesheet
					.replace( 'Version: 0.1.0', 'Version: 0.2.0' )
					.replace( 'Text Domain: forge-tag', 'Text Domain: wrong' )
			);
		} );

		assertFailure( result, /Theme identity contract is incorrect/ );
	} );

	it( 'rejects forbidden reference assets and source paths', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			await writeFile(
				join( themeRoot, 'homepage.png' ),
				'not an asset'
			);
			await writeFile(
				join( themeRoot, 'parts/footer.html' ),
				'<!-- docs/design/reference -->'
			);
		} );

		assertFailure( result, /homepage\.png is a forbidden Theme asset/ );
		assertFailure( result, /leaks a source-design path/ );
	} );

	it( 'rejects remote runtime assets and unsupported claims', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			await writeFile(
				join( themeRoot, 'assets/css/remote.css' ),
				'@import url("https://example.com/font.css");'
			);
			await writeFile(
				join( themeRoot, 'parts/footer.html' ),
				'<p>End-to-end encrypted and verified pairing.</p>'
			);
		} );

		assertFailure( result, /contains a remote runtime URL/ );
		assertFailure( result, /contains an unapproved product claim/ );
	} );

	it( 'rejects a modified font binary', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join(
				themeRoot,
				'assets/fonts/inter/Inter-Variable-Roman.woff2'
			);
			const font = await readFile( path );
			await writeFile(
				path,
				Buffer.concat( [ font, Buffer.from( 'x' ) ] )
			);
		} );

		assertFailure( result, /Inter font SHA-256/ );
	} );

	it( 'rejects a modified approved product image', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join(
				themeRoot,
				'assets/images/product-smart-tag.png'
			);
			const image = await readFile( path );
			await writeFile(
				path,
				Buffer.concat( [ image, Buffer.from( 'x' ) ] )
			);
		} );

		assertFailure( result, /smart_tag product image SHA-256/ );
	} );

	it( 'rejects a modified brand-heritage image', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join(
				themeRoot,
				'assets/images/forge-travel-lock-family.png'
			);
			const image = await readFile( path );
			await writeFile(
				path,
				Buffer.concat( [ image, Buffer.from( 'x' ) ] )
			);
		} );

		assertFailure( result, /brand-heritage image SHA-256/ );
	} );

	it( 'rejects missing brand-owner content approval', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'asset-manifest.json' );
			const manifest = JSON.parse( await readFile( path, 'utf8' ) );
			manifest.brandHeritage.contentApproval = 'Pending review';
			await writeFile( path, JSON.stringify( manifest, null, 2 ) );
		} );

		assertFailure( result, /brand-heritage asset contract is incorrect/ );
	} );

	it( 'rejects icons outside the approved whitelist', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			await writeFile(
				join( themeRoot, 'assets/icons/unapproved.svg' ),
				'<svg></svg>'
			);
		} );

		assertFailure( result, /exact approved whitelist/ );
	} );

	it( 'rejects a missing approved palette token', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'theme.json' );
			const themeJson = JSON.parse( await readFile( path, 'utf8' ) );
			themeJson.settings.color.palette =
				themeJson.settings.color.palette.filter(
					( color ) => color.slug !== 'forge-red'
				);
			await writeFile( path, JSON.stringify( themeJson, null, 2 ) );
		} );

		assertFailure( result, /palette must expose only the approved slugs/ );
	} );

	it( 'rejects hard-coded entry paths and copied forms', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'patterns/home-hero.php' );
			const pattern = await readFile( path, 'utf8' );
			await writeFile(
				path,
				`${ pattern }\n<form action="/tag/activate/"><input name="tag_id"></form>`
			);
		} );

		assertFailure( result, /must not hard-code TagCore entry paths/ );
		assertFailure( result, /must not reproduce a Tag ID form/ );
	} );

	it( 'rejects TagCore coupling outside homepage Patterns', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'templates/page.html' );
			const template = await readFile( path, 'utf8' );
			await writeFile(
				path,
				`${ template }\n<a href="/tag/report/">Report</a><a href="/t/A7R2W9/">Tag</a><form><input name="returntag_tag_id"></form>`
			);

			const cssPath = join( themeRoot, 'assets/css/commerce.css' );
			const stylesheet = await readFile( cssPath, 'utf8' );
			await writeFile(
				cssPath,
				`${ stylesheet }\n.returntag-entry-dialog__surface { color: red; }`
			);
		} );

		assertFailure( result, /must not hard-code a TagCore entry path/ );
		assertFailure( result, /must not reproduce a TagCore Tag ID form/ );
		assertFailure(
			result,
			/must not style or depend on TagCore DOM internals/
		);
	} );

	it( 'rejects missing entry intents and secondary treatment', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'patterns/home-hero.php' );
			const pattern = await readFile( path, 'utf8' );
			await writeFile(
				path,
				pattern.replace(
					'"intent":"report","className":"is-style-secondary"',
					'"intent":"activate"'
				)
			);
		} );

		assertFailure( result, /two Activate and two Report/ );
	} );

	it( 'rejects missing or reordered homepage trust Patterns', async () => {
		const missing = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'templates/front-page.html' );
			const template = await readFile( path, 'utf8' );
			await writeFile(
				path,
				template.replace(
					'\t<!-- wp:pattern {"slug":"forge-tag/home-confidence"} /-->\n',
					''
				)
			);
		} );

		assertFailure( missing, /must include forge-tag\/home-confidence/ );

		const reordered = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'templates/front-page.html' );
			const template = await readFile( path, 'utf8' );
			await writeFile(
				path,
				template.replace(
					'\t<!-- wp:pattern {"slug":"forge-tag/home-brand-story"} /-->\n\t<!-- wp:pattern {"slug":"forge-tag/home-confidence"} /-->',
					'\t<!-- wp:pattern {"slug":"forge-tag/home-confidence"} /-->\n\t<!-- wp:pattern {"slug":"forge-tag/home-brand-story"} /-->'
				)
			);
		} );

		assertFailure( reordered, /homepage Pattern order is incorrect/ );
	} );

	it( 'rejects a malformed recovery-fact strip and unsupported security claims', async () => {
		const malformed = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'patterns/home-brand-story.php' );
			const pattern = await readFile( path, 'utf8' );
			await writeFile(
				path,
				pattern.replace(
					'<li class="forge-home-brand-story__proof">',
					'<div class="forge-home-brand-story__proof">'
				)
			);
		} );

		assertFailure( malformed, /three-item semantic proof list/ );

		const misplacedClaim = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'parts/footer.html' );
			const footer = await readFile( path, 'utf8' );
			await writeFile( path, `${ footer }\n<p>End-to-end encrypted</p>` );
		} );

		assertFailure( misplacedClaim, /contains an unapproved product claim/ );
	} );

	it( 'rejects unsupported behavior in recovery confidence', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'patterns/home-confidence.php' );
			const pattern = await readFile( path, 'utf8' );
			await writeFile(
				path,
				`${ pattern }\n<script>window.demo = true;</script>`
			);
		} );

		assertFailure( result, /unsupported behavior/ );
	} );

	it( 'requires testimonial demos to stay explicit and development-only', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'patterns/home-testimonials.php' );
			const pattern = await readFile( path, 'utf8' );
			await writeFile(
				path,
				pattern.replace( 'wp_get_environment_type()', "'production'" )
			);
		} );

		assertFailure( result, /testimonial demos must remain explicit/ );
	} );

	it( 'rejects incomplete recovery-confidence cards and icons', async () => {
		const missingIcon = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'patterns/home-confidence.php' );
			const pattern = await readFile( path, 'utf8' );
			await writeFile(
				path,
				pattern.replace(
					'assets/icons/qr-code.svg',
					'assets/icons/x.svg'
				)
			);
		} );

		assertFailure(
			missingIcon,
			/homepage confidence section is missing assets\/icons\/qr-code\.svg/
		);
	} );

	it( 'rejects incomplete global content and metadata surfaces', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			await writeFile(
				join( themeRoot, 'templates/404.html' ),
				'<!-- wp:template-part {"slug":"header"} /-->'
			);
			const functionsPath = join( themeRoot, 'functions.php' );
			const functions = await readFile( functionsPath, 'utf8' );
			await writeFile(
				functionsPath,
				functions.replace( "'document_title_parts'", "'wrong_filter'" )
			);
		} );

		assertFailure( result, /404\.html must render/ );
		assertFailure( result, /consumer-facing document metadata/ );
	} );

	it( 'rejects Theme selectors that cross into TagCore markup', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'assets/css/home.css' );
			const stylesheet = await readFile( path, 'utf8' );
			await writeFile(
				path,
				`${ stylesheet }\n.returntag-entry-link__trigger { color: red; }`
			);
		} );

		assertFailure(
			result,
			/must not style TagCore through deep selectors/
		);
	} );
} );
