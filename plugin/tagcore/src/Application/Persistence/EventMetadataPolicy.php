<?php
/**
 * Event metadata allowlist contract.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence;

/**
 * Supplies approved metadata keys for one event classification.
 */
interface EventMetadataPolicy {
	/**
	 * Return the approved keys for an event type.
	 *
	 * @param string $event_type Canonical event type.
	 * @return list<string>
	 */
	public function allowed_keys( string $event_type ): array;
}
