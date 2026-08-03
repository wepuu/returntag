import assert from 'node:assert/strict';
import { cp, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { afterEach, describe, it } from 'node:test';

import { validateThemeArtifactDirectory } from '../../scripts/check-theme-artifact.mjs';

const repositoryRoot = join( import.meta.dirname, '../..' );
const temporaryRoots = [];

const withArtifactCopy = async ( mutate ) => {
	const temporaryRoot = await mkdtemp( join( tmpdir(), 'returntag-theme-' ) );
	temporaryRoots.push( temporaryRoot );
	const themeRoot = join( temporaryRoot, 'forge-tag' );
	await cp( join( repositoryRoot, 'theme/forge-tag' ), themeRoot, {
		recursive: true,
	} );
	await rm( join( themeRoot, 'README.md' ) );
	await mutate?.( themeRoot );
	return { temporaryRoot, themeRoot };
};

afterEach( async () => {
	await Promise.all(
		temporaryRoots
			.splice( 0 )
			.map( ( root ) => rm( root, { force: true, recursive: true } ) )
	);
} );

describe( 'ForgeTag Theme artifact contract', () => {
	it( 'accepts the exact runtime allowlist and matching tag', async () => {
		const { themeRoot } = await withArtifactCopy();
		const result = await validateThemeArtifactDirectory( {
			tag: 'forge-tag-v0.1.0',
			themeRoot,
		} );

		assert.deepEqual( result.failures, [] );
		assert.equal( result.version, '0.1.0' );
	} );

	it( 'rejects development and reference files', async () => {
		const { themeRoot } = await withArtifactCopy( async ( root ) => {
			await writeFile(
				join( root, 'reference-only.png' ),
				'not runtime'
			);
		} );
		const result = await validateThemeArtifactDirectory( { themeRoot } );

		assert.match( result.failures.join( '\n' ), /unapproved file/ );
	} );

	it( 'rejects a tag that differs from the Theme header', async () => {
		const { themeRoot } = await withArtifactCopy();
		const result = await validateThemeArtifactDirectory( {
			tag: 'forge-tag-v0.2.0',
			themeRoot,
		} );

		assert.match( result.failures.join( '\n' ), /does not match/ );
	} );
} );
