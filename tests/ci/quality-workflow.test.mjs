import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { describe, it } from 'node:test';

const workflowPath = new URL(
	'../../.github/workflows/quality.yml',
	import.meta.url
);

describe( 'Quality workflow contracts', () => {
	it( 'activates mounted plugins before checking the TagCore entry contract', async () => {
		const workflow = await readFile( workflowPath, 'utf8' );
		const integration = workflow.indexOf(
			'Run WordPress integration tests'
		);
		const activation = workflow.indexOf(
			'wp plugin activate tagcore woocommerce'
		);
		const entryContract = workflow.indexOf(
			'Verify the ForgeTag and TagCore entry contract'
		);

		assert.notEqual( integration, -1, 'Integration test step is required' );
		assert.notEqual( activation, -1, 'Mounted plugins must be activated' );
		assert.notEqual(
			entryContract,
			-1,
			'TagCore entry contract is required'
		);
		assert.ok(
			integration < activation,
			'Plugins must activate after integration resets site state'
		);
		assert.ok( activation < entryContract, 'Plugins must activate first' );
	} );

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
