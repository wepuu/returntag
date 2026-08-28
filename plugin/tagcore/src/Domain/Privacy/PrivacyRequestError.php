<?php
/**
 * Fixed privacy request failure codes.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Domain\Privacy;

/** Keeps retryable failures privacy safe and provider neutral. */
enum PrivacyRequestError: string {
	case PROCESSING_ERROR = 'processing_error';
}
