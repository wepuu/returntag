import { createHash } from 'node:crypto';
import { readFile, readdir } from 'node:fs/promises';
import { dirname, extname, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

import { iconWhitelist, lucideVersion } from './sync-theme-icons.mjs';

const defaultRepositoryRoot = dirname(
	dirname( fileURLToPath( import.meta.url ) )
);
const expectedThemeVersion = '0.1.0';
const expectedPalette = new Map( [
	[ 'forge-red', '#DC1117' ],
	[ 'forge-red-hover', '#B90D13' ],
	[ 'ink', '#15171A' ],
	[ 'graphite', '#4F555E' ],
	[ 'cloud', '#F4F5F7' ],
	[ 'surface', '#FFFFFF' ],
	[ 'line', '#D9DDE3' ],
	[ 'focus-inner', '#FFFFFF' ],
	[ 'focus-outer', '#15171A' ],
] );
const expectedSpacing = new Map( [
	[ '2xs', '4px' ],
	[ 'xs', '8px' ],
	[ 'sm', '12px' ],
	[ 'md', '16px' ],
	[ 'lg', '24px' ],
	[ 'xl', '32px' ],
	[ '2xl', '48px' ],
	[ '3xl', '64px' ],
	[ '4xl', '96px' ],
	[ '5xl', '128px' ],
] );
const requiredThemeFiles = [
	'asset-manifest.json',
	'assets/css/commerce.css',
	'assets/css/foundation.css',
	'assets/css/home.css',
	'assets/fonts/inter/Inter-Variable-Roman.woff2',
	'assets/fonts/manrope/Manrope-Variable-Roman.woff2',
	'assets/images/forge-logo.png',
	'assets/images/forge-travel-lock-family.png',
	'assets/images/product-classic-family-safe.png',
	'assets/images/product-smart-tag.png',
	'assets/images/product-sticker-safe.png',
	'assets/images/review-avatars/chris-d.png',
	'assets/images/review-avatars/daniel-k.png',
	'assets/images/review-avatars/megan-r.png',
	'assets/licenses/Inter-OFL-1.1.txt',
	'assets/licenses/Lucide-ISC-and-Feather-MIT.txt',
	'assets/licenses/Manrope-OFL-1.1.txt',
	'functions.php',
	'parts/footer.html',
	'parts/header.html',
	'patterns/home-hero.php',
	'patterns/home-brand-story.php',
	'patterns/home-confidence.php',
	'patterns/home-testimonials.php',
	'patterns/home-privacy.php',
	'patterns/home-process.php',
	'patterns/home-products.php',
	'patterns/home-recovery-paths.php',
	'patterns/home-use-cases.php',
	'patterns/page-404.php',
	'patterns/search-empty.php',
	'patterns/search-header.php',
	'patterns/site-footer.php',
	'patterns/site-header.php',
	'style.css',
	'templates/404.html',
	'templates/front-page.html',
	'templates/index.html',
	'templates/archive-product.html',
	'templates/page-cart.html',
	'templates/page-checkout.html',
	'templates/page.html',
	'templates/search.html',
	'templates/single-product.html',
	'theme.json',
];
const homepagePatternSlugs = [
	'forge-tag/home-hero',
	'forge-tag/home-process',
	'forge-tag/home-products',
	'forge-tag/home-recovery-paths',
	'forge-tag/home-use-cases',
	'forge-tag/home-brand-story',
	'forge-tag/home-confidence',
	'forge-tag/home-testimonials',
	'forge-tag/home-privacy',
];
const approvedBrandStoryPatternPath = 'patterns/home-brand-story.php';
const requiredBrandHeritagePath = 'assets/images/forge-travel-lock-family.png';
const requiredReviewAvatarPaths = new Map( [
	[ 'megan_r', 'assets/images/review-avatars/megan-r.png' ],
	[ 'chris_d', 'assets/images/review-avatars/chris-d.png' ],
	[ 'daniel_k', 'assets/images/review-avatars/daniel-k.png' ],
] );
const requiredProductImageRoles = new Map( [
	[ 'sticker', 'assets/images/product-sticker-safe.png' ],
	[ 'classic_tag', 'assets/images/product-classic-family-safe.png' ],
	[ 'smart_tag', 'assets/images/product-smart-tag.png' ],
] );
const forbiddenAssetNames = [
	'homepage.png',
	'tanchuang.png',
	'forge-logo-light.png',
	'tag1.jpg',
	'tag2.png',
	'tag3.jpg',
	'tag4.jpg',
	'forge-smarttag.png',
	'a1.jpg',
	'ForgeTag文案设计.docx',
];

const readJson = async ( path ) => JSON.parse( await readFile( path, 'utf8' ) );

const sha256 = ( bytes ) =>
	createHash( 'sha256' ).update( bytes ).digest( 'hex' );

const collectFiles = async ( root ) => {
	const files = [];

	for ( const entry of await readdir( root, { withFileTypes: true } ) ) {
		const path = join( root, entry.name );

		if ( entry.isDirectory() ) {
			files.push( ...( await collectFiles( path ) ) );
		} else if ( entry.isFile() ) {
			files.push( path );
		}
	}

	return files;
};

const mapBySlug = ( values = [] ) =>
	new Map( values.map( ( value ) => [ value.slug, value ] ) );

const checkExactMap = ( actualValues, expected, label, failures ) => {
	const actual = mapBySlug( actualValues );

	if (
		actual.size !== expected.size ||
		[ ...actual.keys() ].some( ( slug ) => ! expected.has( slug ) )
	) {
		failures.push( `${ label } must expose only the approved slugs` );
	}

	for ( const [ slug, expectedValue ] of expected ) {
		if ( actual.get( slug )?.color !== undefined ) {
			if ( actual.get( slug )?.color !== expectedValue ) {
				failures.push( `${ label } ${ slug } has the wrong color` );
			}
		} else if ( actual.get( slug )?.size !== expectedValue ) {
			failures.push( `${ label } ${ slug } has the wrong size` );
		}
	}
};

const checkThemeJson = ( themeJson, failures ) => {
	if ( themeJson.version !== 3 ) {
		failures.push( 'theme.json must use schema version 3' );
	}

	checkExactMap(
		themeJson.settings?.color?.palette,
		expectedPalette,
		'theme.json palette',
		failures
	);
	checkExactMap(
		themeJson.settings?.spacing?.spacingSizes,
		expectedSpacing,
		'theme.json spacing scale',
		failures
	);

	if (
		themeJson.settings?.layout?.contentSize !== '48rem' ||
		themeJson.settings?.layout?.wideSize !== '90rem'
	) {
		failures.push( 'theme.json must keep the approved 48rem/90rem layout' );
	}

	const fontFamilies = mapBySlug(
		themeJson.settings?.typography?.fontFamilies
	);
	const fontContracts = [
		[
			'display',
			'Manrope',
			'200 800',
			'file:./assets/fonts/manrope/Manrope-Variable-Roman.woff2',
		],
		[
			'body',
			'Inter',
			'100 900',
			'file:./assets/fonts/inter/Inter-Variable-Roman.woff2',
		],
	];

	if ( fontFamilies.size !== fontContracts.length ) {
		failures.push(
			'theme.json must expose only the approved font families'
		);
	}

	for ( const [ slug, family, weight, source ] of fontContracts ) {
		const face = fontFamilies.get( slug )?.fontFace?.[ 0 ];

		if (
			face?.fontFamily !== family ||
			face?.fontWeight !== weight ||
			face?.fontDisplay !== 'swap' ||
			face?.src?.[ 0 ] !== source
		) {
			failures.push( `theme.json ${ slug } font contract is incorrect` );
		}
	}

	const disabledSettings = [
		themeJson.settings?.color?.custom,
		themeJson.settings?.color?.customDuotone,
		themeJson.settings?.color?.customGradient,
		themeJson.settings?.color?.defaultDuotone,
		themeJson.settings?.color?.defaultGradients,
		themeJson.settings?.color?.defaultPalette,
		themeJson.settings?.spacing?.customSpacingSize,
		themeJson.settings?.spacing?.defaultSpacingSizes,
		themeJson.settings?.typography?.customFontSize,
		themeJson.settings?.typography?.defaultFontSizes,
	];

	if ( disabledSettings.some( ( value ) => value !== false ) ) {
		failures.push(
			'theme.json must keep unapproved custom/default tokens off'
		);
	}
};

const checkManifestFile = async (
	themeRoot,
	path,
	expectedHash,
	label,
	failures
) => {
	try {
		const bytes = await readFile( join( themeRoot, path ) );
		if ( sha256( bytes ) !== expectedHash ) {
			failures.push(
				`${ label } SHA-256 does not match asset-manifest.json`
			);
		}
		return bytes;
	} catch {
		failures.push( `${ label } is missing` );
		return undefined;
	}
};

const checkAssets = async (
	themeRoot,
	sourcePackageRoot,
	manifest,
	failures
) => {
	if (
		manifest.schemaVersion !== 1 ||
		manifest.themeVersion !== expectedThemeVersion
	) {
		failures.push( 'asset-manifest.json version contract is incorrect' );
	}

	const logo = await checkManifestFile(
		themeRoot,
		manifest.brand?.runtimePath,
		manifest.brand?.sha256,
		'approved logo',
		failures
	);

	if ( logo ) {
		if (
			logo.subarray( 1, 4 ).toString( 'ascii' ) !== 'PNG' ||
			logo.readUInt32BE( 16 ) !== 300 ||
			logo.readUInt32BE( 20 ) !== 57 ||
			logo[ 25 ] !== 6
		) {
			failures.push( 'approved logo must remain a 300x57 RGBA PNG' );
		}
	}

	const brandHeritage = manifest.brandHeritage;
	if (
		brandHeritage?.runtimePath !== requiredBrandHeritagePath ||
		brandHeritage?.sourceSha256 !== brandHeritage?.runtimeSha256 ||
		brandHeritage?.transformation !== 'Exact byte-for-byte runtime copy' ||
		! /user-authorized/i.test( brandHeritage?.rights ?? '' ) ||
		! /brand owner approved/i.test( brandHeritage?.contentApproval ?? '' )
	) {
		failures.push( 'brand-heritage asset contract is incorrect' );
	} else {
		const runtime = await checkManifestFile(
			themeRoot,
			requiredBrandHeritagePath,
			brandHeritage.runtimeSha256,
			'brand-heritage image',
			failures
		);

		if (
			runtime &&
			( runtime.subarray( 1, 4 ).toString( 'ascii' ) !== 'PNG' ||
				runtime.readUInt32BE( 16 ) !== 3377 ||
				runtime.readUInt32BE( 20 ) !== 2424 ||
				runtime[ 25 ] !== 6 )
		) {
			failures.push(
				'brand-heritage image must remain a 3377x2424 RGBA PNG'
			);
		}
	}

	const reviewAvatars = new Map(
		( manifest.reviewAvatars ?? [] ).map( ( avatar ) => [
			avatar.role,
			avatar,
		] )
	);
	if (
		reviewAvatars.size !== requiredReviewAvatarPaths.size ||
		[ ...reviewAvatars.keys() ].some(
			( role ) => ! requiredReviewAvatarPaths.has( role )
		)
	) {
		failures.push(
			'asset-manifest.json must declare exactly the three reviewer avatars'
		);
	}
	for ( const [ role, runtimePath ] of requiredReviewAvatarPaths ) {
		const avatar = reviewAvatars.get( role );
		if (
			avatar?.runtimePath !== runtimePath ||
			avatar?.dimensions?.width !== 256 ||
			avatar?.dimensions?.height !== 256 ||
			! /non-identifying illustration, not a customer photograph/i.test(
				avatar?.source ?? ''
			)
		) {
			failures.push( `${ role } reviewer avatar contract is incorrect` );
			continue;
		}

		const runtime = await checkManifestFile(
			themeRoot,
			runtimePath,
			avatar.runtimeSha256,
			`${ role } reviewer avatar`,
			failures
		);

		if (
			runtime &&
			( runtime.subarray( 1, 4 ).toString( 'ascii' ) !== 'PNG' ||
				runtime.readUInt32BE( 16 ) !== 256 ||
				runtime.readUInt32BE( 20 ) !== 256 )
		) {
			failures.push(
				`${ role } reviewer avatar must remain a 256x256 PNG`
			);
		}
	}

	for ( const font of manifest.fonts ?? [] ) {
		const runtime = await checkManifestFile(
			themeRoot,
			font.runtimePath,
			font.runtimeSha256,
			`${ font.family } font`,
			failures
		);
		await checkManifestFile(
			themeRoot,
			font.licensePath,
			font.licenseSha256,
			`${ font.family } license`,
			failures
		);

		if ( runtime?.subarray( 0, 4 ).toString( 'ascii' ) !== 'wOF2' ) {
			failures.push( `${ font.family } runtime font must be WOFF2` );
		}
	}

	if ( manifest.fonts?.length !== 2 ) {
		failures.push( 'asset-manifest.json must declare exactly two fonts' );
	}

	const productImages = new Map(
		( manifest.productImages ?? [] ).map( ( image ) => [
			image.role,
			image,
		] )
	);

	if (
		productImages.size !== requiredProductImageRoles.size ||
		[ ...productImages.keys() ].some(
			( role ) => ! requiredProductImageRoles.has( role )
		)
	) {
		failures.push(
			'asset-manifest.json must declare exactly the three approved product image roles'
		);
	}

	for ( const [ role, runtimePath ] of requiredProductImageRoles ) {
		const image = productImages.get( role );
		if ( image?.runtimePath !== runtimePath ) {
			failures.push( `${ role } product image path is incorrect` );
			continue;
		}

		const runtime = await checkManifestFile(
			themeRoot,
			runtimePath,
			image.runtimeSha256,
			`${ role } product image`,
			failures
		);

		if (
			runtime &&
			( runtime.subarray( 1, 4 ).toString( 'ascii' ) !== 'PNG' ||
				runtime.readUInt32BE( 16 ) !== 1254 ||
				runtime.readUInt32BE( 20 ) !== 1254 ||
				runtime[ 25 ] !== 2 )
		) {
			failures.push(
				`${ role } product image must remain a 1254x1254 RGB PNG`
			);
		}
	}

	if (
		manifest.icons?.package !== 'lucide-static' ||
		manifest.icons?.version !== lucideVersion
	) {
		failures.push(
			`icon source must remain lucide-static ${ lucideVersion }`
		);
	}

	await checkManifestFile(
		themeRoot,
		manifest.icons?.licensePath,
		manifest.icons?.licenseSha256,
		'Lucide license',
		failures
	);

	const manifestIcons = new Map(
		( manifest.icons?.files ?? [] ).map( ( icon ) => [ icon.name, icon ] )
	);
	const runtimeIcons = ( await readdir( join( themeRoot, 'assets/icons' ) ) )
		.filter( ( name ) => extname( name ) === '.svg' )
		.map( ( name ) => name.slice( 0, -4 ) );

	if (
		manifestIcons.size !== iconWhitelist.length ||
		runtimeIcons.length !== iconWhitelist.length ||
		[ ...manifestIcons.keys(), ...runtimeIcons ].some(
			( name ) => ! iconWhitelist.includes( name )
		)
	) {
		failures.push( 'Theme icons must match the exact approved whitelist' );
	}

	for ( const name of iconWhitelist ) {
		const icon = manifestIcons.get( name );
		const runtime = await checkManifestFile(
			themeRoot,
			`assets/icons/${ name }.svg`,
			icon?.runtimeSha256,
			`${ name } icon`,
			failures
		);

		try {
			const source = await readFile(
				join( sourcePackageRoot, 'icons', `${ name }.svg` )
			);
			if ( sha256( source ) !== icon?.sourceSha256 ) {
				failures.push(
					`${ name } source icon does not match the manifest`
				);
			}
		} catch {
			failures.push( `${ name } source icon is unavailable` );
		}

		if ( runtime ) {
			const source = runtime.toString( 'utf8' );
			if (
				! source.includes( 'stroke-width="1.5"' ) ||
				source.includes( 'stroke-width="2"' )
			) {
				failures.push(
					`${ name } icon must use the approved 1.5 stroke`
				);
			}
		}
	}
};

const checkRuntimeBoundaries = async ( themeRoot, files, failures ) => {
	for ( const path of files ) {
		const relativePath = relative( themeRoot, path )
			.split( sep )
			.join( '/' );
		const lowerPath = relativePath.toLowerCase();

		if (
			forbiddenAssetNames.some(
				( name ) =>
					lowerPath === name.toLowerCase() ||
					lowerPath.endsWith( `/${ name.toLowerCase() }` )
			)
		) {
			failures.push( `${ relativePath } is a forbidden Theme asset` );
		}

		if ( ! /\.(?:css|html|json|md|mjs|php|svg|txt)$/i.test( path ) ) {
			continue;
		}

		const contents = await readFile( path, 'utf8' );
		if ( contents.includes( 'docs/design/' ) ) {
			failures.push( `${ relativePath } leaks a source-design path` );
		}
		if (
			forbiddenAssetNames.some( ( name ) =>
				contents.toLowerCase().includes( name.toLowerCase() )
			)
		) {
			failures.push( `${ relativePath } references a forbidden asset` );
		}
	}

	const runtimeTextPaths = files.filter(
		( path ) =>
			/\.(?:css|html|php)$/i.test( path ) &&
			relative( themeRoot, path ).split( sep ).join( '/' ) !== 'style.css'
	);
	for ( const path of runtimeTextPaths ) {
		const relativePath = relative( themeRoot, path )
			.split( sep )
			.join( '/' );
		const contents = await readFile( path, 'utf8' );

		if ( /https?:\/\//i.test( contents ) ) {
			failures.push( `${ relativePath } contains a remote runtime URL` );
		}
		if (
			/["'(=]\s*\/(?:tag\/(?:activate|report)\/?|t\/[^"')\s<]*)(?:["')\s]|$)/i.test(
				contents
			)
		) {
			failures.push(
				`${ relativePath } must not hard-code a TagCore entry path`
			);
		}
		if (
			/\.(?:returntag-entry-link|returntag-entry-dialog|returntag-entry-page)(?:\b|__)/.test(
				contents
			)
		) {
			failures.push(
				`${ relativePath } must not style or depend on TagCore DOM internals`
			);
		}
		if (
			/<(?:form|input)\b[^>]*(?:returntag|tag[_-]?id)/i.test( contents )
		) {
			failures.push(
				`${ relativePath } must not reproduce a TagCore Tag ID form`
			);
		}
		if (
			/end[- ]to[- ]end encrypt|verified pair|pairing verified|tsa approved|free shipping|30-day guarantee/i.test(
				contents
			)
		) {
			failures.push(
				`${ relativePath } contains an unapproved product claim`
			);
		}
	}

	const functions = await readFile(
		join( themeRoot, 'functions.php' ),
		'utf8'
	);
	const businessPatterns = [
		/\$wpdb\b/,
		/\b(?:get|update|add|delete)_option\s*\(/,
		/\bWC_[A-Za-z_]+/,
		/\bwp_mail\s*\(/,
		/["']\/(?:t|tag\/activate|finder)\//,
		/\bregister_rest_route\s*\(/,
	];
	if ( businessPatterns.some( ( pattern ) => pattern.test( functions ) ) ) {
		failures.push(
			'functions.php crosses the Theme presentation boundary'
		);
	}
};

const checkHomepageContract = async ( themeRoot, failures ) => {
	const template = await readFile(
		join( themeRoot, 'templates/front-page.html' ),
		'utf8'
	);

	let previousPatternIndex = -1;
	for ( const slug of homepagePatternSlugs ) {
		const patternIndex = template.indexOf( `"slug":"${ slug }"` );
		if ( patternIndex === -1 ) {
			failures.push( `front-page.html must include ${ slug }` );
		} else if ( patternIndex <= previousPatternIndex ) {
			failures.push(
				'front-page.html homepage Pattern order is incorrect'
			);
		}
		previousPatternIndex = patternIndex;
	}

	const patternFiles = [
		'patterns/site-header.php',
		'patterns/home-hero.php',
		'patterns/home-process.php',
		'patterns/home-products.php',
		'patterns/home-recovery-paths.php',
		'patterns/home-use-cases.php',
		'patterns/home-brand-story.php',
		'patterns/home-confidence.php',
		'patterns/home-privacy.php',
		'patterns/site-footer.php',
	];
	const contents = (
		await Promise.all(
			patternFiles.map( ( path ) =>
				readFile( join( themeRoot, path ), 'utf8' )
			)
		)
	).join( '\n' );

	if ( /\/(?:tag\/activate|tag\/report)\/?/i.test( contents ) ) {
		failures.push(
			'homepage Patterns must not hard-code TagCore entry paths'
		);
	}
	if ( /<(?:form|input)\b/i.test( contents ) ) {
		failures.push( 'homepage Patterns must not reproduce a Tag ID form' );
	}
	if (
		/\.returntag-entry-(?:link|dialog|entry)/.test(
			await readFile( join( themeRoot, 'assets/css/home.css' ), 'utf8' )
		)
	) {
		failures.push(
			'home.css must not style TagCore through deep selectors'
		);
	}

	const entryBlocks = [
		...contents.matchAll(
			/<!-- wp:tagcore\/tag-entry-link\s+(\{[^\r\n]+\})\s+\/-->/g
		),
	].map( ( match ) => JSON.parse( match[ 1 ] ) );
	const activate = entryBlocks.filter(
		( attributes ) => attributes.intent === 'activate'
	);
	const report = entryBlocks.filter(
		( attributes ) => attributes.intent === 'report'
	);

	if (
		entryBlocks.length !== 4 ||
		activate.length !== 2 ||
		report.length !== 2
	) {
		failures.push(
			'homepage shell must place two Activate and two Report TagCore blocks'
		);
	}
	if (
		report.some(
			( attributes ) =>
				typeof attributes.className !== 'string' ||
				! attributes.className
					.split( /\s+/ )
					.includes( 'is-style-secondary' )
		)
	) {
		failures.push(
			'every homepage Report block must use the secondary style'
		);
	}
	if (
		entryBlocks.some( ( attributes ) =>
			Object.keys( attributes ).some(
				( key ) => ! [ 'className', 'intent' ].includes( key )
			)
		)
	) {
		failures.push(
			'homepage TagCore blocks accept only intent and Block Style'
		);
	}

	for ( const family of [ 'Sticker', 'Classic Tag', 'Smart Tag' ] ) {
		if ( ! contents.includes( family ) ) {
			failures.push(
				`homepage is missing the ${ family } product family`
			);
		}
	}

	for ( const runtimePath of requiredProductImageRoles.values() ) {
		if ( ! contents.includes( runtimePath ) ) {
			failures.push(
				`homepage is missing the approved ${ runtimePath } product image`
			);
		}
	}

	if ( ! contents.includes( requiredBrandHeritagePath ) ) {
		failures.push(
			'homepage is missing the approved brand-heritage image'
		);
	}

	const brandStoryPattern = await readFile(
		join( themeRoot, approvedBrandStoryPatternPath ),
		'utf8'
	);
	const requiredBrandProofIcons = [
		'assets/icons/key-round.svg',
		'assets/icons/smartphone.svg',
		'assets/icons/calendar-days.svg',
		'assets/icons/chart-no-axes-column-increasing.svg',
		'assets/icons/shield-check.svg',
	];
	const requiredBrandCopy = [
		'BUILT FOR THE MOMENT SOMETHING GOES MISSING',
		'A clear route back, without public contact details',
		'ForgeTag keeps the recovery path simple:',
		'One Tag ID',
		'Six-character recovery route',
		'Any phone',
		'No ForgeTag app required',
		'Email hidden',
		'Private relay by default',
		'FROM A BRAND BUILT ON TRAVEL SECURITY',
		'Since 2015, Forge has helped travelers protect what matters',
		'Millions',
		'Sold',
		'Trusted',
		'Travel Brand',
		'Demo content · development environment',
	];
	if (
		( brandStoryPattern.match( /<h2\b/g ) ?? [] ).length !== 1 ||
		( brandStoryPattern.match( /<ul\b/g ) ?? [] ).length !== 1 ||
		( brandStoryPattern.match( /<li\b/g ) ?? [] ).length !== 1 ||
		! brandStoryPattern.includes(
			'foreach ( $forge_tag_brand_proofs as $forge_tag_brand_proof )'
		) ||
		! /<p class="forge-home-brand-story__summary">/.test(
			brandStoryPattern
		)
	) {
		failures.push(
			'homepage brand story must keep one heading, one summary, and a three-item semantic proof list'
		);
	}
	for ( const icon of requiredBrandProofIcons ) {
		if ( ! brandStoryPattern.includes( icon.split( '/' ).at( -1 ) ) ) {
			failures.push( `homepage brand story is missing ${ icon }` );
		}
	}
	for ( const copy of requiredBrandCopy ) {
		if ( ! brandStoryPattern.includes( copy ) ) {
			failures.push(
				`homepage brand story is missing owner-approved copy: ${ copy }`
			);
		}
	}

	const confidencePattern = await readFile(
		join( themeRoot, 'patterns/home-confidence.php' ),
		'utf8'
	);
	const requiredConfidenceIcons = [
		'assets/icons/qr-code.svg',
		'assets/icons/mail-check.svg',
		'assets/icons/shield-check.svg',
	];
	const requiredConfidenceCopy = [
		'RECOVERY, WITHOUT PUBLIC CONTACT DETAILS',
		'Private by design, clear in the moment',
		'A finder opens the recovery page',
		'Contact details stay private',
		'Smart finding stays separate',
		'No ForgeTag app is required.',
		'without showing either person’s email address.',
		'ForgeTag does not read network account, device, battery, pairing, or location data.',
	];
	if (
		( confidencePattern.match( /<article\b/g ) ?? [] ).length !== 3 ||
		( confidencePattern.match( /<h3\b/g ) ?? [] ).length !== 3 ||
		( confidencePattern.match( /aria-hidden="true"/g ) ?? [] ).length !== 3
	) {
		failures.push(
			'homepage confidence section must contain exactly three semantic recovery facts'
		);
	}
	for ( const path of requiredConfidenceIcons ) {
		if ( ! confidencePattern.includes( path ) ) {
			failures.push( `homepage confidence section is missing ${ path }` );
		}
	}
	for ( const copy of requiredConfidenceCopy ) {
		if ( ! confidencePattern.includes( copy ) ) {
			failures.push(
				`homepage confidence section is missing approved content: ${ copy }`
			);
		}
	}
	if (
		/placeholder|active tracking|tracking feature|carousel|swiper|pagination|autoplay|<script\b/i.test(
			confidencePattern
		)
	) {
		failures.push(
			'homepage confidence section contains unsupported behavior'
		);
	}

	const testimonialsPattern = await readFile(
		join( themeRoot, 'patterns/home-testimonials.php' ),
		'utf8'
	);
	if (
		! testimonialsPattern.includes( 'wp_get_environment_type()' ) ||
		! testimonialsPattern.includes( "array( 'development', 'local' )" ) ||
		! testimonialsPattern.includes(
			'Demo content · development environment'
		) ||
		( testimonialsPattern.match( /'avatar'\s+=>/g ) ?? [] ).length !== 3 ||
		! testimonialsPattern.includes( 'Verified Buyer' ) ||
		! testimonialsPattern.includes( 'Rated 5 out of 5' )
	) {
		failures.push(
			'homepage testimonial demos must remain explicit and development-only'
		);
	}

	if ( contents.includes( 'forge-home-hero--awaiting-media' ) ) {
		failures.push( 'homepage must not retain the awaiting-media state' );
	}
};

const checkContentSurfaceContract = async ( themeRoot, failures ) => {
	const functions = await readFile(
		join( themeRoot, 'functions.php' ),
		'utf8'
	);
	if (
		! functions.includes( "'document_title_parts'" ) ||
		! functions.includes( "_x( 'ForgeTag', 'Consumer-facing site title'" )
	) {
		failures.push(
			'functions.php must provide ForgeTag consumer-facing document metadata'
		);
	}

	const page = await readFile(
		join( themeRoot, 'templates/page.html' ),
		'utf8'
	);
	if (
		! page.includes( 'forge-content-surface forge-page' ) ||
		! page.includes( 'wp:post-title {"level":1}' ) ||
		! page.includes( 'wp:post-content ' )
	) {
		failures.push(
			'page.html must render one semantic Page title and assigned Page content in the global surface'
		);
	}

	const search = await readFile(
		join( themeRoot, 'templates/search.html' ),
		'utf8'
	);
	for ( const required of [
		'forge-tag/search-header',
		'wp:post-template',
		'wp:query-no-results',
		'forge-tag/search-empty',
	] ) {
		if ( ! search.includes( required ) ) {
			failures.push( `search.html is missing ${ required }` );
		}
	}
	const searchHeader = await readFile(
		join( themeRoot, 'patterns/search-header.php' ),
		'utf8'
	);
	if (
		! searchHeader.includes( 'wp:query-title' ) ||
		! searchHeader.includes( 'type":"search' )
	) {
		failures.push(
			'Search heading Pattern must render the inherited Search query title'
		);
	}

	const notFound = await readFile(
		join( themeRoot, 'templates/404.html' ),
		'utf8'
	);
	if (
		! notFound.includes( 'forge-content-surface--state' ) ||
		! notFound.includes( 'forge-tag/page-404' )
	) {
		failures.push( '404.html must render the ForgeTag recovery state' );
	}

	const notFoundPattern = await readFile(
		join( themeRoot, 'patterns/page-404.php' ),
		'utf8'
	);
	const notFoundEntryBlocks = [
		...notFoundPattern.matchAll(
			/<!-- wp:tagcore\/tag-entry-link\s+(\{[^\r\n]+\})\s+\/-->/g
		),
	].map( ( match ) => JSON.parse( match[ 1 ] ) );
	if (
		( notFoundPattern.match( /<h1\b/g ) ?? [] ).length !== 1 ||
		! notFoundPattern.includes( "home_url( '/' )" ) ||
		notFoundEntryBlocks.length !== 2 ||
		! notFoundEntryBlocks.some(
			( attributes ) => attributes.intent === 'activate'
		) ||
		! notFoundEntryBlocks.some(
			( attributes ) => attributes.intent === 'report'
		)
	) {
		failures.push(
			'404 Pattern must provide one H1, Home, Activate, and Report recovery actions'
		);
	}

	const searchEmpty = await readFile(
		join( themeRoot, 'patterns/search-empty.php' ),
		'utf8'
	);
	if (
		( searchEmpty.match( /<h2\b/g ) ?? [] ).length !== 1 ||
		! searchEmpty.includes( 'wp:search ' ) ||
		! searchEmpty.includes( "home_url( '/' )" )
	) {
		failures.push(
			'Search empty Pattern must provide one H2, a labelled Search form, and a Home action'
		);
	}
};

const checkCommerceContract = async ( themeRoot, failures ) => {
	const templatePaths = [
		'templates/archive-product.html',
		'templates/single-product.html',
		'templates/page-cart.html',
		'templates/page-checkout.html',
	];
	const templates = new Map(
		await Promise.all(
			templatePaths.map( async ( path ) => [
				path,
				await readFile( join( themeRoot, path ), 'utf8' ),
			] )
		)
	);

	for ( const [ path, contents ] of templates ) {
		if ( ! contents.includes( 'forge-commerce' ) ) {
			failures.push( `${ path } must use the Theme commerce wrapper` );
		}
		if ( /<(?:form|iframe|input|script)\b|wp:html\b/i.test( contents ) ) {
			failures.push( `${ path } contains forbidden custom behavior` );
		}
		if ( /\b(?:tag_id|owner_id|returntag_)\b/i.test( contents ) ) {
			failures.push( `${ path } crosses the TagCore data boundary` );
		}
	}

	const archive = templates.get( 'templates/archive-product.html' );
	if (
		! archive.includes( 'wp:woocommerce/product-collection ' ) ||
		! archive.includes( '"inherit":true' ) ||
		! archive.includes( 'wp:woocommerce/product-template' ) ||
		! archive.includes( 'wp:query-pagination' )
	) {
		failures.push(
			'archive-product.html must keep the inherited WooCommerce catalog contract'
		);
	}

	const single = templates.get( 'templates/single-product.html' );
	for ( const block of [
		'woocommerce/product-image-gallery',
		'post-title',
		'woocommerce/product-price',
		'post-excerpt',
		'woocommerce/add-to-cart-form',
		'woocommerce/product-details',
	] ) {
		if ( ! single.includes( `wp:${ block }` ) ) {
			failures.push( `single-product.html is missing ${ block }` );
		}
	}

	for ( const page of [ 'cart', 'checkout' ] ) {
		const path = `templates/page-${ page }.html`;
		const contents = templates.get( path );
		if (
			! contents.includes(
				`wp:woocommerce/page-content-wrapper {"page":"${ page }"}`
			) ||
			! contents.includes( 'wp:post-content ' )
		) {
			failures.push(
				`${ path } must render the assigned WooCommerce page content`
			);
		}
		if (
			new RegExp( `wp:woocommerce/${ page }(?:\\s|/)` ).test( contents )
		) {
			failures.push(
				`${ path } must not replace assigned page content with a direct ${ page } block`
			);
		}
	}

	const commerceCss = await readFile(
		join( themeRoot, 'assets/css/commerce.css' ),
		'utf8'
	);
	if (
		/\.wc-block-components-|\.woocommerce(?:\s|[-_.#:[>+~])/i.test(
			commerceCss
		)
	) {
		failures.push(
			'commerce.css must not depend on WooCommerce internal selectors'
		);
	}
};

export const validateTheme = async ( {
	repositoryRoot = defaultRepositoryRoot,
	themeRoot = join( repositoryRoot, 'theme/forge-tag' ),
	sourcePackageRoot = join( repositoryRoot, 'node_modules/lucide-static' ),
} = {} ) => {
	const failures = [];
	let files = [];

	try {
		files = await collectFiles( themeRoot );
	} catch {
		return { failures: [ 'theme/forge-tag is missing' ], fileCount: 0 };
	}

	const availableFiles = new Set(
		files.map( ( path ) =>
			relative( themeRoot, path ).split( sep ).join( '/' )
		)
	);
	for ( const required of requiredThemeFiles ) {
		if ( ! availableFiles.has( required ) ) {
			failures.push( `${ required } is missing` );
		}
	}

	for ( const name of iconWhitelist ) {
		if ( ! availableFiles.has( `assets/icons/${ name }.svg` ) ) {
			failures.push( `assets/icons/${ name }.svg is missing` );
		}
	}

	try {
		const stylesheet = await readFile(
			join( themeRoot, 'style.css' ),
			'utf8'
		);
		const headers = new Map(
			[ ...stylesheet.matchAll( /^([A-Za-z ]+):\s*(.+)$/gm ) ].map(
				( match ) => [ match[ 1 ], match[ 2 ].trim() ]
			)
		);
		if (
			headers.get( 'Theme Name' ) !== 'ForgeTag' ||
			headers.get( 'Version' ) !== expectedThemeVersion ||
			headers.get( 'Text Domain' ) !== 'forge-tag' ||
			headers.get( 'Requires at least' ) !== '6.9' ||
			headers.get( 'Requires PHP' ) !== '8.3'
		) {
			failures.push( 'style.css Theme identity contract is incorrect' );
		}
	} catch {
		// The missing-file failure above is sufficient.
	}

	try {
		checkThemeJson(
			await readJson( join( themeRoot, 'theme.json' ) ),
			failures
		);
	} catch ( error ) {
		failures.push(
			`theme.json is invalid: ${
				error instanceof Error ? error.message : String( error )
			}`
		);
	}

	try {
		await checkAssets(
			themeRoot,
			sourcePackageRoot,
			await readJson( join( themeRoot, 'asset-manifest.json' ) ),
			failures
		);
	} catch ( error ) {
		failures.push(
			`asset validation failed: ${
				error instanceof Error ? error.message : String( error )
			}`
		);
	}

	try {
		await checkRuntimeBoundaries( themeRoot, files, failures );
	} catch ( error ) {
		failures.push(
			`Theme boundary validation failed: ${
				error instanceof Error ? error.message : String( error )
			}`
		);
	}

	try {
		await checkHomepageContract( themeRoot, failures );
	} catch ( error ) {
		failures.push(
			`Homepage contract validation failed: ${
				error instanceof Error ? error.message : String( error )
			}`
		);
	}

	try {
		await checkContentSurfaceContract( themeRoot, failures );
	} catch ( error ) {
		failures.push(
			`Content surface contract validation failed: ${
				error instanceof Error ? error.message : String( error )
			}`
		);
	}

	try {
		await checkCommerceContract( themeRoot, failures );
	} catch ( error ) {
		failures.push(
			`Commerce contract validation failed: ${
				error instanceof Error ? error.message : String( error )
			}`
		);
	}

	return { failures: [ ...new Set( failures ) ], fileCount: files.length };
};

if (
	process.argv[ 1 ] &&
	fileURLToPath( import.meta.url ) === resolve( process.argv[ 1 ] )
) {
	const result = await validateTheme();

	if ( result.failures.length > 0 ) {
		for ( const failure of result.failures ) {
			process.stderr.write( `Theme check: ${ failure }.\n` );
		}
		process.exitCode = 1;
	} else {
		process.stdout.write(
			`Theme check passed: ${ result.fileCount } files, ${ iconWhitelist.length } pinned icons, two local fonts, one approved logo, and one approved brand-heritage image.\n`
		);
	}
}
