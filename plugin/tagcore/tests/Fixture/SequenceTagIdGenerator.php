<?php
/**
 * Deterministic Tag ID generator fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Fixture;

use LogicException;
use ReturnTag\TagCore\Application\Tag\TagIdGenerator;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Returns an ordered finite sequence of canonical Tag IDs.
 */
final class SequenceTagIdGenerator implements TagIdGenerator {
	/**
	 * Number of generated candidates.
	 *
	 * @var int
	 */
	public int $calls = 0;

	/**
	 * Create the sequence.
	 *
	 * @param array $values Canonical Tag IDs.
	 * @phpstan-param list<string> $values
	 */
	public function __construct( private readonly array $values ) {
	}

	/**
	 * Return the next candidate.
	 *
	 * @throws LogicException When the configured sequence is exhausted.
	 */
	public function generate(): TagId {
		if ( ! array_key_exists( $this->calls, $this->values ) ) {
			throw new LogicException( 'The Tag ID fixture sequence is exhausted.' );
		}

		$value = $this->values[ $this->calls ];
		++$this->calls;

		return TagId::from_canonical( $value );
	}
}
