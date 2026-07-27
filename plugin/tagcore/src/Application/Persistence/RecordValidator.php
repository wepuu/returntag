<?php
/**
 * Persistence-record scalar guards.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence;

use DateTimeImmutable;
use InvalidArgumentException;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Validates storage-shape constraints without implementing business workflows.
 */
final class RecordValidator {
	/**
	 * Return one positive identifier.
	 *
	 * @param int    $value Candidate identifier.
	 * @param string $field Internal field label.
	 * @throws InvalidArgumentException When the value is not positive.
	 */
	public static function positive_id( int $value, string $field ): int {
		unset( $field );

		if ( $value < 1 ) {
			throw new InvalidArgumentException( 'Persistence value is invalid.' );
		}

		return $value;
	}

	/**
	 * Return one unsigned integer representable by PHP.
	 *
	 * @param int    $value Candidate integer.
	 * @param string $field Internal field label.
	 * @throws InvalidArgumentException When the value is negative.
	 */
	public static function unsigned_int( int $value, string $field ): int {
		unset( $field );

		if ( $value < 0 ) {
			throw new InvalidArgumentException( 'Persistence value is invalid.' );
		}

		return $value;
	}

	/**
	 * Validate a bounded ASCII storage code.
	 *
	 * @param string $value          Candidate value.
	 * @param int    $maximum_length Maximum bytes.
	 * @param string $field          Internal field label.
	 * @param bool   $allow_empty    Whether an empty string is accepted.
	 * @throws InvalidArgumentException When the value is outside the storage contract.
	 */
	public static function ascii( string $value, int $maximum_length, string $field, bool $allow_empty = false ): string {
		unset( $field );
		$length = strlen( $value );

		if ( ( ! $allow_empty && 0 === $length ) || $length > $maximum_length || 1 !== preg_match( '/^[\x20-\x7E]*$/D', $value ) ) {
			throw new InvalidArgumentException( 'Persistence value is invalid.' );
		}

		return $value;
	}

	/**
	 * Validate a nullable bounded ASCII storage code.
	 *
	 * @param string|null $value          Candidate value.
	 * @param int         $maximum_length Maximum bytes.
	 * @param string      $field          Internal field label.
	 * @throws InvalidArgumentException When the value is outside the storage contract.
	 */
	public static function nullable_ascii( ?string $value, int $maximum_length, string $field ): ?string {
		return null === $value ? null : self::ascii( $value, $maximum_length, $field );
	}

	/**
	 * Validate a bounded UTF-8 value by characters.
	 *
	 * @param string $value          Candidate value.
	 * @param int    $maximum_length Maximum characters.
	 * @param string $field          Internal field label.
	 * @param bool   $allow_empty    Whether an empty string is accepted.
	 * @throws InvalidArgumentException When the value is outside the storage contract.
	 */
	public static function text( string $value, int $maximum_length, string $field, bool $allow_empty = false ): string {
		unset( $field );
		$length = mb_strlen( $value, 'UTF-8' );

		if ( ( ! $allow_empty && 0 === $length ) || $length > $maximum_length || 1 !== preg_match( '//u', $value ) ) {
			throw new InvalidArgumentException( 'Persistence value is invalid.' );
		}

		return $value;
	}

	/**
	 * Validate a nullable bounded UTF-8 value by characters.
	 *
	 * @param string|null $value          Candidate value.
	 * @param int         $maximum_length Maximum characters.
	 * @param string      $field          Internal field label.
	 * @throws InvalidArgumentException When the value is outside the storage contract.
	 */
	public static function nullable_text( ?string $value, int $maximum_length, string $field ): ?string {
		return null === $value ? null : self::text( $value, $maximum_length, $field, true );
	}

	/**
	 * Validate a nullable UTF-8 TEXT value by encoded bytes.
	 *
	 * @param string|null $value         Candidate value.
	 * @param int         $maximum_bytes Maximum encoded bytes.
	 * @param string      $field         Internal field label.
	 * @throws InvalidArgumentException When the value is outside the storage contract.
	 */
	public static function nullable_text_bytes( ?string $value, int $maximum_bytes, string $field ): ?string {
		unset( $field );

		if ( null === $value ) {
			return null;
		}

		if ( strlen( $value ) > $maximum_bytes || 1 !== preg_match( '//u', $value ) ) {
			throw new InvalidArgumentException( 'Persistence value is invalid.' );
		}

		return $value;
	}

	/**
	 * Validate the canonical public six-character Tag ID.
	 *
	 * @param string $value Candidate Tag ID.
	 * @throws InvalidArgumentException When the value is not canonical.
	 */
	public static function tag_id( string $value ): string {
		return TagId::from_canonical( $value )->value;
	}

	/**
	 * Reject identifiers that visibly contain private identity or credential data.
	 *
	 * Event-specific policies must still approve the identifier's exact type and
	 * format. This guard supplies a non-bypassable minimum privacy boundary.
	 *
	 * @param string $value Candidate Event target or correlation identifier.
	 * @param int    $maximum_length Maximum bytes.
	 * @throws InvalidArgumentException When the identifier resembles sensitive data.
	 */
	public static function privacy_safe_event_identifier( string $value, int $maximum_length ): string {
		self::ascii( $value, $maximum_length, 'event_identifier' );

		if (
			false !== filter_var( $value, FILTER_VALIDATE_EMAIL )
			|| false !== filter_var( $value, FILTER_VALIDATE_IP )
			|| 1 === preg_match( '/^[0-9a-f]{64}$/iD', $value )
			|| 1 === preg_match( '/^[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}$/D', $value )
			|| 1 === preg_match( '/(?:authorization|bearer|cookie|email|otp|password|secret|session|token|device|location|latitude|longitude)/i', $value )
		) {
			throw new InvalidArgumentException( 'Event identifier is not privacy-safe.' );
		}

		return $value;
	}

	/**
	 * Validate a canonical lowercase 64-character hexadecimal digest.
	 *
	 * @param string $value Candidate digest.
	 * @param string $field Internal field label.
	 * @throws InvalidArgumentException When the value is not canonical.
	 */
	public static function digest( string $value, string $field ): string {
		unset( $field );

		if ( 1 !== preg_match( '/^[0-9a-f]{64}$/D', $value ) ) {
			throw new InvalidArgumentException( 'Persistence value is invalid.' );
		}

		return $value;
	}

	/**
	 * Validate non-empty bounded opaque binary data.
	 *
	 * @param string $value         Opaque bytes.
	 * @param int    $maximum_bytes Maximum bytes.
	 * @param string $field         Internal field label.
	 * @throws InvalidArgumentException When the value is empty or too large.
	 */
	public static function opaque_bytes( string $value, int $maximum_bytes, string $field ): string {
		unset( $field );
		$length = strlen( $value );

		if ( 0 === $length || $length > $maximum_bytes ) {
			throw new InvalidArgumentException( 'Persistence value is invalid.' );
		}

		return $value;
	}

	/**
	 * Require a UTC timestamp.
	 *
	 * @param DateTimeImmutable $value Candidate timestamp.
	 * @param string            $field Internal field label.
	 * @throws InvalidArgumentException When the timestamp is not UTC.
	 */
	public static function utc( DateTimeImmutable $value, string $field ): DateTimeImmutable {
		unset( $field );

		if ( 0 !== $value->getOffset() ) {
			throw new InvalidArgumentException( 'Persistence timestamp must use UTC.' );
		}

		return $value;
	}

	/**
	 * Require a nullable UTC timestamp.
	 *
	 * @param DateTimeImmutable|null $value Candidate timestamp.
	 * @param string                 $field Internal field label.
	 * @throws InvalidArgumentException When the timestamp is not UTC.
	 */
	public static function nullable_utc( ?DateTimeImmutable $value, string $field ): ?DateTimeImmutable {
		return null === $value ? null : self::utc( $value, $field );
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
