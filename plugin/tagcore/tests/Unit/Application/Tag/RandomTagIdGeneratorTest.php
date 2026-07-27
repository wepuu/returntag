<?php
/**
 * Random Tag ID generator tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Tag;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Tag\RandomTagIdGenerator;
use ReturnTag\TagCore\Tests\Unit\Application\Tag\Fixture\SequenceRandomIntegerSource;
use UnexpectedValueException;

/**
 * Verifies alphabet mapping and random-source boundaries.
 */
final class RandomTagIdGeneratorTest extends TestCase {
	/**
	 * Six uniform indexes map directly to one canonical candidate.
	 */
	public function test_generates_one_candidate_from_six_random_indexes(): void {
		$random    = new SequenceRandomIntegerSource( array( 8, 5, 23, 0, 28, 7 ) );
		$generator = new RandomTagIdGenerator( $random );

		self::assertSame( 'A7R2W9', $generator->generate()->value );
		self::assertSame(
			array_fill( 0, 6, array( 0, 31 ) ),
			$random->bounds
		);
	}

	/**
	 * A broken random adapter cannot inject an invalid alphabet index.
	 */
	public function test_rejects_out_of_range_random_source_output(): void {
		$generator = new RandomTagIdGenerator( new SequenceRandomIntegerSource( array( 32 ) ) );

		$this->expectException( UnexpectedValueException::class );

		$generator->generate();
	}
}
