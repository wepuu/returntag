import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { findUnintendedStructuralEscapes } from '../../scripts/check-docs.mjs';

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
