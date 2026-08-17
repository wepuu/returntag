<?php
/**
 * Ephemeral processed evidence response.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

/** Keeps controlled bytes out of JSON serialization. */
final readonly class AdminEvidencePreview {
	/**
	 * Create an ephemeral binary response wrapper.
	 *
	 * @param string $bytes Processed Review derivative bytes.
	 */
	public function __construct( public string $bytes ) {
	}
}
