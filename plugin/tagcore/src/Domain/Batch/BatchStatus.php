<?php
/**
 * Manufacturing batch lifecycle values.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\Batch;

/**
 * Canonical persisted batch states.
 */
enum BatchStatus: string {
	case DRAFT      = 'draft';
	case GENERATING = 'generating';
	case GENERATED  = 'generated';
	case EXPORTED   = 'exported';
	case RELEASED   = 'released';
	case SUSPENDED  = 'suspended';
	case VOIDED     = 'voided';
}
