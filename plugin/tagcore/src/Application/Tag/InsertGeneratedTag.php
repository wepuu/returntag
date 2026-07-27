<?php
/**
 * Insert one generated Tag with bounded collision retry.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceDuplicateKeyException;
use ReturnTag\TagCore\Application\Persistence\Record\NewTagRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\TagRepository;
use ReturnTag\TagCore\Application\Tag\Exception\TagIdCollisionRetryExhausted;
use ReturnTag\TagCore\Domain\Tag\TagStatus;

/**
 * Persists one candidate and retries only explicit duplicate-key collisions.
 */
final readonly class InsertGeneratedTag {
	/**
	 * Maximum number of generated candidates per insertion.
	 */
	public const MAXIMUM_ATTEMPTS = 10;

	/**
	 * Create the use case.
	 *
	 * @param TagIdGenerator $generator Candidate generator.
	 * @param TagRepository  $tags Tag persistence.
	 */
	public function __construct(
		private TagIdGenerator $generator,
		private TagRepository $tags
	) {
	}

	/**
	 * Generate and insert one unregistered Tag.
	 *
	 * @param GeneratedTagInput $input Trusted Batch snapshot.
	 * @throws TagIdCollisionRetryExhausted When every candidate collides.
	 */
	public function execute( GeneratedTagInput $input ): InsertGeneratedTagResult {
		for ( $attempt = 1; $attempt <= self::MAXIMUM_ATTEMPTS; ++$attempt ) {
			$candidate = $this->generator->generate();

			try {
				$tag = $this->tags->insert(
					new NewTagRecord(
						$candidate->value,
						$input->batch_id,
						null,
						$input->tag_type,
						$input->model_code,
						null,
						null,
						TagStatus::UNREGISTERED,
						false,
						null,
						null,
						null,
						null,
						null,
						$input->created_at,
						$input->created_at
					)
				);

				return new InsertGeneratedTagResult( $tag, $attempt - 1 );
			} catch ( PersistenceDuplicateKeyException $exception ) {
				if ( self::MAXIMUM_ATTEMPTS === $attempt ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The fixed exception is chained, not rendered.
					throw new TagIdCollisionRetryExhausted( 'Unable to allocate a unique Tag ID.', 0, $exception );
				}
			}
		}

		throw new TagIdCollisionRetryExhausted( 'Unable to allocate a unique Tag ID.' );
	}
}
