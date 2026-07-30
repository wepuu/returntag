<?php
/**
 * Public Tag page decision policy.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\PublicTag;

use ReturnTag\TagCore\Application\Tag\TagActivationAvailability;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Domain\Tag\TagStatus;

/**
 * Derives a privacy-minimized page without platform or persistence access.
 */
final readonly class PublicTagPagePolicy {
	/**
	 * Create the policy.
	 *
	 * @param TagActivationAvailabilityPolicy $activation First-activation policy.
	 */
	public function __construct( private TagActivationAvailabilityPolicy $activation ) {
	}

	/**
	 * Derive one public page from trusted stored facts and server controls.
	 *
	 * @param PublicTagStateRecord $record Public state projection.
	 * @param int|null             $current_user_id Server-derived WordPress user ID.
	 * @param bool                 $global_activation_enabled Global activation control.
	 * @param bool                 $finder_contact_enabled Finder contact control.
	 */
	public function decide(
		PublicTagStateRecord $record,
		?int $current_user_id,
		bool $global_activation_enabled,
		bool $finder_contact_enabled
	): PublicTagPage {
		if ( null === $record->batch_status || null === $record->batch_activation_enabled ) {
			return PublicTagPage::service_unavailable();
		}

		if ( TagStatus::SUSPENDED === $record->tag_status ) {
			return PublicTagPage::suspended();
		}

		if ( TagStatus::RETIRED === $record->tag_status ) {
			return PublicTagPage::retired();
		}

		if ( TagStatus::UNREGISTERED === $record->tag_status ) {
			if ( null !== $record->owner_id || null !== $record->activated_at ) {
				return PublicTagPage::service_unavailable();
			}

			$availability = $this->activation->decide(
				$record->tag_status,
				$record->batch_status,
				$record->batch_activation_enabled,
				$global_activation_enabled,
				$record->activated_at
			);

			return TagActivationAvailability::ELIGIBLE === $availability
				? PublicTagPage::activation_entry( $record->tag_type )
				: PublicTagPage::activation_unavailable( $record->tag_type );
		}

		if ( null === $record->owner_id || null === $record->activated_at ) {
			return PublicTagPage::service_unavailable();
		}

		if ( null !== $current_user_id && $record->owner_id === $current_user_id ) {
			return PublicTagPage::owner_entry( $record->tag_type );
		}

		if ( ! $finder_contact_enabled ) {
			return PublicTagPage::finder_unavailable( $record->tag_type );
		}

		return PublicTagPage::finder_entry(
			$record->tag_type,
			$record->public_label,
			$record->lost_mode,
			$record->lost_message
		);
	}
}
