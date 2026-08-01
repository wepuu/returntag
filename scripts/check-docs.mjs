import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { readFile } from 'node:fs/promises';
import { dirname, extname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const repositoryRoot = dirname( dirname( fileURLToPath( import.meta.url ) ) );
const designDirectory = join( repositoryRoot, 'docs', 'design' );
const manifestPath = join( designDirectory, 'ASSET-MANIFEST-V1.md' );
const guidePath = join( designDirectory, 'UI-STYLE-GUIDE-V1.md' );
const excludedAssets = [ 'a1.jpg', 'ForgeTag文案设计.docx' ];

const runGit = ( arguments_ ) => {
	const result = spawnSync( 'git', arguments_, {
		cwd: repositoryRoot,
		encoding: 'utf8',
	} );

	if ( result.status !== 0 ) {
		throw new Error(
			result.stderr.trim() || `git ${ arguments_.join( ' ' ) } failed.`
		);
	}

	return result.stdout;
};

const trackedMarkdownFiles = () =>
	runGit( [
		'ls-files',
		'--cached',
		'--others',
		'--exclude-standard',
		'-z',
		'--',
		'*.md',
	] )
		.split( '\0' )
		.filter( Boolean );

const trackedTextFiles = () =>
	runGit( [ 'ls-files', '--cached', '--others', '--exclude-standard', '-z' ] )
		.split( '\0' )
		.filter( ( path ) =>
			/(?:^|\/)(?:\.gitignore|\.nvmrc)$|\.(?:css|html|js|json|jsx|md|mjs|php|ps1|sh|ts|tsx|txt|xml|ya?ml)$/i.test(
				path
			)
		);

const stripFencedCode = ( contents ) => {
	let fenced = false;

	return contents
		.split( /\r?\n/ )
		.map( ( line ) => {
			if ( /^\s*```/.test( line ) ) {
				fenced = ! fenced;
				return '';
			}

			return fenced ? '' : line;
		} )
		.join( '\n' );
};

const checkRelativeLinks = async ( files ) => {
	const failures = [];
	let checked = 0;

	for ( const relativePath of files ) {
		const absolutePath = join( repositoryRoot, relativePath );
		const contents = stripFencedCode(
			await readFile( absolutePath, 'utf8' )
		);
		const links = contents.matchAll(
			/!?\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g
		);

		for ( const match of links ) {
			const target = match[ 1 ];

			if ( /^(?:https?:|mailto:|#)/i.test( target ) ) {
				continue;
			}

			checked += 1;
			const withoutFragment = target.split( '#', 1 )[ 0 ];
			const resolved = resolve(
				dirname( absolutePath ),
				decodeURIComponent( withoutFragment )
			);

			if ( ! existsSync( resolved ) ) {
				failures.push( `${ relativePath }: missing ${ target }` );
			}
		}
	}

	return { checked, failures };
};

const pngMetadata = ( bytes ) => ( {
	width: bytes.readUInt32BE( 16 ),
	height: bytes.readUInt32BE( 20 ),
	colorType: bytes[ 25 ],
	hasTransparencyChunk: bytes.includes( Buffer.from( 'tRNS', 'ascii' ) ),
} );

const jpegMetadata = ( bytes ) => {
	const startOfFrameMarkers = new Set( [
		0xc0, 0xc1, 0xc2, 0xc3, 0xc5, 0xc6, 0xc7, 0xc9, 0xca, 0xcb, 0xcd, 0xce,
		0xcf,
	] );

	for ( let index = 0; index < bytes.length - 9; index += 1 ) {
		if (
			bytes[ index ] === 0xff &&
			startOfFrameMarkers.has( bytes[ index + 1 ] )
		) {
			return {
				width: bytes.readUInt16BE( index + 7 ),
				height: bytes.readUInt16BE( index + 5 ),
			};
		}
	}

	throw new Error( 'JPEG start-of-frame marker was not found.' );
};

const validateFormatDescription = ( filename, format, metadata ) => {
	if ( extname( filename ).toLowerCase() === '.jpg' ) {
		return (
			format.includes( 'JPEG RGB 8-bit' ) && format.includes( '无 Alpha' )
		);
	}

	if ( metadata.colorType === 6 ) {
		return (
			format.includes( 'PNG RGBA 8-bit' ) && format.includes( '有 Alpha' )
		);
	}

	if ( metadata.colorType === 2 ) {
		return (
			format.includes( 'PNG RGB 8-bit' ) && format.includes( '无 Alpha' )
		);
	}

	if ( metadata.colorType === 3 && ! metadata.hasTransparencyChunk ) {
		return (
			format.includes( 'PNG Indexed 8-bit' ) &&
			format.includes( '无 Alpha' )
		);
	}

	return false;
};

const checkAssetManifest = async () => {
	const manifest = await readFile( manifestPath, 'utf8' );
	const rowPattern =
		/^\| `([^`]+\.(?:png|jpg))` \| `(?:reference-only|production-approved)` \| (\d+)×(\d+) \| (.+?) \| ([\d,]+) \| `([a-f0-9]{64})` \|$/gm;
	const rows = [ ...manifest.matchAll( rowPattern ) ];
	const failures = [];

	if ( rows.length !== 9 ) {
		failures.push(
			`manifest contains ${ rows.length } image rows; expected 9`
		);
	}

	for ( const row of rows ) {
		const [ , filename, width, height, format, byteCount, expectedHash ] =
			row;
		const path = join( designDirectory, filename );

		if ( ! existsSync( path ) ) {
			failures.push( `${ filename }: source file is missing` );
			continue;
		}

		const bytes = readFileSync( path );
		const metadata =
			extname( filename ).toLowerCase() === '.png'
				? pngMetadata( bytes )
				: jpegMetadata( bytes );
		const actualHash = createHash( 'sha256' )
			.update( bytes )
			.digest( 'hex' );

		if (
			metadata.width !== Number( width ) ||
			metadata.height !== Number( height )
		) {
			failures.push(
				`${ filename }: dimensions are ${ metadata.width }×${ metadata.height }, manifest declares ${ width }×${ height }`
			);
		}

		if ( bytes.length !== Number( byteCount.replaceAll( ',', '' ) ) ) {
			failures.push(
				`${ filename }: byte count does not match the manifest`
			);
		}

		if ( actualHash !== expectedHash ) {
			failures.push(
				`${ filename }: SHA-256 does not match the manifest`
			);
		}

		if ( ! validateFormatDescription( filename, format, metadata ) ) {
			failures.push(
				`${ filename }: format or Alpha declaration is incorrect`
			);
		}
	}

	return failures;
};

const checkExclusions = async () => {
	const ignore = await readFile(
		join( repositoryRoot, '.gitignore' ),
		'utf8'
	);
	const guide = await readFile( guidePath, 'utf8' );
	const tracked = new Set(
		runGit( [ 'ls-files', '-z' ] ).split( '\0' ).filter( Boolean )
	);
	const failures = [];

	for ( const filename of excludedAssets ) {
		const repositoryPath = `docs/design/${ filename }`;
		const expectedRule = `/${ repositoryPath }`;

		if ( ! ignore.split( /\r?\n/ ).includes( expectedRule ) ) {
			failures.push( `${ filename }: exact .gitignore rule is missing` );
		}

		if ( tracked.has( repositoryPath ) ) {
			failures.push( `${ filename }: excluded local file is tracked` );
		}

		if ( guide.includes( filename ) ) {
			failures.push(
				`${ filename }: excluded file appears in the UI guide`
			);
		}
	}

	return failures;
};

const checkSecrets = async ( files ) => {
	const patterns = [
		/-----BEGIN (?:RSA |OPENSSH |EC )?PRIVATE KEY-----/,
		/\bAKIA[0-9A-Z]{16}\b/,
		/\bgh[opusr]_[A-Za-z0-9]{20,}\b/,
		/\bsk-[A-Za-z0-9_-]{20,}\b/,
		/\bxox[baprs]-[A-Za-z0-9-]{20,}\b/,
	];
	const failures = [];

	for ( const relativePath of files ) {
		const contents = await readFile(
			join( repositoryRoot, relativePath ),
			'utf8'
		);

		if ( patterns.some( ( pattern ) => pattern.test( contents ) ) ) {
			failures.push(
				`${ relativePath }: possible secret pattern detected`
			);
		}
	}

	return failures;
};

const markdownFiles = trackedMarkdownFiles();
const textFiles = trackedTextFiles();
const links = await checkRelativeLinks( markdownFiles );
const failures = [
	...links.failures,
	...( await checkAssetManifest() ),
	...( await checkExclusions() ),
	...( await checkSecrets( textFiles ) ),
];

if ( failures.length > 0 ) {
	for ( const failure of failures ) {
		process.stderr.write( `Documentation check: ${ failure }.\n` );
	}

	process.exitCode = 1;
} else {
	process.stdout.write(
		`Documentation check passed: ${ markdownFiles.length } Markdown files, ${ links.checked } relative links, ${ textFiles.length } text files scanned for secrets, and 9 design assets.\n`
	);
}
