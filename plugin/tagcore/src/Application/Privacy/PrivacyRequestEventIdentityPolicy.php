<?php
/**
 * Metadata-free privacy request Event identities.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Privacy;

use ReturnTag\TagCore\Application\Persistence\EventIdentityPolicy;

/** Restricts privacy Events to an internal numeric request target. */
final class PrivacyRequestEventIdentityPolicy implements EventIdentityPolicy {
	private const EVENTS = array(
		'privacy_request_queued',
		'privacy_request_processing',
		'privacy_request_action_required',
		'privacy_request_failed',
		'privacy_request_completed',
	);

	/**
	 * Determine whether one privacy Event identity is approved.
	 *
	 * @param string      $event_type Event classification.
	 * @param string      $actor_type Actor classification.
	 * @param int|null    $actor_id Optional internal actor ID.
	 * @param string      $target_type Target classification.
	 * @param string      $target_id Internal numeric request ID.
	 * @param string|null $correlation_id Optional correlation ID.
	 */
	public function allows( string $event_type, string $actor_type, ?int $actor_id, string $target_type, string $target_id, ?string $correlation_id ): bool {
		if ( ! in_array( $event_type, self::EVENTS, true ) || 'privacy_request' !== $target_type || ! ctype_digit( $target_id ) || '0' === $target_id || null !== $correlation_id ) {
			return false;
		}

		if ( 'privacy_request_queued' === $event_type ) {
			return ( 'user' === $actor_type && null !== $actor_id && $actor_id > 0 )
				|| ( in_array( $actor_type, array( 'finder', 'system' ), true ) && null === $actor_id );
		}

		return 'system' === $actor_type && null === $actor_id;
	}
}
