<?php
/**
 * Fixed feature flag reader fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture;

use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;

/**
 * Returns one deterministic global activation state.
 */
final readonly class FixedFeatureFlagReader implements FeatureFlagReader {
	/**
	 * Create the reader.
	 *
	 * @param bool $enabled Fixed result.
	 */
	public function __construct( private bool $enabled ) {
	}

	/**
	 * Return the fixed result.
	 *
	 * @param FeatureFlag $feature_flag Requested flag.
	 */
	public function is_enabled( FeatureFlag $feature_flag ): bool {
		unset( $feature_flag );

		return $this->enabled;
	}
}
