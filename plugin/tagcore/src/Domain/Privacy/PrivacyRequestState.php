<?php
/**
 * Canonical privacy request states.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\Privacy;

/** Tracks resumable request orchestration without storing private payloads. */
enum PrivacyRequestState: string {
	case QUEUED          = 'queued';
	case PROCESSING      = 'processing';
	case ACTION_REQUIRED = 'action_required';
	case COMPLETED       = 'completed';
	case FAILED          = 'failed';
}
