<?php
/**
 * WordPress database Finder email verification store.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\FinderReport\FinderEmailVerification;
use ReturnTag\TagCore\Application\FinderReport\FinderEmailVerificationStore;
use ReturnTag\TagCore\Application\Persistence\Record\AuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewAuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/** Row-locked Finder email OTP storage over the shared challenge table. */
final readonly class WpdbFinderEmailVerificationStore implements FinderEmailVerificationStore {
	/**
	 * Create the store.
	 *
	 * @param WpdbGateway                 $gateway Safe database gateway.
	 * @param TableNames                  $tables Trusted table names.
	 * @param DatabaseDateTimeCodec       $dates UTC datetime codec.
	 * @param WpdbAuthChallengeRepository $challenges Challenge repository.
	 * @param TransactionManager          $transactions Atomic boundary.
	 */
	public function __construct( private WpdbGateway $gateway, private TableNames $tables, private DatabaseDateTimeCodec $dates, private WpdbAuthChallengeRepository $challenges, private TransactionManager $transactions ) {
	}

	/**
	 * Count recent requests for one keyed email.
	 *
	 * @param LookupDigest      $lookup Keyed email lookup.
	 * @param DateTimeImmutable $since Inclusive UTC boundary.
	 */
	public function count_recent_for_email( LookupDigest $lookup, DateTimeImmutable $since ): int {
		return $this->count( 'SELECT COUNT(*) AS challenge_count FROM %i WHERE purpose = %s AND email_lookup = %s AND created_at >= %s', array( $this->tables->auth_challenges(), FinderEmailVerification::PURPOSE, $lookup->value, $this->dates->format( $since ) ) );
	}

	/**
	 * Count recent requests for one report.
	 *
	 * @param int               $finder_report_id Internal report identifier.
	 * @param DateTimeImmutable $since Inclusive UTC boundary.
	 */
	public function count_recent_for_report( int $finder_report_id, DateTimeImmutable $since ): int {
		return $this->count( 'SELECT COUNT(*) AS challenge_count FROM %i WHERE purpose = %s AND subject_type = %s AND subject_id = %s AND created_at >= %s', array( $this->tables->auth_challenges(), FinderEmailVerification::PURPOSE, FinderEmailVerification::SUBJECT_TYPE, (string) $finder_report_id, $this->dates->format( $since ) ) );
	}

	/**
	 * Replace open challenges with one new placeholder.
	 *
	 * @param NewAuthChallengeRecord $challenge New placeholder challenge.
	 */
	public function create_replacing( NewAuthChallengeRecord $challenge ): AuthChallengeRecord {
		return $this->transactions->transactional(
			function () use ( $challenge ): AuthChallengeRecord {
				$this->gateway->execute( 'UPDATE %i SET consumed_at = %s WHERE purpose = %s AND subject_type = %s AND subject_id = %s AND consumed_at IS NULL', array( $this->tables->auth_challenges(), $this->dates->format( $challenge->created_at ), FinderEmailVerification::PURPOSE, FinderEmailVerification::SUBJECT_TYPE, $challenge->subject_id ) );
				return $this->challenges->insert( $challenge );
			}
		);
	}

	/**
	 * Find one challenge by identifier.
	 *
	 * @param int $challenge_id Internal challenge identifier.
	 */
	public function find_by_id( int $challenge_id ): ?AuthChallengeRecord {
		return $this->challenges->find_by_id( $challenge_id );
	}

	/**
	 * Atomically issue one queued challenge.
	 *
	 * @param int               $challenge_id Internal challenge identifier.
	 * @param OtpHash           $hash Issued OTP hash.
	 * @param DateTimeImmutable $expires_at Issued expiry.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function claim_for_dispatch( int $challenge_id, OtpHash $hash, DateTimeImmutable $expires_at, DateTimeImmutable $now ): ?AuthChallengeRecord {
		return $this->transactions->transactional(
			function () use ( $challenge_id, $hash, $expires_at, $now ): ?AuthChallengeRecord {
				$locked = $this->gateway->row( 'SELECT challenge_id FROM %i WHERE challenge_id = %d FOR UPDATE', array( $this->tables->auth_challenges(), $challenge_id ) );
				$record = null === $locked ? null : $this->challenges->find_by_id( $challenge_id );
				if ( null === $record || FinderEmailVerification::PURPOSE !== $record->data->purpose || FinderEmailVerification::SUBJECT_TYPE !== $record->data->subject_type || 0 !== $record->data->send_count || null !== $record->data->consumed_at || $record->data->expires_at <= $now ) {
						return null;
				}
				$updated = $this->gateway->execute( 'UPDATE %i SET code_hash = %s, send_count = 1, expires_at = %s WHERE challenge_id = %d AND send_count = 0 AND consumed_at IS NULL AND expires_at > %s', array( $this->tables->auth_challenges(), $hash->value, $this->dates->format( $expires_at ), $challenge_id, $this->dates->format( $now ) ) );
				return 1 === $updated ? $this->challenges->find_by_id( $challenge_id ) : null;
			}
		);
	}

	/**
	 * Verify and run the success mutation under one row lock.
	 *
	 * @param int               $finder_report_id Internal report identifier.
	 * @param LookupDigest      $lookup Keyed email lookup.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param int               $maximum_attempts Attempt ceiling.
	 * @param callable          $matches Constant-time matcher.
	 * @param callable          $on_verified Atomic success callback.
	 */
	public function verify_latest( int $finder_report_id, LookupDigest $lookup, DateTimeImmutable $now, int $maximum_attempts, callable $matches, callable $on_verified ): ?AuthChallengeRecord {
		return $this->transactions->transactional(
			function () use ( $finder_report_id, $lookup, $now, $maximum_attempts, $matches, $on_verified ): ?AuthChallengeRecord {
				$row = $this->gateway->row( 'SELECT challenge_id FROM %i WHERE purpose = %s AND subject_type = %s AND subject_id = %s AND email_lookup = %s ORDER BY created_at DESC, challenge_id DESC LIMIT 1 FOR UPDATE', array( $this->tables->auth_challenges(), FinderEmailVerification::PURPOSE, FinderEmailVerification::SUBJECT_TYPE, (string) $finder_report_id, $lookup->value ) );
				$id  = $row['challenge_id'] ?? null;
				if ( ! is_numeric( $id ) ) {
						return null;
				}
				$record = $this->challenges->find_by_id( (int) $id );
				if ( null === $record || 1 !== $record->data->send_count || null !== $record->data->verified_at || null !== $record->data->consumed_at || $record->data->expires_at <= $now || $record->data->attempt_count >= $maximum_attempts ) {
					return null;
				}
				if ( ! $matches( $record->data->code_hash ) ) {
					$this->gateway->execute( 'UPDATE %i SET attempt_count = attempt_count + 1 WHERE challenge_id = %d AND attempt_count = %d AND attempt_count < %d AND consumed_at IS NULL', array( $this->tables->auth_challenges(), $record->challenge_id, $record->data->attempt_count, $maximum_attempts ) );
					return null;
				}
				$on_verified( $record );
				$updated = $this->gateway->execute( 'UPDATE %i SET verified_at = %s, consumed_at = %s WHERE challenge_id = %d AND attempt_count < %d AND send_count = 1 AND verified_at IS NULL AND consumed_at IS NULL AND expires_at > %s', array( $this->tables->auth_challenges(), $this->dates->format( $now ), $this->dates->format( $now ), $record->challenge_id, $maximum_attempts, $this->dates->format( $now ) ) );
				return 1 === $updated ? $this->challenges->find_by_id( $record->challenge_id ) : null;
			}
		);
	}

	/**
	 * Revoke one unissued challenge.
	 *
	 * @param int               $challenge_id Internal challenge identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function revoke_unissued( int $challenge_id, DateTimeImmutable $now ): void {
		$this->gateway->execute( 'UPDATE %i SET consumed_at = %s WHERE challenge_id = %d AND send_count = 0 AND consumed_at IS NULL', array( $this->tables->auth_challenges(), $this->dates->format( $now ), $challenge_id ) );
	}

	/**
	 * Read one aggregate count safely.
	 *
	 * @param string $query Prepared gateway query template.
	 * @param array  $arguments Query arguments.
	 * @phpstan-param list<mixed> $arguments
	 */
	private function count( string $query, array $arguments ): int {
		$row   = $this->gateway->row( $query, $arguments );
		$value = $row['challenge_count'] ?? null;
		return is_numeric( $value ) ? (int) $value : 0;
	}
}
