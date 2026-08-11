<?php
/**
 * Validated Owner Tag metadata input.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use InvalidArgumentException;

/** Holds bounded Owner-only and Finder-visible labels. */
final readonly class OwnerTagMetadata {
	/**
	 * Owner-only item name.
	 *
	 * @var string|null
	 */
	public ?string $item_name;

	/**
	 * Finder-visible public label.
	 *
	 * @var string|null
	 */
	public ?string $public_label;

	/**
	 * Validate complete metadata input.
	 *
	 * @param string $item_name Submitted private name.
	 * @param string $public_label Submitted Finder-visible label.
	 */
	public function __construct( string $item_name, string $public_label ) {
		$this->item_name    = self::optional_plain_text( $item_name );
		$this->public_label = self::optional_plain_text( $public_label );
	}

	/**
	 * Return one optional, bounded, plain-text value.
	 *
	 * @param string $value Submitted value.
	 * @throws InvalidArgumentException When the value violates the contract.
	 */
	private static function optional_plain_text( string $value ): ?string {
		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		if (
			mb_strlen( $value, 'UTF-8' ) > 191
			|| 1 !== preg_match( '//u', $value )
			|| 1 === preg_match( '/[\x00-\x1F\x7F]/u', $value )
			|| 1 === preg_match( '/<\s*\/?\s*[a-z][^>]*>/iu', $value )
		) {
			throw new InvalidArgumentException( 'Tag metadata is invalid.' );
		}

		return $value;
	}
}
