<?php
/**
 * RT-301 public response policy tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\PublicSite;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\PublicSite\PublicTagResponsePolicy;

/**
 * Verifies the route fails closed without exposing Tag state.
 */
final class PublicTagResponsePolicyTest extends TestCase {
	/**
	 * Read-only methods receive the temporary-unavailable response.
	 *
	 * @dataProvider read_method_provider
	 *
	 * @param string $method Supported HTTP method.
	 */
	public function test_read_methods_are_temporarily_unavailable( string $method ): void {
		$policy  = new PublicTagResponsePolicy();
		$headers = $policy->headers_for_method( $method );

		self::assertSame( 503, $policy->status_for_method( $method ) );
		self::assertSame( 'no-store, private', $headers['Cache-Control'] );
		self::assertSame( 'no-cache', $headers['Pragma'] );
		self::assertSame( 'no-referrer', $headers['Referrer-Policy'] );
		self::assertSame( 'nosniff', $headers['X-Content-Type-Options'] );
		self::assertSame( 'noindex, nofollow, noarchive', $headers['X-Robots-Tag'] );
		self::assertArrayNotHasKey( 'Allow', $headers );
	}

	/**
	 * Unsupported methods are rejected with an explicit allow-list.
	 */
	public function test_mutating_methods_are_rejected(): void {
		$policy  = new PublicTagResponsePolicy();
		$headers = $policy->headers_for_method( 'POST' );

		self::assertSame( 405, $policy->status_for_method( 'POST' ) );
		self::assertSame( 'GET, HEAD', $headers['Allow'] );
	}

	/**
	 * Return supported read methods in representative casing.
	 *
	 * @return array<string, array{string}>
	 */
	public function read_method_provider(): array {
		return array(
			'get'        => array( 'GET' ),
			'head'       => array( 'HEAD' ),
			'lower-case' => array( 'get' ),
		);
	}
}
