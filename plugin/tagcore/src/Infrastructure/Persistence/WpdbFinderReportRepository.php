<?php
/**
 * WordPress database Finder Report Repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
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
use ReturnTag\TagCore\Domain\Tag\TagStatus;
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
	 * Read the canonical Conversation link.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 */
	public function find_conversation_id( int $finder_report_id ): ?int {
		RecordValidator::positive_id( $finder_report_id, 'finder_report_id' );
		$row = $this->gateway->row(
			'SELECT conversation_id FROM %i WHERE finder_report_id = %d LIMIT 1',
			array( $this->tables->finder_reports(), $finder_report_id )
		);

		return null === $row ? null : StoredRow::nullable_positive_int( $row, 'conversation_id' );
	}

	/**
	 * Resolve the current Owner at Conversation creation time.
	 *
	 * @param int $finder_report_id Internal report identifier.
	 */
	public function find_current_owner_id( int $finder_report_id ): ?int {
		RecordValidator::positive_id( $finder_report_id, 'finder_report_id' );
		$row = $this->gateway->row(
			'SELECT tags.owner_id FROM %i reports INNER JOIN %i tags ON tags.tag_id = reports.tag_id WHERE reports.finder_report_id = %d AND tags.tag_status = %s LIMIT 1',
			array( $this->tables->finder_reports(), $this->tables->tags(), $finder_report_id, TagStatus::ACTIVE->value )
		);

		return null === $row ? null : StoredRow::nullable_positive_int( $row, 'owner_id' );
	}

	/**
	 * Atomically attach the first canonical Conversation.
	 *
	 * @param int               $finder_report_id Internal report identifier.
	 * @param int               $conversation_id Internal Conversation identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function link_conversation( int $finder_report_id, int $conversation_id, DateTimeImmutable $now ): bool {
		RecordValidator::positive_id( $finder_report_id, 'finder_report_id' );
		RecordValidator::positive_id( $conversation_id, 'conversation_id' );

		return 1 === $this->gateway->execute(
			'UPDATE %i SET conversation_id = %d, updated_at = %s WHERE finder_report_id = %d AND conversation_id IS NULL AND report_status IN (%s, %s, %s, %s)',
			array(
				$this->tables->finder_reports(),
				$conversation_id,
				$this->dates->format( $now ),
				$finder_report_id,
				FinderReportStatus::RECEIVED->value,
				FinderReportStatus::PROCESSING->value,
				FinderReportStatus::READY->value,
				FinderReportStatus::NOTIFIED->value,
			)
		);
	}

	/**
	 * Claim a received or stale report.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param DateTimeImmutable $stale_before Stale lease cutoff.
	 */
	public function claim_processing( int $finder_report_id, DateTimeImmutable $now, DateTimeImmutable $stale_before ): bool {
		RecordValidator::positive_id( $finder_report_id, 'finder_report_id' );

		return 1 === $this->gateway->execute(
			'UPDATE %i SET report_status = %s, evidence_status = %s, updated_at = %s WHERE finder_report_id = %d AND ((report_status = %s AND evidence_status = %s) OR (report_status = %s AND evidence_status = %s AND updated_at <= %s))',
			array(
				$this->tables->finder_reports(),
				FinderReportStatus::PROCESSING->value,
				FinderEvidenceStatus::PROCESSING->value,
				$this->dates->format( $now ),
				$finder_report_id,
				FinderReportStatus::RECEIVED->value,
				FinderEvidenceStatus::QUARANTINED->value,
				FinderReportStatus::PROCESSING->value,
				FinderEvidenceStatus::PROCESSING->value,
				$this->dates->format( $stale_before ),
			)
		);
	}

	/**
	 * Mark a processing report ready.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_ready( int $finder_report_id, DateTimeImmutable $now ): bool {
		return $this->transition(
			$finder_report_id,
			FinderReportStatus::PROCESSING,
			FinderEvidenceStatus::PROCESSING,
			FinderReportStatus::READY,
			FinderEvidenceStatus::READY,
			$now
		);
	}

	/**
	 * Mark a non-terminal report blocked.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_blocked( int $finder_report_id, DateTimeImmutable $now ): bool {
		RecordValidator::positive_id( $finder_report_id, 'finder_report_id' );

		return 1 === $this->gateway->execute(
			'UPDATE %i SET report_status = %s, evidence_status = %s, updated_at = %s WHERE finder_report_id = %d AND report_status IN (%s, %s) AND evidence_status IN (%s, %s)',
			array(
				$this->tables->finder_reports(),
				FinderReportStatus::BLOCKED->value,
				FinderEvidenceStatus::REJECTED->value,
				$this->dates->format( $now ),
				$finder_report_id,
				FinderReportStatus::RECEIVED->value,
				FinderReportStatus::PROCESSING->value,
				FinderEvidenceStatus::QUARANTINED->value,
				FinderEvidenceStatus::PROCESSING->value,
			)
		);
	}

	/**
	 * Claim one ready report for notification.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function claim_owner_notification( int $finder_report_id, DateTimeImmutable $now ): bool {
		RecordValidator::positive_id( $finder_report_id, 'finder_report_id' );

		return 1 === $this->gateway->execute(
			'UPDATE %i SET owner_notification_status = %s, updated_at = %s WHERE finder_report_id = %d AND report_status = %s AND evidence_status = %s AND owner_notification_status IS NULL AND expires_at > %s',
			array(
				$this->tables->finder_reports(),
				DeliveryStatus::QUEUED->value,
				$this->dates->format( $now ),
				$finder_report_id,
				FinderReportStatus::READY->value,
				FinderEvidenceStatus::READY->value,
				$this->dates->format( $now ),
			)
		);
	}

	/**
	 * Record mailer acceptance and notified retention.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $expires_at Notified evidence retention boundary.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_owner_notified( int $finder_report_id, DateTimeImmutable $expires_at, DateTimeImmutable $now ): bool {
		RecordValidator::positive_id( $finder_report_id, 'finder_report_id' );

		return 1 === $this->gateway->execute(
			'UPDATE %i SET report_status = %s, owner_notification_status = %s, owner_notified_at = %s, expires_at = %s, updated_at = %s WHERE finder_report_id = %d AND report_status = %s AND evidence_status = %s AND owner_notification_status = %s',
			array(
				$this->tables->finder_reports(),
				FinderReportStatus::NOTIFIED->value,
				DeliveryStatus::SENT->value,
				$this->dates->format( $now ),
				$this->dates->format( $expires_at ),
				$this->dates->format( $now ),
				$finder_report_id,
				FinderReportStatus::READY->value,
				FinderEvidenceStatus::READY->value,
				DeliveryStatus::QUEUED->value,
			)
		);
	}

	/**
	 * Mark one claimed notification terminally failed.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_owner_notification_failed( int $finder_report_id, DateTimeImmutable $now ): bool {
		RecordValidator::positive_id( $finder_report_id, 'finder_report_id' );

		return 1 === $this->gateway->execute(
			'UPDATE %i SET owner_notification_status = %s, updated_at = %s WHERE finder_report_id = %d AND report_status = %s AND evidence_status = %s AND owner_notification_status = %s',
			array(
				$this->tables->finder_reports(),
				DeliveryStatus::FAILED->value,
				$this->dates->format( $now ),
				$finder_report_id,
				FinderReportStatus::READY->value,
				FinderEvidenceStatus::READY->value,
				DeliveryStatus::QUEUED->value,
			)
		);
	}

	/**
	 * Find ready notifications that have never been claimed.
	 *
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param int               $limit Row limit.
	 * @return list<int>
	 */
	public function find_notifiable( DateTimeImmutable $now, int $limit ): array {
		$limit = max( 1, min( 100, $limit ) );
		$rows  = $this->gateway->rows(
			'SELECT finder_report_id FROM %i WHERE report_status = %s AND evidence_status = %s AND owner_notification_status IS NULL AND expires_at > %s ORDER BY updated_at ASC, finder_report_id ASC LIMIT %d',
			array(
				$this->tables->finder_reports(),
				FinderReportStatus::READY->value,
				FinderEvidenceStatus::READY->value,
				$this->dates->format( $now ),
				$limit,
			)
		);

		return array_map(
			static fn( array $row ): int => StoredRow::positive_int( $row, 'finder_report_id' ),
			$rows
		);
	}

	/**
	 * Find stale queued claims for fail-closed convergence.
	 *
	 * @param DateTimeImmutable $stale_before Stale cutoff.
	 * @param int               $limit Row limit.
	 * @return list<int>
	 */
	public function find_stale_owner_notification_claims( DateTimeImmutable $stale_before, int $limit ): array {
		$limit = max( 1, min( 100, $limit ) );
		$rows  = $this->gateway->rows(
			'SELECT finder_report_id FROM %i WHERE report_status = %s AND evidence_status = %s AND owner_notification_status = %s AND updated_at <= %s ORDER BY updated_at ASC, finder_report_id ASC LIMIT %d',
			array(
				$this->tables->finder_reports(),
				FinderReportStatus::READY->value,
				FinderEvidenceStatus::READY->value,
				DeliveryStatus::QUEUED->value,
				$this->dates->format( $stale_before ),
				$limit,
			)
		);

		return array_map(
			static fn( array $row ): int => StoredRow::positive_int( $row, 'finder_report_id' ),
			$rows
		);
	}

	/**
	 * Mark a terminal report expired.
	 *
	 * @param int               $finder_report_id Internal identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_expired( int $finder_report_id, DateTimeImmutable $now ): bool {
		RecordValidator::positive_id( $finder_report_id, 'finder_report_id' );

		return 1 === $this->gateway->execute(
			'UPDATE %i SET report_status = %s, evidence_status = %s, updated_at = %s WHERE finder_report_id = %d AND report_status IN (%s, %s, %s, %s)',
			array(
				$this->tables->finder_reports(),
				FinderReportStatus::EXPIRED->value,
				FinderEvidenceStatus::DELETED->value,
				$this->dates->format( $now ),
				$finder_report_id,
				FinderReportStatus::RECEIVED->value,
				FinderReportStatus::PROCESSING->value,
				FinderReportStatus::READY->value,
				FinderReportStatus::BLOCKED->value,
			)
		);
	}

	/**
	 * Perform one exact two-column state transition.
	 *
	 * @param int                  $finder_report_id Internal identifier.
	 * @param FinderReportStatus   $from_report Expected report state.
	 * @param FinderEvidenceStatus $from_evidence Expected evidence state.
	 * @param FinderReportStatus   $to_report New report state.
	 * @param FinderEvidenceStatus $to_evidence New evidence state.
	 * @param DateTimeImmutable    $now Current UTC time.
	 */
	private function transition(
		int $finder_report_id,
		FinderReportStatus $from_report,
		FinderEvidenceStatus $from_evidence,
		FinderReportStatus $to_report,
		FinderEvidenceStatus $to_evidence,
		DateTimeImmutable $now
	): bool {
		RecordValidator::positive_id( $finder_report_id, 'finder_report_id' );

		return 1 === $this->gateway->execute(
			'UPDATE %i SET report_status = %s, evidence_status = %s, updated_at = %s WHERE finder_report_id = %d AND report_status = %s AND evidence_status = %s',
			array(
				$this->tables->finder_reports(),
				$to_report->value,
				$to_evidence->value,
				$this->dates->format( $now ),
				$finder_report_id,
				$from_report->value,
				$from_evidence->value,
			)
		);
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
