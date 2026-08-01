<?php
/**
 * Manual Tag entry browser boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use ReturnTag\TagCore\Application\PublicTag\ManualTagEntryResultState;
use ReturnTag\TagCore\Application\PublicTag\SubmitManualTagEntry;
use ReturnTag\TagCore\Infrastructure\Security\WordPressPublicRequestHasher;
use Throwable;

/**
 * Validates one anonymous same-site manual-entry request.
 */
final readonly class ManualTagEntryFormHandler {
	public const NONCE_ACTION = 'returntag_submit_manual_tag_entry';

	public const NONCE_FIELD = 'returntag_tag_entry_nonce';

	public const ACTION_FIELD = 'returntag_tag_entry_action';

	public const SUBMIT_ACTION = 'continue';

	public const TAG_ID_FIELD = 'returntag_tag_id';

	/**
	 * Create the public form adapter.
	 *
	 * @param SubmitManualTagEntry         $submissions Application use case.
	 * @param PublicFormRequestGuard       $request_guard Shared browser request guard.
	 * @param WordPressPublicRequestHasher $hasher Privacy-safe request hasher.
	 */
	public function __construct(
		private SubmitManualTagEntry $submissions,
		private PublicFormRequestGuard $request_guard,
		private WordPressPublicRequestHasher $hasher
	) {
	}

	/**
	 * Validate and submit the current bounded POST request.
	 */
	public function submit(): ManualTagEntrySubmission {
		if (
			self::SUBMIT_ACTION !== $this->request_guard->post_string( self::ACTION_FIELD, 32 )
			|| ! $this->request_guard->is_same_site()
			|| ! $this->request_guard->valid_nonce( self::NONCE_FIELD, self::NONCE_ACTION )
		) {
			return new ManualTagEntrySubmission( ManualTagEntryFormState::FORBIDDEN );
		}

		try {
			$result = $this->submissions->execute(
				$this->request_guard->post_string( self::TAG_ID_FIELD, 64 ),
				$this->hasher->ip_lookup( $this->request_guard->direct_peer_ip() )
			);
		} catch ( Throwable ) {
			return new ManualTagEntrySubmission( ManualTagEntryFormState::UNAVAILABLE );
		}

		return match ( $result->state ) {
			ManualTagEntryResultState::ACCEPTED => new ManualTagEntrySubmission( ManualTagEntryFormState::READY, $result->tag_id ),
			ManualTagEntryResultState::INVALID => new ManualTagEntrySubmission( ManualTagEntryFormState::INVALID ),
			ManualTagEntryResultState::THROTTLED => new ManualTagEntrySubmission( ManualTagEntryFormState::THROTTLED ),
		};
	}
}
