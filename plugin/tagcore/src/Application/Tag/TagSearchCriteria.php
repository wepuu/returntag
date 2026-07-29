<?php
/**
 * Validated administrative Tag search criteria.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;

/**
 * Models exactly one approved search mode.
 */
final readonly class TagSearchCriteria {
	/**
	 * Create validated criteria.
	 *
	 * @param TagSearchMode  $mode Search mode.
	 * @param TagId|null     $tag_id Exact Tag ID.
	 * @param string|null    $batch_code Exact Batch Code.
	 * @param TagStatus|null $tag_status Optional Tag status.
	 * @throws InvalidArgumentException When the criteria mix incompatible modes and anchors.
	 */
	private function __construct(
		public TagSearchMode $mode,
		public ?TagId $tag_id,
		public ?string $batch_code,
		public ?TagStatus $tag_status
	) {
		if (
			( TagSearchMode::TAG_ID === $this->mode && ( null === $this->tag_id || null !== $this->batch_code || null !== $this->tag_status ) )
			|| ( TagSearchMode::BATCH === $this->mode && ( null !== $this->tag_id || null === $this->batch_code ) )
		) {
			throw new InvalidArgumentException( 'Tag search criteria are invalid.' );
		}
	}

	/**
	 * Build exact Tag ID criteria.
	 *
	 * @param TagId $tag_id Canonical Tag ID.
	 */
	public static function for_tag_id( TagId $tag_id ): self {
		return new self( TagSearchMode::TAG_ID, $tag_id, null, null );
	}

	/**
	 * Build exact Batch Code criteria.
	 *
	 * @param string         $batch_code Exact Batch Code.
	 * @param TagStatus|null $tag_status Optional status filter.
	 * @throws InvalidArgumentException When Batch Code is empty.
	 */
	public static function for_batch( string $batch_code, ?TagStatus $tag_status ): self {
		return new self(
			TagSearchMode::BATCH,
			null,
			RecordValidator::ascii( $batch_code, 191, 'batch_code' ),
			$tag_status
		);
	}

	/**
	 * Return a stable non-secret filter binding for opaque cursors.
	 */
	public function fingerprint(): string {
		return substr(
			hash(
				'sha256',
				implode(
					"\0",
					array(
						$this->mode->value,
						null === $this->tag_id ? '' : $this->tag_id->value,
						$this->batch_code ?? '',
						null === $this->tag_status ? '' : $this->tag_status->value,
					)
				)
			),
			0,
			24
		);
	}
}
