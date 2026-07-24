<?php
/**
 * Default event metadata policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence;

/**
 * Fails closed until a later product ticket approves event-specific keys.
 */
final class DenyAllEventMetadataPolicy implements EventMetadataPolicy {
	/**
	 * Return no approved keys.
	 *
	 * @param string $event_type Canonical event type.
	 * @return list<string>
	 */
	public function allowed_keys( string $event_type ): array {
		unset( $event_type );

		return array();
	}
}
