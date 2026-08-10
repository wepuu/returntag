<?php
/**
 * WordPress option-backed Finder Report idempotency ledger.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use ReturnTag\TagCore\Domain\Tag\TagId;

/** Uses add_option as the atomic single-use claim. */
final class WordPressFinderReportSubmissionLedger implements FinderReportSubmissionLedger {
	private const LIFETIME     = 1800;
	private const CLEANUP_HOOK = 'returntag_expire_finder_report_form_claim';

	/** Register bounded claim cleanup. */
	public function __construct() {
		add_action( self::CLEANUP_HOOK, array( $this, 'expire' ), 10, 1 );
	}

	/**
	 * Issue a signed, Tag-bound token.
	 *
	 * @param TagId $tag_id Server-resolved Tag.
	 */
	public function issue( TagId $tag_id ): string {
		$expiry = time() + self::LIFETIME;
		$random = bin2hex( random_bytes( 24 ) );
		$value  = $expiry . '.' . $random;

		return $value . '.' . hash_hmac( 'sha256', $tag_id->value . '|' . $value, wp_salt( 'nonce' ) );
	}

	/**
	 * Atomically claim one valid token.
	 *
	 * @param TagId  $tag_id Server-resolved Tag.
	 * @param string $token Signed token.
	 */
	public function claim( TagId $tag_id, string $token ): FinderReportSubmissionClaim {
		if ( ! $this->is_valid( $tag_id, $token ) ) {
			return FinderReportSubmissionClaim::INVALID;
		}

		$option  = $this->option_name( $tag_id, $token );
		$value   = array(
			'state'      => FinderReportSubmissionClaim::CLAIMED->value,
			'expires_at' => time() + self::LIFETIME,
		);
		$claimed = add_option( $option, $value, '', false );

		if ( $claimed ) {
			$scheduled = wp_next_scheduled( self::CLEANUP_HOOK, array( $option ) );

			if ( false === $scheduled ) {
				$scheduled = wp_schedule_single_event( time() + self::LIFETIME, self::CLEANUP_HOOK, array( $option ), true );
			}

			if ( true !== $scheduled && ! is_int( $scheduled ) ) {
				delete_option( $option );

				return FinderReportSubmissionClaim::INVALID;
			}

			return FinderReportSubmissionClaim::CLAIMED;
		}

		$stored = get_option( $option, null );

		return is_array( $stored )
			&& FinderReportSubmissionClaim::REPLAYED->value === ( $stored['state'] ?? null )
			? FinderReportSubmissionClaim::REPLAYED
			: FinderReportSubmissionClaim::INVALID;
	}

	/**
	 * Mark a successfully persisted claim complete.
	 *
	 * @param TagId    $tag_id Server-resolved Tag.
	 * @param string   $token Signed token.
	 * @param int|null $finder_report_id Internal persisted report identifier.
	 */
	public function complete( TagId $tag_id, string $token, ?int $finder_report_id = null ): void {
		if ( ! $this->is_valid( $tag_id, $token ) ) {
			return;
		}

		update_option(
			$this->option_name( $tag_id, $token ),
			array(
				'state'      => FinderReportSubmissionClaim::REPLAYED->value,
				'expires_at' => time() + self::LIFETIME,
				'report_id'  => $finder_report_id,
			),
			false
		);
	}

	/**
	 * Resolve the internal report from one completed claim.
	 *
	 * @param TagId  $tag_id Server-resolved Tag.
	 * @param string $token Signed token.
	 */
	public function resolve_report_id( TagId $tag_id, string $token ): ?int {
		if ( ! $this->is_valid( $tag_id, $token ) ) {
			return null;
		}
		$value = get_option( $this->option_name( $tag_id, $token ), null );
		$id    = is_array( $value ) && FinderReportSubmissionClaim::REPLAYED->value === ( $value['state'] ?? null ) ? ( $value['report_id'] ?? null ) : null;
		return is_int( $id ) && $id > 0 ? $id : null;
	}

	/**
	 * Delete one expired opaque claim option.
	 *
	 * @param string $option Opaque option name supplied only by the scheduled action.
	 */
	public function expire( string $option ): void {
		if ( 1 === preg_match( '/^returntag_finder_form_[a-f0-9]{64}$/D', $option ) ) {
			delete_option( $option );
		}
	}

	/**
	 * Release only the exact claim derived from a valid token.
	 *
	 * @param TagId  $tag_id Server-resolved Tag.
	 * @param string $token Signed token.
	 */
	public function release( TagId $tag_id, string $token ): void {
		if ( $this->is_valid( $tag_id, $token ) ) {
			delete_option( $this->option_name( $tag_id, $token ) );
		}
	}

	/**
	 * Verify syntax, expiry, and Tag-bound signature.
	 *
	 * @param TagId  $tag_id Server-resolved Tag.
	 * @param string $token Signed token.
	 */
	private function is_valid( TagId $tag_id, string $token ): bool {
		if ( strlen( $token ) > 160 || 1 !== preg_match( '/^(\d{10})\.([a-f0-9]{48})\.([a-f0-9]{64})$/D', $token, $parts ) ) {
			return false;
		}

		$unsigned = $parts[1] . '.' . $parts[2];
		$expected = hash_hmac( 'sha256', $tag_id->value . '|' . $unsigned, wp_salt( 'nonce' ) );

		return (int) $parts[1] >= time() && hash_equals( $expected, $parts[3] );
	}

	/**
	 * Derive a bounded non-sensitive option key.
	 *
	 * @param TagId  $tag_id Server-resolved Tag.
	 * @param string $token Signed token.
	 */
	private function option_name( TagId $tag_id, string $token ): string {
		return 'returntag_finder_form_' . hash_hmac( 'sha256', $tag_id->value . '|' . $token, wp_salt( 'auth' ) );
	}
}
