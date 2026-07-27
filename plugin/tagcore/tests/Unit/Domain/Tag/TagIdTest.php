<?php
/**
 * Canonical Tag ID tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Domain\Tag;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Guards the frozen public Tag ID format.
 */
final class TagIdTest extends TestCase {
	/**
	 * Canonical IDs retain their exact public value.
	 */
	public function test_accepts_only_the_canonical_public_form(): void {
		$tag_id = TagId::from_canonical( 'A7R2W9' );

		self::assertSame( '23456789ABCDEFGHJKLMNPQRSTUVWXYZ', TagId::ALPHABET );
		self::assertSame( 6, TagId::LENGTH );
		self::assertSame( 'A7R2W9', $tag_id->value );
		self::assertSame( 'A7R2W9', (string) $tag_id );
	}

	/**
	 * Non-canonical storage or input forms fail closed.
	 *
	 * @dataProvider invalid_tag_id_provider
	 *
	 * @param string $value Invalid candidate.
	 */
	public function test_rejects_non_canonical_values( string $value ): void {
		$this->expectException( InvalidArgumentException::class );

		TagId::from_canonical( $value );
	}

	/**
	 * Return invalid Tag ID examples.
	 *
	 * @return array<string, array{string}>
	 */
	public function invalid_tag_id_provider(): array {
		return array(
			'empty'              => array( '' ),
			'too short'          => array( 'A7R2W' ),
			'too long'           => array( 'A7R2W99' ),
			'lowercase'          => array( 'a7r2w9' ),
			'space'              => array( 'A7 R2W9' ),
			'hyphen'             => array( 'A7-R2W9' ),
			'zero excluded'      => array( 'A7R2W0' ),
			'one excluded'       => array( 'A7R2W1' ),
			'capital i excluded' => array( 'A7R2WI' ),
			'capital o excluded' => array( 'A7R2WO' ),
			'non-ascii'          => array( 'A7R2Wé' ),
		);
	}
}
