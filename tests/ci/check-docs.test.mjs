import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import {
	findMissingFinderEvidenceContract,
	findUnintendedStructuralEscapes,
} from '../../scripts/check-docs.mjs';

describe( 'findUnintendedStructuralEscapes', () => {
	it( 'reports escaped headings and list markers with line numbers', () => {
		const contents = [
			'\\# Escaped heading',
			'',
			'\\* Escaped bullet',
			'  \\- Escaped nested bullet',
			'1\\. Escaped ordered item',
		].join( '\n' );

		assert.deepEqual(
			findUnintendedStructuralEscapes( contents ),
			[ 1, 3, 4, 5 ]
		);
	} );

	it( 'accepts ordinary Markdown structure and inline escapes', () => {
		const contents = [
			'# Heading',
			'* Bullet',
			'1. Ordered item',
			'Use \\* when documenting a literal asterisk.',
			'C:\\Users\\admin\\project',
		].join( '\n' );

		assert.deepEqual( findUnintendedStructuralEscapes( contents ), [] );
	} );

	it( 'ignores examples inside fenced code blocks', () => {
		const contents = [
			'```text',
			'\\# Escaped heading example',
			'1\\. Escaped ordered example',
			'```',
		].join( '\n' );

		assert.deepEqual( findUnintendedStructuralEscapes( contents ), [] );
	} );
} );

describe( 'findMissingFinderEvidenceContract', () => {
	it( 'reports missing and superseded Finder contract text', () => {
		const failures = findMissingFinderEvidenceContract( {
			'AGENTS.md':
				'Finder email must be verified before the owner is notified.',
		} );

		assert.ok(
			failures.some( ( failure ) =>
				failure.includes( 'missing RT-315 contract' )
			)
		);
		assert.ok(
			failures.some( ( failure ) =>
				failure.includes( 'superseded Finder contract remains' )
			)
		);
	} );

	it( 'accepts the frozen Finder contract documents', () => {
		const contentsByPath = {
			'AGENTS.md': [
				'exactly one required Finder Report evidence image',
				'Finder email verification remains mandatory before two-way conversation',
			].join( '\n' ),
			'docs/PRD.md': [
				'| Message for the owner | 选填，填写时为 10–500 字符 |',
				'| Item photo | 必填，且只能上传一张物品凭证照片 |',
				'returntag_finder_evidence_enabled',
			].join( '\n' ),
			'docs/adr/0019-finder-evidence-report-without-verification-gate.md':
				[
					'The initial flow is deliberately one-way',
					'**Schema before/after:** `8 -> 8`',
				].join( '\n' ),
			'docs/ARCHITECTURE.md': [
				'Finder evidence-report contract',
				'Infrastructure Stage 2 provides purpose-bound',
			].join( '\n' ),
			'docs/DATABASE.md': [
				'RT-315 Finder Report and private-media persistence expansion',
				'Schema `8 -> 10`',
			].join( '\n' ),
			'docs/SECURITY.md': [
				'RT-315 Finder evidence-report security contract',
				'Content-safety review is mandatory',
				'RETURNTAG_TAGCORE_PRIVATE_MEDIA_OBJECT_KEY_V1',
			].join( '\n' ),
			'docs/RELEASE.md': [
				'RT-315 Stage 2 keeps project/plugin version',
				'`returntag_finder_evidence_enabled`',
			].join( '\n' ),
			'docs/PROJECT_STATUS.md': [
				'RT-315 Stage 2 private-media safety foundation',
				'Schema is `10`',
			].join( '\n' ),
		};

		assert.deepEqual(
			findMissingFinderEvidenceContract( contentsByPath ),
			[]
		);
	} );
} );
