<?php
/**
 * Owner Tag mutation form boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Account\MutateOwnerTag;
use ReturnTag\TagCore\Application\Account\OwnerTagLostState;
use ReturnTag\TagCore\Application\Account\OwnerTagMetadata;
use ReturnTag\TagCore\Application\Account\OwnerTagMutationResult;
use ReturnTag\TagCore\Domain\Tag\TagId;
use Throwable;

/** Accepts one same-site, nonce-protected, closed Stage 2 action. */
final readonly class AccountTagMutationFormHandler {
	public const NONCE_PREFIX = 'returntag_account_tag_';

	public const NONCE_FIELD = 'returntag_account_tag_nonce';

	public const ACTION_FIELD = 'returntag_account_tag_action';

	public const ITEM_NAME_FIELD = 'returntag_item_name';

	public const PUBLIC_LABEL_FIELD = 'returntag_public_label';

	public const LOST_MODE_FIELD = 'returntag_lost_mode';

	public const LOST_MESSAGE_FIELD = 'returntag_lost_message';

	public const UPDATE_METADATA = 'update_metadata';

	public const UPDATE_LOST_STATE = 'update_lost_state';

	public const ACKNOWLEDGE_SMART_SETUP = 'acknowledge_smart_setup';

	/**
	 * Create the Tag form boundary.
	 *
	 * @param MutateOwnerTag          $mutations Stage 2 use cases.
	 * @param AccountFormRequestGuard $guard Same-site and nonce guard.
	 */
	public function __construct(
		private MutateOwnerTag $mutations,
		private AccountFormRequestGuard $guard
	) {
	}

	/**
	 * Validate and execute one Tag Detail POST.
	 *
	 * @param TagId $tag_id Selected public Tag identifier.
	 */
	public function submit( TagId $tag_id ): AccountTagMutationFeedback {
		if (
			! $this->guard->is_same_site()
			|| ! $this->guard->valid_nonce( self::NONCE_FIELD, self::NONCE_PREFIX . $tag_id->value )
			|| $this->contains_forbidden_authority_input()
		) {
			return new AccountTagMutationFeedback( AccountTagMutationState::UNAVAILABLE );
		}

		$action = $this->guard->post_string( self::ACTION_FIELD, 40 );

		try {
			return match ( $action ) {
				self::UPDATE_METADATA => $this->update_metadata( $tag_id ),
				self::UPDATE_LOST_STATE => $this->update_lost_state( $tag_id ),
				self::ACKNOWLEDGE_SMART_SETUP => $this->map_result( $this->mutations->acknowledge_smart_setup( $tag_id ), true ),
				default => new AccountTagMutationFeedback( AccountTagMutationState::UNAVAILABLE ),
			};
		} catch ( Throwable ) {
			return new AccountTagMutationFeedback( AccountTagMutationState::UNAVAILABLE );
		}
	}

	/**
	 * Validate the complete metadata form.
	 *
	 * @param TagId $tag_id Selected public Tag identifier.
	 */
	private function update_metadata( TagId $tag_id ): AccountTagMutationFeedback {
		try {
			$metadata = new OwnerTagMetadata(
				$this->guard->post_string( self::ITEM_NAME_FIELD, 1024 ),
				$this->guard->post_string( self::PUBLIC_LABEL_FIELD, 1024 )
			);
		} catch ( InvalidArgumentException ) {
			return new AccountTagMutationFeedback( AccountTagMutationState::INVALID_METADATA );
		}

		return $this->map_result( $this->mutations->update_metadata( $tag_id, $metadata ) );
	}

	/**
	 * Validate the complete Lost Mode form.
	 *
	 * @param TagId $tag_id Selected public Tag identifier.
	 */
	private function update_lost_state( TagId $tag_id ): AccountTagMutationFeedback {
		$raw_mode = $this->guard->post_string( self::LOST_MODE_FIELD, 8 );

		if ( ! in_array( $raw_mode, array( '', '1' ), true ) ) {
			return new AccountTagMutationFeedback( AccountTagMutationState::INVALID_LOST_MESSAGE );
		}

		try {
			$state = new OwnerTagLostState(
				'1' === $raw_mode,
				$this->guard->post_string( self::LOST_MESSAGE_FIELD, 4096 )
			);
		} catch ( InvalidArgumentException ) {
			return new AccountTagMutationFeedback( AccountTagMutationState::INVALID_LOST_MESSAGE );
		}

		return $this->map_result( $this->mutations->update_lost_state( $tag_id, $state ) );
	}

	/**
	 * Map Application outcomes without exposing persistence detail.
	 *
	 * @param OwnerTagMutationResult $result Application outcome.
	 * @param bool                   $acknowledgement Whether the action is Smart Setup acknowledgement.
	 */
	private function map_result( OwnerTagMutationResult $result, bool $acknowledgement = false ): AccountTagMutationFeedback {
		$state = match ( $result ) {
			OwnerTagMutationResult::UPDATED => $acknowledgement ? AccountTagMutationState::SMART_SETUP_ACKNOWLEDGED : AccountTagMutationState::UPDATED,
			OwnerTagMutationResult::UNCHANGED => AccountTagMutationState::UNCHANGED,
			OwnerTagMutationResult::THROTTLED => AccountTagMutationState::THROTTLED,
			OwnerTagMutationResult::AUTHENTICATION_REQUIRED,
			OwnerTagMutationResult::UNAVAILABLE => AccountTagMutationState::UNAVAILABLE,
		};

		return new AccountTagMutationFeedback( $state );
	}

	/** Reject browser attempts to supply server-owned authority fields. */
	private function contains_forbidden_authority_input(): bool {
		foreach ( array( 'owner_id', 'tag_status', 'actor_role', 'event_type', 'authorization_result' ) as $field ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified before this method is called.
			if ( array_key_exists( $field, $_POST ) ) {
				return true;
			}
		}

		return false;
	}
}
