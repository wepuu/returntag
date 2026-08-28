<?php
/**
 * Metadata-only privacy request repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Privacy\PrivacyRequestRecord;
use ReturnTag\TagCore\Application\Privacy\PrivacyRequestRepository;
use ReturnTag\TagCore\Application\Privacy\PrivacyRequestStart;
use ReturnTag\TagCore\Application\Privacy\PrivacyRequestSubject;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestError;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestReason;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestState;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestType;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/** Never stores an email, IP address, request payload, evidence, token, or note. */
final class WpdbPrivacyRequestRepository implements PrivacyRequestRepository {
	/**
	 * Create the privacy request repository.
	 *
	 * @param WpdbGateway           $gateway Safe prepared-query gateway.
	 * @param TableNames            $tables Trusted table names.
	 * @param DatabaseDateTimeCodec $dates UTC database codec.
	 */
	public function __construct(
		private readonly WpdbGateway $gateway,
		private readonly TableNames $tables,
		private readonly DatabaseDateTimeCodec $dates
	) {}

	/**
	 * Create or resolve the single unfinished request for one subject/type.
	 *
	 * @param PrivacyRequestSubject $subject Privacy-safe requester reference.
	 * @param PrivacyRequestType    $type Approved request type.
	 * @param string                $policy_version Frozen policy identifier.
	 * @param string                $idempotency_key Request idempotency digest.
	 * @param string                $active_request_key Unique unfinished slot digest.
	 * @param DateTimeImmutable     $now Current UTC time.
	 * @throws PersistenceMappingException When a conflicting stored row is invalid.
	 */
	public function begin( PrivacyRequestSubject $subject, PrivacyRequestType $type, string $policy_version, string $idempotency_key, string $active_request_key, DateTimeImmutable $now ): PrivacyRequestStart {
		$time     = $this->dates->format( $now );
		$inserted = 1 === $this->gateway->execute(
			'INSERT INTO %i (requester_type,requester_user_id,requester_key,request_type,request_state,policy_version,idempotency_key,active_request_key,attempt_count,row_version,created_at,updated_at) VALUES (%s,NULLIF(%d,0),%s,%s,%s,%s,%s,%s,0,1,%s,%s) ON DUPLICATE KEY UPDATE request_id=request_id',
			array(
				$this->tables->privacy_requests(),
				$subject->requester_type,
				$subject->user_id ?? 0,
				$subject->requester_key,
				$type->value,
				PrivacyRequestState::QUEUED->value,
				$policy_version,
				$idempotency_key,
				$active_request_key,
				$time,
				$time,
			)
		);

		$row = $this->gateway->row(
			'SELECT * FROM %i WHERE idempotency_key=%s OR active_request_key=%s ORDER BY CASE WHEN idempotency_key=%s THEN 0 ELSE 1 END,request_id ASC LIMIT 1',
			array( $this->tables->privacy_requests(), $idempotency_key, $active_request_key, $idempotency_key )
		);
		if ( null === $row ) {
			throw new PersistenceMappingException( 'Stored privacy request is unavailable.' );
		}

		$record = $this->hydrate( $row );
		if (
			$record->subject->requester_type !== $subject->requester_type
			|| $record->subject->user_id !== $subject->user_id
			|| $record->subject->requester_key !== $subject->requester_key
			|| $record->request_type !== $type
		) {
			throw new PersistenceMappingException( 'Stored privacy request is invalid.' );
		}

		return new PrivacyRequestStart( $record, $inserted );
	}

	/**
	 * Return one request by internal ID.
	 *
	 * @param int $request_id Internal request identifier.
	 */
	public function find( int $request_id ): ?PrivacyRequestRecord {
		$row = $this->gateway->row( 'SELECT * FROM %i WHERE request_id=%d LIMIT 1', array( $this->tables->privacy_requests(), $request_id ) );
		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Conditionally claim a queued or failed request.
	 *
	 * @param int               $request_id Internal request identifier.
	 * @param int               $row_version Expected row version.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function claim( int $request_id, int $row_version, DateTimeImmutable $now ): ?PrivacyRequestRecord {
		$time = $this->dates->format( $now );
		return $this->transition(
			'UPDATE %i SET request_state=%s,checkpoint_code=%s,reason_code=NULL,error_code=NULL,processing_started_at=%s,action_required_at=NULL,failed_at=NULL,attempt_count=attempt_count+1,row_version=row_version+1,updated_at=%s WHERE request_id=%d AND row_version=%d AND request_state IN (%s,%s)',
			array( $this->tables->privacy_requests(), PrivacyRequestState::PROCESSING->value, 'processing_claimed', $time, $time, $request_id, $row_version, PrivacyRequestState::QUEUED->value, PrivacyRequestState::FAILED->value ),
			$request_id
		);
	}

	/**
	 * Conditionally advance one fixed processing checkpoint.
	 *
	 * @param int               $request_id Internal request identifier.
	 * @param int               $row_version Expected row version.
	 * @param string            $checkpoint_code Fixed checkpoint code.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function checkpoint( int $request_id, int $row_version, string $checkpoint_code, DateTimeImmutable $now ): ?PrivacyRequestRecord {
		return $this->transition(
			'UPDATE %i SET checkpoint_code=%s,row_version=row_version+1,updated_at=%s WHERE request_id=%d AND row_version=%d AND request_state=%s',
			array( $this->tables->privacy_requests(), $checkpoint_code, $this->dates->format( $now ), $request_id, $row_version, PrivacyRequestState::PROCESSING->value ),
			$request_id
		);
	}

	/**
	 * Conditionally pause processing for one fixed reason.
	 *
	 * @param int                  $request_id Internal request identifier.
	 * @param int                  $row_version Expected row version.
	 * @param PrivacyRequestReason $reason Fixed reason code.
	 * @param DateTimeImmutable    $now Current UTC time.
	 */
	public function require_action( int $request_id, int $row_version, PrivacyRequestReason $reason, DateTimeImmutable $now ): ?PrivacyRequestRecord {
		$time = $this->dates->format( $now );
		return $this->transition(
			'UPDATE %i SET request_state=%s,reason_code=%s,error_code=NULL,action_required_at=%s,row_version=row_version+1,updated_at=%s WHERE request_id=%d AND row_version=%d AND request_state=%s',
			array( $this->tables->privacy_requests(), PrivacyRequestState::ACTION_REQUIRED->value, $reason->value, $time, $time, $request_id, $row_version, PrivacyRequestState::PROCESSING->value ),
			$request_id
		);
	}

	/**
	 * Conditionally record one retryable processing failure.
	 *
	 * @param int                 $request_id Internal request identifier.
	 * @param int                 $row_version Expected row version.
	 * @param PrivacyRequestError $error Fixed error code.
	 * @param DateTimeImmutable   $now Current UTC time.
	 */
	public function fail( int $request_id, int $row_version, PrivacyRequestError $error, DateTimeImmutable $now ): ?PrivacyRequestRecord {
		$time = $this->dates->format( $now );
		return $this->transition(
			'UPDATE %i SET request_state=%s,reason_code=NULL,error_code=%s,failed_at=%s,row_version=row_version+1,updated_at=%s WHERE request_id=%d AND row_version=%d AND request_state=%s',
			array( $this->tables->privacy_requests(), PrivacyRequestState::FAILED->value, $error->value, $time, $time, $request_id, $row_version, PrivacyRequestState::PROCESSING->value ),
			$request_id
		);
	}

	/**
	 * Conditionally complete a request and release its slot.
	 *
	 * @param int               $request_id Internal request identifier.
	 * @param int               $row_version Expected row version.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function complete( int $request_id, int $row_version, DateTimeImmutable $now ): ?PrivacyRequestRecord {
		$time = $this->dates->format( $now );
		return $this->transition(
			'UPDATE %i SET request_state=%s,active_request_key=NULL,reason_code=NULL,error_code=NULL,completed_at=%s,row_version=row_version+1,updated_at=%s WHERE request_id=%d AND row_version=%d AND request_state=%s',
			array( $this->tables->privacy_requests(), PrivacyRequestState::COMPLETED->value, $time, $time, $request_id, $row_version, PrivacyRequestState::PROCESSING->value ),
			$request_id
		);
	}

	/**
	 * Conditionally requeue an action-required request.
	 *
	 * @param int               $request_id Internal request identifier.
	 * @param int               $row_version Expected row version.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function requeue( int $request_id, int $row_version, DateTimeImmutable $now ): ?PrivacyRequestRecord {
		return $this->transition(
			'UPDATE %i SET request_state=%s,reason_code=NULL,error_code=NULL,action_required_at=NULL,row_version=row_version+1,updated_at=%s WHERE request_id=%d AND row_version=%d AND request_state=%s',
			array( $this->tables->privacy_requests(), PrivacyRequestState::QUEUED->value, $this->dates->format( $now ), $request_id, $row_version, PrivacyRequestState::ACTION_REQUIRED->value ),
			$request_id
		);
	}

	/**
	 * Execute one conditional transition and reload the committed row.
	 *
	 * @param string $query Trusted prepared-query template.
	 * @param array  $arguments Prepared query arguments.
	 * @param int    $request_id Internal request identifier.
	 * @phpstan-param list<mixed> $arguments
	 */
	private function transition( string $query, array $arguments, int $request_id ): ?PrivacyRequestRecord {
		if ( 1 !== $this->gateway->execute( $query, $arguments ) ) {
			return null;
		}

		return $this->find( $request_id );
	}

	/**
	 * Hydrate one strict stored request row.
	 *
	 * @param array<string,mixed> $row Stored row.
	 * @throws PersistenceMappingException When stored data is invalid.
	 */
	private function hydrate( array $row ): PrivacyRequestRecord {
		try {
			$requester_type = StoredRow::string( $row, 'requester_type' );
			$user_id        = StoredRow::nullable_positive_int( $row, 'requester_user_id' );
			$reason_value   = StoredRow::nullable_string( $row, 'reason_code' );
			$error_value    = StoredRow::nullable_string( $row, 'error_code' );
			$reason         = null === $reason_value ? null : PrivacyRequestReason::tryFrom( $reason_value );
			$error          = null === $error_value ? null : PrivacyRequestError::tryFrom( $error_value );
			if ( ( null !== $reason_value && null === $reason ) || ( null !== $error_value && null === $error ) ) {
				throw new PersistenceMappingException( 'Stored privacy request is invalid.' );
			}

			return new PrivacyRequestRecord(
				StoredRow::positive_int( $row, 'request_id' ),
				new PrivacyRequestSubject( $requester_type, $user_id, StoredRow::string( $row, 'requester_key' ) ),
				$this->enum( $row, 'request_type', PrivacyRequestType::class ),
				$this->enum( $row, 'request_state', PrivacyRequestState::class ),
				StoredRow::string( $row, 'policy_version' ),
				StoredRow::string( $row, 'idempotency_key' ),
				StoredRow::nullable_string( $row, 'active_request_key' ),
				StoredRow::nullable_string( $row, 'checkpoint_code' ),
				StoredRow::unsigned_int( $row, 'attempt_count' ),
				StoredRow::positive_int( $row, 'row_version' ),
				$reason,
				$error,
				$this->dates->parse( StoredRow::string( $row, 'created_at' ) ),
				$this->dates->parse( StoredRow::string( $row, 'updated_at' ) ),
				$this->date( $row, 'processing_started_at' ),
				$this->date( $row, 'action_required_at' ),
				$this->date( $row, 'completed_at' ),
				$this->date( $row, 'failed_at' )
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored privacy request is invalid.' );
		}
	}

	/**
	 * Hydrate one strict backed Enum.
	 *
	 * @template T of \BackedEnum
	 * @param array<string,mixed> $row Stored row.
	 * @param string              $key Column name.
	 * @param string              $enum_class Enum class.
	 * @return T
	 * @phpstan-param class-string<T> $enum_class
	 */
	private function enum( array $row, string $key, string $enum_class ): \BackedEnum {
		// @phpstan-var T $value
		$value = StoredRow::enum( $row, $key, $enum_class );
		return $value;
	}

	/**
	 * Hydrate one optional UTC datetime.
	 *
	 * @param array<string,mixed> $row Stored row.
	 * @param string              $key Column name.
	 */
	private function date( array $row, string $key ): ?DateTimeImmutable {
		$value = StoredRow::nullable_string( $row, $key );
		return null === $value ? null : $this->dates->parse( $value );
	}
}
