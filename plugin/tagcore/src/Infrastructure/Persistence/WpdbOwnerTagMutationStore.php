<?php
/**
 * WordPress database Owner Tag mutation adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Account\OwnerTagLostState;
use ReturnTag\TagCore\Application\Account\OwnerTagMetadata;
use ReturnTag\TagCore\Application\Account\OwnerTagMutationResult;
use ReturnTag\TagCore\Application\Account\OwnerTagMutationStore;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/** Locks, compares, and conditionally writes only active current-Owner Tags. */
final readonly class WpdbOwnerTagMutationStore implements OwnerTagMutationStore {
	/**
	 * Create the mutation adapter.
	 *
	 * @param WpdbGateway           $gateway Prepared database gateway.
	 * @param TableNames            $tables Site-scoped table names.
	 * @param DatabaseDateTimeCodec $dates UTC database date codec.
	 */
	public function __construct(
		private WpdbGateway $gateway,
		private TableNames $tables,
		private DatabaseDateTimeCodec $dates
	) {
	}

	/**
	 * Update private/public labels inside a caller-owned transaction.
	 *
	 * @param TagId             $tag_id Selected public Tag identifier.
	 * @param int               $owner_id Current Owner identifier.
	 * @param OwnerTagMetadata  $metadata Validated complete metadata.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function update_metadata( TagId $tag_id, int $owner_id, OwnerTagMetadata $metadata, DateTimeImmutable $now ): OwnerTagMutationResult {
		$row = $this->lock_active_owner_tag( $tag_id, $owner_id );

		if ( null === $row ) {
			return OwnerTagMutationResult::UNAVAILABLE;
		}

		if (
			StoredRow::nullable_string( $row, 'item_name' ) === $metadata->item_name
			&& StoredRow::nullable_string( $row, 'public_label' ) === $metadata->public_label
		) {
			return OwnerTagMutationResult::UNCHANGED;
		}

		$updated = $this->gateway->execute(
			'UPDATE %i SET item_name = NULLIF(%s, %s), public_label = NULLIF(%s, %s), updated_at = %s WHERE tag_id = %s AND owner_id = %d AND tag_status = %s',
			array(
				$this->tables->tags(),
				$metadata->item_name ?? '',
				'',
				$metadata->public_label ?? '',
				'',
				$this->dates->format( $now ),
				$tag_id->value,
				$owner_id,
				TagStatus::ACTIVE->value,
			)
		);

		return 1 === $updated ? OwnerTagMutationResult::UPDATED : OwnerTagMutationResult::UNAVAILABLE;
	}

	/**
	 * Update Lost Mode and approved message inside a caller-owned transaction.
	 *
	 * @param TagId             $tag_id Selected public Tag identifier.
	 * @param int               $owner_id Current Owner identifier.
	 * @param OwnerTagLostState $state Validated complete Lost Mode state.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function update_lost_state( TagId $tag_id, int $owner_id, OwnerTagLostState $state, DateTimeImmutable $now ): OwnerTagMutationResult {
		$row = $this->lock_active_owner_tag( $tag_id, $owner_id );

		if ( null === $row ) {
			return OwnerTagMutationResult::UNAVAILABLE;
		}

		if (
			StoredRow::boolean( $row, 'lost_mode' ) === $state->enabled
			&& StoredRow::nullable_string( $row, 'lost_message' ) === $state->message
		) {
			return OwnerTagMutationResult::UNCHANGED;
		}

		$updated = $this->gateway->execute(
			'UPDATE %i SET lost_mode = %d, lost_message = NULLIF(%s, %s), updated_at = %s WHERE tag_id = %s AND owner_id = %d AND tag_status = %s',
			array(
				$this->tables->tags(),
				$state->enabled ? 1 : 0,
				$state->message ?? '',
				'',
				$this->dates->format( $now ),
				$tag_id->value,
				$owner_id,
				TagStatus::ACTIVE->value,
			)
		);

		return 1 === $updated ? OwnerTagMutationResult::UPDATED : OwnerTagMutationResult::UNAVAILABLE;
	}

	/**
	 * Set the Smart Setup acknowledgement once inside a caller-owned transaction.
	 *
	 * @param TagId             $tag_id Selected public Tag identifier.
	 * @param int               $owner_id Current Owner identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function acknowledge_smart_setup( TagId $tag_id, int $owner_id, DateTimeImmutable $now ): OwnerTagMutationResult {
		$row = $this->lock_active_owner_tag( $tag_id, $owner_id );

		if ( null === $row || TagType::SMART_TAG->value !== StoredRow::string( $row, 'tag_type' ) ) {
			return OwnerTagMutationResult::UNAVAILABLE;
		}

		if ( null !== StoredRow::nullable_string( $row, 'owner_pairing_ack_at' ) ) {
			return OwnerTagMutationResult::UNCHANGED;
		}

		$updated = $this->gateway->execute(
			'UPDATE %i SET owner_pairing_ack_at = %s, updated_at = %s WHERE tag_id = %s AND owner_id = %d AND tag_status = %s AND tag_type = %s AND owner_pairing_ack_at IS NULL',
			array(
				$this->tables->tags(),
				$this->dates->format( $now ),
				$this->dates->format( $now ),
				$tag_id->value,
				$owner_id,
				TagStatus::ACTIVE->value,
				TagType::SMART_TAG->value,
			)
		);

		return 1 === $updated ? OwnerTagMutationResult::UPDATED : OwnerTagMutationResult::UNAVAILABLE;
	}

	/**
	 * Lock one minimal candidate while retaining the Owner and active predicates.
	 *
	 * @param TagId $tag_id Selected public Tag identifier.
	 * @param int   $owner_id Current Owner identifier.
	 *
	 * @return array<string, mixed>|null
	 */
	private function lock_active_owner_tag( TagId $tag_id, int $owner_id ): ?array {
		RecordValidator::positive_id( $owner_id, 'owner_id' );

		return $this->gateway->row(
			'SELECT tag_type, tag_status, item_name, public_label, lost_mode, lost_message, owner_pairing_ack_at FROM %i WHERE tag_id = %s AND owner_id = %d AND tag_status = %s LIMIT 1 FOR UPDATE',
			array( $this->tables->tags(), $tag_id->value, $owner_id, TagStatus::ACTIVE->value )
		);
	}
}
