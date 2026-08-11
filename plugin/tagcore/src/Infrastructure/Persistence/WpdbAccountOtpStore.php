<?php
/**
 * WordPress database Owner Account OTP Store.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use InvalidArgumentException;
use ReturnTag\TagCore\Application\Auth\AccountOtpStore;
use ReturnTag\TagCore\Application\Auth\ActivationOtpVerificationResult;
use ReturnTag\TagCore\Application\Auth\RequestAccountOtp;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceException;
use ReturnTag\TagCore\Application\Persistence\Record\AuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewAuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Keeps Account challenges isolated by purpose and subject type.
 */
final readonly class WpdbAccountOtpStore implements AccountOtpStore {
	/**
	 * Create the Account challenge Store.
	 *
	 * @param WpdbGateway                 $gateway Safe query gateway.
	 * @param TableNames                  $tables Trusted table names.
	 * @param DatabaseDateTimeCodec       $dates UTC datetime codec.
	 * @param WpdbAuthChallengeRepository $challenges Typed challenge Repository.
	 * @param TransactionManager          $transactions Atomic transaction boundary.
	 */
	public function __construct(
		private WpdbGateway $gateway,
		private TableNames $tables,
		private DatabaseDateTimeCodec $dates,
		private WpdbAuthChallengeRepository $challenges,
		private TransactionManager $transactions
	) {
	}

	/**
	 * Count recent Account challenges for one keyed email.
	 *
	 * @param LookupDigest      $email_lookup Keyed email digest.
	 * @param DateTimeImmutable $since Inclusive UTC boundary.
	 */
	public function count_recent_for_email( LookupDigest $email_lookup, DateTimeImmutable $since ): int {
		$row = $this->gateway->row(
			'SELECT COUNT(*) AS challenge_count FROM %i WHERE purpose = %s AND subject_type = %s AND email_lookup = %s AND created_at >= %s',
			array(
				$this->tables->auth_challenges(),
				RequestAccountOtp::PURPOSE,
				RequestAccountOtp::SUBJECT_TYPE,
				$email_lookup->value,
				$this->dates->format( $since ),
			)
		);

		return $this->count_from_row( $row );
	}

	/**
	 * Consume prior matches and insert one unissued challenge atomically.
	 *
	 * @param NewAuthChallengeRecord $challenge Unissued Account challenge.
	 * @throws InvalidArgumentException When the challenge scope is invalid.
	 */
	public function create_replacing( NewAuthChallengeRecord $challenge ): AuthChallengeRecord {
		if (
			RequestAccountOtp::PURPOSE !== $challenge->purpose
			|| RequestAccountOtp::SUBJECT_TYPE !== $challenge->subject_type
			|| $challenge->subject_id !== $challenge->email_lookup->value
		) {
			throw new InvalidArgumentException( 'Account challenge scope is invalid.' );
		}

		return $this->transactions->transactional(
			function () use ( $challenge ): AuthChallengeRecord {
				$this->gateway->execute(
					'UPDATE %i SET consumed_at = %s WHERE purpose = %s AND subject_type = %s AND email_lookup = %s AND consumed_at IS NULL',
					array(
						$this->tables->auth_challenges(),
						$this->dates->format( $challenge->created_at ),
						RequestAccountOtp::PURPOSE,
						RequestAccountOtp::SUBJECT_TYPE,
						$challenge->email_lookup->value,
					)
				);

				return $this->challenges->insert( $challenge );
			}
		);
	}

	/**
	 * Find one challenge by internal identifier.
	 *
	 * @param int $challenge_id Positive challenge identifier.
	 */
	public function find_by_id( int $challenge_id ): ?AuthChallengeRecord {
		return $this->challenges->find_by_id( $challenge_id );
	}

	/**
	 * Claim the latest unissued challenge for dispatch.
	 *
	 * @param int               $challenge_id Positive challenge identifier.
	 * @param OtpHash           $code_hash Issued code hash.
	 * @param DateTimeImmutable $expires_at Issued code expiry.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function claim_for_dispatch(
		int $challenge_id,
		OtpHash $code_hash,
		DateTimeImmutable $expires_at,
		DateTimeImmutable $now
	): ?AuthChallengeRecord {
		return $this->transactions->transactional(
			function () use ( $challenge_id, $code_hash, $expires_at, $now ): ?AuthChallengeRecord {
				$locked = $this->gateway->row(
					'SELECT challenge_id FROM %i WHERE challenge_id = %d FOR UPDATE',
					array( $this->tables->auth_challenges(), $challenge_id )
				);

				if ( null === $locked ) {
					return null;
				}

				$challenge = $this->challenges->find_by_id( $challenge_id );

				if (
					null === $challenge
					|| RequestAccountOtp::PURPOSE !== $challenge->data->purpose
					|| RequestAccountOtp::SUBJECT_TYPE !== $challenge->data->subject_type
					|| 0 !== $challenge->data->send_count
					|| null !== $challenge->data->consumed_at
					|| $challenge->data->expires_at <= $now
				) {
					return null;
				}

				$latest = $this->gateway->row(
					'SELECT challenge_id FROM %i WHERE purpose = %s AND subject_type = %s AND email_lookup = %s AND consumed_at IS NULL ORDER BY created_at DESC, challenge_id DESC LIMIT 1',
					array(
						$this->tables->auth_challenges(),
						RequestAccountOtp::PURPOSE,
						RequestAccountOtp::SUBJECT_TYPE,
						$challenge->data->email_lookup->value,
					)
				);

				if ( null === $latest || (string) ( $latest['challenge_id'] ?? '' ) !== (string) $challenge_id ) {
					return null;
				}

				$updated = $this->gateway->execute(
					'UPDATE %i SET code_hash = %s, send_count = 1, expires_at = %s WHERE challenge_id = %d AND send_count = 0 AND consumed_at IS NULL AND expires_at > %s',
					array(
						$this->tables->auth_challenges(),
						$code_hash->value,
						$this->dates->format( $expires_at ),
						$challenge_id,
						$this->dates->format( $now ),
					)
				);

				return 1 === $updated ? $this->challenges->find_by_id( $challenge_id ) : null;
			}
		);
	}

	/**
	 * Check whether the latest challenge may allocate email scope.
	 *
	 * @param LookupDigest      $email_lookup Keyed email digest.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param int               $maximum_attempts Hard attempt ceiling.
	 */
	public function has_verifiable_latest(
		LookupDigest $email_lookup,
		DateTimeImmutable $now,
		int $maximum_attempts
	): bool {
		$challenge = $this->latest( $email_lookup, false );

		return null !== $challenge
			&& 1 === $challenge->data->send_count
			&& null === $challenge->data->verified_at
			&& null === $challenge->data->consumed_at
			&& $challenge->data->expires_at > $now
			&& $challenge->data->attempt_count < $maximum_attempts;
	}

	/**
	 * Verify the latest Account challenge atomically.
	 *
	 * @param LookupDigest            $email_lookup Keyed email digest.
	 * @param DateTimeImmutable       $now Current UTC time.
	 * @param int                     $maximum_attempts Hard attempt ceiling.
	 * @param callable(OtpHash): bool $matches Constant-time code comparison.
	 * @throws InvalidArgumentException When the attempt ceiling is invalid.
	 */
	public function verify_latest(
		LookupDigest $email_lookup,
		DateTimeImmutable $now,
		int $maximum_attempts,
		callable $matches
	): ActivationOtpVerificationResult {
		if ( $maximum_attempts < 1 ) {
			throw new InvalidArgumentException( 'Maximum attempts must be positive.' );
		}

		return $this->transactions->transactional(
			function () use ( $email_lookup, $now, $maximum_attempts, $matches ): ActivationOtpVerificationResult {
				$challenge = $this->latest( $email_lookup, true );

				if (
					null === $challenge
					|| 1 !== $challenge->data->send_count
					|| null !== $challenge->data->verified_at
					|| null !== $challenge->data->consumed_at
					|| $challenge->data->expires_at <= $now
					|| $challenge->data->attempt_count >= $maximum_attempts
				) {
					return ActivationOtpVerificationResult::INVALID;
				}

				if ( ! $matches( $challenge->data->code_hash ) ) {
					$updated = $this->gateway->execute(
						'UPDATE %i SET attempt_count = attempt_count + 1 WHERE challenge_id = %d AND attempt_count = %d AND attempt_count < %d AND send_count = 1 AND verified_at IS NULL AND consumed_at IS NULL AND expires_at > %s',
						array(
							$this->tables->auth_challenges(),
							$challenge->challenge_id,
							$challenge->data->attempt_count,
							$maximum_attempts,
							$this->dates->format( $now ),
						)
					);

					if ( 1 !== $updated ) {
						throw new PersistenceException( 'Account OTP attempt could not be recorded.' );
					}

					return ActivationOtpVerificationResult::INVALID;
				}

				$updated = $this->gateway->execute(
					'UPDATE %i SET verified_at = %s, consumed_at = %s WHERE challenge_id = %d AND attempt_count < %d AND send_count = 1 AND verified_at IS NULL AND consumed_at IS NULL AND expires_at > %s',
					array(
						$this->tables->auth_challenges(),
						$this->dates->format( $now ),
						$this->dates->format( $now ),
						$challenge->challenge_id,
						$maximum_attempts,
						$this->dates->format( $now ),
					)
				);

				if ( 1 !== $updated ) {
					throw new PersistenceException( 'Account OTP verification could not be completed.' );
				}

				return ActivationOtpVerificationResult::VERIFIED;
			}
		);
	}

	/**
	 * Revoke one unissued challenge after queue failure.
	 *
	 * @param int               $challenge_id Positive challenge identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function revoke_unissued( int $challenge_id, DateTimeImmutable $now ): void {
		$this->gateway->execute(
			'UPDATE %i SET consumed_at = %s WHERE challenge_id = %d AND purpose = %s AND send_count = 0 AND consumed_at IS NULL',
			array( $this->tables->auth_challenges(), $this->dates->format( $now ), $challenge_id, RequestAccountOtp::PURPOSE )
		);
	}

	/**
	 * Delete one bounded set of expired Account challenges.
	 *
	 * @param DateTimeImmutable $before Exclusive UTC boundary.
	 * @param int               $limit Maximum rows removed.
	 */
	public function cleanup_expired( DateTimeImmutable $before, int $limit ): int {
		return $this->gateway->execute(
			'DELETE FROM %i WHERE purpose = %s AND expires_at < %s ORDER BY expires_at ASC LIMIT %d',
			array( $this->tables->auth_challenges(), RequestAccountOtp::PURPOSE, $this->dates->format( $before ), max( 1, min( 500, $limit ) ) )
		);
	}

	/**
	 * Return the latest Account challenge for one keyed email.
	 *
	 * @param LookupDigest $email_lookup Keyed email digest.
	 * @param bool         $lock Whether to lock the selected row.
	 * @throws PersistenceException When the selected identifier is invalid.
	 */
	private function latest( LookupDigest $email_lookup, bool $lock ): ?AuthChallengeRecord {
		$sql = 'SELECT challenge_id FROM %i WHERE purpose = %s AND subject_type = %s AND email_lookup = %s ORDER BY created_at DESC, challenge_id DESC LIMIT 1';

		if ( $lock ) {
			$sql .= ' FOR UPDATE';
		}

		$row = $this->gateway->row(
			$sql,
			array( $this->tables->auth_challenges(), RequestAccountOtp::PURPOSE, RequestAccountOtp::SUBJECT_TYPE, $email_lookup->value )
		);

		if ( null === $row ) {
			return null;
		}

		$value = $row['challenge_id'] ?? null;

		if ( ! is_int( $value ) && ! ( is_string( $value ) && ctype_digit( $value ) ) ) {
			throw new PersistenceException( 'Account challenge identifier is invalid.' );
		}

		return $this->challenges->find_by_id( (int) $value );
	}

	/**
	 * Read one non-negative aggregate result.
	 *
	 * @param array<string, mixed>|null $row Aggregate row.
	 * @throws PersistenceException When the aggregate shape is invalid.
	 */
	private function count_from_row( ?array $row ): int {
		$value = $row['challenge_count'] ?? null;

		if ( is_int( $value ) && $value >= 0 ) {
			return $value;
		}

		if ( is_string( $value ) && ctype_digit( $value ) ) {
			return (int) $value;
		}

		throw new PersistenceException( 'Account OTP aggregate is invalid.' );
	}
}
