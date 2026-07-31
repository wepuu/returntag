<?php
/**
 * Render-ready Smart Tag setup guidance.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

/**
 * Contains translated static copy for the Smart Tag activation guide.
 */
final readonly class SmartTagGuideView {
	/**
	 * Create a render-ready static guide.
	 *
	 * @param string $title Guide heading.
	 * @param string $summary Parallel-system explanation.
	 * @param string $network_label Smart finding-network label.
	 * @param string $network_message Smart finding-network boundary.
	 * @param string $qr_label ReturnTag QR recovery label.
	 * @param string $qr_message ReturnTag QR recovery boundary.
	 * @param string $privacy_message Data and verification disclaimer.
	 */
	public function __construct(
		public string $title,
		public string $summary,
		public string $network_label,
		public string $network_message,
		public string $qr_label,
		public string $qr_message,
		public string $privacy_message
	) {
	}
}
