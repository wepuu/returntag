<?php
/**
 * In-memory transactional email attachment.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Email;

use InvalidArgumentException;

/** Keeps attachment bytes out of persistence while crossing the email port. */
final readonly class TransactionalEmailAttachment {
	/**
	 * Create one bounded inline attachment.
	 *
	 * @param string $filename Safe attachment filename.
	 * @param string $content_type Bounded MIME type.
	 * @param string $content In-memory bytes.
	 * @param string $content_id Inline content identifier.
	 * @throws InvalidArgumentException When any value violates the contract.
	 */
	public function __construct(
		public string $filename,
		public string $content_type,
		public string $content,
		public string $content_id
	) {
		if (
			1 !== preg_match( '/^[A-Za-z0-9._-]{1,100}$/D', $filename )
			|| 1 !== preg_match( '#^[a-z0-9.+-]+/[a-z0-9.+-]+$#D', $content_type )
			|| '' === $content
			|| strlen( $content ) > 5 * 1024 * 1024
			|| 1 !== preg_match( '/^[A-Za-z0-9._@-]{1,191}$/D', $content_id )
		) {
			throw new InvalidArgumentException( 'Transactional email attachment is invalid.' );
		}
	}
}
