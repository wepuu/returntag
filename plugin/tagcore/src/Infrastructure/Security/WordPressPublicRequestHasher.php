<?php
/**
 * Privacy-safe public request hasher.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Security;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use RuntimeException;

/**
 * Converts direct-peer IP values to domain-separated keyed lookup digests.
 */
final class WordPressPublicRequestHasher {
	/**
	 * Hash one validated direct-peer address.
	 *
	 * @param string $ip Valid IP address.
	 * @throws InvalidArgumentException When the IP address is invalid.
	 * @throws RuntimeException When WordPress hashing material is unavailable.
	 */
	public function ip_lookup( string $ip ): LookupDigest {
		return $this->peer_lookup( $ip, 'manual-entry' );
	}

	/**
	 * Hash a direct peer for the Finder Report abuse boundary.
	 *
	 * @param string $ip Valid IP address.
	 */
	public function finder_peer_lookup( string $ip ): LookupDigest {
		return $this->peer_lookup( $ip, 'finder-report' );
	}

	/**
	 * Hash one server-issued opaque risk token.
	 *
	 * @param string $token Server-issued digest input.
	 * @throws InvalidArgumentException When syntax is invalid.
	 * @throws RuntimeException When WordPress hashing material is unavailable.
	 */
	public function finder_risk_lookup( string $token ): LookupDigest {
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/D', $token ) ) {
			throw new InvalidArgumentException( 'Finder Report risk token is invalid.' );
		}

		$key = wp_salt( 'auth' );

		if ( '' === $key ) {
			throw new RuntimeException( 'Public request hashing is unavailable.' );
		}

		return LookupDigest::from_digest(
			hash_hmac( 'sha256', "returntag:finder-report:risk:v1\0" . $token, $key )
		);
	}

	/**
	 * Hash a validated direct peer under one domain.
	 *
	 * @param string $ip Valid IP address.
	 * @param string $domain Hash domain.
	 * @throws InvalidArgumentException When the address is invalid.
	 * @throws RuntimeException When WordPress hashing material is unavailable.
	 */
	private function peer_lookup( string $ip, string $domain ): LookupDigest {
		$packed = inet_pton( $ip );

		if ( false === $packed ) {
			throw new InvalidArgumentException( 'Client address is invalid.' );
		}

		$key = wp_salt( 'auth' );

		if ( '' === $key ) {
			throw new RuntimeException( 'Public request hashing is unavailable.' );
		}

		return LookupDigest::from_digest(
			hash_hmac( 'sha256', "returntag:{$domain}:ip:v1\0" . $packed, $key )
		);
	}
}
