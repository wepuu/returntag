<?php
/**
 * TagCore administrative capabilities.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

/**
 * Canonical capability names used by administrative adapters.
 */
final class Capability {
	public const MANAGE_RETURNTAG               = 'manage_returntag';
	public const MANAGE_BATCHES                 = 'manage_returntag_batches';
	public const MANAGE_TAGS                    = 'manage_returntag_tags';
	public const MANAGE_TAG_LIFECYCLE           = 'manage_returntag_tag_lifecycle';
	public const MANAGE_DISPUTES                = 'manage_returntag_disputes';
	public const MANAGE_FINDER_REPORT_DECISIONS = 'manage_returntag_finder_report_decisions';
	public const VIEW_USERS                     = 'view_returntag_users';
	public const VIEW_AUDIT_LOGS                = 'view_returntag_audit_logs';

	/**
	 * Static constants only.
	 */
	private function __construct() {
	}
}
