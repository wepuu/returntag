<?php
/**
 * Deterministic random integer source fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Tag\Fixture;

use ReturnTag\TagCore\Application\Tag\RandomIntegerSource;
use UnexpectedValueException;

/**
 * Returns a predefined sequence for generator tests.
 */
final class SequenceRandomIntegerSource implements RandomIntegerSource {
	/**
	 * Requested inclusive bounds.
	 *
	 * @var list<array{int, int}>
	 */
	public array $bounds = array();

	/**
	 * Create the source.
	 *
	 * @param array $values Ordered integer values.
	 * @phpstan-param list<int> $values
	 */
	public function __construct( private array $values ) {
	}

	/**
	 * Return the next deterministic integer.
	 *
	 * @param int $minimum Inclusive minimum.
	 * @param int $maximum Inclusive maximum.
	 * @throws UnexpectedValueException When the test sequence is exhausted.
	 */
	public function between( int $minimum, int $maximum ): int {
		$this->bounds[] = array( $minimum, $maximum );
		$value          = array_shift( $this->values );

		if ( null === $value ) {
			throw new UnexpectedValueException( 'Test random sequence is exhausted.' );
		}

		return $value;
	}
}
