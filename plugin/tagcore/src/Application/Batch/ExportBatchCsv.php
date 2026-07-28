<?php
/**
 * Audited Batch CSV export use case.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Batch\Exception\BatchExportIntegrityViolation;
use ReturnTag\TagCore\Application\Batch\Exception\BatchExportNotAllowed;
use ReturnTag\TagCore\Application\Batch\Exception\BatchExportNotFound;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Record\BatchExportRecord;
use ReturnTag\TagCore\Application\Persistence\Record\BatchRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewBatchExportRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchExportRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use Throwable;

/**
 * Prepares, validates, commits, and returns one immutable CSV artifact.
 */
final readonly class ExportBatchCsv {
	private const FILE_FORMAT = 'csv';

	/**
	 * Create the export use case.
	 *
	 * @param BatchRepository               $batches Batch persistence.
	 * @param BatchExportSourceReader       $source Narrow deterministic source.
	 * @param BatchExportArtifactBuilder    $artifacts CSV builder.
	 * @param BatchExportWorkflowRepository $workflow Locking and state writes.
	 * @param BatchExportRepository         $exports Append-only export audit.
	 * @param EventRepository               $events Business Event audit.
	 * @param TransactionManager            $transactions Transaction boundary.
	 * @param Clock                         $clock UTC clock.
	 */
	public function __construct(
		private BatchRepository $batches,
		private BatchExportSourceReader $source,
		private BatchExportArtifactBuilder $artifacts,
		private BatchExportWorkflowRepository $workflow,
		private BatchExportRepository $exports,
		private EventRepository $events,
		private TransactionManager $transactions,
		private Clock $clock
	) {
	}

	/**
	 * Export one complete Batch for an authorized operator.
	 *
	 * @param int $batch_id Batch identifier.
	 * @param int $operator_id WordPress User ID.
	 * @throws BatchExportNotFound When the Batch does not exist.
	 * @throws BatchExportIntegrityViolation When immutable data has drifted.
	 * @throws Throwable When artifact preparation or persistence fails.
	 */
	public function execute( int $batch_id, int $operator_id ): BatchExportResult {
		$batch = $this->batches->find_by_id( $batch_id );

		if ( null === $batch ) {
			throw new BatchExportNotFound( 'Batch export is unavailable.' );
		}

		$this->assert_exportable( $batch->data->batch_status );

		if ( $batch->data->generated_quantity !== $batch->data->requested_quantity ) {
			throw new BatchExportIntegrityViolation( 'Batch export is unavailable.' );
		}

		$artifact = $this->artifacts->build(
			$batch,
			$this->source->iterate_for_batch( $batch_id )
		);

		try {
			return $this->transactions->transactional(
				fn(): BatchExportResult => $this->commit(
					$batch,
					$artifact,
					$operator_id
				)
			);
		} catch ( Throwable $exception ) {
			$artifact->cleanup();
			throw $exception;
		}
	}

	/**
	 * Commit one prepared artifact and its lifecycle Event.
	 *
	 * @param BatchRecord         $source_batch Source snapshot used to build the file.
	 * @param BatchExportArtifact $artifact Prepared artifact.
	 * @param int                 $operator_id WordPress User ID.
	 * @throws BatchExportNotFound When the locked Batch no longer exists.
	 * @throws BatchExportIntegrityViolation When data or audit integrity checks fail.
	 */
	private function commit(
		BatchRecord $source_batch,
		BatchExportArtifact $artifact,
		int $operator_id
	): BatchExportResult {
		$state = $this->workflow->lock_by_id( $source_batch->batch_id );

		if ( null === $state ) {
			throw new BatchExportNotFound( 'Batch export is unavailable.' );
		}

		$this->assert_exportable( $state->batch_status );
		$this->assert_same_snapshot( $source_batch, $state );

		$stored_count = $this->workflow->count_tags( $state->batch_id );

		if (
			$state->generated_quantity !== $state->requested_quantity
			|| $stored_count !== $state->generated_quantity
			|| $artifact->row_count() !== $state->generated_quantity
		) {
			throw new BatchExportIntegrityViolation( 'Batch export is unavailable.' );
		}

		$history = $this->exports->list_by_batch( $state->batch_id, null, new PageSize( 1 ) );
		$latest  = $history->items[0] ?? null;

		if ( BatchStatus::GENERATED === $state->batch_status && null !== $latest ) {
			throw new BatchExportIntegrityViolation( 'Batch export history is inconsistent.' );
		}

		if ( BatchStatus::GENERATED !== $state->batch_status && ! $latest instanceof BatchExportRecord ) {
			throw new BatchExportIntegrityViolation( 'Batch export history is inconsistent.' );
		}

		if (
			$latest instanceof BatchExportRecord
			&& (
				self::FILE_FORMAT !== $latest->data->file_format
				|| $artifact->row_count() !== $latest->data->row_count
				|| ! hash_equals( $latest->data->file_checksum, $artifact->checksum() )
			)
		) {
			throw new BatchExportIntegrityViolation( 'Batch export content has changed.' );
		}

		$version = $latest instanceof BatchExportRecord
			? $latest->data->export_version + 1
			: 1;
		$now     = $this->clock->now();
		$record  = $this->exports->append(
			new NewBatchExportRecord(
				$state->batch_id,
				$version,
				$artifact->row_count(),
				self::FILE_FORMAT,
				$artifact->checksum(),
				$operator_id,
				$now
			)
		);

		$next_status = $state->batch_status;

		if ( BatchStatus::GENERATED === $state->batch_status ) {
			if ( ! $this->workflow->mark_exported( $state->batch_id, $now ) ) {
				throw new BatchExportIntegrityViolation( 'Batch export state could not be committed.' );
			}

			$next_status = BatchStatus::EXPORTED;
		}

		$this->events->append(
			new NewEventRecord(
				'batch_exported',
				'user',
				$operator_id,
				'batch',
				(string) $state->batch_id,
				'success',
				null,
				EventMetadata::none(),
				$now
			)
		);

		return new BatchExportResult(
			$state->batch_code,
			$next_status,
			$record,
			$artifact
		);
	}

	/**
	 * Reject states that should not issue manufacturing files.
	 *
	 * @param BatchStatus $status Current Batch state.
	 * @throws BatchExportNotAllowed When the state forbids export.
	 */
	private function assert_exportable( BatchStatus $status ): void {
		if ( ! in_array( $status, array( BatchStatus::GENERATED, BatchStatus::EXPORTED, BatchStatus::RELEASED ), true ) ) {
			throw new BatchExportNotAllowed( 'Batch export is unavailable in the current state.' );
		}
	}

	/**
	 * Ensure the file was built from the same locked manufacturing snapshot.
	 *
	 * @param BatchRecord      $source_batch Source Batch.
	 * @param BatchExportState $locked Locked Batch state.
	 * @throws BatchExportIntegrityViolation When the locked snapshot has changed.
	 */
	private function assert_same_snapshot( BatchRecord $source_batch, BatchExportState $locked ): void {
		$source = $source_batch->data;

		if (
			$source_batch->batch_id !== $locked->batch_id
			|| $source->batch_code !== $locked->batch_code
			|| $source->tag_type !== $locked->tag_type
			|| $source->model_code !== $locked->model_code
			|| $source->smart_network !== $locked->smart_network
			|| $source->requested_quantity !== $locked->requested_quantity
			|| $source->generated_quantity !== $locked->generated_quantity
		) {
			throw new BatchExportIntegrityViolation( 'Batch export source has changed.' );
		}
	}
}
