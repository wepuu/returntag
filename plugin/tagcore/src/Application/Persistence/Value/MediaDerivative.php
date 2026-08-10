<?php
/**
 * Controlled private-media derivative metadata.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Value;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Keeps derivative reference, digest, dimensions, and size together.
 */
final readonly class MediaDerivative {
	/**
	 * Create one bounded derivative tuple.
	 *
	 * @param PrivateMediaReferenceCiphertext $reference_ciphertext Encrypted object reference.
	 * @param MediaDigest                     $sha256 Integrity digest.
	 * @param int                             $byte_count Encoded byte count.
	 * @param int                             $width Pixel width.
	 * @param int                             $height Pixel height.
	 * @param int                             $maximum_edge Approved maximum edge.
	 * @param int|null                        $maximum_bytes Optional byte limit.
	 * @throws InvalidArgumentException When the derivative exceeds its approved bounds.
	 */
	private function __construct(
		public PrivateMediaReferenceCiphertext $reference_ciphertext,
		public MediaDigest $sha256,
		public int $byte_count,
		public int $width,
		public int $height,
		int $maximum_edge,
		?int $maximum_bytes
	) {
		RecordValidator::positive_id( $this->byte_count, 'byte_count' );
		RecordValidator::positive_id( $this->width, 'width' );
		RecordValidator::positive_id( $this->height, 'height' );

		if ( max( $this->width, $this->height ) > $maximum_edge || ( null !== $maximum_bytes && $this->byte_count > $maximum_bytes ) ) {
			throw new InvalidArgumentException( 'Persistence value is invalid.' );
		}
	}

	/**
	 * Build the controlled review derivative.
	 *
	 * @param PrivateMediaReferenceCiphertext $reference_ciphertext Encrypted object reference.
	 * @param MediaDigest                     $sha256 Integrity digest.
	 * @param int                             $byte_count Encoded byte count.
	 * @param int                             $width Pixel width.
	 * @param int                             $height Pixel height.
	 */
	public static function review(
		PrivateMediaReferenceCiphertext $reference_ciphertext,
		MediaDigest $sha256,
		int $byte_count,
		int $width,
		int $height
	): self {
		return new self( $reference_ciphertext, $sha256, $byte_count, $width, $height, 1600, null );
	}

	/**
	 * Build the controlled inline-email derivative.
	 *
	 * @param PrivateMediaReferenceCiphertext $reference_ciphertext Encrypted object reference.
	 * @param MediaDigest                     $sha256 Integrity digest.
	 * @param int                             $byte_count Encoded byte count.
	 * @param int                             $width Pixel width.
	 * @param int                             $height Pixel height.
	 */
	public static function email(
		PrivateMediaReferenceCiphertext $reference_ciphertext,
		MediaDigest $sha256,
		int $byte_count,
		int $width,
		int $height
	): self {
		return new self( $reference_ciphertext, $sha256, $byte_count, $width, $height, 800, 204800 );
	}
}
