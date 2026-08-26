<?php
/**
 * Email delivery convergence policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Email;

use DateTimeImmutable;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;

/** Prevents duplicate or out-of-order provider events from regressing state. */
final class EmailDeliveryTransitionPolicy {
	/**
	 * Return whether the candidate event may update the current projection.
	 *
	 * @param DeliveryStatus         $current Current canonical state.
	 * @param DateTimeImmutable|null $current_event_at Current provider event time.
	 * @param DeliveryStatus         $candidate Candidate canonical state.
	 * @param DateTimeImmutable      $candidate_event_at Candidate provider event time.
	 */
	public function allows(
		DeliveryStatus $current,
		?DateTimeImmutable $current_event_at,
		DeliveryStatus $candidate,
		DateTimeImmutable $candidate_event_at
	): bool {
		if ( null !== $current_event_at && $candidate_event_at < $current_event_at ) {
			return false;
		}

		if ( $current === $candidate ) {
			return true;
		}

		if ( in_array( $current, array( DeliveryStatus::BOUNCED, DeliveryStatus::COMPLAINED, DeliveryStatus::FAILED ), true ) ) {
			return false;
		}

		if ( DeliveryStatus::QUEUED === $current ) {
			return true;
		}
		if ( DeliveryStatus::SENT === $current ) {
			return DeliveryStatus::QUEUED !== $candidate;
		}
		if ( DeliveryStatus::DEFERRED === $current ) {
			return in_array( $candidate, array( DeliveryStatus::DELIVERED, DeliveryStatus::BOUNCED, DeliveryStatus::COMPLAINED, DeliveryStatus::FAILED ), true );
		}
		return in_array( $candidate, array( DeliveryStatus::BOUNCED, DeliveryStatus::COMPLAINED, DeliveryStatus::FAILED ), true );
	}
}
