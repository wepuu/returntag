<?php
/**
 * Canonical public ReturnTag identifier.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\Tag;

use InvalidArgumentException;

/**
 * Represents one canonical six-character public Tag ID.
 */
final readonly class TagId {
	/**
	 * Canonical unambiguous Tag ID alphabet.
	 */
	public const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

	/**
	 * Canonical Tag ID length.
	 */
	public const LENGTH = 6;

	/**
	 * Create one canonical Tag ID.
	 *
	 * @param string $value Uppercase canonical Tag ID.
	 * @throws InvalidArgumentException When the value is not canonical.
	 */
	private function __construct( public string $value ) {
		if (
			self::LENGTH !== strlen( $this->value )
			|| self::LENGTH !== strspn( $this->value, self::ALPHABET )
		) {
			throw new InvalidArgumentException( 'Tag ID must use the canonical six-character ReturnTag format.' );
		}
	}

	/**
	 * Create a Tag ID from an already canonical value.
	 *
	 * Input normalization belongs to the public-input boundary in RT-302.
	 *
	 * @param string $value Uppercase canonical Tag ID.
	 */
	public static function from_canonical( string $value ): self {
		return new self( $value );
	}

	/**
	 * Return the canonical public value.
	 */
	public function __toString(): string {
		return $this->value;
	}
}
