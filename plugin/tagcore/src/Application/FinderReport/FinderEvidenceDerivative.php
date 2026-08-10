<?php
/**
 * Metadata-free Finder evidence derivative.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDigest;

/**
 * Couples safe JPEG bytes with integrity, dimensions, and purpose limits.
 */
final readonly class FinderEvidenceDerivative {
	public const MIME = 'image/jpeg';

	/**
	 * Create a controlled derivative.
	 *
	 * @param string      $bytes Re-encoded metadata-free JPEG bytes.
	 * @param MediaDigest $sha256 Integrity digest of the bytes.
	 * @param int         $width Pixel width.
	 * @param int         $height Pixel height.
	 * @param int         $maximum_edge Approved maximum edge.
	 * @param int|null    $maximum_bytes Optional byte limit.
	 * @throws InvalidArgumentException When metadata exceeds approved bounds.
	 */
	private function __construct(
		public string $bytes,
		public MediaDigest $sha256,
		public int $width,
		public int $height,
		int $maximum_edge,
		?int $maximum_bytes
	) {
		$byte_count = strlen( $this->bytes );

		if (
			0 === $byte_count
			|| $this->width < 1
			|| $this->height < 1
			|| max( $this->width, $this->height ) > $maximum_edge
			|| ( null !== $maximum_bytes && $byte_count > $maximum_bytes )
			|| ! hash_equals( $this->sha256->value, hash( 'sha256', $this->bytes ) )
		) {
			throw new InvalidArgumentException( 'Finder evidence derivative is invalid.' );
		}
	}

	/**
	 * Build a review derivative with a maximum 1600-pixel edge.
	 *
	 * @param string $bytes Re-encoded JPEG bytes.
	 * @param int    $width Pixel width.
	 * @param int    $height Pixel height.
	 */
	public static function review( string $bytes, int $width, int $height ): self {
		return new self( $bytes, MediaDigest::from_digest( hash( 'sha256', $bytes ) ), $width, $height, 1600, null );
	}

	/**
	 * Build an email derivative with an 800-pixel and 200-KiB limit.
	 *
	 * @param string $bytes Re-encoded JPEG bytes.
	 * @param int    $width Pixel width.
	 * @param int    $height Pixel height.
	 */
	public static function email( string $bytes, int $width, int $height ): self {
		return new self( $bytes, MediaDigest::from_digest( hash( 'sha256', $bytes ) ), $width, $height, 800, 204800 );
	}

	/** Return the encoded byte count. */
	public function byte_count(): int {
		return strlen( $this->bytes );
	}
}
