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
		$packed = inet_pton( $ip );

		if ( false === $packed ) {
			throw new InvalidArgumentException( 'Client address is invalid.' );
		}

		$key = wp_salt( 'auth' );

		if ( '' === $key ) {
			throw new RuntimeException( 'Public request hashing is unavailable.' );
		}

		return LookupDigest::from_digest(
			hash_hmac( 'sha256', "returntag:manual-entry:ip:v1\0" . $packed, $key )
		);
	}
}
