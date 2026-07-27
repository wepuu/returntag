<?php
/**
 * Create Batch application service.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Batch;

use ReturnTag\TagCore\Application\Batch\Exception\BatchCodeAlreadyExists;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceException;
use ReturnTag\TagCore\Application\Persistence\Record\BatchRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewBatchRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\BatchRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;

/**
 * Creates one disabled draft Batch and its audit Event atomically.
 */
final readonly class CreateBatch {
	/**
	 * Create the use case.
	 *
	 * @param BatchRepository    $batches Batch persistence.
	 * @param EventRepository    $events Event persistence.
	 * @param TransactionManager $transactions Transaction boundary.
	 * @param Clock              $clock UTC clock.
	 */
	public function __construct(
		private BatchRepository $batches,
		private EventRepository $events,
		private TransactionManager $transactions,
		private Clock $clock
	) {
	}

	/**
	 * Create one new draft Batch.
	 *
	 * @param CreateBatchInput $input Validated operator input.
	 * @throws BatchCodeAlreadyExists When the Batch Code already exists.
	 */
	public function execute( CreateBatchInput $input ): BatchRecord {
		return $this->transactions->transactional(
			function () use ( $input ): BatchRecord {
				if ( null !== $this->batches->find_by_code( $input->batch_code ) ) {
					throw new BatchCodeAlreadyExists( 'Batch Code already exists.' );
				}

				$now = $this->clock->now();

				try {
					$batch = $this->batches->insert(
						new NewBatchRecord(
							$input->batch_code,
							$input->tag_type,
							$input->model_code,
							$input->smart_network,
							$input->manufacturer,
							$input->sales_channel,
							$input->requested_quantity,
							0,
							BatchStatus::DRAFT,
							false,
							$input->notes,
							$input->created_by,
							$now,
							$now
						)
					);
				} catch ( PersistenceException $exception ) {
					if ( null !== $this->batches->find_by_code( $input->batch_code ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The previous exception is chained, not rendered.
						throw new BatchCodeAlreadyExists( 'Batch Code already exists.', 0, $exception );
					}

					throw $exception;
				}

				$this->events->append(
					new NewEventRecord(
						'batch.created',
						'user',
						$input->created_by,
						'batch',
						(string) $batch->batch_id,
						'success',
						null,
						EventMetadata::none(),
						$now
					)
				);

				return $batch;
			}
		);
	}
}
