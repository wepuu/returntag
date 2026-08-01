import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const expectedResult = ( value ) => value === 'true';

export const evaluateQualityGate = ( environment ) => {
	const failures = [];
	const classifierResult = environment.RESULT_CLASSIFIER;

	if ( classifierResult !== 'success' ) {
		failures.push(
			`classifier job was ${ classifierResult || 'missing' }`
		);
	}

	if ( environment.CLASSIFIER_OK !== 'true' ) {
		failures.push( 'change classification failed closed' );
	}

	const jobs = [
		[ 'documentation', 'EXPECT_DOCS', 'RESULT_DOCS' ],
		[ 'PHP', 'EXPECT_RUNTIME', 'RESULT_PHP' ],
		[ 'JavaScript', 'EXPECT_RUNTIME', 'RESULT_JAVASCRIPT' ],
		[ 'WordPress integration', 'EXPECT_RUNTIME', 'RESULT_INTEGRATION' ],
		[ 'database compatibility', 'EXPECT_DATABASE', 'RESULT_DATABASE' ],
		[ 'end-to-end', 'EXPECT_RUNTIME', 'RESULT_E2E' ],
	];

	for ( const [ label, expectedKey, resultKey ] of jobs ) {
		const expected = expectedResult( environment[ expectedKey ] );
		const result = environment[ resultKey ] || 'missing';

		if ( expected && result !== 'success' ) {
			failures.push( `${ label } job was ${ result }` );
		}

		if ( ! expected && ! [ 'skipped', 'success' ].includes( result ) ) {
			failures.push( `unexpected ${ label } job result was ${ result }` );
		}
	}

	return failures;
};

if (
	process.argv[ 1 ] &&
	fileURLToPath( import.meta.url ) === resolve( process.argv[ 1 ] )
) {
	const failures = evaluateQualityGate( process.env );

	if ( failures.length > 0 ) {
		for ( const failure of failures ) {
			process.stderr.write( `Quality Gate: ${ failure }.\n` );
		}

		process.exitCode = 1;
	} else {
		process.stdout.write( 'Quality Gate passed for every required job.\n' );
	}
}
