<?php
/**
 * Privacy-minimized Owner notification email.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use InvalidArgumentException;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/** Carries only the current Owner, optional message, and approved email JPEG. */
final readonly class FinderReportOwnerNotificationEmail {
	/**
	 * Create a send-ready notification.
	 *
	 * @param EmailAddress $recipient Current Owner email.
	 * @param string|null  $message Optional decrypted Finder message.
	 * @param string       $evidence_jpeg Metadata-free controlled JPEG bytes.
	 * @param string       $idempotency_key Opaque report-and-derivative key.
	 * @throws InvalidArgumentException When the email contract is invalid.
	 */
	public function __construct(
		public EmailAddress $recipient,
		public ?string $message,
		public string $evidence_jpeg,
		public string $idempotency_key
	) {
		if (
			'' === $this->evidence_jpeg
			|| strlen( $this->evidence_jpeg ) > 204800
			|| ! str_starts_with( $this->evidence_jpeg, "\xFF\xD8" )
			|| ! str_ends_with( $this->evidence_jpeg, "\xFF\xD9" )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/D', $this->idempotency_key )
		) {
			throw new InvalidArgumentException( 'Owner notification email is invalid.' );
		}

		if ( null !== $this->message ) {
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $this->message, 'UTF-8' ) : preg_match_all( '/./us', $this->message );

			if ( ! is_int( $length ) || $length < 10 || $length > 500 ) {
				throw new InvalidArgumentException( 'Owner notification email is invalid.' );
			}
		}
	}
}
