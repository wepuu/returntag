import { lstat, readFile, readdir } from 'node:fs/promises';
import { basename, dirname, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const repositoryRoot = dirname( dirname( fileURLToPath( import.meta.url ) ) );

const runtimeFiles = new Set( [
	'asset-manifest.json',
	'assets/css/commerce.css',
	'assets/css/foundation.css',
	'assets/css/home.css',
	'assets/fonts/inter/Inter-Variable-Roman.woff2',
	'assets/fonts/manrope/Manrope-Variable-Roman.woff2',
	'assets/icons/arrow-right.svg',
	'assets/icons/chevron-down.svg',
	'assets/icons/calendar-days.svg',
	'assets/icons/chart-no-axes-column-increasing.svg',
	'assets/icons/circle-alert.svg',
	'assets/icons/circle-check.svg',
	'assets/icons/key-round.svg',
	'assets/icons/loader-circle.svg',
	'assets/icons/luggage.svg',
	'assets/icons/mail-check.svg',
	'assets/icons/menu.svg',
	'assets/icons/package.svg',
	'assets/icons/qr-code.svg',
	'assets/icons/search.svg',
	'assets/icons/shield-check.svg',
	'assets/icons/shopping-bag.svg',
	'assets/icons/smartphone.svg',
	'assets/icons/star.svg',
	'assets/icons/user.svg',
	'assets/icons/x.svg',
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
	'patterns/home-brand-story.php',
	'patterns/home-hero.php',
	'patterns/home-privacy.php',
	'patterns/home-process.php',
	'patterns/home-products.php',
	'patterns/home-recovery-paths.php',
	'patterns/home-confidence.php',
	'patterns/home-testimonials.php',
	'patterns/home-use-cases.php',
	'patterns/page-404.php',
	'patterns/search-empty.php',
	'patterns/search-header.php',
	'patterns/site-footer.php',
	'patterns/site-header.php',
	'style.css',
	'templates/archive-product.html',
	'templates/404.html',
	'templates/front-page.html',
	'templates/index.html',
	'templates/page-cart.html',
	'templates/page-checkout.html',
	'templates/page.html',
	'templates/search.html',
	'templates/single-product.html',
	'theme.json',
] );

const toPortablePath = ( path ) => path.split( sep ).join( '/' );

const listFiles = async ( root, directory = root ) => {
	const files = [];
	for ( const entry of await readdir( directory, { withFileTypes: true } ) ) {
		const path = join( directory, entry.name );
		const stats = await lstat( path );
		if ( stats.isSymbolicLink() ) {
			files.push(
				`${ toPortablePath( relative( root, path ) ) }@symlink`
			);
		} else if ( entry.isDirectory() ) {
			files.push( ...( await listFiles( root, path ) ) );
		} else if ( entry.isFile() ) {
			files.push( toPortablePath( relative( root, path ) ) );
		}
	}

	return files.sort();
};

export const validateThemeArtifactDirectory = async ( {
	allowSourceReadme = false,
	themeRoot = join( repositoryRoot, 'theme/forge-tag' ),
	tag,
} = {} ) => {
	const failures = [];
	if ( basename( themeRoot ) !== 'forge-tag' ) {
		failures.push( 'Theme artifact directory must be named forge-tag' );
	}

	let files;
	try {
		files = await listFiles( themeRoot );
	} catch {
		return {
			failures: [ 'Theme artifact directory is unavailable' ],
			fileCount: 0,
		};
	}

	const actualFiles = new Set( files );
	if ( allowSourceReadme ) {
		actualFiles.delete( 'README.md' );
	}
	for ( const file of runtimeFiles ) {
		if ( ! actualFiles.has( file ) ) {
			failures.push( `Theme artifact is missing ${ file }` );
		}
	}
	for ( const file of actualFiles ) {
		if ( ! runtimeFiles.has( file ) ) {
			failures.push(
				`Theme artifact contains unapproved file ${ file }`
			);
		}
	}

	let version;
	try {
		const stylesheet = await readFile(
			join( themeRoot, 'style.css' ),
			'utf8'
		);
		version = stylesheet.match( /^Version:\s+(\S+)\s*$/m )?.[ 1 ];
	} catch {
		version = undefined;
	}
	if ( ! version ) {
		failures.push( 'Theme artifact style.css has no Version header' );
	}

	if ( tag !== undefined && tag !== `forge-tag-v${ version }` ) {
		failures.push(
			`Theme tag ${ tag } does not match forge-tag-v${ String(
				version
			) }`
		);
	}

	return { failures, fileCount: actualFiles.size, version };
};

const isDirectRun =
	process.argv[ 1 ] &&
	fileURLToPath( import.meta.url ) === resolve( process.argv[ 1 ] );

if ( isDirectRun ) {
	const rootIndex = process.argv.indexOf( '--theme-root' );
	const tagIndex = process.argv.indexOf( '--tag' );
	const result = await validateThemeArtifactDirectory( {
		allowSourceReadme: process.argv.includes( '--allow-source-readme' ),
		tag: tagIndex >= 0 ? process.argv[ tagIndex + 1 ] : undefined,
		themeRoot:
			rootIndex >= 0
				? resolve( process.argv[ rootIndex + 1 ] )
				: undefined,
	} );

	if ( result.failures.length > 0 ) {
		for ( const failure of result.failures ) {
			process.stderr.write( `Theme artifact check: ${ failure }.\n` );
		}
		process.exitCode = 1;
	} else {
		process.stdout.write(
			`Theme artifact contract passed: ForgeTag ${ result.version }, ${ result.fileCount } runtime files.\n`
		);
	}
}
