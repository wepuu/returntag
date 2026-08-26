<?php
/**
 * Durable email dispatch start result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Email;

use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;

/** Reports whether a new provider attempt is permitted. */
final readonly class EmailDeliveryStart {
	/**
	 * Create one persisted start decision.
	 *
	 * @param int            $delivery_id Internal delivery identifier.
	 * @param DeliveryStatus $status Current canonical state.
	 * @param string|null    $provider_message_id Optional provider identifier.
	 * @param bool           $dispatch_allowed Whether one provider call is permitted.
	 */
	public function __construct(
		public int $delivery_id,
		public DeliveryStatus $status,
		public ?string $provider_message_id,
		public bool $dispatch_allowed
	) {}
}
