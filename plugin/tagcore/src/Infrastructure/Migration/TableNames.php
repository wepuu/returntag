<?php
/**
 * Trusted ReturnTag table-name mapping.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

/**
 * Builds identifiers exclusively from the WordPress-configured table prefix.
 */
final class TableNames {
	/**
	 * Create the table-name mapping.
	 *
	 * @param string $wordpress_prefix Trusted prefix from the active wpdb instance.
	 */
	public function __construct( private readonly string $wordpress_prefix ) {
	}

	/**
	 * Return the manufacturing batches table name.
	 */
	public function batches(): string {

		return $this->wordpress_prefix . 'returntag_batches';
	}

	/**
	 * Return the physical tags table name.
	 */
	public function tags(): string {

		return $this->wordpress_prefix . 'returntag_tags';
	}

	/**
	 * Return the immutable batch export audit table name.
	 */
	public function batch_exports(): string {

		return $this->wordpress_prefix . 'returntag_batch_exports';
	}
}
