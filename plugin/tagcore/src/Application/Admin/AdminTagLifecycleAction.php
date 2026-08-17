<?php
/**
 * Administrator Tag lifecycle actions.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

/** Canonical high-risk actions exposed only to authorized administrators. */
enum AdminTagLifecycleAction: string {
	case SUSPEND        = 'suspend';
	case RETIRE         = 'retire';
	case REMOVE_OWNER   = 'remove-owner';
	case TRANSFER_OWNER = 'transfer-owner';
}
