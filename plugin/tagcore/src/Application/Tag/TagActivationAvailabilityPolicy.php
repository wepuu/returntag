<?php
/**
 * Administrative Tag activation availability policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Tag;

use DateTimeImmutable;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagStatus;

/**
 * Derives one presentation-safe activation decision without changing stored state.
 */
final class TagActivationAvailabilityPolicy {
	/**
	 * Derive the effective first-activation availability.
	 *
	 * @param TagStatus              $tag_status Canonical Tag status.
	 * @param BatchStatus            $batch_status Canonical Batch status.
	 * @param bool                   $batch_activation_enabled Batch activation control.
	 * @param bool                   $global_activation_enabled Global activation control.
	 * @param DateTimeImmutable|null $activated_at Optional activation timestamp.
	 */
	public function decide(
		TagStatus $tag_status,
		BatchStatus $batch_status,
		bool $batch_activation_enabled,
		bool $global_activation_enabled,
		?DateTimeImmutable $activated_at
	): TagActivationAvailability {
		if (
			( TagStatus::ACTIVE === $tag_status && null === $activated_at )
			|| ( TagStatus::UNREGISTERED === $tag_status && null !== $activated_at )
		) {
			return TagActivationAvailability::DATA_INCONSISTENT;
		}

		if ( TagStatus::ACTIVE === $tag_status ) {
			return TagActivationAvailability::EXISTING_ACTIVATION_RETAINED;
		}

		if ( TagStatus::SUSPENDED === $tag_status ) {
			return TagActivationAvailability::BLOCKED_TAG_SUSPENDED;
		}

		if ( TagStatus::RETIRED === $tag_status ) {
			return TagActivationAvailability::BLOCKED_TAG_RETIRED;
		}

		if ( BatchStatus::VOIDED === $batch_status ) {
			return TagActivationAvailability::BLOCKED_BATCH_VOIDED;
		}

		if ( BatchStatus::SUSPENDED === $batch_status ) {
			return TagActivationAvailability::BLOCKED_BATCH_SUSPENDED;
		}

		if ( BatchStatus::RELEASED !== $batch_status ) {
			return TagActivationAvailability::AWAITING_RELEASE;
		}

		if ( ! $batch_activation_enabled ) {
			return TagActivationAvailability::BLOCKED_BATCH_CONTROL;
		}

		if ( ! $global_activation_enabled ) {
			return TagActivationAvailability::PAUSED_GLOBALLY;
		}

		return TagActivationAvailability::ELIGIBLE;
	}
}
