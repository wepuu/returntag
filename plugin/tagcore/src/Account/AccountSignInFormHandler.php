<?php
/**
 * Owner Account passwordless sign-in form boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Auth\AccountOtpRequestResult;
use ReturnTag\TagCore\Application\Auth\CompleteAccountPasswordlessAuthentication;
use ReturnTag\TagCore\Application\Auth\PasswordlessAuthenticationResult;
use ReturnTag\TagCore\Application\Auth\RequestAccountOtp;
use ReturnTag\TagCore\Domain\Auth\ActivationOtpCode;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use Throwable;

/**
 * Validates one anonymous same-site Account sign-in mutation.
 */
final readonly class AccountSignInFormHandler {
	public const NONCE_ACTION = 'returntag_account_sign_in';

	public const NONCE_FIELD = 'returntag_account_nonce';

	public const ACTION_FIELD = 'returntag_account_action';

	public const EMAIL_FIELD = 'returntag_account_email';

	public const CODE_FIELD = 'returntag_account_code';

	public const REQUEST_ACTION = 'request_code';

	public const VERIFY_ACTION = 'verify_code';

	/**
	 * Create the form boundary.
	 *
	 * @param RequestAccountOtp|null                         $requests Configured request use case.
	 * @param CompleteAccountPasswordlessAuthentication|null $authentication Configured verification use case.
	 * @param AccountFormRequestGuard                        $guard Browser request guard.
	 */
	public function __construct(
		private ?RequestAccountOtp $requests,
		private ?CompleteAccountPasswordlessAuthentication $authentication,
		private AccountFormRequestGuard $guard
	) {
	}

	/** Validate and submit one closed Account sign-in action. */
	public function submit(): AccountFormResult {
		$action = $this->guard->post_string( self::ACTION_FIELD, 32 );
		$email  = $this->guard->post_string( self::EMAIL_FIELD, 254 );

		if ( ! $this->guard->is_same_site() || ! $this->guard->valid_nonce( self::NONCE_FIELD, self::NONCE_ACTION ) ) {
			return new AccountFormResult( AccountFormState::UNAVAILABLE );
		}

		return match ( $action ) {
			self::REQUEST_ACTION => $this->request_code( $email ),
			self::VERIFY_ACTION => $this->verify_code( $email ),
			default => new AccountFormResult( AccountFormState::UNAVAILABLE ),
		};
	}

	/**
	 * Submit one non-enumerating code request.
	 *
	 * @param string $email_input Bounded untrusted email input.
	 */
	private function request_code( string $email_input ): AccountFormResult {
		if ( null === $this->requests ) {
			return new AccountFormResult( AccountFormState::UNAVAILABLE );
		}

		try {
			$email = new EmailAddress( $email_input );
			$ip    = $this->guard->direct_peer_ip();
		} catch ( InvalidArgumentException ) {
			return new AccountFormResult( AccountFormState::INVALID_EMAIL );
		}

		try {
			$result = $this->requests->execute( $email, $ip );
		} catch ( Throwable ) {
			return new AccountFormResult( AccountFormState::UNAVAILABLE );
		}

		return in_array( $result, array( AccountOtpRequestResult::ACCEPTED, AccountOtpRequestResult::THROTTLED ), true )
			? new AccountFormResult( AccountFormState::CODE_SENT, $email->value )
			: new AccountFormResult( AccountFormState::UNAVAILABLE );
	}

	/**
	 * Verify one code and establish a WordPress session.
	 *
	 * @param string $email_input Bounded untrusted email input.
	 */
	private function verify_code( string $email_input ): AccountFormResult {
		if ( null === $this->authentication ) {
			return new AccountFormResult( AccountFormState::VERIFICATION_INVALID, $email_input );
		}

		try {
			$email = new EmailAddress( $email_input );
			$code  = new ActivationOtpCode( $this->guard->post_string( self::CODE_FIELD, 6 ) );
			$ip    = $this->guard->direct_peer_ip();
		} catch ( InvalidArgumentException ) {
			return new AccountFormResult( AccountFormState::VERIFICATION_INVALID, $email_input );
		}

		try {
			$result = $this->authentication->execute( $email, $code, $ip );
		} catch ( Throwable ) {
			return new AccountFormResult( AccountFormState::VERIFICATION_INVALID, $email->value );
		}

		return in_array(
			$result,
			array( PasswordlessAuthenticationResult::AUTHENTICATED, PasswordlessAuthenticationResult::ALREADY_AUTHENTICATED ),
			true
		)
			? new AccountFormResult( AccountFormState::AUTHENTICATED )
			: new AccountFormResult( AccountFormState::VERIFICATION_INVALID, $email->value );
	}
}
