<?php
/**
 * Defensive sanitization for operational logs.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure;

use ReturnTag\TagCore\Application\LogContextSanitizer;
use Stringable;
use Throwable;

/**
 * Removes sensitive values and bounds untrusted log context.
 */
final class SensitiveLogContextSanitizer implements LogContextSanitizer {
	private const REDACTED = '[redacted]';

	private const REDACTED_EMAIL = '[redacted-email]';

	private const INVALID_STRING = '[invalid-string]';

	private const UNPRINTABLE_VALUE = '[unprintable-value]';

	private const MAX_DEPTH = 5;

	private const MAX_ITEMS = 50;

	private const MAX_STRING_LENGTH = 1024;

	private const TRUNCATED_SUFFIX = '[truncated]';

	/**
	 * Normalized key fragments whose values must never be logged.
	 *
	 * @var list<string>
	 */
	private const SENSITIVE_KEY_FRAGMENTS = array(
		'authorization',
		'cookie',
		'credential',
		'email',
		'encryptionkey',
		'apikey',
		'privatekey',
		'message',
		'body',
		'content',
		'payload',
		'otp',
		'password',
		'passphrase',
		'secret',
		'token',
	);

	/**
	 * Sanitize an operational log message.
	 *
	 * @param string|Stringable $message Log message.
	 * @return string Sanitized message.
	 */
	public function sanitize_message( string|Stringable $message ): string {
		if ( $message instanceof Stringable ) {
			try {
				$message = (string) $message;
			} catch ( Throwable ) {
				return self::UNPRINTABLE_VALUE;
			}
		}

		return $this->sanitize_string( $message );
	}

	/**
	 * Sanitize structured operational log context.
	 *
	 * @param array<array-key, mixed> $context Log context.
	 * @return array<array-key, mixed> Sanitized context.
	 */
	public function sanitize_context( array $context ): array {
		return $this->sanitize_array( $context, 0 );
	}

	/**
	 * Recursively sanitize an array while preserving safe keys.
	 *
	 * @param array<array-key, mixed> $values Values to sanitize.
	 * @param int                     $depth  Current recursion depth.
	 * @return array<array-key, mixed> Sanitized values.
	 */
	private function sanitize_array( array $values, int $depth ): array {
		$sanitized  = array();
		$item_count = 0;

		foreach ( $values as $key => $value ) {
			if ( $item_count >= self::MAX_ITEMS ) {
				$sanitized['__truncated__'] = true;
				break;
			}

			$safe_key = $this->sanitize_key( $key, $item_count );

			if ( is_string( $key ) && $this->is_sensitive_key( $key ) ) {
				$sanitized[ $safe_key ] = $this->redacted_value_for_key( $key );
			} else {
				$sanitized[ $safe_key ] = $this->sanitize_value( $value, $depth + 1 );
			}

			++$item_count;
		}

		return $sanitized;
	}

	/**
	 * Sanitize a single context value.
	 *
	 * @param mixed $value Value to sanitize.
	 * @param int   $depth Current recursion depth.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_value( mixed $value, int $depth ): mixed {
		if ( $value instanceof Throwable ) {
			return array(
				'exception_class' => $value::class,
				'exception_code'  => $value->getCode(),
			);
		}

		if ( is_array( $value ) ) {
			if ( $depth > self::MAX_DEPTH ) {
				return '[max-depth]';
			}

			return $this->sanitize_array( $value, $depth );
		}

		if ( is_string( $value ) ) {
			return $this->sanitize_string( $value );
		}

		if ( $value instanceof Stringable ) {
			try {
				return $this->sanitize_string( (string) $value );
			} catch ( Throwable ) {
				return self::UNPRINTABLE_VALUE;
			}
		}

		if ( is_object( $value ) ) {
			return '[object]';
		}

		if ( is_resource( $value ) ) {
			return '[resource]';
		}

		return $value;
	}

	/**
	 * Sanitize an array key without exposing data embedded in the key.
	 *
	 * @param int|string $key        Original array key.
	 * @param int        $item_count Zero-based item position.
	 * @return array-key Safe array key.
	 */
	private function sanitize_key( int|string $key, int $item_count ): int|string {
		if ( ! is_string( $key ) ) {
			return $key;
		}

		$sanitized_key = $this->sanitize_string( $key );

		if ( $sanitized_key !== $key ) {
			return 'redacted_key_' . $item_count;
		}

		return $key;
	}

	/**
	 * Determine whether a context key identifies sensitive data.
	 *
	 * @param string $key Context key.
	 * @return bool Whether the value must be redacted.
	 */
	private function is_sensitive_key( string $key ): bool {
		$normalized_key = preg_replace( '/[^a-z0-9]/', '', strtolower( $key ) );

		if ( ! is_string( $normalized_key ) ) {
			return true;
		}

		foreach ( self::SENSITIVE_KEY_FRAGMENTS as $fragment ) {
			if ( str_contains( $normalized_key, $fragment ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Select the correct redaction marker for a sensitive key.
	 *
	 * @param string $key Context key.
	 * @return string Redaction marker.
	 */
	private function redacted_value_for_key( string $key ): string {
		return str_contains( strtolower( $key ), 'email' ) ? self::REDACTED_EMAIL : self::REDACTED;
	}

	/**
	 * Remove secrets and bound a printable string.
	 *
	 * @param string $value Original string.
	 * @return string Sanitized string.
	 */
	private function sanitize_string( string $value ): string {
		if ( 1 !== preg_match( '//u', $value ) ) {
			return self::INVALID_STRING;
		}

		$sanitized = preg_replace(
			array(
				'/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,63}/i',
				'/\bBearer\s+[^\s,;]+/i',
				'/\b(otp|token|password|passphrase|secret|authorization|cookie|api[_-]?key|private[_-]?key|encryption[_-]?key)["\']?\s*[:=]\s*["\']?[^\s,;"\']+["\']?/i',
			),
			array(
				self::REDACTED_EMAIL,
				'Bearer ' . self::REDACTED,
				'$1=' . self::REDACTED,
			),
			$value
		);

		if ( ! is_string( $sanitized ) ) {
			return self::INVALID_STRING;
		}

		if ( strlen( $sanitized ) <= self::MAX_STRING_LENGTH ) {
			return $sanitized;
		}

		$prefix_length = self::MAX_STRING_LENGTH - strlen( self::TRUNCATED_SUFFIX );
		$prefix        = substr( $sanitized, 0, $prefix_length );

		while ( '' !== $prefix && 1 !== preg_match( '//u', $prefix ) ) {
			$prefix = substr( $prefix, 0, -1 );
		}

		return $prefix . self::TRUNCATED_SUFFIX;
	}
}
