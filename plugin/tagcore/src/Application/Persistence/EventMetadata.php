<?php
/**
 * Privacy-safe event metadata value.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence;

use InvalidArgumentException;
use JsonException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;

/**
 * Canonical, bounded, flat JSON metadata.
 */
final readonly class EventMetadata {
	private const MAXIMUM_BYTES = 4096;

	/**
	 * Create canonical metadata.
	 *
	 * @param string|null                         $json   Canonical JSON or null.
	 * @param array<string, int|string|bool|null> $values Validated values.
	 */
	private function __construct(
		private ?string $json,
		private array $values
	) {
	}

	/**
	 * Return empty metadata.
	 */
	public static function none(): self {
		return new self( null, array() );
	}

	/**
	 * Validate and encode approved structured metadata.
	 *
	 * @param string               $event_type Canonical event type.
	 * @param array<string, mixed> $values     Metadata values.
	 * @param EventMetadataPolicy  $policy     Event-specific allowlist.
	 * @throws InvalidArgumentException When metadata is not approved or encodable.
	 */
	public static function from_values( string $event_type, array $values, EventMetadataPolicy $policy ): self {
		if ( array() === $values ) {
			return self::none();
		}

		$values = self::validate_values( $event_type, $values, $policy );
		ksort( $values );

		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Application code must remain independent from WordPress APIs.
			$json = json_encode( $values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		} catch ( JsonException ) {
			throw new InvalidArgumentException( 'Event metadata cannot be encoded.' );
		}

		if ( strlen( $json ) > self::MAXIMUM_BYTES ) {
			throw new InvalidArgumentException( 'Event metadata exceeds the storage limit.' );
		}

		return new self( $json, $values );
	}

	/**
	 * Parse and validate one stored JSON value.
	 *
	 * @param string              $event_type Canonical event type.
	 * @param string|null         $json       Stored JSON.
	 * @param EventMetadataPolicy $policy     Event-specific allowlist.
	 * @throws PersistenceMappingException When stored metadata is invalid.
	 */
	public static function from_stored_json( string $event_type, ?string $json, EventMetadataPolicy $policy ): self {
		if ( null === $json || '' === $json ) {
			return self::none();
		}

		if ( strlen( $json ) > self::MAXIMUM_BYTES ) {
			throw new PersistenceMappingException( 'Stored event metadata is invalid.' );
		}

		try {
			$decoded = json_decode( $json, true, 2, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new PersistenceMappingException( 'Stored event metadata is invalid.' );
		}

		if ( ! is_array( $decoded ) || array_is_list( $decoded ) ) {
			throw new PersistenceMappingException( 'Stored event metadata is invalid.' );
		}

		$values = array();

		foreach ( $decoded as $key => $value ) {
			if ( ! is_string( $key ) || ( ! is_int( $value ) && ! is_string( $value ) && ! is_bool( $value ) && null !== $value ) ) {
				throw new PersistenceMappingException( 'Stored event metadata is invalid.' );
			}

			$values[ $key ] = $value;
		}

		try {
			$values = self::validate_values( $event_type, $values, $policy );
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored event metadata is invalid.' );
		}

		ksort( $values );

		return new self( $json, $values );
	}

	/**
	 * Return canonical JSON for storage.
	 */
	public function json(): ?string {
		return $this->json;
	}

	/**
	 * Return validated values.
	 *
	 * @return array<string, int|string|bool|null>
	 */
	public function values(): array {
		return $this->values;
	}

	/**
	 * Validate flat scalar metadata and its event-specific allowlist.
	 *
	 * @param string               $event_type Canonical event type.
	 * @param array<string, mixed> $values     Metadata values.
	 * @param EventMetadataPolicy  $policy     Event-specific allowlist.
	 * @return array<string, int|string|bool|null>
	 * @throws InvalidArgumentException When metadata is not approved.
	 */
	private static function validate_values( string $event_type, array $values, EventMetadataPolicy $policy ): array {
		RecordValidator::ascii( $event_type, 64, 'event_type' );
		$allowed   = array_fill_keys( $policy->allowed_keys( $event_type ), true );
		$validated = array();

		foreach ( $values as $key => $value ) {
			if (
				( ! is_int( $value ) && ! is_string( $value ) && ! is_bool( $value ) && null !== $value )
				||
				1 !== preg_match( '/^[a-z][a-z0-9_]{0,63}$/D', $key )
				|| ! isset( $allowed[ $key ] )
				|| self::is_sensitive_key( $key )
				|| ( is_string( $value ) && self::looks_like_email( $value ) )
			) {
				throw new InvalidArgumentException( 'Event metadata contains an unapproved value.' );
			}

			if ( is_string( $value ) ) {
				RecordValidator::text( $value, 512, 'event_metadata_value', true );
			}

			$validated[ $key ] = $value;
		}

		return $validated;
	}

	/**
	 * Reject keys that can carry secrets or prohibited personal data.
	 *
	 * @param string $key Metadata key.
	 */
	private static function is_sensitive_key( string $key ): bool {
		return 1 === preg_match(
			'/(?:email|otp|token|secret|password|cipher|message|body|location|latitude|longitude|device|account)/',
			$key
		);
	}

	/**
	 * Reject full email-shaped values.
	 *
	 * @param string $value Metadata value.
	 */
	private static function looks_like_email( string $value ): bool {
		return false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
	}
}
