<?php
/**
 * Conversation relay Event identity policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Conversation;

use ReturnTag\TagCore\Application\Persistence\EventIdentityPolicy;

/** Allows only metadata-free internal relay lifecycle Events. */
final class ConversationRelayEventIdentityPolicy implements EventIdentityPolicy {
	/**
	 * Approve one metadata-free relay Event.
	 *
	 * @param string      $event_type Event type.
	 * @param string      $actor_type Actor type.
	 * @param int|null    $actor_id Actor identifier.
	 * @param string      $target_type Target type.
	 * @param string      $target_id Target identifier.
	 * @param string|null $correlation_id Correlation identifier.
	 */
	public function allows( string $event_type, string $actor_type, ?int $actor_id, string $target_type, string $target_id, ?string $correlation_id ): bool {
		return in_array( $event_type, array( 'finder_message_submitted', 'owner_reply_sent', 'conversation_closed', 'conversation_reported' ), true )
			&& 'system' === $actor_type
			&& null === $actor_id
			&& 'conversation' === $target_type
			&& ctype_digit( $target_id )
			&& null === $correlation_id;
	}
}
