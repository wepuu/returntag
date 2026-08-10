<?php
/**
 * Public Finder Report form states.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

/** Privacy-safe outcomes rendered by the public form. */
enum FinderReportFormState: string {
	case READY           = 'ready';
	case INVALID_MESSAGE = 'invalid_message';
	case INVALID_PHOTO   = 'invalid_photo';
	case ERROR           = 'error';
	case ACCEPTED        = 'accepted';
}
