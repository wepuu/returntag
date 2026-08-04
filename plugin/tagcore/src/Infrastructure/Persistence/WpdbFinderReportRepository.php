<?php
/**
 * WordPress database Finder Report Repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceConstraintViolationException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\Record\FinderReportRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewFinderReportRecord;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportRepository;
use ReturnTag\TagCore\Application\Persistence\Value\FinderReportMessageCiphertext;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Persists one-way Finder Reports separately from Conversations.
 */
final class WpdbFinderReportRepository implements FinderReportRepository {
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
	 * Insert one Finder Report after verifying its Owner snapshot.
	 *
	 * @param NewFinderReportRecord $record New report data.
	 */
	public function insert( NewFinderReportRecord $record ): FinderReportRecord {
		$this->assert_owner_snapshot( $record );
		$finder_report_id = $this->gateway->insert(
			$this->tables->finder_reports(),
			array(
				'tag_id'                    => $record->tag_id,
				'owner_id_at_submission'    => $record->owner_id_at_submission,
				'message_ciphertext'        => $record->message_ciphertext?->value,
				'report_status'             => $record->report_status->value,
				'evidence_status'           => $record->evidence_status->value,
				'owner_notification_status' => $record->owner_notification_status?->value,
				'owner_notified_at'         => $this->dates->format_nullable( $record->owner_notified_at ),
				'expires_at'                => $this->dates->format( $record->expires_at ),
				'created_at'                => $this->dates->format( $record->created_at ),
				'updated_at'                => $this->dates->format( $record->updated_at ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return new FinderReportRecord( $finder_report_id, $record );
	}

	/**
	 * Find one Finder Report by internal identifier.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 */
	public function find_by_id( int $finder_report_id ): ?FinderReportRecord {
		RecordValidator::positive_id( $finder_report_id, 'finder_report_id' );
		$row = $this->gateway->row(
			'SELECT * FROM %i WHERE finder_report_id = %d LIMIT 1',
			array( $this->tables->finder_reports(), $finder_report_id )
		);

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Verify that the submission-time Owner is the current stored Owner.
	 *
	 * @param NewFinderReportRecord $record New report data.
	 * @throws PersistenceConstraintViolationException When the Owner snapshot is inconsistent.
	 */
	private function assert_owner_snapshot( NewFinderReportRecord $record ): void {
		$row = $this->gateway->row(
			'SELECT owner_id FROM %i WHERE tag_id = %s LIMIT 1',
			array( $this->tables->tags(), $record->tag_id )
		);

		if ( null === $row || StoredRow::nullable_positive_int( $row, 'owner_id' ) !== $record->owner_id_at_submission ) {
			throw new PersistenceConstraintViolationException( 'Referenced record is unavailable or inconsistent.' );
		}
	}

	/**
	 * Map one strict stored Finder Report row.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @throws PersistenceMappingException When stored data is invalid.
	 */
	private function hydrate( array $row ): FinderReportRecord {
		try {
			$message = StoredRow::nullable_string( $row, 'message_ciphertext' );

			return new FinderReportRecord(
				StoredRow::positive_int( $row, 'finder_report_id' ),
				new NewFinderReportRecord(
					StoredRow::string( $row, 'tag_id' ),
					StoredRow::positive_int( $row, 'owner_id_at_submission' ),
					null === $message ? null : FinderReportMessageCiphertext::from_storage( $message ),
					StoredRow::enum( $row, 'report_status', FinderReportStatus::class ),
					StoredRow::enum( $row, 'evidence_status', FinderEvidenceStatus::class ),
					$this->nullable_delivery_status( $row ),
					$this->dates->parse_nullable( StoredRow::nullable_string( $row, 'owner_notified_at' ) ),
					$this->dates->parse( StoredRow::string( $row, 'expires_at' ) ),
					$this->dates->parse( StoredRow::string( $row, 'created_at' ) ),
					$this->dates->parse( StoredRow::string( $row, 'updated_at' ) )
				)
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Finder Report record is invalid.' );
		}
	}

	/**
	 * Map an optional canonical delivery status.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @throws PersistenceMappingException When stored status is unknown.
	 */
	private function nullable_delivery_status( array $row ): ?DeliveryStatus {
		$value = StoredRow::nullable_string( $row, 'owner_notification_status' );

		if ( null === $value ) {
			return null;
		}

		return DeliveryStatus::tryFrom( $value ) ?? throw new PersistenceMappingException( 'Stored Finder Report record is invalid.' );
	}
}
