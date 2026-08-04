<?php
/**
 * Bounded Finder evidence source bytes.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use InvalidArgumentException;

/**
 * Holds one source image without trusting a filename or client MIME value.
 */
final readonly class FinderEvidenceSource {
	public const MAXIMUM_BYTES = 8388608;

	/**
	 * Create one bounded source value.
	 *
	 * @param string $bytes Untrusted upload bytes.
	 * @throws InvalidArgumentException When bytes are empty or exceed 8 MiB.
	 */
	public function __construct( public string $bytes ) {
		$length = strlen( $this->bytes );

		if ( 0 === $length || $length > self::MAXIMUM_BYTES ) {
			throw new InvalidArgumentException( 'Finder evidence source is invalid.' );
		}
	}
}
