import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { describe, it } from 'node:test';

const workflowPath = new URL(
	'../../.github/workflows/quality.yml',
	import.meta.url
);

describe( 'Quality workflow contracts', () => {
	it( 'verifies Commerce templates without session-dependent HTTP requests', async () => {
		const workflow = await readFile( workflowPath, 'utf8' );
		const commerceStep = workflow.match(
			/- name: Verify ForgeTag commerce template resolution[\s\S]*?(?=\n\s+- name:)/
		)?.[ 0 ];

		assert.ok(
			commerceStep,
			'Commerce template resolution step is required'
		);
		assert.match( commerceStep, /get_block_template\(/ );
		assert.match( commerceStep, /WP_Block_Template/ );
		assert.match( commerceStep, /archive-product/ );
		assert.match( commerceStep, /page-cart/ );
		assert.match( commerceStep, /page-checkout/ );
		assert.doesNotMatch( commerceStep, /wp_remote_get\(/ );
	} );
} );
