<?php
/**
 * Provider-neutral transactional email request.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Email;

use InvalidArgumentException;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/** Contains private send material only in Worker memory. */
final readonly class TransactionalEmail {
	/**
	 * Create a send request.
	 *
	 * @param string       $purpose Bounded business purpose.
	 * @param string       $idempotency_key Opaque stable key.
	 * @param EmailAddress $recipient Private in-memory recipient.
	 * @param string       $subject Translatable message subject.
	 * @param string       $text Plain-text body.
	 * @param string|null  $html Optional HTML alternative.
	 * @param array        $attachments In-memory inline attachments.
	 * @phpstan-param list<TransactionalEmailAttachment> $attachments
	 * @throws InvalidArgumentException When the request violates the contract.
	 */
	public function __construct(
		public string $purpose,
		public string $idempotency_key,
		public EmailAddress $recipient,
		public string $subject,
		public string $text,
		public ?string $html = null,
		public array $attachments = array()
	) {
		if (
			1 !== preg_match( '/^[a-z0-9_]{3,64}$/D', $purpose )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/D', $idempotency_key )
			|| '' === trim( $subject )
			|| strlen( $subject ) > 200
			|| '' === $text
			|| strlen( $text ) > 100000
			|| ( null !== $html && ( '' === $html || strlen( $html ) > 200000 ) )
			|| count( $attachments ) > 1
		) {
			throw new InvalidArgumentException( 'Transactional email request is invalid.' );
		}
	}
}
