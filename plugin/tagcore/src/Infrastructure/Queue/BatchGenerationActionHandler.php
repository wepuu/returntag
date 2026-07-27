<?php
/**
 * Action Scheduler Batch generation handler.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\Batch\BatchGenerationScheduler;
use ReturnTag\TagCore\Application\Batch\GenerateBatchChunk;
use RuntimeException;
use Throwable;

/**
 * Runs one chunk and arranges bounded delayed retries.
 */
final readonly class BatchGenerationActionHandler {
	/**
	 * Retry delays in seconds.
	 *
	 * @var list<int>
	 */
	private const RETRY_DELAYS = array( 60, 300, 900, 3600, 21600 );

	/**
	 * Create the handler.
	 *
	 * @param GenerateBatchChunk       $generate Generate one chunk.
	 * @param BatchGenerationScheduler $scheduler Background scheduler.
	 */
	public function __construct(
		private GenerateBatchChunk $generate,
		private BatchGenerationScheduler $scheduler
	) {
	}

	/**
	 * Register the internal queue hook.
	 */
	public function register(): void {
		add_action(
			ActionSchedulerBatchGenerationScheduler::HOOK,
			array( $this, 'handle' ),
			10,
			3
		);
	}

	/**
	 * Run a scheduled generation action.
	 *
	 * @param int $batch_id Batch identifier.
	 * @param int $checkpoint Last committed checkpoint.
	 * @param int $retry_attempt Zero-based retry attempt.
	 * @throws RuntimeException When a generation chunk fails.
	 */
	public function handle( int $batch_id, int $checkpoint, int $retry_attempt ): void {
		try {
			$this->generate->execute( $batch_id, $checkpoint, $retry_attempt );
		} catch ( Throwable $exception ) {
			if ( isset( self::RETRY_DELAYS[ $retry_attempt ] ) ) {
				$this->scheduler->schedule(
					$batch_id,
					$checkpoint,
					$retry_attempt + 1,
					self::RETRY_DELAYS[ $retry_attempt ]
				);
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Only the fixed outer message is exposed to Action Scheduler.
			throw new RuntimeException( 'Batch generation chunk failed.', 0, $exception );
		}
	}
}
