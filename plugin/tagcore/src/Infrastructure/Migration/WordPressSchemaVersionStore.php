<?php
/**
 * WordPress option-backed schema version store.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use RuntimeException;

/**
 * Persists the schema version as one non-autoloaded, site-scoped option.
 */
final class WordPressSchemaVersionStore implements SchemaVersionStore {
	public const OPTION_NAME = 'returntag_schema_version';

	/**
	 * Read a non-negative integer version, treating invalid storage as zero.
	 */
	public function current_version(): int {
		$value = get_option( self::OPTION_NAME, 0 );

		if ( is_int( $value ) && $value >= 0 ) {
			return $value;
		}

		if ( is_string( $value ) && ctype_digit( $value ) ) {
			return (int) $value;
		}

		return 0;
	}

	/**
	 * Persist a positive successfully verified version without autoloading it.
	 *
	 * @param int $version Applied migration version.
	 * @throws RuntimeException When the version is invalid or cannot be stored.
	 */
	public function mark_applied( int $version ): void {
		if ( $version <= 0 ) {
			throw new RuntimeException( 'Schema versions must be positive.' );
		}

		if ( ! update_option( self::OPTION_NAME, $version, false ) ) {
			throw new RuntimeException( 'The schema version could not be persisted.' );
		}
	}
}
