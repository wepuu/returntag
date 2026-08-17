<?php
/**
 * Criteria-bound admin operations cursor codec.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use InvalidArgumentException;

/** Produces opaque HMAC-authenticated cursors that never contain an email. */
final class AdminOperationsCursorCodec {
	/**
	 * HMAC secret sourced from WordPress or an explicit test fixture.
	 *
	 * @var string
	 */
	private ?string $secret;

	/**
	 * Create the codec.
	 *
	 * @param string|null $secret Optional injectable HMAC secret.
	 */
	public function __construct( ?string $secret = null ) {
		$this->secret = $secret;
	}

	/**
	 * Encode one criteria-bound keyset position.
	 *
	 * @param string $scope Cursor collection scope.
	 * @param array  $criteria Normalized search criteria without email.
	 * @param string $position Internal keyset position.
	 * @return string Opaque cursor.
	 * @phpstan-param array<string, mixed> $criteria
	 * @throws InvalidArgumentException When the cursor cannot be encoded.
	 */
	public function encode( string $scope, array $criteria, string $position ): string {
		$secret = $this->secret();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Framework-independent canonical testable payload.
		$payload = json_encode(
			array(
				'v' => 1,
				's' => $scope,
				'c' => hash_hmac( 'sha256', $this->canonical( $criteria ), $secret ),
				'p' => $position,
			)
		);
		if ( ! is_string( $payload ) ) {
			throw new InvalidArgumentException( 'Cursor cannot be encoded.' );
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Benign opaque pagination cursor.
		return rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' );
	}

	/**
	 * Decode and authenticate one cursor under the current criteria.
	 *
	 * @param string $scope Cursor collection scope.
	 * @param array  $criteria Normalized search criteria without email.
	 * @param string $cursor External cursor.
	 * @return string Internal keyset position.
	 * @phpstan-param array<string, mixed> $criteria
	 * @throws InvalidArgumentException When the cursor is invalid or rebound.
	 */
	public function decode( string $scope, array $criteria, string $cursor ): string {
		$secret  = $this->secret();
		$padding = strlen( $cursor ) % 4;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Strictly validated benign cursor payload.
		$decoded = base64_decode( strtr( $cursor . ( 0 === $padding ? '' : str_repeat( '=', 4 - $padding ) ), '-_', '+/' ), true );
		$data    = is_string( $decoded ) ? json_decode( $decoded, true ) : null;
		$hash    = hash_hmac( 'sha256', $this->canonical( $criteria ), $secret );

		if ( ! is_array( $data ) || 1 !== ( $data['v'] ?? null ) || ( $data['s'] ?? null ) !== $scope || ! is_string( $data['c'] ?? null ) || ! hash_equals( $hash, $data['c'] ) || ! is_string( $data['p'] ?? null ) ) {
			throw new InvalidArgumentException( 'Cursor is invalid.' );
		}
		return $data['p'];
	}

	/**
	 * Resolve the WordPress salt only when a cursor is actually used.
	 *
	 * Keeping resolution lazy prevents an unavailable salt from taking down
	 * every TagCore surface; pagination itself still fails closed.
	 *
	 * @throws InvalidArgumentException When no secret is available.
	 */
	private function secret(): string {
		$secret = $this->secret ?? ( function_exists( 'wp_salt' ) ? wp_salt( 'nonce' ) : '' );
		if ( '' === $secret ) {
			throw new InvalidArgumentException( 'Cursor secret is unavailable.' );
		}
		return $secret;
	}

	/**
	 * Canonicalize criteria for the HMAC input.
	 *
	 * @param array $criteria Normalized criteria.
	 * @phpstan-param array<string, mixed> $criteria
	 * @throws InvalidArgumentException When criteria cannot be encoded.
	 */
	private function canonical( array $criteria ): string {
		ksort( $criteria );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Framework-independent canonical testable payload.
		$json = json_encode( $criteria );
		if ( ! is_string( $json ) ) {
			throw new InvalidArgumentException( 'Criteria are invalid.' );
		}
		return $json;
	}
}
