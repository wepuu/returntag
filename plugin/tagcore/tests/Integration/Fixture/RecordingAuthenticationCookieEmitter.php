<?php
/**
 * Recording authentication cookie emitter.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration\Fixture;

use ReturnTag\TagCore\Infrastructure\Auth\AuthenticationCookieEmitter;
use ReturnTag\TagCore\Infrastructure\Auth\AuthenticationCookieOptions;

/**
 * Captures non-sensitive cookie metadata without emitting CLI headers.
 */
final class RecordingAuthenticationCookieEmitter implements AuthenticationCookieEmitter {
	/**
	 * Number of cookie-clearing requests.
	 *
	 * @var int
	 */
	public int $clear_count = 0;

	/**
	 * Captured non-sensitive cookie metadata.
	 *
	 * @var list<array{name: string, options: AuthenticationCookieOptions}>
	 */
	public array $writes = array();

	/**
	 * Create the test emitter.
	 *
	 * @param bool $write_result Whether every cookie write succeeds.
	 */
	public function __construct( private readonly bool $write_result = true ) {
	}

	/**
	 * Test responses always permit header work.
	 */
	public function headers_sent(): bool {
		return false;
	}

	/**
	 * Record one cookie-clearing request.
	 */
	public function clear_existing(): void {
		++$this->clear_count;
	}

	/**
	 * Record metadata while intentionally discarding the cookie value.
	 *
	 * @param string                      $name Cookie name.
	 * @param string                      $value WordPress-generated cookie value.
	 * @param AuthenticationCookieOptions $options Cookie options.
	 */
	public function write( string $name, string $value, AuthenticationCookieOptions $options ): bool {
		unset( $value );

		$this->writes[] = array(
			'name'    => $name,
			'options' => $options,
		);

		return $this->write_result;
	}
}
