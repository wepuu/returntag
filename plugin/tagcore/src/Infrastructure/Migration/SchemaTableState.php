<?php
/**
 * Schema table inspection states.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

/**
 * Classifies whether dbDelta may safely create or repair a table.
 */
enum SchemaTableState {
	case ABSENT;
	case EXACT;
	case REPAIRABLE_INDEX_DRIFT;
	case INCOMPATIBLE;
}
