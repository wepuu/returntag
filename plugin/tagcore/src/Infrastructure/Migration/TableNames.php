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

	/**
	 * Return the one-time authentication challenges table name.
	 */
	public function auth_challenges(): string {

		return $this->wordpress_prefix . 'returntag_auth_challenges';
	}

	/**
	 * Return the privacy-preserving finder conversations table name.
	 */
	public function conversations(): string {

		return $this->wordpress_prefix . 'returntag_conversations';
	}

	/**
	 * Return the encrypted conversation messages table name.
	 */
	public function messages(): string {

		return $this->wordpress_prefix . 'returntag_messages';
	}

	/**
	 * Return the hashed conversation access tokens table name.
	 */
	public function access_tokens(): string {

		return $this->wordpress_prefix . 'returntag_access_tokens';
	}

	/**
	 * Return the privacy-safe business audit events table name.
	 */
	public function events(): string {

		return $this->wordpress_prefix . 'returntag_events';
	}
}
