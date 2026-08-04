<?php
/**
 * WordPress database Finder Report media Repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceConstraintViolationException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\Record\FinderReportMediaRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewFinderReportMediaRecord;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportMediaRepository;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDerivative;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDigest;
use ReturnTag\TagCore\Application\Persistence\Value\PrivateMediaReferenceCiphertext;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceMime;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Persists private evidence metadata without exposing object references.
 */
final class WpdbFinderReportMediaRepository implements FinderReportMediaRepository {
	/**
	 * Create the Repository.
	 *
	 * @param WpdbGateway           $gateway Database gateway.
	 * @param TableNames            $tables Trusted table names.
	 * @param DatabaseDateTimeCodec $dates UTC datetime codec.
	 */
	public function __construct(
		private readonly WpdbGateway $gateway,
		private readonly TableNames $tables,
		private readonly DatabaseDateTimeCodec $dates
	) {
	}

	/**
	 * Insert exactly one private-media row for a stored Finder Report.
	 *
	 * @param NewFinderReportMediaRecord $record New media data.
	 */
	public function insert( NewFinderReportMediaRecord $record ): FinderReportMediaRecord {
		$this->assert_report_exists( $record->finder_report_id );
		$review   = $record->review_derivative;
		$email    = $record->email_derivative;
		$media_id = $this->gateway->insert(
			$this->tables->finder_report_media(),
			array(
				'finder_report_id'            => $record->finder_report_id,
				'object_reference_ciphertext' => $record->object_reference_ciphertext->value,
				'encryption_key_id'           => $record->encryption_key_id,
				'content_sha256'              => $record->content_sha256->value,
				'source_mime'                 => $record->source_mime->value,
				'source_byte_count'           => $record->source_byte_count,
				'source_width'                => $record->source_width,
				'source_height'               => $record->source_height,
				'review_reference_ciphertext' => $review?->reference_ciphertext->value,
				'review_sha256'               => $review?->sha256->value,
				'review_byte_count'           => $review?->byte_count,
				'review_width'                => $review?->width,
				'review_height'               => $review?->height,
				'email_reference_ciphertext'  => $email?->reference_ciphertext->value,
				'email_sha256'                => $email?->sha256->value,
				'email_byte_count'            => $email?->byte_count,
				'email_width'                 => $email?->width,
				'email_height'                => $email?->height,
				'media_status'                => $record->media_status->value,
				'retention_until'             => $this->dates->format( $record->retention_until ),
				'created_at'                  => $this->dates->format( $record->created_at ),
				'updated_at'                  => $this->dates->format( $record->updated_at ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return new FinderReportMediaRecord( $media_id, $record );
	}

	/**
	 * Find the unique private-media row for one Finder Report.
	 *
	 * @param int $finder_report_id Parent report identifier.
	 */
	public function find_by_report_id( int $finder_report_id ): ?FinderReportMediaRecord {
		RecordValidator::positive_id( $finder_report_id, 'finder_report_id' );
		$row = $this->gateway->row(
			'SELECT * FROM %i WHERE finder_report_id = %d LIMIT 1',
			array( $this->tables->finder_report_media(), $finder_report_id )
		);

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Reject media that does not reference a stored Finder Report.
	 *
	 * @param int $finder_report_id Parent report identifier.
	 * @throws PersistenceConstraintViolationException When the report is absent.
	 */
	private function assert_report_exists( int $finder_report_id ): void {
		$row = $this->gateway->row(
			'SELECT finder_report_id FROM %i WHERE finder_report_id = %d LIMIT 1',
			array( $this->tables->finder_reports(), $finder_report_id )
		);

		if ( null === $row ) {
			throw new PersistenceConstraintViolationException( 'Referenced record is unavailable or inconsistent.' );
		}
	}

	/**
	 * Map one strict stored private-media row.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @throws PersistenceMappingException When stored data is invalid.
	 */
	private function hydrate( array $row ): FinderReportMediaRecord {
		try {
			return new FinderReportMediaRecord(
				StoredRow::positive_int( $row, 'finder_report_media_id' ),
				new NewFinderReportMediaRecord(
					StoredRow::positive_int( $row, 'finder_report_id' ),
					PrivateMediaReferenceCiphertext::from_storage( StoredRow::string( $row, 'object_reference_ciphertext' ) ),
					StoredRow::string( $row, 'encryption_key_id' ),
					MediaDigest::from_storage( StoredRow::string( $row, 'content_sha256' ) ),
					StoredRow::enum( $row, 'source_mime', FinderEvidenceMime::class ),
					StoredRow::positive_int( $row, 'source_byte_count' ),
					StoredRow::positive_int( $row, 'source_width' ),
					StoredRow::positive_int( $row, 'source_height' ),
					$this->hydrate_derivative( $row, 'review' ),
					$this->hydrate_derivative( $row, 'email' ),
					StoredRow::enum( $row, 'media_status', FinderEvidenceStatus::class ),
					$this->dates->parse( StoredRow::string( $row, 'retention_until' ) ),
					$this->dates->parse( StoredRow::string( $row, 'created_at' ) ),
					$this->dates->parse( StoredRow::string( $row, 'updated_at' ) )
				)
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Finder Report media record is invalid.' );
		}
	}

	/**
	 * Map an all-null or complete controlled derivative tuple.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @param string               $prefix Closed derivative prefix.
	 * @throws PersistenceMappingException When a tuple is incomplete.
	 */
	private function hydrate_derivative( array $row, string $prefix ): ?MediaDerivative {
		$reference = StoredRow::nullable_string( $row, $prefix . '_reference_ciphertext' );
		$digest    = StoredRow::nullable_string( $row, $prefix . '_sha256' );
		$bytes     = $this->nullable_positive_int( $row, $prefix . '_byte_count' );
		$width     = $this->nullable_positive_int( $row, $prefix . '_width' );
		$height    = $this->nullable_positive_int( $row, $prefix . '_height' );
		$values    = array( $reference, $digest, $bytes, $width, $height );

		if ( count( array_filter( $values, static fn( mixed $value ): bool => null !== $value ) ) === 0 ) {
			return null;
		}

		if ( null === $reference || null === $digest || null === $bytes || null === $width || null === $height ) {
			throw new PersistenceMappingException( 'Stored Finder Report media record is invalid.' );
		}

		$reference_ciphertext = PrivateMediaReferenceCiphertext::from_storage( $reference );
		$sha256               = MediaDigest::from_storage( $digest );

		if ( 'review' === $prefix ) {
			return MediaDerivative::review( $reference_ciphertext, $sha256, $bytes, $width, $height );
		}

		return MediaDerivative::email( $reference_ciphertext, $sha256, $bytes, $width, $height );
	}

	/**
	 * Map a nullable positive integer column.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @param string               $key Column name.
	 */
	private function nullable_positive_int( array $row, string $key ): ?int {
		if ( ! array_key_exists( $key, $row ) || null === $row[ $key ] ) {
			return null;
		}

		return StoredRow::positive_int( $row, $key );
	}
}
