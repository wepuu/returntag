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
use ReturnTag\TagCore\Application\Auth\RequestActivationOtp;
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

	/**
	 * Create the form handler.
	 *
	 * @param RequestActivationOtp|null $requests Configured request use case.
	 */
	public function __construct( private ?RequestActivationOtp $requests ) {
	}

	/**
	 * Validate and submit one activation request.
	 *
	 * @param TagId $tag_id Server-resolved eligible Tag.
	 */
	public function submit( TagId $tag_id ): ActivationOtpFormState {
		if ( null === $this->requests || ! $this->same_site_request() || ! $this->valid_nonce() ) {
			return ActivationOtpFormState::ERROR;
		}

		$email_input = $this->post_string( self::EMAIL_FIELD, 254 );

		try {
			$email = new EmailAddress( $email_input );
			$ip    = $this->client_ip();
		} catch ( InvalidArgumentException ) {
			return ActivationOtpFormState::INVALID_EMAIL;
		}

		try {
			$result = $this->requests->execute( $tag_id, $email, $ip );
		} catch ( Throwable ) {
			return ActivationOtpFormState::ERROR;
		}

		return in_array(
			$result,
			array( ActivationOtpRequestResult::ACCEPTED, ActivationOtpRequestResult::THROTTLED ),
			true
		)
			? ActivationOtpFormState::ACCEPTED
			: ActivationOtpFormState::ERROR;
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
