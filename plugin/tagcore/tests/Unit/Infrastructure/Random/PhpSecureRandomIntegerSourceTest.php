<?php
/**
 * PHP secure random source tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Random;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Tag\RandomTagIdGenerator;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Infrastructure\Random\PhpSecureRandomIntegerSource;

/**
 * Exercises the production cryptographically secure random adapter.
 */
final class PhpSecureRandomIntegerSourceTest extends TestCase {
	/**
	 * The production composition always creates canonical candidate IDs.
	 */
	public function test_secure_source_generates_canonical_candidates(): void {
		$generator = new RandomTagIdGenerator( new PhpSecureRandomIntegerSource() );

		for ( $iteration = 0; $iteration < 256; $iteration++ ) {
			$candidate = $generator->generate();

			self::assertSame( TagId::LENGTH, strlen( $candidate->value ) );
			self::assertSame( TagId::LENGTH, strspn( $candidate->value, TagId::ALPHABET ) );
		}
	}
}
