import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const baseUrl = new URL(
	process.env.RETURNTAG_WP_TEST_BASE_URL ?? 'http://127.0.0.1:8889/'
);

const countOccurrences = ( contents, value ) =>
	contents.split( value ).length - 1;

const requestPage = async ( path ) => {
	const response = await fetch( new URL( path, baseUrl ), {
		signal: AbortSignal.timeout( 15_000 ),
	} );

	return {
		body: await response.text(),
		response,
	};
};

const assert = ( condition, message ) => {
	if ( ! condition ) {
		throw new Error( message );
	}
};

const assertEntryPlacements = ( body ) => {
	assert(
		countOccurrences( body, 'data-returntag-tag-entry="activate"' ) === 2,
		'ForgeTag must render exactly two Activate entry placements.'
	);
	assert(
		countOccurrences( body, 'data-returntag-tag-entry="report"' ) === 2,
		'ForgeTag must render exactly two Report entry placements.'
	);
};

const checkEntry = async () => {
	const home = await requestPage( '/' );

	assert( home.response.status === 200, 'ForgeTag home response failed.' );
	assertEntryPlacements( home.body );

	for ( const intent of [ 'activate', 'report' ] ) {
		const page = await requestPage( `/tag/${ intent }/` );

		assert(
			page.response.status === 200,
			`TagCore ${ intent } entry failed.`
		);
		assert(
			page.response.headers.get( 'cache-control' ) ===
				'no-store, private',
			`TagCore ${ intent } cache policy failed.`
		);
		assert(
			page.response.headers.get( 'referrer-policy' ) === 'no-referrer',
			`TagCore ${ intent } referrer policy failed.`
		);
		assert(
			page.body.includes( 'returntag-entry-page' ),
			`TagCore ${ intent } entry markup failed.`
		);
	}
};

const checkWithoutWooCommerce = async () => {
	const home = await requestPage( '/' );

	assert(
		home.response.status === 200,
		'ForgeTag home response failed without WooCommerce.'
	);
	assertEntryPlacements( home.body );
};

const checkReplacementTheme = async () => {
	for ( const intent of [ 'activate', 'report' ] ) {
		const page = await requestPage( `/tag/${ intent }/` );

		assert(
			page.response.status === 200 &&
				page.body.includes( 'returntag-entry-page' ),
			`TagCore ${ intent } entry depends on ForgeTag Theme.`
		);
	}

	const scan = await requestPage( '/t/A7R2W9/' );
	assert(
		scan.response.status === 404 &&
			scan.body.includes( 'returntag-public--invalid' ) &&
			scan.body.includes( 'We could not find this ForgeTag' ),
		'Canonical Tag route depends on ForgeTag Theme.'
	);
};

const checkWithoutTagCore = async () => {
	const home = await requestPage( '/' );

	assert(
		home.response.status === 200 &&
			home.body.includes( 'Help what matters find its way back.' ),
		'ForgeTag brand shell failed without TagCore.'
	);
	assert(
		! home.body.includes( 'data-returntag-tag-entry' ) &&
			! home.body.includes( '/tag/activate/' ) &&
			! home.body.includes( '/tag/report/' ),
		'ForgeTag published fallback TagCore entry paths.'
	);
};

const checks = new Map( [
	[ 'entry', checkEntry ],
	[ 'without-woocommerce', checkWithoutWooCommerce ],
	[ 'replacement-theme', checkReplacementTheme ],
	[ 'without-tagcore', checkWithoutTagCore ],
] );

const run = async () => {
	const mode = process.argv[ 2 ];
	const check = checks.get( mode );

	if ( ! check ) {
		throw new Error(
			`Unknown wp-env runtime check: ${ mode ?? '(missing)' }`
		);
	}

	await check();
	process.stdout.write( `wp-env runtime check passed: ${ mode }.\n` );
};

if (
	process.argv[ 1 ] &&
	import.meta.url === pathToFileURL( resolve( process.argv[ 1 ] ) ).href
) {
	await run();
}
