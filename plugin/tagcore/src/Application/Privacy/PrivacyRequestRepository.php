<?php
/**
 * Privacy request persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Privacy;

use DateTimeImmutable;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestError;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestReason;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestType;

/** Persists metadata-only, concurrency-safe request orchestration. */
interface PrivacyRequestRepository {
	/**
	 * Create or resolve the single unfinished request for one subject/type.
	 *
	 * @param PrivacyRequestSubject $subject Privacy-safe requester reference.
	 * @param PrivacyRequestType    $type Approved request type.
	 * @param string                $policy_version Frozen policy identifier.
	 * @param string                $idempotency_key Request idempotency digest.
	 * @param string                $active_request_key Unique unfinished slot digest.
	 * @param DateTimeImmutable     $now Current UTC time.
	 */
	public function begin( PrivacyRequestSubject $subject, PrivacyRequestType $type, string $policy_version, string $idempotency_key, string $active_request_key, DateTimeImmutable $now ): PrivacyRequestStart;

	/**
	 * Return one request by internal ID.
	 *
	 * @param int $request_id Internal request identifier.
	 */
	public function find( int $request_id ): ?PrivacyRequestRecord;

	/**
	 * Conditionally claim a queued or failed request.
	 *
	 * @param int               $request_id Internal request identifier.
	 * @param int               $row_version Expected row version.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function claim( int $request_id, int $row_version, DateTimeImmutable $now ): ?PrivacyRequestRecord;

	/**
	 * Conditionally advance one fixed processing checkpoint.
	 *
	 * @param int               $request_id Internal request identifier.
	 * @param int               $row_version Expected row version.
	 * @param string            $checkpoint_code Fixed checkpoint code.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function checkpoint( int $request_id, int $row_version, string $checkpoint_code, DateTimeImmutable $now ): ?PrivacyRequestRecord;

	/**
	 * Conditionally pause processing for one fixed user-action reason.
	 *
	 * @param int                  $request_id Internal request identifier.
	 * @param int                  $row_version Expected row version.
	 * @param PrivacyRequestReason $reason Fixed reason code.
	 * @param DateTimeImmutable    $now Current UTC time.
	 */
	public function require_action( int $request_id, int $row_version, PrivacyRequestReason $reason, DateTimeImmutable $now ): ?PrivacyRequestRecord;

	/**
	 * Conditionally record one retryable processing failure.
	 *
	 * @param int                 $request_id Internal request identifier.
	 * @param int                 $row_version Expected row version.
	 * @param PrivacyRequestError $error Fixed error code.
	 * @param DateTimeImmutable   $now Current UTC time.
	 */
	public function fail( int $request_id, int $row_version, PrivacyRequestError $error, DateTimeImmutable $now ): ?PrivacyRequestRecord;

	/**
	 * Conditionally complete a request and release its unfinished slot.
	 *
	 * @param int               $request_id Internal request identifier.
	 * @param int               $row_version Expected row version.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function complete( int $request_id, int $row_version, DateTimeImmutable $now ): ?PrivacyRequestRecord;

	/**
	 * Conditionally requeue an action-required request after committed recheck.
	 *
	 * @param int               $request_id Internal request identifier.
	 * @param int               $row_version Expected row version.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function requeue( int $request_id, int $row_version, DateTimeImmutable $now ): ?PrivacyRequestRecord;
}
