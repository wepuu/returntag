import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { classifyPaths } from '../../scripts/classify-ci-changes.mjs';

describe( 'classifyPaths', () => {
	it( 'routes documentation-only changes to documentation checks', () => {
		assert.deepEqual( classifyPaths( [ 'docs/PROJECT_STATUS.md' ] ), {
			docs_changed: true,
			runtime_changed: false,
			database_sensitive: false,
			full_e2e: false,
			mode: 'docs',
			reasons: [],
		} );
	} );

	it( 'uses the union of documentation and runtime checks', () => {
		const result = classifyPaths( [
			'README.md',
			'plugin/tagcore/src/PublicSite/PublicTagRoute.php',
		] );

		assert.equal( result.docs_changed, true );
		assert.equal( result.runtime_changed, true );
		assert.equal( result.database_sensitive, false );
		assert.equal( result.full_e2e, false );
		assert.equal( result.mode, 'runtime' );
	} );

	it( 'adds database checks for persistence and integration changes', () => {
		for ( const path of [
			'plugin/tagcore/src/Infrastructure/Persistence/WpdbTagRepository.php',
			'plugin/tagcore/src/Infrastructure/Migration/Migration001.php',
			'plugin/tagcore/src/Application/Tag/TagActivationRepository.php',
			'plugin/tagcore/tests/Integration/ActivationTest.php',
		] ) {
			const result = classifyPaths( [ path ] );

			assert.equal( result.runtime_changed, true, path );
			assert.equal( result.database_sensitive, true, path );
			assert.equal( result.full_e2e, false, path );
		}
	} );

	it( 'treats theme files as runtime changes', () => {
		const result = classifyPaths( [
			'theme/forge-tag/templates/index.html',
		] );

		assert.equal( result.runtime_changed, true );
		assert.equal( result.database_sensitive, false );
		assert.equal( result.full_e2e, false );
	} );

	it( 'runs the full matrix for workflow and E2E infrastructure', () => {
		for ( const path of [
			'.github/workflows/quality.yml',
			'tests/e2e/public-tag-route.spec.ts',
			'playwright.pr.config.ts',
			'package-lock.json',
		] ) {
			const result = classifyPaths( [ path ] );

			assert.equal( result.runtime_changed, true, path );
			assert.equal( result.database_sensitive, true, path );
			assert.equal( result.full_e2e, true, path );
			assert.equal( result.mode, 'full', path );
			assert.equal( result.docs_changed, true, path );
		}
	} );

	it( 'normalizes renamed or deleted Windows-style paths', () => {
		const result = classifyPaths( [
			'docs\\design\\old-name.md',
			'theme\\forge-tag\\style.css',
		] );

		assert.equal( result.docs_changed, true );
		assert.equal( result.runtime_changed, true );
		assert.equal( result.full_e2e, false );
	} );

	it( 'fails closed for unknown paths', () => {
		const result = classifyPaths( [ 'unexpected/configuration.toml' ] );

		assert.equal( result.runtime_changed, true );
		assert.equal( result.database_sensitive, true );
		assert.equal( result.full_e2e, true );
		assert.deepEqual( result.reasons, [ 'unexpected/configuration.toml' ] );
	} );

	it( 'fails closed for an empty diff', () => {
		const result = classifyPaths( [] );

		assert.equal( result.runtime_changed, true );
		assert.equal( result.database_sensitive, true );
		assert.equal( result.full_e2e, true );
		assert.deepEqual( result.reasons, [ 'empty_diff' ] );
	} );
} );
