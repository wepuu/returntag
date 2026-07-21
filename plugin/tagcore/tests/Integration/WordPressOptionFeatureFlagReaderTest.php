<?php
/**
 * WordPress integration coverage for operational feature flags.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Infrastructure\WordPressOptionFeatureFlagReader;
use WP_UnitTestCase;

/**
 * Verifies fail-closed feature flag reads through the Options API.
 */
final class WordPressOptionFeatureFlagReaderTest extends WP_UnitTestCase {
	/**
	 * Remove test options before each assertion group.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->deleteFeatureFlagOptions();
	}

	/**
	 * Remove test options after each assertion group.
	 */
	protected function tearDown(): void {
		$this->deleteFeatureFlagOptions();

		parent::tearDown();
	}

	/**
	 * Missing options must fail closed.
	 */
	public function test_missing_options_are_disabled(): void {
		$reader = new WordPressOptionFeatureFlagReader();

		foreach ( FeatureFlag::cases() as $feature_flag ) {
			self::assertFalse( $reader->is_enabled( $feature_flag ) );
		}
	}

	/**
	 * Every canonical enabled representation enables a flag.
	 */
	public function test_canonical_enabled_values_are_enabled(): void {
		$reader       = new WordPressOptionFeatureFlagReader();
		$feature_flag = FeatureFlag::EMAIL_DISPATCH;
		$filter_name  = 'pre_option_' . $feature_flag->value;

		foreach ( array( true, 1, '1' ) as $enabled_value ) {
			$filter = static fn (): bool|int|string => $enabled_value;
			add_filter( $filter_name, $filter );

			try {
				self::assertTrue( $reader->is_enabled( $feature_flag ) );
			} finally {
				remove_filter( $filter_name, $filter );
			}
		}
	}

	/**
	 * Ambiguous values must not accidentally enable a flag.
	 */
	public function test_noncanonical_values_are_disabled(): void {
		$reader       = new WordPressOptionFeatureFlagReader();
		$feature_flag = FeatureFlag::FINDER_CONTACT;

		update_option( $feature_flag->value, 'yes' );

		self::assertFalse( $reader->is_enabled( $feature_flag ) );
	}

	/**
	 * Delete every global feature flag option from the test database.
	 */
	private function deleteFeatureFlagOptions(): void {
		foreach ( FeatureFlag::cases() as $feature_flag ) {
			delete_option( $feature_flag->value );
		}
	}
}
