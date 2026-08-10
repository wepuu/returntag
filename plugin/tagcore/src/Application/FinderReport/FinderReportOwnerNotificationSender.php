<?php
/**
 * Finder Report Owner notification sender port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

/** Submits one privacy-minimized message without claiming delivery. */
interface FinderReportOwnerNotificationSender {
	/**
	 * Return true only when the configured mailer accepts the message.
	 *
	 * @param FinderReportOwnerNotificationEmail $email Privacy-minimized message.
	 */
	public function send( FinderReportOwnerNotificationEmail $email ): bool;
}
