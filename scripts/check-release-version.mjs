import { readFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const repositoryRoot = dirname( dirname( fileURLToPath( import.meta.url ) ) );
const expectedVersion = '0.4.0';
const expectedThemeVersion = '0.1.0';

const readJson = async ( path ) =>
	JSON.parse( await readFile( join( repositoryRoot, path ), 'utf8' ) );

const [ packageJson, packageLock, bootstrap, themeStylesheet ] =
	await Promise.all( [
		readJson( 'package.json' ),
		readJson( 'package-lock.json' ),
		readFile(
			join( repositoryRoot, 'plugin/tagcore/tagcore.php' ),
			'utf8'
		),
		readFile( join( repositoryRoot, 'theme/forge-tag/style.css' ), 'utf8' ),
	] );

const versions = new Map( [
	[ 'package.json', packageJson.version ],
	[ 'package-lock.json', packageLock.version ],
	[ 'package-lock.json root package', packageLock.packages?.[ '' ]?.version ],
	[
		'TagCore plugin header',
		bootstrap.match( /^\s*\*\s+Version:\s+(\S+)\s*$/m )?.[ 1 ],
	],
	[
		'RETURNTAG_TAGCORE_VERSION',
		bootstrap.match(
			/define\(\s*'RETURNTAG_TAGCORE_VERSION',\s*'([^']+)'\s*\);/
		)?.[ 1 ],
	],
] );

const mismatches = [ ...versions ].filter(
	( [ , version ] ) => version !== expectedVersion
);

const themeVersions = new Map( [
	[
		'ForgeTag theme header',
		themeStylesheet.match( /^Version:\s+(\S+)\s*$/m )?.[ 1 ],
	],
] );

const themeMismatches = [ ...themeVersions ].filter(
	( [ , version ] ) => version !== expectedThemeVersion
);

if ( mismatches.length > 0 || themeMismatches.length > 0 ) {
	for ( const [ source, version ] of [ ...mismatches, ...themeMismatches ] ) {
		process.stderr.write(
			`${ source } declares ${ String( version ) }; expected ${
				themeVersions.has( source )
					? expectedThemeVersion
					: expectedVersion
			}.\n`
		);
	}

	process.exitCode = 1;
} else {
	process.stdout.write(
		`Release metadata consistently declares TagCore ${ expectedVersion } and ForgeTag Theme ${ expectedThemeVersion }.\n`
	);
}
