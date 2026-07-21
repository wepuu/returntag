<?php
/**
 * WordPress operational logging adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure;

use Closure;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use ReturnTag\TagCore\Application\ApplicationLogger;
use ReturnTag\TagCore\Application\LogContextSanitizer;
use Stringable;

/**
 * Writes sanitized single-line JSON records to the PHP error log.
 */
final class WordPressErrorLogLogger extends AbstractLogger implements ApplicationLogger {
	/**
	 * Supported PSR-3 levels.
	 *
	 * @var list<string>
	 */
	private const LEVELS = array(
		LogLevel::EMERGENCY,
		LogLevel::ALERT,
		LogLevel::CRITICAL,
		LogLevel::ERROR,
		LogLevel::WARNING,
		LogLevel::NOTICE,
		LogLevel::INFO,
		LogLevel::DEBUG,
	);

	/**
	 * Sanitizes messages and context before encoding.
	 *
	 * @var LogContextSanitizer
	 */
	private LogContextSanitizer $sanitizer;

	/**
	 * Whether emission is explicitly enabled.
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Final output sink.
	 *
	 * @var Closure(string): void
	 */
	private Closure $writer;

	/**
	 * Create an operational logger without producing side effects.
	 *
	 * @param LogContextSanitizer $sanitizer Message and context sanitizer.
	 * @param bool                $enabled   Whether emission is enabled.
	 * @param ?Closure            $writer    Optional output sink.
	 * @phpstan-param (Closure(string): void)|null $writer
	 */
	public function __construct(
		LogContextSanitizer $sanitizer,
		bool $enabled = false,
		?Closure $writer = null
	) {
		$this->sanitizer = $sanitizer;
		$this->enabled   = $enabled;
		$this->writer    = $writer ?? static function ( string $line ): void {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- This adapter intentionally targets the configured PHP error log.
			error_log( $line );
		};
	}

	/**
	 * Write a sanitized PSR-3 record when explicitly enabled.
	 *
	 * @param mixed                   $level   PSR-3 log level.
	 * @param string|Stringable       $message Log message.
	 * @param array<array-key, mixed> $context Structured context.
	 * @return void
	 *
	 * @throws InvalidArgumentException When the log level is invalid.
	 */
	public function log( $level, string|Stringable $message, array $context = array() ): void {
		if ( ! is_string( $level ) || ! in_array( $level, self::LEVELS, true ) ) {
			throw new InvalidArgumentException( 'The log level must be a valid PSR-3 level.' );
		}

		if ( ! $this->enabled ) {
			return;
		}

		$payload = array(
			'channel' => 'tagcore',
			'level'   => $level,
			'message' => $this->sanitizer->sanitize_message( $message ),
			'context' => $this->sanitizer->sanitize_context( $context ),
		);
		$encoded = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );

		if ( ! is_string( $encoded ) ) {
			$encoded = '{"channel":"tagcore","level":"' . $level . '","message":"[encoding-failed]","context":{}}';
		}

		( $this->writer )( '[TagCore] ' . $encoded );
	}
}
