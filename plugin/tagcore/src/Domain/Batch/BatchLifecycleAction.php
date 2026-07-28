<?php
/**
 * Approved Batch lifecycle actions.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\Batch;

/**
 * Operator actions supported by RT-208.
 */
enum BatchLifecycleAction: string {
	case RELEASE = 'release';
	case SUSPEND = 'suspend';
	case VOID    = 'void';
}
