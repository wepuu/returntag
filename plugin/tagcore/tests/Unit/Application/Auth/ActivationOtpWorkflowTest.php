<?php
/**
 * Activation OTP request and Worker tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Auth;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Auth\ActivationOtpCodeGenerator;
use ReturnTag\TagCore\Application\Auth\ActivationOtpDispatchResult;
use ReturnTag\TagCore\Application\Auth\ActivationOtpEmailSender;
use ReturnTag\TagCore\Application\Auth\ActivationOtpRateLimiter;
use ReturnTag\TagCore\Application\Auth\ActivationOtpRequestResult;
use ReturnTag\TagCore\Application\Auth\ActivationOtpRequestStore;
use ReturnTag\TagCore\Application\Auth\ActivationOtpScheduler;
use ReturnTag\TagCore\Application\Auth\DispatchActivationOtp;
use ReturnTag\TagCore\Application\Auth\RequestActivationOtp;
use ReturnTag\TagCore\Application\Auth\WordPressAccountEmailPolicy;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Record\AuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewAuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPagePolicy;
use ReturnTag\TagCore\Application\PublicTag\PublicTagStateReader;
use ReturnTag\TagCore\Application\PublicTag\PublicTagStateRecord;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Security\ActivationOtpSecrets;
use ReturnTag\TagCore\Infrastructure\Security\SodiumActivationOtpProtector;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;

/**
 * Verifies that requests persist only unissued state and Workers generate once.
 */
final class ActivationOtpWorkflowTest extends TestCase {
	/**
	 * Public requests queue only the ID and repeated Workers send once.
	 */
	public function test_request_queues_only_the_challenge_identifier_and_worker_sends_once(): void {
		$time      = new DateTimeImmutable( '2026-07-30 00:00:00', new DateTimeZone( 'UTC' ) );
		$clock     = new FixedClock( $time );
		$flags     = $this->enabled_flags();
		$pages     = $this->activation_pages( $flags );
		$protector = new SodiumActivationOtpProtector(
			ActivationOtpSecrets::from_keys(
				str_repeat( 'e', 32 ),
				str_repeat( 'l', 32 ),
				str_repeat( 'p', 32 )
			)
		);
		$store     = $this->store();
		$scheduler = new class() implements ActivationOtpScheduler {
			/**
			 * Recorded challenge identifiers.
			 *
			 * @var list<int>
			 */
			public array $challenge_ids = array();

			/**
			 * Record one scheduled challenge.
			 *
			 * @param int $challenge_id Challenge ID.
			 */
			public function schedule( int $challenge_id ): void {
				$this->challenge_ids[] = $challenge_id;
			}
		};
		$limiter   = new class() implements ActivationOtpRateLimiter {
			/**
			 * Allow the test request.
			 *
			 * @param LookupDigest      $ip_lookup Keyed IP.
			 * @param LookupDigest      $email_lookup Keyed email.
			 * @param TagId             $tag_id Public Tag.
			 * @param DateTimeImmutable $now Current time.
			 */
			public function reserve(
				LookupDigest $ip_lookup,
				LookupDigest $email_lookup,
				TagId $tag_id,
				DateTimeImmutable $now
			): bool {
				unset( $ip_lookup, $email_lookup, $tag_id, $now );
				return true;
			}
		};
		$request   = new RequestActivationOtp(
			$pages,
			$flags,
			$store,
			$protector,
			$limiter,
			$scheduler,
			new WordPressAccountEmailPolicy(),
			$clock
		);

		self::assertSame(
			ActivationOtpRequestResult::UNAVAILABLE,
			$request->execute(
				TagId::from_canonical( 'A7R2W9' ),
				new EmailAddress( str_repeat( 'a', 64 ) . '@' . str_repeat( 'b', 30 ) . '.example.test' ),
				'192.0.2.4'
			)
		);
		self::assertSame( array(), $scheduler->challenge_ids );

		self::assertSame(
			ActivationOtpRequestResult::ACCEPTED,
			$request->execute(
				TagId::from_canonical( 'A7R2W9' ),
				new EmailAddress( 'owner@example.test' ),
				'192.0.2.4'
			)
		);
		self::assertSame( array( 1 ), $scheduler->challenge_ids );
		self::assertSame( 0, $store->records[1]->data->send_count );
		self::assertStringNotContainsString( 'owner@example.test', $store->records[1]->data->email_ciphertext->value );

		$codes  = new class() implements ActivationOtpCodeGenerator {
			/**
			 * Generator call count.
			 *
			 * @var int
			 */
			public int $calls = 0;

			/**
			 * Generate the fixed test code.
			 */
			public function generate(): string {
				++$this->calls;
				return '123456';
			}
		};
		$mail   = new class() implements ActivationOtpEmailSender {
			/**
			 * Submitted test codes.
			 *
			 * @var list<string>
			 */
			public array $codes = array();

			/**
			 * Capture one submission.
			 *
			 * @param EmailAddress $recipient Target address.
			 * @param string       $code OTP code.
			 * @param string       $idempotency_key Opaque stable business key.
			 */
			public function send( EmailAddress $recipient, string $code, string $idempotency_key ): bool {
				TestCase::assertSame( 'owner@example.test', $recipient->value );
				TestCase::assertSame( 64, strlen( $idempotency_key ) );
				$this->codes[] = $code;
				return true;
			}
		};
		$worker = new DispatchActivationOtp( $pages, $flags, $store, $protector, $codes, $mail, $clock );

		self::assertSame( ActivationOtpDispatchResult::ACCEPTED_BY_MAILER, $worker->execute( 1 ) );
		self::assertSame( ActivationOtpDispatchResult::NO_ACTION, $worker->execute( 1 ) );
		self::assertSame( 1, $codes->calls );
		self::assertSame( array( '123456' ), $mail->codes );
		self::assertSame( 1, $store->records[1]->data->send_count );
		self::assertStringNotContainsString( '123456', $store->records[1]->data->code_hash->value );
	}

	/**
	 * Build enabled feature flags.
	 */
	private function enabled_flags(): FeatureFlagReader {
		return new class() implements FeatureFlagReader {
			/**
			 * Enable every requested flag.
			 *
			 * @param FeatureFlag $feature_flag Requested flag.
			 */
			public function is_enabled( FeatureFlag $feature_flag ): bool {
				unset( $feature_flag );
				return true;
			}
		};
	}

	/**
	 * Build an eligible activation state resolver.
	 *
	 * @param FeatureFlagReader $flags Enabled flags.
	 */
	private function activation_pages( FeatureFlagReader $flags ): ResolvePublicTagPage {
		$states = new class() implements PublicTagStateReader {
			/**
			 * Return one eligible synthetic Tag.
			 *
			 * @param TagId $tag_id Requested Tag.
			 */
			public function find( TagId $tag_id ): ?PublicTagStateRecord {
				unset( $tag_id );

				return new PublicTagStateRecord(
					null,
					TagType::CLASSIC_TAG,
					null,
					TagStatus::UNREGISTERED,
					false,
					null,
					null,
					BatchStatus::RELEASED,
					true
				);
			}
		};

		return new ResolvePublicTagPage(
			$states,
			$flags,
			new PublicTagPagePolicy( new TagActivationAvailabilityPolicy() )
		);
	}

	/**
	 * Build an in-memory challenge store.
	 *
	 * @return ActivationOtpRequestStore&object{records: array<int, AuthChallengeRecord>}
	 */
	private function store(): ActivationOtpRequestStore {
		return new class() implements ActivationOtpRequestStore {
			/**
			 * Stored challenges.
			 *
			 * @var array<int, AuthChallengeRecord>
			 */
			public array $records = array();

			/**
			 * Return no prior email requests.
			 *
			 * @param LookupDigest      $email_lookup Keyed email.
			 * @param DateTimeImmutable $since Boundary.
			 */
			public function count_recent_for_email( LookupDigest $email_lookup, DateTimeImmutable $since ): int {
				unset( $email_lookup, $since );
				return 0;
			}

			/**
			 * Return no prior Tag requests.
			 *
			 * @param TagId             $tag_id Tag.
			 * @param DateTimeImmutable $since Boundary.
			 */
			public function count_recent_for_tag( TagId $tag_id, DateTimeImmutable $since ): int {
				unset( $tag_id, $since );
				return 0;
			}

			/**
			 * Store one challenge.
			 *
			 * @param NewAuthChallengeRecord $challenge Challenge.
			 */
			public function create_replacing( NewAuthChallengeRecord $challenge ): AuthChallengeRecord {
				$record                                 = new AuthChallengeRecord( count( $this->records ) + 1, $challenge );
				$this->records[ $record->challenge_id ] = $record;
				return $record;
			}

			/**
			 * Find one stored challenge.
			 *
			 * @param int $challenge_id Challenge ID.
			 */
			public function find_by_id( int $challenge_id ): ?AuthChallengeRecord {
				return $this->records[ $challenge_id ] ?? null;
			}

			/**
			 * Mark one challenge issued.
			 *
			 * @param int               $challenge_id Challenge ID.
			 * @param OtpHash           $code_hash OTP hash.
			 * @param DateTimeImmutable $expires_at Expiry.
			 * @param DateTimeImmutable $now Current time.
			 */
			public function claim_for_dispatch(
				int $challenge_id,
				OtpHash $code_hash,
				DateTimeImmutable $expires_at,
				DateTimeImmutable $now
			): ?AuthChallengeRecord {
				unset( $now );
				$record = $this->records[ $challenge_id ] ?? null;

				if ( null === $record || 0 !== $record->data->send_count || null !== $record->data->consumed_at ) {
					return null;
				}

				$data                           = $record->data;
				$sent                           = new AuthChallengeRecord(
					$challenge_id,
					new NewAuthChallengeRecord(
						$data->purpose,
						$data->subject_type,
						$data->subject_id,
						$data->email_ciphertext,
						$data->email_lookup,
						$code_hash,
						$data->attempt_count,
						1,
						$data->ip_hash,
						$expires_at,
						$data->verified_at,
						$data->consumed_at,
						$data->created_at
					)
				);
				$this->records[ $challenge_id ] = $sent;

				return $sent;
			}

			/**
			 * Ignore revocation in this fixture.
			 *
			 * @param int               $challenge_id Challenge ID.
			 * @param DateTimeImmutable $now Current time.
			 */
			public function revoke_unissued( int $challenge_id, DateTimeImmutable $now ): void {
				unset( $challenge_id, $now );
			}

			/**
			 * Return no expired rows in this fixture.
			 *
			 * @param DateTimeImmutable $before Boundary.
			 * @param int               $limit Row limit.
			 */
			public function cleanup_expired( DateTimeImmutable $before, int $limit ): int {
				unset( $before, $limit );
				return 0;
			}
		};
	}
}
