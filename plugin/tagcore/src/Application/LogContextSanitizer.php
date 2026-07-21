<?php
/**
 * Log context sanitization contract.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application;

use Stringable;

/**
 * Sanitizes operational log messages and structured context.
 */
interface LogContextSanitizer {
	/**
	 * Sanitize an operational log message.
	 *
	 * @param string|Stringable $message Log message.
	 * @return string Sanitized message.
	 */
	public function sanitize_message( string|Stringable $message ): string;

	/**
	 * Sanitize structured operational log context.
	 *
	 * @param array<array-key, mixed> $context Log context.
	 * @return array<array-key, mixed> Sanitized context.
	 */
	public function sanitize_context( array $context ): array;
}
