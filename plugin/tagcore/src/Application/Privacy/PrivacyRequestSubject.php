<?php
/**
 * Privacy-safe requester identity.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Privacy;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/** Carries no email address or other raw external identity. */
final readonly class PrivacyRequestSubject {
	/**
	 * Create one approved requester reference.
	 *
	 * @param string   $requester_type Fixed requester classification.
	 * @param int|null $user_id Optional WordPress user identifier.
	 * @param string   $requester_key Keyed, irreversible identity lookup digest.
	 * @throws InvalidArgumentException When the requester reference is invalid.
	 */
	public function __construct(
		public string $requester_type,
		public ?int $user_id,
		public string $requester_key
	) {
		if ( ! in_array( $this->requester_type, array( 'user', 'finder' ), true ) ) {
			throw new InvalidArgumentException( 'Privacy request subject is invalid.' );
		}

		if ( 'user' === $this->requester_type ) {
			if ( null === $this->user_id ) {
				throw new InvalidArgumentException( 'Privacy request subject is invalid.' );
			}
			RecordValidator::positive_id( $this->user_id, 'requester_user_id' );
		} elseif ( null !== $this->user_id ) {
			throw new InvalidArgumentException( 'Privacy request subject is invalid.' );
		}

		RecordValidator::digest( $this->requester_key, 'requester_key' );
	}
}
