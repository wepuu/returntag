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
	public const MANAGE_RETURNTAG = 'manage_returntag';
	public const MANAGE_BATCHES   = 'manage_returntag_batches';

	/**
	 * Static constants only.
	 */
	private function __construct() {
	}
}
