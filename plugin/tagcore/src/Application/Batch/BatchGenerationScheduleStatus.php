<?php
/**
 * Batch generation schedule outcomes.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

/**
 * Describes whether a background action was created or already existed.
 */
enum BatchGenerationScheduleStatus: string {
	case QUEUED            = 'queued';
	case ALREADY_SCHEDULED = 'already_scheduled';
}
