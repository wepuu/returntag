<?php
/**
 * Sensitive administration preview audit port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

use DateTimeImmutable;

/** Records metadata-free access to one Finder Report sensitive value. */
interface SensitivePreviewAudit {
	/**
	 * Record one successful reveal.
	 *
	 * @param string            $event_type Approved reveal event type.
	 * @param int               $operator_id WordPress operator User ID.
	 * @param int               $finder_report_id Finder Report identifier.
	 * @param DateTimeImmutable $occurred_at UTC event time.
	 */
	public function record( string $event_type, int $operator_id, int $finder_report_id, DateTimeImmutable $occurred_at ): void;
}
