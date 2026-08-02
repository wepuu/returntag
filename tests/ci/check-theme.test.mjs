import assert from 'node:assert/strict';
import { cp, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

import { validateTheme } from '../../scripts/check-theme.mjs';

const repositoryRoot = dirname(
	dirname( dirname( fileURLToPath( import.meta.url ) ) )
);
const sourceThemeRoot = join( repositoryRoot, 'theme/forge-tag' );

const withThemeCopy = async ( mutate ) => {
	const temporaryRoot = await mkdtemp( join( tmpdir(), 'returntag-theme-' ) );
	const themeRoot = join( temporaryRoot, 'forge-tag' );

	try {
		await cp( sourceThemeRoot, themeRoot, { recursive: true } );
		await mutate( themeRoot );
		return await validateTheme( { repositoryRoot, themeRoot } );
	} finally {
		await rm( temporaryRoot, { recursive: true, force: true } );
	}
};

const assertFailure = ( result, pattern ) => {
	assert.ok(
		result.failures.some( ( failure ) => pattern.test( failure ) ),
		`Expected ${ pattern }, received:\n${ result.failures.join( '\n' ) }`
	);
};

describe( 'ForgeTag Theme contract', () => {
	it( 'accepts the source-controlled Stage 3B baseline', async () => {
		const result = await validateTheme( { repositoryRoot } );

		assert.deepEqual( result.failures, [] );
	} );

	it( 'rejects missing third-party license evidence', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			await rm( join( themeRoot, 'assets/licenses/Inter-OFL-1.1.txt' ) );
		} );

		assertFailure( result, /Inter license is missing/ );
	} );

	it( 'rejects a changed Theme identity', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'style.css' );
			const stylesheet = await readFile( path, 'utf8' );
			await writeFile(
				path,
				stylesheet
					.replace( 'Version: 0.1.0', 'Version: 0.2.0' )
					.replace( 'Text Domain: forge-tag', 'Text Domain: wrong' )
			);
		} );

		assertFailure( result, /Theme identity contract is incorrect/ );
	} );

	it( 'rejects forbidden reference assets and source paths', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			await writeFile(
				join( themeRoot, 'homepage.png' ),
				'not an asset'
			);
			await writeFile(
				join( themeRoot, 'parts/footer.html' ),
				'<!-- docs/design/reference -->'
			);
		} );

		assertFailure( result, /homepage\.png is a forbidden Theme asset/ );
		assertFailure( result, /leaks a source-design path/ );
	} );

	it( 'rejects remote runtime assets and unsupported claims', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			await writeFile(
				join( themeRoot, 'assets/css/remote.css' ),
				'@import url("https://example.com/font.css");'
			);
			await writeFile(
				join( themeRoot, 'parts/footer.html' ),
				'<p>End-to-end encrypted and verified pairing.</p>'
			);
		} );

		assertFailure( result, /contains a remote runtime URL/ );
		assertFailure( result, /contains an unapproved product claim/ );
	} );

	it( 'rejects a modified font binary', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join(
				themeRoot,
				'assets/fonts/inter/Inter-Variable-Roman.woff2'
			);
			const font = await readFile( path );
			await writeFile(
				path,
				Buffer.concat( [ font, Buffer.from( 'x' ) ] )
			);
		} );

		assertFailure( result, /Inter font SHA-256/ );
	} );

	it( 'rejects a modified approved product image', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join(
				themeRoot,
				'assets/images/product-smart-tag.png'
			);
			const image = await readFile( path );
			await writeFile(
				path,
				Buffer.concat( [ image, Buffer.from( 'x' ) ] )
			);
		} );

		assertFailure( result, /smart_tag product image SHA-256/ );
	} );

	it( 'rejects icons outside the approved whitelist', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			await writeFile(
				join( themeRoot, 'assets/icons/unapproved.svg' ),
				'<svg></svg>'
			);
		} );

		assertFailure( result, /exact approved whitelist/ );
	} );

	it( 'rejects a missing approved palette token', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'theme.json' );
			const themeJson = JSON.parse( await readFile( path, 'utf8' ) );
			themeJson.settings.color.palette =
				themeJson.settings.color.palette.filter(
					( color ) => color.slug !== 'forge-red'
				);
			await writeFile( path, JSON.stringify( themeJson, null, 2 ) );
		} );

		assertFailure( result, /palette must expose only the approved slugs/ );
	} );

	it( 'rejects hard-coded entry paths and copied forms', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'patterns/home-hero.php' );
			const pattern = await readFile( path, 'utf8' );
			await writeFile(
				path,
				`${ pattern }\n<form action="/tag/activate/"><input name="tag_id"></form>`
			);
		} );

		assertFailure( result, /must not hard-code TagCore entry paths/ );
		assertFailure( result, /must not reproduce a Tag ID form/ );
	} );

	it( 'rejects missing entry intents and secondary treatment', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'patterns/home-hero.php' );
			const pattern = await readFile( path, 'utf8' );
			await writeFile(
				path,
				pattern.replace(
					'"intent":"report","className":"is-style-secondary"',
					'"intent":"activate"'
				)
			);
		} );

		assertFailure( result, /two Activate and two Report/ );
	} );

	it( 'rejects Theme selectors that cross into TagCore markup', async () => {
		const result = await withThemeCopy( async ( themeRoot ) => {
			const path = join( themeRoot, 'assets/css/home.css' );
			const stylesheet = await readFile( path, 'utf8' );
			await writeFile(
				path,
				`${ stylesheet }\n.returntag-entry-link__trigger { color: red; }`
			);
		} );

		assertFailure(
			result,
			/must not style TagCore through deep selectors/
		);
	} );
} );
