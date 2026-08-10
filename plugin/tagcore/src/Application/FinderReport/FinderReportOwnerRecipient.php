<?php
/**
 * Current Finder Report Owner recipient.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use InvalidArgumentException;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/** Contains only the server-resolved current Owner identity and email. */
final readonly class FinderReportOwnerRecipient {
	/**
	 * Create a current Owner recipient.
	 *
	 * @param int          $owner_id Server-resolved WordPress user ID.
	 * @param EmailAddress $email Current validated Owner email.
	 * @throws InvalidArgumentException When the Owner ID is invalid.
	 */
	public function __construct(
		public int $owner_id,
		public EmailAddress $email
	) {
		if ( $this->owner_id < 1 ) {
			throw new InvalidArgumentException( 'Owner identity is invalid.' );
		}
	}
}
