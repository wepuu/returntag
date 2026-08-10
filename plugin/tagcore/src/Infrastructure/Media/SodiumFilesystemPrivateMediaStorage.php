<?php
/**
 * Encrypted filesystem private-media storage.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Media;

use ReturnTag\TagCore\Application\FinderReport\PrivateMediaObject;
use ReturnTag\TagCore\Application\FinderReport\PrivateMediaStorage;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDigest;
use ReturnTag\TagCore\Application\Persistence\Value\PrivateMediaReferenceCiphertext;
use ReturnTag\TagCore\Domain\FinderReport\PrivateMediaObjectKind;
use RuntimeException;

/**
 * Uses purpose-bound XChaCha20-Poly1305 envelopes outside public web roots.
 */
final readonly class SodiumFilesystemPrivateMediaStorage implements PrivateMediaStorage {
	private const OBJECT_PREFIX = "RTMO1\0";

	private const REFERENCE_PREFIX = 'RTMR1:v1:';

	/**
	 * Validated absolute non-public root.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Normalized public roots forbidden to private storage.
	 *
	 * @var list<string>
	 */
	private array $public_roots;

	/**
	 * Create the private storage adapter.
	 *
	 * @param string              $root Absolute non-public storage root.
	 * @param PrivateMediaSecrets $secrets Independent external keys.
	 * @param array               $public_roots Absolute roots that must not contain storage.
	 * @phpstan-param list<string> $public_roots
	 * @throws RuntimeException When configuration is unsafe or Sodium is unavailable.
	 */
	public function __construct(
		string $root,
		private PrivateMediaSecrets $secrets,
		array $public_roots
	) {
		if ( ! function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ) ) {
			throw new RuntimeException( 'Private-media encryption is unavailable.' );
		}

		foreach ( array( 'ABSPATH', 'WP_CONTENT_DIR' ) as $constant_name ) {
			$value = defined( $constant_name ) ? constant( $constant_name ) : null;

			if ( is_string( $value ) && '' !== $value ) {
				$public_roots[] = $value;
			}
		}

		$this->public_roots = array_map( fn( string $path ): string => $this->normalize_path( $path ), $public_roots );
		$this->root         = $this->validate_root( $root, $this->public_roots );
	}

	/**
	 * Encrypt and persist one private object.
	 *
	 * @param PrivateMediaObjectKind $kind Cryptographic object purpose.
	 * @param string                 $bytes Plaintext bytes.
	 * @throws RuntimeException When encryption or storage fails.
	 */
	public function put( PrivateMediaObjectKind $kind, string $bytes ): PrivateMediaObject {
		$byte_count = strlen( $bytes );

		if ( 0 === $byte_count || $byte_count > 33554432 ) {
			throw new RuntimeException( 'Private-media object is invalid.' );
		}

		$this->ensure_directory( $this->root );
		$this->assert_canonical_root();

		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			$identifier = bin2hex( random_bytes( 32 ) );
			$directory  = $this->root . '/' . substr( $identifier, 0, 2 );
			$path       = $directory . '/' . $identifier . '.rtm';
			$this->ensure_directory( $directory );

			$nonce      = random_bytes( SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
			$ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
				$bytes,
				$this->object_associated_data( $kind, $identifier ),
				$nonce,
				$this->secrets->object_key
			);

			if ( $this->write_exclusive( $path, self::OBJECT_PREFIX . $nonce . $ciphertext ) ) {
				return new PrivateMediaObject(
					$this->encrypt_reference( $kind, $identifier ),
					PrivateMediaSecrets::KEY_ID,
					MediaDigest::from_digest( hash( 'sha256', $bytes ) ),
					$byte_count
				);
			}
		}

		throw new RuntimeException( 'Private-media storage is unavailable.' );
	}

	/**
	 * Authenticate, decrypt, and verify one private object.
	 *
	 * @param PrivateMediaObjectKind $kind Expected object purpose.
	 * @param PrivateMediaObject     $stored_object Stored descriptor.
	 * @throws RuntimeException When reference, object, or integrity verification fails.
	 */
	public function read( PrivateMediaObjectKind $kind, PrivateMediaObject $stored_object ): string {
		$identifier = $this->decrypt_reference( $kind, $stored_object );
		$path       = $this->path( $identifier );
		$envelope   = $this->read_file( $path );
		$prefix_len = strlen( self::OBJECT_PREFIX );
		$nonce_len  = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

		if ( ! str_starts_with( $envelope, self::OBJECT_PREFIX ) || strlen( $envelope ) <= $prefix_len + $nonce_len ) {
			throw new RuntimeException( 'Private-media object is invalid.' );
		}

		$payload   = substr( $envelope, $prefix_len );
		$plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
			substr( $payload, $nonce_len ),
			$this->object_associated_data( $kind, $identifier ),
			substr( $payload, 0, $nonce_len ),
			$this->secrets->object_key
		);

		if (
			false === $plaintext
			|| strlen( $plaintext ) !== $stored_object->byte_count
			|| ! hash_equals( $stored_object->sha256->value, hash( 'sha256', $plaintext ) )
		) {
			throw new RuntimeException( 'Private-media object is invalid.' );
		}

		return $plaintext;
	}

	/**
	 * Idempotently remove one private object.
	 *
	 * @param PrivateMediaObjectKind $kind Expected object purpose.
	 * @param PrivateMediaObject     $stored_object Stored descriptor.
	 * @throws RuntimeException When reference authentication or deletion fails.
	 */
	public function delete( PrivateMediaObjectKind $kind, PrivateMediaObject $stored_object ): void {
		$path = $this->path( $this->decrypt_reference( $kind, $stored_object ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Deletes only an authenticated path under the configured private root.
		if ( is_link( $path ) || ( is_file( $path ) && ! unlink( $path ) ) ) {
			throw new RuntimeException( 'Private-media cleanup failed.' );
		}
	}

	/**
	 * Encrypt a purpose-bound opaque object reference.
	 *
	 * @param PrivateMediaObjectKind $kind Object purpose.
	 * @param string                 $identifier Random identifier.
	 */
	private function encrypt_reference( PrivateMediaObjectKind $kind, string $identifier ): PrivateMediaReferenceCiphertext {
		$nonce      = random_bytes( SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
		$ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
			$kind->value . ':' . $identifier,
			$this->reference_associated_data(),
			$nonce,
			$this->secrets->reference_key
		);

		return PrivateMediaReferenceCiphertext::from_encrypted_bytes(
			self::REFERENCE_PREFIX . sodium_bin2base64(
				$nonce . $ciphertext,
				SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING
			)
		);
	}

	/**
	 * Authenticate a reference and return its random identifier.
	 *
	 * @param PrivateMediaObjectKind $kind Expected object purpose.
	 * @param PrivateMediaObject     $stored_object Stored descriptor.
	 * @throws RuntimeException When reference authentication fails.
	 */
	private function decrypt_reference( PrivateMediaObjectKind $kind, PrivateMediaObject $stored_object ): string {
		if ( PrivateMediaSecrets::KEY_ID !== $stored_object->encryption_key_id || ! str_starts_with( $stored_object->reference_ciphertext->value, self::REFERENCE_PREFIX ) ) {
			throw new RuntimeException( 'Private-media reference is invalid.' );
		}

		try {
			$payload = sodium_base642bin(
				substr( $stored_object->reference_ciphertext->value, strlen( self::REFERENCE_PREFIX ) ),
				SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
				''
			);
		} catch ( \SodiumException ) {
			throw new RuntimeException( 'Private-media reference is invalid.' );
		}

		$nonce_len = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

		if ( strlen( $payload ) <= $nonce_len ) {
			throw new RuntimeException( 'Private-media reference is invalid.' );
		}

		$plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
			substr( $payload, $nonce_len ),
			$this->reference_associated_data(),
			substr( $payload, 0, $nonce_len ),
			$this->secrets->reference_key
		);
		$prefix    = $kind->value . ':';

		if ( false === $plaintext || ! str_starts_with( $plaintext, $prefix ) ) {
			throw new RuntimeException( 'Private-media reference is invalid.' );
		}

		$identifier = substr( $plaintext, strlen( $prefix ) );

		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/D', $identifier ) ) {
			throw new RuntimeException( 'Private-media reference is invalid.' );
		}

		return $identifier;
	}

	/**
	 * Build stable object associated data.
	 *
	 * @param PrivateMediaObjectKind $kind Object purpose.
	 * @param string                 $identifier Random identifier.
	 */
	private function object_associated_data( PrivateMediaObjectKind $kind, string $identifier ): string {
		return 'finder-evidence-object|' . PrivateMediaSecrets::KEY_ID . '|' . $kind->value . '|' . $identifier;
	}

	/** Build stable reference associated data. */
	private function reference_associated_data(): string {
		return 'finder-evidence-reference|' . PrivateMediaSecrets::KEY_ID . '|v1';
	}

	/**
	 * Resolve a safe path from a validated random identifier.
	 *
	 * @param string $identifier Random identifier.
	 */
	private function path( string $identifier ): string {
		return $this->root . '/' . substr( $identifier, 0, 2 ) . '/' . $identifier . '.rtm';
	}

	/**
	 * Validate an absolute root outside every public root.
	 *
	 * @param string $root Candidate private root.
	 * @param array  $public_roots Forbidden roots.
	 * @phpstan-param list<string> $public_roots
	 * @throws RuntimeException When the root is not absolute and private.
	 */
	private function validate_root( string $root, array $public_roots ): string {
		$normalized = $this->normalize_path( $root );

		if ( '' === $normalized || ( ! str_starts_with( $normalized, '/' ) && 1 !== preg_match( '/^[A-Za-z]:\//D', $normalized ) ) ) {
			throw new RuntimeException( 'Private-media storage configuration is invalid.' );
		}

		foreach ( $public_roots as $public_root ) {
			$public = $this->normalize_path( $public_root );

			if ( '' !== $public && ( 0 === strcasecmp( $normalized, $public ) || str_starts_with( strtolower( $normalized ), strtolower( $public . '/' ) ) ) ) {
				throw new RuntimeException( 'Private-media storage configuration is invalid.' );
			}
		}

		return $normalized;
	}

	/**
	 * Normalize a trusted configuration path without resolving a missing target.
	 *
	 * @param string $path Candidate path.
	 * @throws RuntimeException When the path contains unsafe segments.
	 */
	private function normalize_path( string $path ): string {
		$normalized = rtrim( str_replace( '\\', '/', trim( $path ) ), '/' );

		if ( str_contains( $normalized, "\0" ) || in_array( '..', explode( '/', $normalized ), true ) ) {
			throw new RuntimeException( 'Private-media storage configuration is invalid.' );
		}

		return $normalized;
	}

	/**
	 * Create a private directory when absent.
	 *
	 * @param string $directory Absolute private directory.
	 * @throws RuntimeException When the directory cannot be created.
	 */
	private function ensure_directory( string $directory ): void {
		if ( is_link( $directory ) ) {
			throw new RuntimeException( 'Private-media storage configuration is invalid.' );
		}

		if ( is_dir( $directory ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Enforces private filesystem permissions directly.
			if ( ! chmod( $directory, 0700 ) ) {
				throw new RuntimeException( 'Private-media storage is unavailable.' );
			}

			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Private storage is outside WordPress and must not invoke credentialed WP_Filesystem.
		if ( ! mkdir( $directory, 0700, true ) && ! is_dir( $directory ) ) {
			throw new RuntimeException( 'Private-media storage is unavailable.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Enforces private filesystem permissions directly.
		if ( ! chmod( $directory, 0700 ) ) {
			throw new RuntimeException( 'Private-media storage is unavailable.' );
		}
	}

	/**
	 * Recheck the created root against symlink and public-root resolution.
	 *
	 * @throws RuntimeException When canonical resolution is unsafe.
	 */
	private function assert_canonical_root(): void {
		$resolved = realpath( $this->root );

		if ( false === $resolved || is_link( $this->root ) ) {
			throw new RuntimeException( 'Private-media storage configuration is invalid.' );
		}

		$canonical = $this->normalize_path( $resolved );

		foreach ( $this->public_roots as $public_root ) {
			$public_resolved = realpath( $public_root );
			$public          = false === $public_resolved ? $public_root : $this->normalize_path( $public_resolved );

			if ( 0 === strcasecmp( $canonical, $public ) || str_starts_with( strtolower( $canonical ), strtolower( $public . '/' ) ) ) {
				throw new RuntimeException( 'Private-media storage configuration is invalid.' );
			}
		}
	}

	/**
	 * Write a new object without overwriting an existing path.
	 *
	 * @param string $path Absolute object path.
	 * @param string $bytes Encrypted envelope.
	 * @throws RuntimeException When an exclusive encrypted write fails.
	 */
	private function write_exclusive( string $path, string $bytes ): bool {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Exclusive creation is required for collision safety.
		$handle = fopen( $path, 'xb' );

		if ( false === $handle ) {
			return false;
		}

		$offset = 0;
		$length = strlen( $bytes );

		try {
			while ( $offset < $length ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes only an authenticated encrypted envelope.
				$written = fwrite( $handle, substr( $bytes, $offset ) );

				if ( false === $written || 0 === $written ) {
					throw new RuntimeException( 'Private-media storage is unavailable.' );
				}

				$offset += $written;
			}

			if ( ! fflush( $handle ) ) {
				throw new RuntimeException( 'Private-media storage is unavailable.' );
			}
		} catch ( RuntimeException $exception ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Completes the direct exclusive write above.
			fclose( $handle );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only the failed newly-created private object.
			unlink( $path );

			throw $exception;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Completes the direct exclusive write above.
		fclose( $handle );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Enforces private object permissions directly.
		if ( ! chmod( $path, 0600 ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only the failed newly-created private object.
			unlink( $path );

			throw new RuntimeException( 'Private-media storage is unavailable.' );
		}

		return true;
	}

	/**
	 * Read one bounded encrypted envelope.
	 *
	 * @param string $path Absolute object path.
	 * @throws RuntimeException When the object is absent, oversized, or unreadable.
	 */
	private function read_file( string $path ): string {
		if ( is_link( $path ) || ! is_file( $path ) ) {
			throw new RuntimeException( 'Private-media object is unavailable.' );
		}

		$size = filesize( $path );

		if ( ! is_int( $size ) || $size < 1 || $size > 33554560 ) {
			throw new RuntimeException( 'Private-media object is invalid.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a validated local encrypted object, never a URL.
		$bytes = file_get_contents( $path );

		if ( ! is_string( $bytes ) || strlen( $bytes ) !== $size ) {
			throw new RuntimeException( 'Private-media object is unavailable.' );
		}

		return $bytes;
	}
}
