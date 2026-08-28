<?php
/**
 * Privacy request persistence orchestration.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Privacy;

use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Application\Privacy\Exception\PrivacyRequestConflict;
use ReturnTag\TagCore\Application\Privacy\Exception\PrivacyRequestUnavailable;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestError;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestReason;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestType;

/** Coordinates metadata-only request state and audit events. */
final class PrivacyRequestWorkflow {
	public const POLICY_VERSION = 'FORGETAG-PRIVACY-RETENTION-v1.0-20260827';

	/**
	 * Create the privacy request workflow.
	 *
	 * @param PrivacyRequestRepository $requests Request persistence.
	 * @param EventRepository          $events Metadata-free Event persistence.
	 * @param TransactionManager       $transactions Database transaction boundary.
	 * @param FeatureFlagReader        $flags Operational controls.
	 * @param Clock                    $clock UTC clock.
	 */
	public function __construct(
		private readonly PrivacyRequestRepository $requests,
		private readonly EventRepository $events,
		private readonly TransactionManager $transactions,
		private readonly FeatureFlagReader $flags,
		private readonly Clock $clock
	) {}

	/**
	 * Create or resolve one idempotent request.
	 *
	 * @param PrivacyRequestSubject $subject Privacy-safe requester reference.
	 * @param PrivacyRequestType    $type Approved request type.
	 * @param string                $idempotency_key Request idempotency digest.
	 * @throws PrivacyRequestUnavailable When intake is disabled.
	 */
	public function start( PrivacyRequestSubject $subject, PrivacyRequestType $type, string $idempotency_key ): PrivacyRequestStart {
		$this->assert_enabled( FeatureFlag::PRIVACY_REQUEST_INTAKE );
		RecordValidator::digest( $idempotency_key, 'idempotency_key' );
		$active_key = hash( 'sha256', "returntag_privacy_request\0{$subject->requester_key}\0{$type->value}" );
		$now        = $this->clock->now();

		return $this->transactions->transactional(
			function () use ( $subject, $type, $idempotency_key, $active_key, $now ): PrivacyRequestStart {
				$start = $this->requests->begin( $subject, $type, self::POLICY_VERSION, $idempotency_key, $active_key, $now );
				if ( $start->created ) {
					$this->append_event( 'privacy_request_queued', $subject->requester_type, $subject->user_id, $start->request, 'queued' );
				}

				return $start;
			}
		);
	}

	/**
	 * Claim one queued or failed request for processing.
	 *
	 * @param int $request_id Internal request identifier.
	 * @param int $row_version Expected row version.
	 * @throws PrivacyRequestUnavailable When processing is disabled.
	 * @throws PrivacyRequestConflict When the request state or version changed.
	 */
	public function claim( int $request_id, int $row_version ): PrivacyRequestRecord {
		return $this->transition( $request_id, $row_version, 'privacy_request_processing', 'processing', fn() => $this->requests->claim( $request_id, $row_version, $this->clock->now() ) );
	}

	/**
	 * Persist one fixed processing checkpoint.
	 *
	 * @param int    $request_id Internal request identifier.
	 * @param int    $row_version Expected row version.
	 * @param string $checkpoint_code Fixed checkpoint code.
	 * @throws PrivacyRequestConflict When the checkpoint or request state is invalid.
	 */
	public function checkpoint( int $request_id, int $row_version, string $checkpoint_code ): PrivacyRequestRecord {
		$this->assert_processing_enabled();
		RecordValidator::ascii( $checkpoint_code, 64, 'checkpoint_code' );
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{0,63}$/D', $checkpoint_code ) ) {
			throw new PrivacyRequestConflict( 'Privacy request state conflict.' );
		}
		$request = $this->requests->checkpoint( $request_id, $row_version, $checkpoint_code, $this->clock->now() );
		return $this->require_request( $request );
	}

	/**
	 * Pause one request for a fixed user-action reason.
	 *
	 * @param int                  $request_id Internal request identifier.
	 * @param int                  $row_version Expected row version.
	 * @param PrivacyRequestReason $reason Fixed reason code.
	 * @throws PrivacyRequestUnavailable When processing is disabled.
	 * @throws PrivacyRequestConflict When the request state or version changed.
	 */
	public function require_action( int $request_id, int $row_version, PrivacyRequestReason $reason ): PrivacyRequestRecord {
		return $this->transition( $request_id, $row_version, 'privacy_request_action_required', 'action_required', fn() => $this->requests->require_action( $request_id, $row_version, $reason, $this->clock->now() ) );
	}

	/**
	 * Record one retryable processing failure.
	 *
	 * @param int                 $request_id Internal request identifier.
	 * @param int                 $row_version Expected row version.
	 * @param PrivacyRequestError $error Fixed error code.
	 * @throws PrivacyRequestUnavailable When processing is disabled.
	 * @throws PrivacyRequestConflict When the request state or version changed.
	 */
	public function fail( int $request_id, int $row_version, PrivacyRequestError $error ): PrivacyRequestRecord {
		return $this->transition( $request_id, $row_version, 'privacy_request_failed', 'failed', fn() => $this->requests->fail( $request_id, $row_version, $error, $this->clock->now() ) );
	}

	/**
	 * Complete one request and release its unfinished slot.
	 *
	 * @param int $request_id Internal request identifier.
	 * @param int $row_version Expected row version.
	 * @throws PrivacyRequestUnavailable When processing is disabled.
	 * @throws PrivacyRequestConflict When the request state or version changed.
	 */
	public function complete( int $request_id, int $row_version ): PrivacyRequestRecord {
		return $this->transition( $request_id, $row_version, 'privacy_request_completed', 'completed', fn() => $this->requests->complete( $request_id, $row_version, $this->clock->now() ) );
	}

	/**
	 * Requeue one action-required request after a committed recheck.
	 *
	 * @param int $request_id Internal request identifier.
	 * @param int $row_version Expected row version.
	 * @throws PrivacyRequestUnavailable When processing is disabled.
	 * @throws PrivacyRequestConflict When the request state or version changed.
	 */
	public function requeue( int $request_id, int $row_version ): PrivacyRequestRecord {
		return $this->transition( $request_id, $row_version, 'privacy_request_queued', 'queued', fn() => $this->requests->requeue( $request_id, $row_version, $this->clock->now() ) );
	}

	/**
	 * Execute one conditional transition and append its Event atomically.
	 *
	 * @param int                              $request_id Internal request identifier.
	 * @param int                              $row_version Expected row version.
	 * @param string                           $event_type Fixed Event type.
	 * @param string                           $result Fixed Event result.
	 * @param callable():?PrivacyRequestRecord $operation Persistence transition.
	 * @throws PrivacyRequestUnavailable When processing is disabled.
	 * @throws PrivacyRequestConflict When the request state or version changed.
	 */
	private function transition( int $request_id, int $row_version, string $event_type, string $result, callable $operation ): PrivacyRequestRecord {
		$this->assert_processing_enabled();
		RecordValidator::positive_id( $request_id, 'request_id' );
		RecordValidator::positive_id( $row_version, 'row_version' );

		return $this->transactions->transactional(
			function () use ( $operation, $event_type, $result ): PrivacyRequestRecord {
				$request = $this->require_request( $operation() );
				$this->append_event( $event_type, 'system', null, $request, $result );
				return $request;
			}
		);
	}

	/**
	 * Append one metadata-free privacy request Event.
	 *
	 * @param string               $event_type Fixed Event type.
	 * @param string               $actor_type Approved actor type.
	 * @param int|null             $actor_id Optional internal actor ID.
	 * @param PrivacyRequestRecord $request Persisted request.
	 * @param string               $result Fixed Event result.
	 */
	private function append_event( string $event_type, string $actor_type, ?int $actor_id, PrivacyRequestRecord $request, string $result ): void {
		$this->events->append(
			new NewEventRecord(
				$event_type,
				$actor_type,
				$actor_id,
				'privacy_request',
				(string) $request->request_id,
				$result,
				null,
				EventMetadata::none(),
				$this->clock->now()
			)
		);
	}

	/**
	 * Require the processing kill switch.
	 *
	 * @throws PrivacyRequestUnavailable When processing is disabled.
	 */
	private function assert_processing_enabled(): void {
		$this->assert_enabled( FeatureFlag::PRIVACY_REQUEST_PROCESSING );
	}

	/**
	 * Require one operational feature flag.
	 *
	 * @param FeatureFlag $flag Required control.
	 * @throws PrivacyRequestUnavailable When the control is disabled.
	 */
	private function assert_enabled( FeatureFlag $flag ): void {
		if ( ! $this->flags->is_enabled( $flag ) ) {
			throw new PrivacyRequestUnavailable( 'Privacy request runtime is unavailable.' );
		}
	}

	/**
	 * Require a successful conditional persistence result.
	 *
	 * @param PrivacyRequestRecord|null $request Optional persisted request.
	 * @throws PrivacyRequestConflict When no row matched the expected state and version.
	 */
	private function require_request( ?PrivacyRequestRecord $request ): PrivacyRequestRecord {
		if ( null === $request ) {
			throw new PrivacyRequestConflict( 'Privacy request state conflict.' );
		}
		return $request;
	}
}
