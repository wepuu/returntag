<?php
/**
 * Finder email form presentation states.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

enum FinderEmailFormState: string {
	case READY     = 'ready';
	case CODE_SENT = 'code_sent';
	case VERIFIED  = 'verified';
	case INVALID   = 'invalid';
	case ERROR     = 'error';
}
