<?php
/**
 * Metadata-free RT-328 audit event identity policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

use ReturnTag\TagCore\Application\Persistence\EventIdentityPolicy;

/** Restricts review Events to an operator and numeric Report target. */
final class AdminFinderReportDecisionEventIdentityPolicy implements EventIdentityPolicy {
	private const EVENTS = array( 'finder_evidence_hold_placed', 'finder_evidence_hold_released', 'finder_report_review_no_action', 'finder_report_blocked' );
	/**
	 * Determine whether the Event identity is permitted.
	 *
	 * @param string      $event_type Canonical Event type.
	 * @param string      $actor_type Canonical actor type.
	 * @param int|null    $actor_id Nullable actor identity.
	 * @param string      $target_type Canonical target type.
	 * @param string      $target_id Target identity.
	 * @param string|null $correlation_id Nullable correlation identity.
	 */
	public function allows( string $event_type, string $actor_type, ?int $actor_id, string $target_type, string $target_id, ?string $correlation_id ): bool {
		return in_array( $event_type, self::EVENTS, true ) && 'user' === $actor_type && null !== $actor_id && $actor_id > 0 && 'finder_report' === $target_type && 1 === preg_match( '/^[1-9][0-9]*$/D', $target_id ) && null === $correlation_id;
	}
}
