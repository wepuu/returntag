<?php
/**
 * Finder Report Event identity policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use ReturnTag\TagCore\Application\Persistence\EventIdentityPolicy;

/** Allows only metadata-free internal Finder Report lifecycle Events. */
final class FinderReportEventIdentityPolicy implements EventIdentityPolicy {
	/**
	 * Permit only metadata-free system lifecycle Events.
	 *
	 * @param string      $event_type Event name.
	 * @param string      $actor_type Actor class.
	 * @param int|null    $actor_id Optional actor identifier.
	 * @param string      $target_type Target class.
	 * @param string      $target_id Target identifier.
	 * @param string|null $correlation_id Optional correlation identifier.
	 */
	public function allows(
		string $event_type,
		string $actor_type,
		?int $actor_id,
		string $target_type,
		string $target_id,
		?string $correlation_id
	): bool {
		return in_array(
			$event_type,
			array( 'finder_report_submitted', 'finder_report_evidence_ready', 'finder_report_blocked', 'finder_report_expired', 'finder_conversation_opened' ),
			true
		)
			&& 'system' === $actor_type
			&& null === $actor_id
			&& 'finder_report' === $target_type
			&& ctype_digit( $target_id )
			&& null === $correlation_id;
	}
}
