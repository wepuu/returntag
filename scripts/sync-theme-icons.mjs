import { mkdir, readFile, readdir, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const repositoryRoot = dirname( dirname( fileURLToPath( import.meta.url ) ) );
const packageRoot = join( repositoryRoot, 'node_modules/lucide-static' );
const outputRoot = join( repositoryRoot, 'theme/forge-tag/assets/icons' );

export const lucideVersion = '1.27.0';

export const iconWhitelist = [
	'menu',
	'x',
	'user',
	'shopping-bag',
	'search',
	'arrow-right',
	'chevron-down',
	'qr-code',
	'mail-check',
	'shield-check',
	'circle-check',
	'circle-alert',
	'loader-circle',
	'calendar-days',
	'chart-no-axes-column-increasing',
	'star',
	'key-round',
	'luggage',
	'package',
	'smartphone',
];

export const normalizeIcon = ( source, name ) => {
	const license = `<!-- @license lucide-static v${ lucideVersion } - ISC -->`;

	if ( ! source.includes( license ) ) {
		throw new Error(
			`${ name }.svg is missing the pinned Lucide license.`
		);
	}

	const matches = source.match( /stroke-width="2"/g ) ?? [];

	if ( matches.length !== 1 ) {
		throw new Error(
			`${ name }.svg must declare exactly one source stroke width.`
		);
	}

	return source.replace( 'stroke-width="2"', 'stroke-width="1.5"' );
};

export const syncThemeIcons = async () => {
	const packageJson = JSON.parse(
		await readFile( join( packageRoot, 'package.json' ), 'utf8' )
	);

	if ( packageJson.version !== lucideVersion ) {
		throw new Error(
			`lucide-static declares ${ String(
				packageJson.version
			) }; expected ${ lucideVersion }.`
		);
	}

	await mkdir( outputRoot, { recursive: true } );

	const existingIcons = ( await readdir( outputRoot ) ).filter( ( name ) =>
		name.endsWith( '.svg' )
	);
	const unexpectedIcons = existingIcons.filter(
		( name ) => ! iconWhitelist.includes( name.slice( 0, -4 ) )
	);

	if ( unexpectedIcons.length > 0 ) {
		throw new Error(
			`Refusing to remove unexpected Theme icons: ${ unexpectedIcons.join(
				', '
			) }.`
		);
	}

	for ( const name of iconWhitelist ) {
		const source = await readFile(
			join( packageRoot, 'icons', `${ name }.svg` ),
			'utf8'
		);
		await writeFile(
			join( outputRoot, `${ name }.svg` ),
			normalizeIcon( source, name ),
			'utf8'
		);
	}
};

const isCommandLine =
	process.argv[ 1 ] &&
	fileURLToPath( import.meta.url ) === resolve( process.argv[ 1 ] );

if ( isCommandLine ) {
	try {
		await syncThemeIcons();
		process.stdout.write(
			`Synchronized ${ iconWhitelist.length } Lucide ${ lucideVersion } icons.\n`
		);
	} catch ( error ) {
		process.stderr.write(
			`${ error instanceof Error ? error.message : String( error ) }\n`
		);
		process.exitCode = 1;
	}
}
