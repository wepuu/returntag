<?php
/**
 * Owner lifecycle form boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

use ReturnTag\TagCore\Application\Account\ManageOwnerLifecycle;
use ReturnTag\TagCore\Application\Account\OwnerLifecycleResult;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Auth\AuthenticatedUserEmailReader;
use ReturnTag\TagCore\Application\Auth\RequestAccountOtp;
use ReturnTag\TagCore\Domain\Auth\ActivationOtpCode;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Tag\TagId;
use Throwable;

/** Accepts only reauthentication, Transfer, and Retire actions. */
final readonly class AccountLifecycleFormHandler {
	public const REQUEST_CODE = 'request_lifecycle_code';
	public const TRANSFER     = 'transfer_tag';
	public const RETIRE       = 'retire_tag';
	public const CANCEL       = 'cancel_transfer';
	public const TARGET_EMAIL = 'returntag_transfer_email';
	public const CODE         = 'returntag_lifecycle_code';
	public const CONFIRM_TAG  = 'returntag_retire_tag_id';
	/**
	 * Create the lifecycle boundary.
	 *
	 * @param RequestAccountOtp|null       $request_otp Account OTP request service.
	 * @param ManageOwnerLifecycle|null    $lifecycle Lifecycle use cases.
	 * @param AuthenticatedSession         $session Current WordPress session.
	 * @param AuthenticatedUserEmailReader $emails Server-side email reader.
	 * @param AccountFormRequestGuard      $guard Same-site request guard.
	 */
	public function __construct( private ?RequestAccountOtp $request_otp, private ?ManageOwnerLifecycle $lifecycle, private AuthenticatedSession $session, private AuthenticatedUserEmailReader $emails, private AccountFormRequestGuard $guard ) {}

	/** Determine whether the current POST selects a lifecycle action. */
	public function supports(): bool {
		return in_array( $this->guard->post_string( AccountTagMutationFormHandler::ACTION_FIELD, 40 ), array( self::REQUEST_CODE, self::TRANSFER, self::RETIRE, self::CANCEL ), true );
	}

	/**
	 * Submit one Tag-bound lifecycle action.
	 *
	 * @param TagId $tag_id Candidate current-Owner Tag.
	 */
	public function submit( TagId $tag_id ): AccountTagMutationFeedback {
		if ( ! $this->guard->is_same_site() || ! $this->guard->valid_nonce( AccountTagMutationFormHandler::NONCE_FIELD, AccountTagMutationFormHandler::NONCE_PREFIX . $tag_id->value ) ) {
			return new AccountTagMutationFeedback( AccountTagMutationState::UNAVAILABLE );
		}
		try {
			$action = $this->guard->post_string( AccountTagMutationFormHandler::ACTION_FIELD, 40 );
			if ( self::REQUEST_CODE === $action ) {
				$user  = $this->session->current_user_id();
				$email = null === $user ? null : $this->emails->find( $user );
				if ( null === $email || null === $this->request_otp ) {
					return new AccountTagMutationFeedback( AccountTagMutationState::UNAVAILABLE );
				}
				$this->request_otp->execute( $email, $this->guard->direct_peer_ip() );
				return new AccountTagMutationFeedback( AccountTagMutationState::REAUTHENTICATION_SENT );
			}
			if ( null === $this->lifecycle ) {
				return new AccountTagMutationFeedback( AccountTagMutationState::UNAVAILABLE );
			}
			if ( self::CANCEL === $action ) {
				return new AccountTagMutationFeedback(
					OwnerLifecycleResult::CANCELLED === $this->lifecycle->cancel( $tag_id ) ? AccountTagMutationState::TRANSFER_CANCELLED : AccountTagMutationState::UNCHANGED
				);
			}
			$code   = new ActivationOtpCode( $this->guard->post_string( self::CODE, 6 ) );
			$result = self::TRANSFER === $action
				? $this->lifecycle->transfer( $tag_id, new EmailAddress( $this->guard->post_string( self::TARGET_EMAIL, 254 ) ), $code, $this->guard->direct_peer_ip() )
				: $this->lifecycle->retire( $tag_id, strtoupper( preg_replace( '/[\s-]+/', '', $this->guard->post_string( self::CONFIRM_TAG, 32 ) ) ?? '' ), $code, $this->guard->direct_peer_ip() );
			return new AccountTagMutationFeedback(
				match ( $result ) {
				OwnerLifecycleResult::CREATED => AccountTagMutationState::TRANSFER_INVITED,
				OwnerLifecycleResult::RETIRED => AccountTagMutationState::RETIRED,
				OwnerLifecycleResult::AUTHENTICATION_REQUIRED => AccountTagMutationState::VERIFICATION_INVALID,
				default => AccountTagMutationState::UNAVAILABLE,
				}
			);
		} catch ( Throwable ) {
			return new AccountTagMutationFeedback( AccountTagMutationState::VERIFICATION_INVALID );
		}
	}
}
