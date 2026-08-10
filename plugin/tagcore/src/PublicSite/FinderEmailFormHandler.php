<?php
/**
 * Finder email verification form boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use ReturnTag\TagCore\Application\FinderReport\FinderEmailVerification;
use ReturnTag\TagCore\Application\FinderReport\FinderEmailVerificationResult;
use ReturnTag\TagCore\Domain\Tag\TagId;

/** Same-site optional Finder email verification boundary. */
final readonly class FinderEmailFormHandler {
	public const NONCE_ACTION   = 'returntag_verify_finder_email';
	public const NONCE_FIELD    = 'returntag_finder_email_nonce';
	public const ACTION_FIELD   = 'returntag_finder_email_action';
	public const REQUEST_ACTION = 'request_finder_email_code';
	public const VERIFY_ACTION  = 'verify_finder_email_code';
	public const EMAIL_FIELD    = 'returntag_finder_email';
	public const CODE_FIELD     = 'returntag_finder_email_code';

	/**
	 * Create the same-site form boundary.
	 *
	 * @param FinderEmailVerification|null $verification Optional configured workflow.
	 * @param FinderReportSubmissionLedger $ledger Opaque continuation ledger.
	 * @param PublicFormRequestGuard       $guard Same-site request guard.
	 */
	public function __construct( private ?FinderEmailVerification $verification, private FinderReportSubmissionLedger $ledger, private PublicFormRequestGuard $guard ) {
	}

	/** Determine whether this POST belongs to Finder email verification. */
	public function is_action(): bool {
		return in_array( $this->guard->post_string( self::ACTION_FIELD, 40 ), array( self::REQUEST_ACTION, self::VERIFY_ACTION ), true );
	}

	/** Determine whether private continuation is configured. */
	public function is_available(): bool {
		return null !== $this->verification;
	}

	/** Read the bounded opaque continuation token. */
	public function continuation_token(): string {
		return $this->guard->post_string( FinderReportFormHandler::TOKEN_FIELD, 160 );
	}

	/**
	 * Process one privacy-safe request or verification POST.
	 *
	 * @param TagId  $tag_id Server-resolved public Tag.
	 * @param string $token Opaque continuation token.
	 */
	public function submit( TagId $tag_id, string $token ): FinderEmailFormState {
		if ( null === $this->verification || ! $this->is_action() || ! $this->guard->is_same_site() || ! $this->guard->valid_nonce( self::NONCE_FIELD, self::NONCE_ACTION ) ) {
			return FinderEmailFormState::ERROR;
		}
		$report_id = $this->ledger->resolve_report_id( $tag_id, $token );
		if ( null === $report_id ) {
			return FinderEmailFormState::ERROR;
		}
		$action = $this->guard->post_string( self::ACTION_FIELD, 40 );
		$result = self::REQUEST_ACTION === $action
			? $this->verification->request( $report_id, $this->guard->post_string( self::EMAIL_FIELD, 254 ), $this->guard->direct_peer_ip() )
			: $this->verification->verify( $report_id, $this->guard->post_string( self::EMAIL_FIELD, 254 ), $this->guard->post_string( self::CODE_FIELD, 6 ), $this->guard->direct_peer_ip() );

		return match ( $result ) {
			FinderEmailVerificationResult::ACCEPTED => FinderEmailFormState::CODE_SENT,
			FinderEmailVerificationResult::VERIFIED => FinderEmailFormState::VERIFIED,
			FinderEmailVerificationResult::INVALID => FinderEmailFormState::INVALID,
			FinderEmailVerificationResult::UNAVAILABLE => FinderEmailFormState::ERROR,
		};
	}
}
