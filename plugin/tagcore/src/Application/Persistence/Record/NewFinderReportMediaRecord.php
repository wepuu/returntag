<?php
/**
 * New Finder Report private-media persistence data.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use DateTimeImmutable;
use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDerivative;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDigest;
use ReturnTag\TagCore\Application\Persistence\Value\PrivateMediaReferenceCiphertext;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceMime;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;

/**
 * Immutable private-media row data without object bytes or public URLs.
 */
final readonly class NewFinderReportMediaRecord {
	/**
	 * Create private-media persistence data.
	 *
	 * @param int                             $finder_report_id Parent report identifier.
	 * @param PrivateMediaReferenceCiphertext $object_reference_ciphertext Encrypted quarantine reference.
	 * @param string                          $encryption_key_id Non-secret key identifier.
	 * @param MediaDigest                     $content_sha256 Source integrity digest.
	 * @param FinderEvidenceMime              $source_mime Validated source MIME.
	 * @param int                             $source_byte_count Source byte count.
	 * @param int                             $source_width Source pixel width.
	 * @param int                             $source_height Source pixel height.
	 * @param MediaDerivative|null            $review_derivative Optional review derivative.
	 * @param MediaDerivative|null            $email_derivative Optional email derivative.
	 * @param FinderEvidenceStatus            $media_status Media lifecycle state.
	 * @param DateTimeImmutable               $retention_until UTC retention boundary.
	 * @param DateTimeImmutable               $created_at UTC creation time.
	 * @param DateTimeImmutable               $updated_at UTC update time.
	 * @throws InvalidArgumentException When source metadata exceeds the approved bounds.
	 */
	public function __construct(
		public int $finder_report_id,
		public PrivateMediaReferenceCiphertext $object_reference_ciphertext,
		public string $encryption_key_id,
		public MediaDigest $content_sha256,
		public FinderEvidenceMime $source_mime,
		public int $source_byte_count,
		public int $source_width,
		public int $source_height,
		public ?MediaDerivative $review_derivative,
		public ?MediaDerivative $email_derivative,
		public FinderEvidenceStatus $media_status,
		public DateTimeImmutable $retention_until,
		public DateTimeImmutable $created_at,
		public DateTimeImmutable $updated_at
	) {
		RecordValidator::positive_id( $this->finder_report_id, 'finder_report_id' );
		RecordValidator::ascii( $this->encryption_key_id, 64, 'encryption_key_id' );
		RecordValidator::positive_id( $this->source_byte_count, 'source_byte_count' );
		RecordValidator::positive_id( $this->source_width, 'source_width' );
		RecordValidator::positive_id( $this->source_height, 'source_height' );
		RecordValidator::utc( $this->retention_until, 'retention_until' );
		RecordValidator::utc( $this->created_at, 'created_at' );
		RecordValidator::utc( $this->updated_at, 'updated_at' );

		if (
			$this->source_byte_count > 8388608
			|| $this->source_width > 20000000
			|| $this->source_width > intdiv( 20000000, $this->source_height )
		) {
			throw new InvalidArgumentException( 'Persistence value is invalid.' );
		}
	}
}
