<?php
/**
 * Transactional-message delivery values.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\Conversation;

/**
 * Canonical persisted delivery states.
 */
enum DeliveryStatus: string {
	case QUEUED     = 'queued';
	case SENT       = 'sent';
	case DELIVERED  = 'delivered';
	case DEFERRED   = 'deferred';
	case BOUNCED    = 'bounced';
	case COMPLAINED = 'complained';
	case FAILED     = 'failed';
}
