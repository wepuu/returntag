<?php
/**
 * Public activation OTP form boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Auth\ActivationOtpRequestResult;
use ReturnTag\TagCore\Application\Auth\ActivationOtpProtector;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Auth\AuthenticatedUserEmailReader;
use ReturnTag\TagCore\Application\Auth\CompletePasswordlessAuthentication;
use ReturnTag\TagCore\Application\Auth\PasswordlessAuthenticationResult;
use ReturnTag\TagCore\Application\Auth\RequestActivationOtp;
use ReturnTag\TagCore\Application\Auth\WordPressAccountEmailPolicy;
use ReturnTag\TagCore\Application\Tag\RateLimitedTagActivation;
use ReturnTag\TagCore\Application\Tag\TagActivationAttemptResult;
use ReturnTag\TagCore\Domain\Auth\ActivationOtpCode;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Tag\TagId;
use Throwable;

/**
 * Validates one anonymous same-site mutation and maps privacy-safe feedback.
 */
final readonly class ActivationOtpFormHandler {
	public const NONCE_ACTION = 'returntag_request_activation_otp';

	public const NONCE_FIELD = 'returntag_otp_nonce';

	public const EMAIL_FIELD = 'returntag_activation_email';

	public const CODE_FIELD = 'returntag_activation_code';

	public const ACTION_FIELD = 'returntag_activation_action';

	public const REQUEST_ACTION = 'request_code';

	public const VERIFY_ACTION = 'verify_code';

	public const ACTIVATE_ACTION = 'activate_tag';

	/**
	 * Create the form handler.
	 *
	 * @param RequestActivationOtp|null               $requests Configured request use case.
	 * @param CompletePasswordlessAuthentication|null $authentication Configured authentication use case.
	 * @param AuthenticatedSession                    $session Current WordPress session.
	 * @param WordPressAccountEmailPolicy             $email_policy WordPress account email boundary.
	 * @param RateLimitedTagActivation|null           $activation Configured authenticated activation.
	 * @param AuthenticatedUserEmailReader|null       $user_emails Server-side User email reader.
	 * @param ActivationOtpProtector|null             $protector Keyed lookup protection.
	 */
	public function __construct(
		private ?RequestActivationOtp $requests,
		private ?CompletePasswordlessAuthentication $authentication,
		private AuthenticatedSession $session,
		private WordPressAccountEmailPolicy $email_policy,
		private ?RateLimitedTagActivation $activation,
		private ?AuthenticatedUserEmailReader $user_emails,
		private ?ActivationOtpProtector $protector
	) {
	}

	/**
	 * Determine whether WordPress already has an authenticated identity.
	 */
	public function is_authenticated(): bool {
		return null !== $this->session->current_user_id();
	}

	/**
	 * Determine whether the bounded POST action requests Tag activation.
	 */
	public function is_activation_action(): bool {
		return self::ACTIVATE_ACTION === $this->post_string( self::ACTION_FIELD, 32 );
	}

	/**
	 * Validate and submit one authenticated activation attempt.
	 *
	 * @param TagId $tag_id Server-resolved eligible Tag.
	 */
	public function activate( TagId $tag_id ): ?TagActivationAttemptResult {
		if (
			! $this->is_activation_action()
			|| ! $this->same_site_request()
			|| ! $this->valid_nonce()
			|| null === $this->activation
			|| null === $this->user_emails
			|| null === $this->protector
		) {
			return null;
		}

		$user_id = $this->session->current_user_id();

		if ( null === $user_id ) {
			return null;
		}

		$email = $this->user_emails->find( $user_id );

		if ( null === $email ) {
			return null;
		}

		try {
			$ip = $this->client_ip();

			return $this->activation->execute(
				$tag_id,
				$user_id,
				$this->protector->email_lookup( $email ),
				$this->protector->ip_lookup( $ip )
			);
		} catch ( Throwable ) {
			return null;
		}
	}

	/**
	 * Validate and submit one activation request.
	 *
	 * @param TagId $tag_id Server-resolved eligible Tag.
	 */
	public function submit( TagId $tag_id ): ActivationOtpFormState {
		$action = $this->post_string( self::ACTION_FIELD, 32 );

		if ( ! $this->same_site_request() || ! $this->valid_nonce() ) {
			return self::VERIFY_ACTION === $action
				? ActivationOtpFormState::VERIFICATION_INVALID
				: ActivationOtpFormState::REQUEST_ERROR;
		}

		return match ( $action ) {
			self::REQUEST_ACTION => $this->request_code( $tag_id ),
			self::VERIFY_ACTION => $this->verify_code( $tag_id ),
			default => ActivationOtpFormState::REQUEST_ERROR,
		};
	}

	/**
	 * Submit one OTP request.
	 *
	 * @param TagId $tag_id Server-resolved eligible Tag.
	 */
	private function request_code( TagId $tag_id ): ActivationOtpFormState {
		if ( null === $this->requests ) {
			return ActivationOtpFormState::REQUEST_ERROR;
		}

		$email_input = $this->post_string( self::EMAIL_FIELD, 254 );

		try {
			$email = new EmailAddress( $email_input );
			$ip    = $this->client_ip();
		} catch ( InvalidArgumentException ) {
			return ActivationOtpFormState::REQUEST_INVALID_EMAIL;
		}

		if ( ! $this->email_policy->allows( $email ) ) {
			return ActivationOtpFormState::REQUEST_INVALID_EMAIL;
		}

		try {
			$result = $this->requests->execute( $tag_id, $email, $ip );
		} catch ( Throwable ) {
			return ActivationOtpFormState::REQUEST_ERROR;
		}

		return in_array(
			$result,
			array( ActivationOtpRequestResult::ACCEPTED, ActivationOtpRequestResult::THROTTLED ),
			true
		)
			? ActivationOtpFormState::REQUEST_ACCEPTED
			: ActivationOtpFormState::REQUEST_ERROR;
	}

	/**
	 * Verify one OTP and establish a passwordless WordPress session.
	 *
	 * @param TagId $tag_id Server-resolved eligible Tag.
	 */
	private function verify_code( TagId $tag_id ): ActivationOtpFormState {
		if ( null === $this->authentication ) {
			return ActivationOtpFormState::VERIFICATION_INVALID;
		}

		try {
			$email = new EmailAddress( $this->post_string( self::EMAIL_FIELD, 254 ) );
			$code  = new ActivationOtpCode( $this->post_string( self::CODE_FIELD, 6 ) );
			$ip    = $this->client_ip();
		} catch ( InvalidArgumentException ) {
			return ActivationOtpFormState::VERIFICATION_INVALID;
		}

		try {
			$result = $this->authentication->execute( $tag_id, $email, $code, $ip );
		} catch ( Throwable ) {
			return ActivationOtpFormState::VERIFICATION_INVALID;
		}

		return in_array(
			$result,
			array(
				PasswordlessAuthenticationResult::AUTHENTICATED,
				PasswordlessAuthenticationResult::ALREADY_AUTHENTICATED,
			),
			true
		)
			? ActivationOtpFormState::AUTHENTICATED
			: ActivationOtpFormState::VERIFICATION_INVALID;
	}

	/**
	 * Validate the anonymous WordPress nonce.
	 */
	private function valid_nonce(): bool {
		$nonce = $this->post_string( self::NONCE_FIELD, 64 );

		return '' !== $nonce && false !== wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * Reject browser requests carrying cross-site evidence.
	 */
	private function same_site_request(): bool {
		$fetch_site = $this->server_string( 'HTTP_SEC_FETCH_SITE', 32 );

		if ( '' !== $fetch_site && ! in_array( strtolower( $fetch_site ), array( 'same-origin', 'same-site', 'none' ), true ) ) {
			return false;
		}

		$origin = $this->server_string( 'HTTP_ORIGIN', 512 );

		if ( '' === $origin ) {
			return true;
		}

		$expected = wp_parse_url( home_url( '/' ) );
		$actual   = wp_parse_url( $origin );

		if ( ! is_array( $expected ) || ! is_array( $actual ) ) {
			return false;
		}

		return strtolower( (string) ( $expected['scheme'] ?? '' ) ) === strtolower( (string) ( $actual['scheme'] ?? '' ) )
			&& strtolower( (string) ( $expected['host'] ?? '' ) ) === strtolower( (string) ( $actual['host'] ?? '' ) )
			&& (int) ( $expected['port'] ?? 0 ) === (int) ( $actual['port'] ?? 0 );
	}

	/**
	 * Return only the direct peer address.
	 *
	 * @throws InvalidArgumentException When the address is missing or invalid.
	 */
	private function client_ip(): string {
		$ip = $this->server_string( 'REMOTE_ADDR', 64 );

		if ( '' === $ip || false === inet_pton( $ip ) ) {
			throw new InvalidArgumentException( 'Client address is unavailable.' );
		}

		return $ip;
	}

	/**
	 * Read one bounded POST string.
	 *
	 * @param string $key Input key.
	 * @param int    $maximum_bytes Hard byte limit.
	 */
	private function post_string( string $key, int $maximum_bytes ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is validated by submit() before business work.
		$value = $_POST[ $key ] ?? '';

		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = wp_unslash( $value );

		return strlen( $value ) <= $maximum_bytes ? $value : '';
	}

	/**
	 * Read one bounded server string.
	 *
	 * @param string $key Server key.
	 * @param int    $maximum_bytes Hard byte limit.
	 */
	private function server_string( string $key, int $maximum_bytes ): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are length-bounded and parsed against closed policies.
		$value = $_SERVER[ $key ] ?? '';

		return is_string( $value ) && strlen( $value ) <= $maximum_bytes ? $value : '';
	}
}
