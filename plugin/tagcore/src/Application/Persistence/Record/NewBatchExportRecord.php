<?php
/**
 * New Batch Export audit data.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Immutable data required to append an export audit record.
 */
final readonly class NewBatchExportRecord {
	/**
	 * Create Batch Export persistence data.
	 *
	 * @param int               $batch_id Batch identifier.
	 * @param int               $export_version Immutable export version.
	 * @param int               $row_count Export row count.
	 * @param string            $file_format File-format code.
	 * @param string            $file_checksum SHA-256 digest.
	 * @param int               $created_by WordPress User ID.
	 * @param DateTimeImmutable $created_at UTC creation time.
	 */
	public function __construct(
		public int $batch_id,
		public int $export_version,
		public int $row_count,
		public string $file_format,
		public string $file_checksum,
		public int $created_by,
		public DateTimeImmutable $created_at
	) {
		RecordValidator::positive_id( $this->batch_id, 'batch_id' );
		RecordValidator::positive_id( $this->export_version, 'export_version' );
		RecordValidator::unsigned_int( $this->row_count, 'row_count' );
		RecordValidator::ascii( $this->file_format, 32, 'file_format' );
		RecordValidator::digest( $this->file_checksum, 'file_checksum' );
		RecordValidator::positive_id( $this->created_by, 'created_by' );
		RecordValidator::utc( $this->created_at, 'created_at' );
	}
}
