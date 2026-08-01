<?php
/**
 * Manual Tag entry response-policy tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\PublicSite;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\PublicSite\ManualTagEntryFormState;
use ReturnTag\TagCore\PublicSite\ManualTagEntryResponsePolicy;

/**
 * Verifies manual-entry HTTP status and header policy.
 */
final class ManualTagEntryResponsePolicyTest extends TestCase {
	/**
	 * Form states map to bounded response statuses.
	 *
	 * @param ManualTagEntryFormState $state Safe form state.
	 * @param int                     $status Expected status.
	 *
	 * @dataProvider state_provider
	 */
	public function test_maps_bounded_form_states( ManualTagEntryFormState $state, int $status ): void {
		$policy = new ManualTagEntryResponsePolicy();

		self::assertSame( $status, $policy->status_for( 'POST', $state ) );
		self::assertSame( 'no-store, private', $policy->headers_for( 'POST', $state )['Cache-Control'] );
		self::assertSame( 'no-referrer', $policy->headers_for( 'POST', $state )['Referrer-Policy'] );
	}

	/**
	 * Unsupported methods and throttling return explicit headers.
	 */
	public function test_rejects_unsupported_methods_and_bounds_retry_header(): void {
		$policy = new ManualTagEntryResponsePolicy();

		self::assertSame( 405, $policy->status_for( 'PUT', ManualTagEntryFormState::READY ) );
		self::assertSame( 'GET, HEAD, POST', $policy->headers_for( 'PUT', ManualTagEntryFormState::READY )['Allow'] );
		self::assertSame( '60', $policy->headers_for( 'POST', ManualTagEntryFormState::THROTTLED )['Retry-After'] );
	}

	/**
	 * Provide form-state status cases.
	 *
	 * @return array<string, array{ManualTagEntryFormState, int}>
	 */
	public function state_provider(): array {
		return array(
			'ready'       => array( ManualTagEntryFormState::READY, 200 ),
			'invalid'     => array( ManualTagEntryFormState::INVALID, 422 ),
			'forbidden'   => array( ManualTagEntryFormState::FORBIDDEN, 403 ),
			'throttled'   => array( ManualTagEntryFormState::THROTTLED, 429 ),
			'unavailable' => array( ManualTagEntryFormState::UNAVAILABLE, 503 ),
		);
	}
}
