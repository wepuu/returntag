<?php
/**
 * Finder evidence safety availability port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

/** Prevents intake when no approved reviewer can run. */
interface FinderEvidenceSafetyAvailability {
	/** Whether the approved safety control is configured and healthy enough for intake. */
	public function is_available(): bool;
}
