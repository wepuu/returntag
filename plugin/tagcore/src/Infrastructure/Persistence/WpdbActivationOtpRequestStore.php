<?php
/**
 * WordPress database activation OTP workflow store.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Auth\ActivationOtpRequestStore;
use ReturnTag\TagCore\Application\Auth\RequestActivationOtp;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceException;
use ReturnTag\TagCore\Application\Persistence\Record\AuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewAuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Implements indexed counts and atomic unissued-to-issued transitions.
 */
final readonly class WpdbActivationOtpRequestStore implements ActivationOtpRequestStore {
	/**
	 * Create the workflow store.
	 *
	 * @param WpdbGateway                 $gateway Safe query gateway.
	 * @param TableNames                  $tables Trusted table names.
	 * @param DatabaseDateTimeCodec       $dates UTC codec.
	 * @param WpdbAuthChallengeRepository $challenges Typed challenge repository.
	 * @param TransactionManager          $transactions Transaction boundary.
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
	 * Count recent requests by indexed email lookup.
	 *
	 * @param LookupDigest      $email_lookup Keyed email digest.
	 * @param DateTimeImmutable $since Inclusive UTC boundary.
	 */
	public function count_recent_for_email( LookupDigest $email_lookup, DateTimeImmutable $since ): int {
		$row = $this->gateway->row(
			'SELECT COUNT(*) AS challenge_count FROM %i WHERE purpose = %s AND email_lookup = %s AND created_at >= %s',
			array(
				$this->tables->auth_challenges(),
				RequestActivationOtp::PURPOSE,
				$email_lookup->value,
				$this->dates->format( $since ),
			)
		);

		return $this->count_from_row( $row );
	}

	/**
	 * Count recent requests by indexed Tag subject.
	 *
	 * @param TagId             $tag_id Public Tag.
	 * @param DateTimeImmutable $since Inclusive UTC boundary.
	 */
	public function count_recent_for_tag( TagId $tag_id, DateTimeImmutable $since ): int {
		$row = $this->gateway->row(
			'SELECT COUNT(*) AS challenge_count FROM %i WHERE subject_type = %s AND subject_id = %s AND created_at >= %s',
			array(
				$this->tables->auth_challenges(),
				RequestActivationOtp::SUBJECT_TYPE,
				$tag_id->value,
				$this->dates->format( $since ),
			)
		);

		return $this->count_from_row( $row );
	}

	/**
	 * Consume matching open challenges and insert the replacement atomically.
	 *
	 * @param NewAuthChallengeRecord $challenge Unissued challenge.
	 */
	public function create_replacing( NewAuthChallengeRecord $challenge ): AuthChallengeRecord {
		return $this->transactions->transactional(
			function () use ( $challenge ): AuthChallengeRecord {
				$this->gateway->execute(
					'UPDATE %i SET consumed_at = %s WHERE purpose = %s AND subject_type = %s AND subject_id = %s AND email_lookup = %s AND consumed_at IS NULL',
					array(
						$this->tables->auth_challenges(),
						$this->dates->format( $challenge->created_at ),
						RequestActivationOtp::PURPOSE,
						RequestActivationOtp::SUBJECT_TYPE,
						$challenge->subject_id,
						$challenge->email_lookup->value,
					)
				);

				return $this->challenges->insert( $challenge );
			}
		);
	}

	/**
	 * Find one challenge by internal ID.
	 *
	 * @param int $challenge_id Positive challenge ID.
	 */
	public function find_by_id( int $challenge_id ): ?AuthChallengeRecord {
		return $this->challenges->find_by_id( $challenge_id );
	}

	/**
	 * Atomically claim the latest eligible unissued challenge.
	 *
	 * @param int               $challenge_id Positive challenge ID.
	 * @param OtpHash           $code_hash Issued OTP hash.
	 * @param DateTimeImmutable $expires_at Issued-code expiry.
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
					|| RequestActivationOtp::PURPOSE !== $challenge->data->purpose
					|| RequestActivationOtp::SUBJECT_TYPE !== $challenge->data->subject_type
					|| 0 !== $challenge->data->send_count
					|| null !== $challenge->data->consumed_at
					|| $challenge->data->expires_at <= $now
				) {
					return null;
				}

				$latest = $this->gateway->row(
					'SELECT challenge_id FROM %i WHERE purpose = %s AND subject_type = %s AND subject_id = %s AND email_lookup = %s AND consumed_at IS NULL ORDER BY created_at DESC, challenge_id DESC LIMIT 1',
					array(
						$this->tables->auth_challenges(),
						RequestActivationOtp::PURPOSE,
						RequestActivationOtp::SUBJECT_TYPE,
						$challenge->data->subject_id,
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
	 * Revoke one unissued challenge after queue failure.
	 *
	 * @param int               $challenge_id Positive challenge ID.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function revoke_unissued( int $challenge_id, DateTimeImmutable $now ): void {
		$this->gateway->execute(
			'UPDATE %i SET consumed_at = %s WHERE challenge_id = %d AND send_count = 0 AND consumed_at IS NULL',
			array(
				$this->tables->auth_challenges(),
				$this->dates->format( $now ),
				$challenge_id,
			)
		);
	}

	/**
	 * Delete a bounded set of expired challenges after retention.
	 *
	 * @param DateTimeImmutable $before Exclusive UTC boundary.
	 * @param int               $limit Maximum rows removed.
	 */
	public function cleanup_expired( DateTimeImmutable $before, int $limit ): int {
		$limit = max( 1, min( 500, $limit ) );

		return $this->gateway->execute(
			'DELETE FROM %i WHERE expires_at < %s ORDER BY expires_at ASC LIMIT %d',
			array(
				$this->tables->auth_challenges(),
				$this->dates->format( $before ),
				$limit,
			)
		);
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

		throw new PersistenceException( 'Persistence aggregate is invalid.' );
	}
}
