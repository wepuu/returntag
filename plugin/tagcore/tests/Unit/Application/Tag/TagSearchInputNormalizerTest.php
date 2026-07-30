<?php
/**
 * RT-209 Tag search input tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Tag;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Tag\TagIdInputNormalizer;
use ReturnTag\TagCore\Application\Tag\TagSearchInputNormalizer;

/**
 * Verifies canonical administrative search normalization.
 */
final class TagSearchInputNormalizerTest extends TestCase {
	/**
	 * Spaces, hyphens, and case normalize before validation.
	 */
	public function test_normalizes_tag_id_spaces_hyphens_and_case(): void {
		self::assertSame(
			'2ABC34',
			( new TagSearchInputNormalizer( new TagIdInputNormalizer() ) )->tag_id( ' 2a-b c34 ' )->value
		);
	}

	/**
	 * Invalid identifiers remain rejected.
	 *
	 * @param string $value Invalid input.
	 * @dataProvider invalid_tag_ids
	 */
	public function test_rejects_noncanonical_tag_ids( string $value ): void {
		$this->expectException( InvalidArgumentException::class );

		( new TagSearchInputNormalizer( new TagIdInputNormalizer() ) )->tag_id( $value );
	}

	/**
	 * Invalid identifier provider.
	 *
	 * @return array<string, array{string}>
	 */
	public function invalid_tag_ids(): array {
		return array(
			'too short'        => array( 'ABC34' ),
			'ambiguous zero'   => array( 'ABC340' ),
			'ambiguous one'    => array( 'ABC341' ),
			'unsupported char' => array( 'ABC34!' ),
		);
	}

	/**
	 * Batch Code matching remains exact and case-sensitive.
	 */
	public function test_trims_but_preserves_batch_code_case(): void {
		self::assertSame(
			'Rt-209-Batch',
			( new TagSearchInputNormalizer( new TagIdInputNormalizer() ) )->batch_code( '  Rt-209-Batch  ' )
		);
	}
}
