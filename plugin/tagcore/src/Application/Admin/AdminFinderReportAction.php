<?php
/**
 * Administrator Finder Report review actions.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

/** Canonical internal mutation route names. */
enum AdminFinderReportAction: string {
	case PLACE_HOLD        = 'place-hold';
	case RELEASE_HOLD      = 'release-hold';
	case RESOLVE_NO_ACTION = 'resolve-no-action';
	case BLOCK             = 'block';
}
