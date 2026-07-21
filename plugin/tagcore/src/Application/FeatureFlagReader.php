<?php
/**
 * Feature flag read contract.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application;

/**
 * Reads operational controls without exposing a platform implementation.
 */
interface FeatureFlagReader {
	/**
	 * Determine whether an operational control is enabled.
	 *
	 * @param FeatureFlag $feature_flag Operational control to read.
	 * @return bool Whether the control is enabled.
	 */
	public function is_enabled( FeatureFlag $feature_flag ): bool;
}
