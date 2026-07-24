<?php
/**
 * New Tag persistence data.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;

/**
 * Immutable data required to insert a physical Tag.
 */
final readonly class NewTagRecord {
	/**
	 * Create Tag persistence data.
	 *
	 * @param string                 $tag_id Public Tag ID.
	 * @param int                    $batch_id Batch identifier.
	 * @param int|null               $owner_id WordPress owner User ID.
	 * @param TagType                $tag_type Physical Tag type.
	 * @param string|null            $model_code Model code snapshot.
	 * @param string|null            $item_name Owner-private item name.
	 * @param string|null            $public_label Finder-safe public label.
	 * @param TagStatus              $tag_status Persisted Tag state.
	 * @param bool                   $lost_mode Independent Lost Mode flag.
	 * @param string|null            $lost_message Finder-safe Lost Mode copy.
	 * @param DateTimeImmutable|null $owner_pairing_ack_at Static-guide acknowledgement.
	 * @param DateTimeImmutable|null $activated_at UTC activation time.
	 * @param DateTimeImmutable|null $owner_changed_at UTC owner-change time.
	 * @param DateTimeImmutable|null $last_scanned_at UTC last-scan time.
	 * @param DateTimeImmutable      $created_at UTC creation time.
	 * @param DateTimeImmutable      $updated_at UTC update time.
	 */
	public function __construct(
		public string $tag_id,
		public int $batch_id,
		public ?int $owner_id,
		public TagType $tag_type,
		public ?string $model_code,
		public ?string $item_name,
		public ?string $public_label,
		public TagStatus $tag_status,
		public bool $lost_mode,
		public ?string $lost_message,
		public ?DateTimeImmutable $owner_pairing_ack_at,
		public ?DateTimeImmutable $activated_at,
		public ?DateTimeImmutable $owner_changed_at,
		public ?DateTimeImmutable $last_scanned_at,
		public DateTimeImmutable $created_at,
		public DateTimeImmutable $updated_at
	) {
		RecordValidator::tag_id( $this->tag_id );
		RecordValidator::positive_id( $this->batch_id, 'batch_id' );

		if ( null !== $this->owner_id ) {
			RecordValidator::positive_id( $this->owner_id, 'owner_id' );
		}

		RecordValidator::nullable_ascii( $this->model_code, 191, 'model_code' );
		RecordValidator::nullable_text( $this->item_name, 191, 'item_name' );
		RecordValidator::nullable_text( $this->public_label, 191, 'public_label' );
		RecordValidator::nullable_text_bytes( $this->lost_message, 65535, 'lost_message' );
		RecordValidator::nullable_utc( $this->owner_pairing_ack_at, 'owner_pairing_ack_at' );
		RecordValidator::nullable_utc( $this->activated_at, 'activated_at' );
		RecordValidator::nullable_utc( $this->owner_changed_at, 'owner_changed_at' );
		RecordValidator::nullable_utc( $this->last_scanned_at, 'last_scanned_at' );
		RecordValidator::utc( $this->created_at, 'created_at' );
		RecordValidator::utc( $this->updated_at, 'updated_at' );
	}
}
