<?php
/**
 * Closed Owner Account routes.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

enum AccountRoute: string {
	case SIGN_IN       = 'sign-in';
	case OVERVIEW      = 'overview';
	case TAG           = 'tag';
	case CONVERSATIONS = 'conversations';
}
