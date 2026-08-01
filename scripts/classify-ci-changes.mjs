import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const documentationPaths = [
	/^docs\//,
	/^\.github\/ISSUE_TEMPLATE\//,
	/^README\.md$/,
	/^AGENTS\.md$/,
	/^\.gitignore$/,
];

const fullCheckPaths = [
	/^\.github\/(?:workflows|scripts)\//,
	/^scripts\//,
	/^tests\/ci\//,
	/^tests\/e2e\//,
	/^playwright(?:\.[^.]+)?\.config\.ts$/,
	/^\.wp-env(?:\.[^.]+)?\.json$/,
	/^(?:package|package-lock|pnpm-lock|pnpm-workspace)\.(?:json|yaml)$/,
	/^plugin\/tagcore\/composer\.(?:json|lock)$/,
];

const runtimePaths = [ /^plugin\/tagcore\//, /^theme\//, /^tests\/js\// ];

const databasePaths = [
	/^plugin\/tagcore\/(?:src|tests)\/.*\/(?:Migration|Persistence)\//,
	/^plugin\/tagcore\/.*Repository(?:Test)?\.php$/,
	/^plugin\/tagcore\/tests\/Integration\//,
];

const matchesAny = ( path, patterns ) =>
	patterns.some( ( pattern ) => pattern.test( path ) );

const fullClassification = ( reasons ) => ( {
	docs_changed: true,
	runtime_changed: true,
	database_sensitive: true,
	full_e2e: true,
	mode: 'full',
	reasons,
} );

export const classifyPaths = ( inputPaths ) => {
	const paths = [
		...new Set(
			inputPaths
				.map( ( path ) => path.replaceAll( '\\', '/' ).trim() )
				.filter( Boolean )
		),
	];

	if ( paths.length === 0 ) {
		return fullClassification( [ 'empty_diff' ] );
	}

	let docsChanged = false;
	let runtimeChanged = false;
	let databaseSensitive = false;
	const fullReasons = [];

	for ( const path of paths ) {
		if ( matchesAny( path, documentationPaths ) ) {
			docsChanged = true;
			continue;
		}

		if ( matchesAny( path, fullCheckPaths ) ) {
			fullReasons.push( path );
			continue;
		}

		if ( matchesAny( path, runtimePaths ) ) {
			runtimeChanged = true;
			databaseSensitive ||= matchesAny( path, databasePaths );
			continue;
		}

		fullReasons.push( path );
	}

	if ( fullReasons.length > 0 ) {
		return fullClassification( fullReasons );
	}

	return {
		docs_changed: docsChanged,
		runtime_changed: runtimeChanged,
		database_sensitive: databaseSensitive,
		full_e2e: false,
		mode: runtimeChanged ? 'runtime' : 'docs',
		reasons: [],
	};
};

const parseArguments = ( arguments_ ) => {
	const options = {};

	for ( let index = 0; index < arguments_.length; index += 2 ) {
		const key = arguments_[ index ];
		const value = arguments_[ index + 1 ];

		if ( ! key?.startsWith( '--' ) || value === undefined ) {
			throw new Error( 'Expected --base <sha> --head <sha>.' );
		}

		options[ key.slice( 2 ) ] = value;
	}

	return options;
};

const readChangedPaths = ( base, head ) => {
	if (
		! /^[a-f0-9]{40}$/i.test( base ) ||
		! /^[a-f0-9]{40}$/i.test( head )
	) {
		throw new Error( 'Base and head must be full Git commit SHAs.' );
	}

	if ( /^0{40}$/.test( base ) ) {
		return [];
	}

	const result = spawnSync(
		'git',
		[
			'diff',
			'--no-renames',
			'--name-only',
			'--diff-filter=ACDMRTUXB',
			'-z',
			base,
			head,
		],
		{ encoding: 'utf8' }
	);

	if ( result.status !== 0 ) {
		throw new Error( result.stderr.trim() || 'git diff failed.' );
	}

	return result.stdout.split( '\0' ).filter( Boolean );
};

const isCommandLine =
	process.argv[ 1 ] &&
	fileURLToPath( import.meta.url ) === resolve( process.argv[ 1 ] );

if ( isCommandLine ) {
	try {
		const { base, head } = parseArguments( process.argv.slice( 2 ) );
		const classification = classifyPaths( readChangedPaths( base, head ) );

		for ( const key of [
			'docs_changed',
			'runtime_changed',
			'database_sensitive',
			'full_e2e',
			'mode',
		] ) {
			process.stdout.write( `${ key }=${ classification[ key ] }\n` );
		}
		process.stdout.write( 'classifier_ok=true\n' );

		process.stderr.write(
			`CI classification: ${ classification.mode }${
				classification.reasons.length > 0
					? ` (${ classification.reasons.join( ', ' ) })`
					: ''
			}\n`
		);
	} catch ( error ) {
		process.stderr.write(
			`CI classification failed closed: ${
				error instanceof Error ? error.message : String( error )
			}\n`
		);
		process.stdout.write(
			'docs_changed=true\nruntime_changed=true\ndatabase_sensitive=true\nfull_e2e=true\nmode=full\nclassifier_ok=false\n'
		);
	}
}
