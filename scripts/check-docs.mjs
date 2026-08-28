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
const statusMarkdownPaths = [ 'README.md', 'docs/PROJECT_STATUS.md' ];
const finderEvidenceContract = {
	'AGENTS.md': [
		'exactly one required Finder Report evidence image',
		'Finder email verification remains mandatory before two-way conversation',
	],
	'docs/PRD.md': [
		'| Message for the owner | 选填，填写时为 10–500 字符 |',
		'| Item photo | 必填，且只能上传一张物品凭证照片 |',
		'returntag_finder_evidence_enabled',
	],
	'docs/adr/0019-finder-evidence-report-without-verification-gate.md': [
		'The initial flow is deliberately one-way',
		'**Schema before/after:** `8 -> 8`',
	],
	'docs/adr/0028-defer-finder-image-content-moderation.md': [
		'The current Finder Report runtime',
		'does not currently review image',
		'Content moderation is not a',
	],
	'docs/ARCHITECTURE.md': [
		'Finder evidence-report contract',
		'Infrastructure Stage 2 provides purpose-bound',
	],
	'docs/DATABASE.md': [
		'RT-315 Finder Report and private-media persistence expansion',
		'Schema `8 -> 10`',
	],
	'docs/SECURITY.md': [
		'RT-315 Finder evidence-report security contract',
		'The current phase performs no content review',
		'RETURNTAG_TAGCORE_PRIVATE_MEDIA_OBJECT_KEY_V1',
	],
	'docs/RELEASE.md': [
		'RT-315 Stage 2 keeps project/plugin version',
		'`returntag_finder_evidence_enabled`',
	],
	'docs/PROJECT_STATUS.md': [
		'RT-315 Stage 2 private-media safety foundation',
		'Schema is `10`',
	],
};
const ownerDashboardContract = {
	'docs/PRD.md': [
		'RT-317 Stage 0 Owner Dashboard contract',
		'returntag_owner_account_enabled',
	],
	'docs/adr/0022-owner-dashboard-and-tag-management-contract.md': [
		'Owner Dashboard and tag-management contract',
		'**Schema before/after:** `12 -> 12`',
	],
	'docs/ARCHITECTURE.md': [
		'RT-317 Owner Dashboard contract',
		'returntag_owner_account_enabled',
	],
	'docs/DATABASE.md': [
		'RT-317 Owner Dashboard data contract',
		'RT-317 Stage 0 keeps Schema `12`',
	],
	'docs/SECURITY.md': [
		'RT-317 Owner Dashboard security contract',
		'WordPress login cannot',
	],
	'docs/RELEASE.md': [
		'RT-317 Stage 0 release and rollback',
		'RT-317 Stage 0 is documentation-only',
	],
	'README.md': [
		'RT-317 Owner Dashboard Stage 0',
		'returntag_owner_account_enabled',
	],
	'docs/PROJECT_STATUS.md': [
		'RT-317 Stage 0 Owner Dashboard contract freeze',
		'ForgeTag Theme remains `0.1.0`, and Schema remains `12`',
	],
	'plugin/tagcore/src/Account/README.md': [
		'ADR 0022 freezes',
		'Account login is not Secure',
	],
};
const privacyRequestContract = {
	'docs/adr/0030-privacy-export-and-constrained-erasure-contract.md': [
		'**Status:** Accepted',
		'FORGETAG-PRIVACY-RETENTION-v1.0-20260827',
		'Backup natural expiry | 35 days',
		'Active owned Tag causes `action_required`',
		'privacy request table does not',
	],
	'docs/privacy/RT-339-DATA-MAP.md': [
		'**Status:** Accepted contract map',
		'External policy version:** `FORGETAG-PRIVACY-RETENTION-v1.0-20260827`',
		'Accountable privacy owner:** Forge Life LLC',
		'RT-340 Stage 1 makes every purpose eligible immediately after expiry or consumption',
		'Finder evidence | Exclude',
	],
	'docs/ARCHITECTURE.md': [
		'RT-339 privacy export and constrained-erasure contract',
		'runtime must remain default-disabled',
	],
	'docs/DATABASE.md': [
		'RT-339 privacy data-map contract',
		'keeps Schema `15`',
	],
	'docs/SECURITY.md': [
		'RT-339 privacy-request security contract',
		'Active Tag ownership is an `action_required` gate',
	],
	'docs/RELEASE.md': [
		'RT-339 privacy-contract release gate',
		'ADR 0030 is Accepted',
		'Production enablement remains separately gated',
	],
	'docs/ROADMAP.md': [
		'RT-339 privacy contract is `IN_PROGRESS`',
		'product/privacy contract is approved',
		'does not add Schema 16',
	],
	'docs/PROJECT_STATUS.md': [
		'RT-339 privacy contract approval',
		'RT-340 engineering is authorized',
		'RT-339 is `ACCEPTED` and merged through PR #101',
	],
};
const supersededFinderStatements = [
	'Finder email must be verified before the owner is notified.',
	'Finder email must be verified before owner notification.',
	'Finder 在邮箱验证前，Owner 不收到消息。',
	'- Finder 图片或附件；',
];

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

export const findUnintendedStructuralEscapes = ( contents ) => {
	const lineNumbers = [];
	let fenced = false;

	for ( const [ index, line ] of contents.split( /\r?\n/ ).entries() ) {
		if ( /^\s*```/.test( line ) ) {
			fenced = ! fenced;
			continue;
		}

		if (
			! fenced &&
			/^\s*(?:\\#{1,6}(?:\s|$)|\\[*+-](?:\s|$)|\d+\\\.(?:\s|$))/.test(
				line
			)
		) {
			lineNumbers.push( index + 1 );
		}
	}

	return lineNumbers;
};

const checkStatusMarkdownStructure = async () => {
	const failures = [];

	for ( const relativePath of statusMarkdownPaths ) {
		const contents = await readFile(
			join( repositoryRoot, relativePath ),
			'utf8'
		);

		for ( const lineNumber of findUnintendedStructuralEscapes(
			contents
		) ) {
			failures.push(
				`${ relativePath }:${ lineNumber }: unintended escaped Markdown heading or list marker`
			);
		}
	}

	return failures;
};

export const findMissingFinderEvidenceContract = ( contentsByPath ) => {
	const failures = [];

	for ( const [ relativePath, requiredText ] of Object.entries(
		finderEvidenceContract
	) ) {
		const contents = contentsByPath[ relativePath ] ?? '';

		for ( const text of requiredText ) {
			if ( ! contents.includes( text ) ) {
				failures.push(
					`${ relativePath }: missing RT-315 contract: ${ text }`
				);
			}
		}
	}

	for ( const [ relativePath, contents ] of Object.entries(
		contentsByPath
	) ) {
		for ( const statement of supersededFinderStatements ) {
			if ( contents.includes( statement ) ) {
				failures.push(
					`${ relativePath }: superseded Finder contract remains: ${ statement }`
				);
			}
		}
	}

	return failures;
};

export const findMissingOwnerDashboardContract = ( contentsByPath ) => {
	const failures = [];

	for ( const [ relativePath, requiredText ] of Object.entries(
		ownerDashboardContract
	) ) {
		const contents = contentsByPath[ relativePath ] ?? '';

		for ( const text of requiredText ) {
			if ( ! contents.includes( text ) ) {
				failures.push(
					`${ relativePath }: missing RT-317 contract: ${ text }`
				);
			}
		}
	}

	return failures;
};

export const findMissingPrivacyRequestContract = ( contentsByPath ) => {
	const failures = [];

	for ( const [ relativePath, requiredText ] of Object.entries(
		privacyRequestContract
	) ) {
		const contents = contentsByPath[ relativePath ] ?? '';

		for ( const text of requiredText ) {
			if ( ! contents.includes( text ) ) {
				failures.push(
					`${ relativePath }: missing RT-339 contract: ${ text }`
				);
			}
		}
	}

	return failures;
};

const checkFinderEvidenceContract = async () => {
	const contentsByPath = {};

	for ( const relativePath of Object.keys( finderEvidenceContract ) ) {
		contentsByPath[ relativePath ] = await readFile(
			join( repositoryRoot, relativePath ),
			'utf8'
		);
	}

	return findMissingFinderEvidenceContract( contentsByPath );
};

const checkOwnerDashboardContract = async () => {
	const contentsByPath = {};

	for ( const relativePath of Object.keys( ownerDashboardContract ) ) {
		contentsByPath[ relativePath ] = await readFile(
			join( repositoryRoot, relativePath ),
			'utf8'
		);
	}

	return findMissingOwnerDashboardContract( contentsByPath );
};

const checkPrivacyRequestContract = async () => {
	const contentsByPath = {};

	for ( const relativePath of Object.keys( privacyRequestContract ) ) {
		contentsByPath[ relativePath ] = await readFile(
			join( repositoryRoot, relativePath ),
			'utf8'
		);
	}

	return findMissingPrivacyRequestContract( contentsByPath );
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

export const runDocumentationChecks = async () => {
	const markdownFiles = trackedMarkdownFiles();
	const textFiles = trackedTextFiles();
	const links = await checkRelativeLinks( markdownFiles );
	const failures = [
		...links.failures,
		...( await checkStatusMarkdownStructure() ),
		...( await checkFinderEvidenceContract() ),
		...( await checkOwnerDashboardContract() ),
		...( await checkPrivacyRequestContract() ),
		...( await checkAssetManifest() ),
		...( await checkExclusions() ),
		...( await checkSecrets( textFiles ) ),
	];

	return {
		failures,
		markdownFileCount: markdownFiles.length,
		relativeLinkCount: links.checked,
		textFileCount: textFiles.length,
	};
};

if (
	process.argv[ 1 ] &&
	fileURLToPath( import.meta.url ) === resolve( process.argv[ 1 ] )
) {
	const result = await runDocumentationChecks();

	if ( result.failures.length > 0 ) {
		for ( const failure of result.failures ) {
			process.stderr.write( `Documentation check: ${ failure }.\n` );
		}

		process.exitCode = 1;
	} else {
		process.stdout.write(
			`Documentation check passed: ${ result.markdownFileCount } Markdown files, ${ result.relativeLinkCount } relative links, ${ result.textFileCount } text files scanned for secrets, and 9 design assets.\n`
		);
	}
}
