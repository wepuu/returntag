<?php
/**
 * RT-302 Tag ID input normalization tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Tag;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Tag\TagIdInputNormalizer;

/**
 * Verifies the shared human-input boundary for public Tag IDs.
 */
final class TagIdInputNormalizerTest extends TestCase {
	/**
	 * Approved formatting characters and case normalize before validation.
	 *
	 * @param string $input Untrusted input.
	 * @dataProvider normalizable_input_provider
	 */
	public function test_normalizes_approved_human_input( string $input ): void {
		self::assertSame(
			'A7R2W9',
			( new TagIdInputNormalizer() )->normalize( $input )->value
		);
	}

	/**
	 * Invalid input fails closed.
	 *
	 * @param string $input Invalid input.
	 * @dataProvider invalid_input_provider
	 */
	public function test_rejects_invalid_input( string $input ): void {
		$this->expectException( InvalidArgumentException::class );

		( new TagIdInputNormalizer() )->normalize( $input );
	}

	/**
	 * Return approved normalization examples.
	 *
	 * @return array<string, array{string}>
	 */
	public function normalizable_input_provider(): array {
		return array(
			'canonical'          => array( 'A7R2W9' ),
			'lowercase'          => array( 'a7r2w9' ),
			'outer spaces'       => array( ' A7R2W9 ' ),
			'internal spaces'    => array( 'A7 R2 W9' ),
			'hyphens'            => array( 'a7-r2-w9' ),
			'mixed whitespace'   => array( "\tA7-\nR2 W9\r" ),
			'unicode whitespace' => array( "A7\u{00A0}R2W9" ),
		);
	}

	/**
	 * Return rejected examples.
	 *
	 * @return array<string, array{string}>
	 */
	public function invalid_input_provider(): array {
		return array(
			'empty'               => array( '' ),
			'formatting only'     => array( ' - ' ),
			'too short'           => array( 'A7R2W' ),
			'too long canonical'  => array( 'A7R2W99' ),
			'zero excluded'       => array( 'A7R2W0' ),
			'one excluded'        => array( 'A7R2W1' ),
			'capital i excluded'  => array( 'A7R2WI' ),
			'capital o excluded'  => array( 'A7R2WO' ),
			'unsupported symbol'  => array( 'A7R2W!' ),
			'non-ascii letter'    => array( 'A7R2Wé' ),
			'over boundary limit' => array( str_repeat( '-', TagIdInputNormalizer::MAX_INPUT_BYTES ) . 'A7R2W9' ),
			'invalid utf-8'       => array( "A7R2W9\xFF" ),
		);
	}
}
