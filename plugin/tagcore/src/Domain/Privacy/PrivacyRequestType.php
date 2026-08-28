<?php
/**
 * Canonical privacy request types.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\Privacy;

/** Identifies the only approved phase-one privacy request types. */
enum PrivacyRequestType: string {
	case EXPORT  = 'export';
	case ERASURE = 'erasure';
}
