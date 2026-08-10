<?php
/**
 * Encrypted private-media object descriptor.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDigest;
use ReturnTag\TagCore\Application\Persistence\Value\PrivateMediaReferenceCiphertext;

/**
 * Describes one encrypted object without exposing its storage identifier.
 */
final readonly class PrivateMediaObject {
	/**
	 * Create one private object descriptor.
	 *
	 * @param PrivateMediaReferenceCiphertext $reference_ciphertext Encrypted object reference.
	 * @param string                          $encryption_key_id Non-secret key identifier.
	 * @param MediaDigest                     $sha256 Plaintext integrity digest.
	 * @param int                             $byte_count Plaintext byte count.
	 */
	public function __construct(
		public PrivateMediaReferenceCiphertext $reference_ciphertext,
		public string $encryption_key_id,
		public MediaDigest $sha256,
		public int $byte_count
	) {
		RecordValidator::ascii( $this->encryption_key_id, 64, 'encryption_key_id' );
		RecordValidator::positive_id( $this->byte_count, 'byte_count' );
	}
}
