<?php
/**
 * Current-Owner Conversation collection result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

/** Carries only a bounded list of privacy-minimized summaries. */
final readonly class OwnerConversationCollection {
	/**
	 * Create one collection outcome.
	 *
	 * @param OwnerConversationAccessState $state Closed access state.
	 * @param array                        $items Bounded summaries.
	 * @phpstan-param list<OwnerConversationSummary> $items
	 */
	public function __construct(
		public OwnerConversationAccessState $state,
		public array $items = array()
	) {
	}
}
