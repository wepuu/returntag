<?php
/**
 * RT-301 public response policy tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\PublicSite;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPageState;
use ReturnTag\TagCore\PublicSite\PublicTagResponsePolicy;

/**
 * Verifies public state HTTP semantics and privacy controls.
 */
final class PublicTagResponsePolicyTest extends TestCase {
	/**
	 * Read-only methods map derived states to bounded statuses.
	 *
	 * @dataProvider read_state_provider
	 *
	 * @param string             $method Supported HTTP method.
	 * @param PublicTagPageState $state Derived page state.
	 * @param int                $expected Expected response status.
	 */
	public function test_read_methods_map_page_state(
		string $method,
		PublicTagPageState $state,
		int $expected
	): void {
		$policy  = new PublicTagResponsePolicy();
		$headers = $policy->headers_for_method( $method );

		self::assertSame( $expected, $policy->status_for( $method, $state ) );
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

		self::assertSame( 405, $policy->status_for( 'POST', PublicTagPageState::FINDER_ENTRY ) );
		self::assertSame( 'GET, HEAD', $headers['Allow'] );
	}

	/**
	 * Return representative state and method combinations.
	 *
	 * @return array<string, array{string, PublicTagPageState, int}>
	 */
	public function read_state_provider(): array {
		return array(
			'invalid'             => array( 'GET', PublicTagPageState::INVALID, 404 ),
			'service unavailable' => array( 'HEAD', PublicTagPageState::SERVICE_UNAVAILABLE, 503 ),
			'activation entry'    => array( 'get', PublicTagPageState::ACTIVATION_ENTRY, 200 ),
			'owner entry'         => array( 'GET', PublicTagPageState::OWNER_ENTRY, 200 ),
			'finder entry'        => array( 'GET', PublicTagPageState::FINDER_ENTRY, 200 ),
			'suspended'           => array( 'GET', PublicTagPageState::SUSPENDED, 200 ),
			'retired'             => array( 'GET', PublicTagPageState::RETIRED, 200 ),
		);
	}
}
