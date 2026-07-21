<?php
/**
 * WordPress option adapter for ReturnTag feature flags.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure;

use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;

/**
 * Reads site-scoped feature flags through the WordPress Options API.
 */
final class WordPressOptionFeatureFlagReader implements FeatureFlagReader {
	/**
	 * Determine whether a feature flag contains a canonical enabled value.
	 *
	 * @param FeatureFlag $feature_flag Operational control to read.
	 * @return bool Whether the control is enabled.
	 */
	public function is_enabled( FeatureFlag $feature_flag ): bool {
		$value = get_option( $feature_flag->value, false );

		return true === $value || 1 === $value || '1' === $value;
	}
}
