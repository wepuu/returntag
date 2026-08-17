<?php
/**
 * Administrator Tag lifecycle Event metadata policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

use ReturnTag\TagCore\Application\Persistence\EventMetadataPolicy;

/** Allows only state and internal Owner identifiers for RT-327 Events. */
final class AdminTagLifecycleEventMetadataPolicy implements EventMetadataPolicy {
	/**
	 * Return approved metadata keys for one lifecycle Event.
	 *
	 * @param string $event_type Event classification.
	 * @return list<string>
	 */
	public function allowed_keys( string $event_type ): array {
		return in_array( $event_type, array( 'tag_suspended', 'tag_retired', 'tag_owner_removed', 'tag_transferred' ), true )
			? array( 'before_status', 'after_status', 'before_owner_id', 'after_owner_id' )
			: array();
	}
}
