<?php
/**
 * Strict stored-row scalar mapper.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use BackedEnum;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;

/**
 * Rejects missing, malformed, overflowing, or unknown stored values.
 */
final class StoredRow {
	/**
	 * Return one required string.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @param string               $key Column key.
	 * @throws PersistenceMappingException When the value is missing or malformed.
	 */
	public static function string( array $row, string $key ): string {
		$value = $row[ $key ] ?? null;

		if ( ! is_string( $value ) ) {
			throw new PersistenceMappingException( 'Stored record has an invalid value.' );
		}

		return $value;
	}

	/**
	 * Return one nullable string.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @param string               $key Column key.
	 * @throws PersistenceMappingException When the value is malformed.
	 */
	public static function nullable_string( array $row, string $key ): ?string {
		if ( ! array_key_exists( $key, $row ) || null === $row[ $key ] ) {
			return null;
		}

		if ( ! is_string( $row[ $key ] ) ) {
			throw new PersistenceMappingException( 'Stored record has an invalid value.' );
		}

		return $row[ $key ];
	}

	/**
	 * Return one positive PHP integer.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @param string               $key Column key.
	 * @throws PersistenceMappingException When the value is missing, invalid, or overflowing.
	 */
	public static function positive_int( array $row, string $key ): int {
		$value = self::integer_string( $row, $key );

		if ( '0' === $value || strlen( $value ) > strlen( (string) PHP_INT_MAX ) || ( strlen( $value ) === strlen( (string) PHP_INT_MAX ) && strcmp( $value, (string) PHP_INT_MAX ) > 0 ) ) {
			throw new PersistenceMappingException( 'Stored record has an invalid value.' );
		}

		return (int) $value;
	}

	/**
	 * Return one nullable positive PHP integer.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @param string               $key Column key.
	 * @throws PersistenceMappingException When the value is malformed.
	 */
	public static function nullable_positive_int( array $row, string $key ): ?int {
		if ( ! array_key_exists( $key, $row ) || null === $row[ $key ] ) {
			return null;
		}

		return self::positive_int( $row, $key );
	}

	/**
	 * Return one unsigned PHP integer.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @param string               $key Column key.
	 * @throws PersistenceMappingException When the value is missing, invalid, or overflowing.
	 */
	public static function unsigned_int( array $row, string $key ): int {
		$value = self::integer_string( $row, $key );

		if ( strlen( $value ) > strlen( (string) PHP_INT_MAX ) || ( strlen( $value ) === strlen( (string) PHP_INT_MAX ) && strcmp( $value, (string) PHP_INT_MAX ) > 0 ) ) {
			throw new PersistenceMappingException( 'Stored record has an invalid value.' );
		}

		return (int) $value;
	}

	/**
	 * Return a strict database boolean.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @param string               $key Column key.
	 * @throws PersistenceMappingException When the value is not a strict database boolean.
	 */
	public static function boolean( array $row, string $key ): bool {
		$value = $row[ $key ] ?? null;

		if ( '0' === $value || 0 === $value ) {
			return false;
		}

		if ( '1' === $value || 1 === $value ) {
			return true;
		}

		throw new PersistenceMappingException( 'Stored record has an invalid value.' );
	}

	/**
	 * Return one known backed-enum value.
	 *
	 * @template T of BackedEnum
	 * @param array<string, mixed> $row Stored row.
	 * @param string               $key Column key.
	 * @param string               $enum_class Enum class.
	 * @return T
	 * @phpstan-param class-string<T> $enum_class
	 * @throws PersistenceMappingException When the value is unknown.
	 */
	public static function enum( array $row, string $key, string $enum_class ): BackedEnum {
		$value = self::string( $row, $key );
		$case  = $enum_class::tryFrom( $value );

		if ( null === $case ) {
			throw new PersistenceMappingException( 'Stored record has an unknown canonical value.' );
		}

		return $case;
	}

	/**
	 * Normalize an integer-shaped database value.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @param string               $key Column key.
	 * @throws PersistenceMappingException When the value is not an unsigned integer string.
	 */
	private static function integer_string( array $row, string $key ): string {
		$value = $row[ $key ] ?? null;

		if ( is_int( $value ) ) {
			$value = (string) $value;
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '/^(?:0|[1-9][0-9]*)$/D', $value ) ) {
			throw new PersistenceMappingException( 'Stored record has an invalid value.' );
		}

		return $value;
	}

	/**
	 * Prevent construction.
	 */
	private function __construct() {
	}
}
