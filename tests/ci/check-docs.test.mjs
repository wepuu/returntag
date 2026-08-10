import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import {
	findMissingFinderEvidenceContract,
	findMissingOwnerDashboardContract,
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

describe( 'findMissingOwnerDashboardContract', () => {
	it( 'reports missing Owner Dashboard contract text', () => {
		const failures = findMissingOwnerDashboardContract( {
			'docs/PRD.md': 'Owner account placeholder.',
		} );

		assert.ok(
			failures.some( ( failure ) =>
				failure.includes( 'missing RT-317 contract' )
			)
		);
	} );

	it( 'accepts the frozen Owner Dashboard contract documents', () => {
		const contentsByPath = {
			'docs/PRD.md': [
				'RT-317 Stage 0 Owner Dashboard contract',
				'returntag_owner_account_enabled',
			].join( '\n' ),
			'docs/adr/0022-owner-dashboard-and-tag-management-contract.md': [
				'Owner Dashboard and tag-management contract',
				'**Schema before/after:** `12 -> 12`',
			].join( '\n' ),
			'docs/ARCHITECTURE.md': [
				'RT-317 Owner Dashboard contract',
				'returntag_owner_account_enabled',
			].join( '\n' ),
			'docs/DATABASE.md': [
				'RT-317 Owner Dashboard data contract',
				'RT-317 Stage 0 keeps Schema `12`',
			].join( '\n' ),
			'docs/SECURITY.md': [
				'RT-317 Owner Dashboard security contract',
				'WordPress login cannot',
			].join( '\n' ),
			'docs/RELEASE.md': [
				'RT-317 Stage 0 release and rollback',
				'RT-317 Stage 0 is documentation-only',
			].join( '\n' ),
			'README.md': [
				'RT-317 Owner Dashboard Stage 0',
				'returntag_owner_account_enabled',
			].join( '\n' ),
			'docs/PROJECT_STATUS.md': [
				'RT-317 Stage 0 Owner Dashboard contract freeze',
				'ForgeTag Theme remains `0.1.0`, and Schema remains `12`',
			].join( '\n' ),
			'plugin/tagcore/src/Account/README.md': [
				'ADR 0022 freezes',
				'Account login is not Secure',
			].join( '\n' ),
		};

		assert.deepEqual(
			findMissingOwnerDashboardContract( contentsByPath ),
			[]
		);
	} );
} );
