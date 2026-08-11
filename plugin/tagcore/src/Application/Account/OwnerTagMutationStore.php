<?php
/**
 * Atomic current-Owner Tag mutation port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use DateTimeImmutable;
use ReturnTag\TagCore\Domain\Tag\TagId;

/** Applies only bounded writes with an active current-Owner predicate. */
interface OwnerTagMutationStore {
	/**
	 * Update private/public labels inside a caller-owned transaction.
	 *
	 * @param TagId             $tag_id Selected public Tag identifier.
	 * @param int               $owner_id Current Owner identifier.
	 * @param OwnerTagMetadata  $metadata Validated complete metadata.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function update_metadata( TagId $tag_id, int $owner_id, OwnerTagMetadata $metadata, DateTimeImmutable $now ): OwnerTagMutationResult;

	/**
	 * Update Lost Mode and its approved message inside a caller-owned transaction.
	 *
	 * @param TagId             $tag_id Selected public Tag identifier.
	 * @param int               $owner_id Current Owner identifier.
	 * @param OwnerTagLostState $state Validated complete Lost Mode state.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function update_lost_state( TagId $tag_id, int $owner_id, OwnerTagLostState $state, DateTimeImmutable $now ): OwnerTagMutationResult;

	/**
	 * Set the Smart Setup acknowledgement once inside a caller-owned transaction.
	 *
	 * @param TagId             $tag_id Selected public Tag identifier.
	 * @param int               $owner_id Current Owner identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function acknowledge_smart_setup( TagId $tag_id, int $owner_id, DateTimeImmutable $now ): OwnerTagMutationResult;
}
