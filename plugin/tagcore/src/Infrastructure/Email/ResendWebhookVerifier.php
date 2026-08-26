<?php
/**
 * Resend Svix signature verifier.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\Clock;

/** Verifies raw-body signatures and bounded replay time without logging secrets. */
final readonly class ResendWebhookVerifier {
	private const TOLERANCE_SECONDS = 300;

	/**
	 * Create a verifier.
	 *
	 * @param string $secret Externally injected Svix signing secret.
	 * @param Clock  $clock UTC clock.
	 */
	public function __construct( private string $secret, private Clock $clock ) {}

	/**
	 * Return true only for an authentic, recent Svix envelope.
	 *
	 * @param string $event_id Svix event identifier.
	 * @param string $timestamp Svix Unix timestamp.
	 * @param string $signature Svix signature list.
	 * @param string $raw_body Exact raw request body.
	 */
	public function verify( string $event_id, string $timestamp, string $signature, string $raw_body ): bool {
		if (
			1 !== preg_match( '/^[A-Za-z0-9_-]{1,191}$/D', $event_id )
			|| ! ctype_digit( $timestamp )
			|| '' === $signature
			|| strlen( $raw_body ) > 1024 * 1024
		) {
			return false;
		}
		$unix = (int) $timestamp;
		if ( abs( $this->clock->now()->getTimestamp() - $unix ) > self::TOLERANCE_SECONDS ) {
			return false;
		}
		$key = base64_decode( substr( $this->secret, 6 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Svix secret encoding.
		if ( false === $key || '' === $key ) {
			return false;
		}
		$expected   = base64_encode( hash_hmac( 'sha256', $event_id . '.' . $timestamp . '.' . $raw_body, $key, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Svix signature encoding.
		$candidates = preg_split( '/\s+/', trim( $signature ) );
		foreach ( false === $candidates ? array() : $candidates as $candidate ) {
			if ( str_starts_with( $candidate, 'v1,' ) && hash_equals( $expected, substr( $candidate, 3 ) ) ) {
				return true;
			}
		}
		return false;
	}
}
