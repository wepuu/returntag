<?php
/**
 * Random Tag ID generator.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use ReturnTag\TagCore\Domain\Tag\TagId;
use UnexpectedValueException;

/**
 * Maps uniform random indexes to the canonical Tag ID alphabet.
 */
final readonly class RandomTagIdGenerator implements TagIdGenerator {
	/**
	 * Create the generator.
	 *
	 * @param RandomIntegerSource $random Random integer source.
	 */
	public function __construct( private RandomIntegerSource $random ) {
	}

	/**
	 * Generate one candidate without persistence or collision handling.
	 *
	 * @throws UnexpectedValueException When the random source violates its bounds.
	 */
	public function generate(): TagId {
		$value         = '';
		$maximum_index = strlen( TagId::ALPHABET ) - 1;

		for ( $position = 0; $position < TagId::LENGTH; $position++ ) {
			$index = $this->random->between( 0, $maximum_index );

			if ( $index < 0 || $index > $maximum_index ) {
				throw new UnexpectedValueException( 'Random integer source returned an out-of-range value.' );
			}

			$value .= TagId::ALPHABET[ $index ];
		}

		return TagId::from_canonical( $value );
	}
}
