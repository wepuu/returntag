import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { evaluateQualityGate } from '../../scripts/check-quality-gate.mjs';

const successfulEnvironment = ( overrides = {} ) => ( {
	RESULT_CLASSIFIER: 'success',
	CLASSIFIER_OK: 'true',
	EXPECT_DOCS: 'false',
	EXPECT_RUNTIME: 'false',
	EXPECT_DATABASE: 'false',
	RESULT_DOCS: 'skipped',
	RESULT_PHP: 'skipped',
	RESULT_JAVASCRIPT: 'skipped',
	RESULT_INTEGRATION: 'skipped',
	RESULT_DATABASE: 'skipped',
	RESULT_E2E: 'skipped',
	...overrides,
} );

describe( 'evaluateQualityGate', () => {
	it( 'passes a successful documentation-only route', () => {
		const failures = evaluateQualityGate(
			successfulEnvironment( {
				EXPECT_DOCS: 'true',
				RESULT_DOCS: 'success',
			} )
		);

		assert.deepEqual( failures, [] );
	} );

	it( 'passes a successful runtime route with skipped database checks', () => {
		const failures = evaluateQualityGate(
			successfulEnvironment( {
				EXPECT_RUNTIME: 'true',
				RESULT_PHP: 'success',
				RESULT_JAVASCRIPT: 'success',
				RESULT_INTEGRATION: 'success',
				RESULT_E2E: 'success',
			} )
		);

		assert.deepEqual( failures, [] );
	} );

	it( 'passes a successful database-sensitive route', () => {
		const failures = evaluateQualityGate(
			successfulEnvironment( {
				EXPECT_RUNTIME: 'true',
				EXPECT_DATABASE: 'true',
				RESULT_PHP: 'success',
				RESULT_JAVASCRIPT: 'success',
				RESULT_INTEGRATION: 'success',
				RESULT_DATABASE: 'success',
				RESULT_E2E: 'success',
			} )
		);

		assert.deepEqual( failures, [] );
	} );

	it( 'fails when an expected job is missing', () => {
		const failures = evaluateQualityGate(
			successfulEnvironment( {
				EXPECT_RUNTIME: 'true',
				RESULT_PHP: 'success',
				RESULT_JAVASCRIPT: 'success',
				RESULT_INTEGRATION: 'missing',
				RESULT_E2E: 'success',
			} )
		);

		assert.ok(
			failures.includes( 'WordPress integration job was missing' )
		);
	} );

	it( 'fails closed when classification reports an error', () => {
		const failures = evaluateQualityGate(
			successfulEnvironment( { CLASSIFIER_OK: 'false' } )
		);

		assert.ok( failures.includes( 'change classification failed closed' ) );
	} );

	it( 'fails for an unexpected failed job', () => {
		const failures = evaluateQualityGate(
			successfulEnvironment( { RESULT_DATABASE: 'failure' } )
		);

		assert.ok(
			failures.includes(
				'unexpected database compatibility job result was failure'
			)
		);
	} );
} );
