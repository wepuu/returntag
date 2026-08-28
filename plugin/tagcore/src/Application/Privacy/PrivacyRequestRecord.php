<?php
/**
 * Privacy request persistence projection.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Privacy;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestError;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestReason;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestState;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestType;

/** Contains orchestration metadata only; never private request payloads. */
final readonly class PrivacyRequestRecord {
	/**
	 * Create one strict persistence projection.
	 *
	 * @param int                       $request_id Internal request identifier.
	 * @param PrivacyRequestSubject     $subject Privacy-safe requester reference.
	 * @param PrivacyRequestType        $request_type Approved request type.
	 * @param PrivacyRequestState       $state Current orchestration state.
	 * @param string                    $policy_version Frozen policy identifier.
	 * @param string                    $idempotency_key Request idempotency digest.
	 * @param string|null               $active_request_key Unique unfinished-request slot.
	 * @param string|null               $checkpoint_code Fixed resumable checkpoint.
	 * @param int                       $attempt_count Processing claim count.
	 * @param int                       $row_version Optimistic concurrency version.
	 * @param PrivacyRequestReason|null $reason Fixed action-required reason.
	 * @param PrivacyRequestError|null  $error Fixed retryable error.
	 * @param DateTimeImmutable         $created_at UTC creation time.
	 * @param DateTimeImmutable         $updated_at UTC update time.
	 * @param DateTimeImmutable|null    $processing_started_at UTC processing time.
	 * @param DateTimeImmutable|null    $action_required_at UTC action-required time.
	 * @param DateTimeImmutable|null    $completed_at UTC completion time.
	 * @param DateTimeImmutable|null    $failed_at UTC failure time.
	 * @throws \InvalidArgumentException When stored metadata is invalid.
	 */
	public function __construct(
		public int $request_id,
		public PrivacyRequestSubject $subject,
		public PrivacyRequestType $request_type,
		public PrivacyRequestState $state,
		public string $policy_version,
		public string $idempotency_key,
		public ?string $active_request_key,
		public ?string $checkpoint_code,
		public int $attempt_count,
		public int $row_version,
		public ?PrivacyRequestReason $reason,
		public ?PrivacyRequestError $error,
		public DateTimeImmutable $created_at,
		public DateTimeImmutable $updated_at,
		public ?DateTimeImmutable $processing_started_at,
		public ?DateTimeImmutable $action_required_at,
		public ?DateTimeImmutable $completed_at,
		public ?DateTimeImmutable $failed_at
	) {
		RecordValidator::positive_id( $this->request_id, 'request_id' );
		RecordValidator::ascii( $this->policy_version, 64, 'policy_version' );
		RecordValidator::digest( $this->idempotency_key, 'idempotency_key' );
		if ( null !== $this->active_request_key ) {
			RecordValidator::digest( $this->active_request_key, 'active_request_key' );
		}
		if ( null !== $this->checkpoint_code ) {
			RecordValidator::ascii( $this->checkpoint_code, 64, 'checkpoint_code' );
			if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{0,63}$/D', $this->checkpoint_code ) ) {
				throw new \InvalidArgumentException( 'Privacy request checkpoint is invalid.' );
			}
		}
		RecordValidator::unsigned_int( $this->attempt_count, 'attempt_count' );
		RecordValidator::positive_id( $this->row_version, 'row_version' );
		RecordValidator::utc( $this->created_at, 'created_at' );
		RecordValidator::utc( $this->updated_at, 'updated_at' );
		RecordValidator::nullable_utc( $this->processing_started_at, 'processing_started_at' );
		RecordValidator::nullable_utc( $this->action_required_at, 'action_required_at' );
		RecordValidator::nullable_utc( $this->completed_at, 'completed_at' );
		RecordValidator::nullable_utc( $this->failed_at, 'failed_at' );
	}
}
