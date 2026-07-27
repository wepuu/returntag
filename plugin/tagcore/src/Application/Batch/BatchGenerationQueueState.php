<?php
/**
 * Administrative Batch generation queue states.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

/**
 * Privacy-safe operational states exposed by the Batch progress query.
 */
enum BatchGenerationQueueState: string {
	case IDLE            = 'idle';
	case SCHEDULED       = 'scheduled';
	case RUNNING         = 'running';
	case NEEDS_ATTENTION = 'needs_attention';
	case COMPLETE        = 'complete';
	case UNAVAILABLE     = 'unavailable';
}
