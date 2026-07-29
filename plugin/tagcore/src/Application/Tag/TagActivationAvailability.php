<?php
/**
 * Administrative Tag activation availability values.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

/**
 * Read-only, non-persisted reasons describing whether first activation is available.
 */
enum TagActivationAvailability: string {
	case ELIGIBLE                     = 'eligible';
	case AWAITING_RELEASE             = 'awaiting_release';
	case PAUSED_GLOBALLY              = 'paused_globally';
	case BLOCKED_BATCH_CONTROL        = 'blocked_batch_control';
	case BLOCKED_BATCH_SUSPENDED      = 'blocked_batch_suspended';
	case BLOCKED_BATCH_VOIDED         = 'blocked_batch_voided';
	case BLOCKED_TAG_SUSPENDED        = 'blocked_tag_suspended';
	case BLOCKED_TAG_RETIRED          = 'blocked_tag_retired';
	case EXISTING_ACTIVATION_RETAINED = 'existing_activation_retained';
	case DATA_INCONSISTENT            = 'data_inconsistent';
}
