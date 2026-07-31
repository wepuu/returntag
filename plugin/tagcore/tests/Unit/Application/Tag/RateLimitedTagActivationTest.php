<?php
/**
 * RT-309 rate-limited activation Application tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Tag;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPagePolicy;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPageState;
use ReturnTag\TagCore\Application\PublicTag\PublicTagStateReader;
use ReturnTag\TagCore\Application\PublicTag\PublicTagStateRecord;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Application\Tag\ActivateTag;
use ReturnTag\TagCore\Application\Tag\ActivateTagAndResolvePage;
use ReturnTag\TagCore\Application\Tag\RateLimitedTagActivation;
use ReturnTag\TagCore\Application\Tag\TagActivationAttemptStatus;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Application\Tag\TagActivationRateLimiter;
use ReturnTag\TagCore\Application\Tag\TagActivationRepository;
use ReturnTag\TagCore\Application\Tag\TagActivationResult;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedFeatureFlagReader;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\ImmediateTransactionManager;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryEventRepository;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionTagActivationRateLimiter;

/**
 * Verifies eligibility, reservation ordering, and generic throttling.
 */
final class RateLimitedTagActivationTest extends TestCase {
	/**
	 * Infrastructure exposes the approved balanced limit contract.
	 */
	public function test_balanced_limit_contract_is_exact(): void {
		self::assertSame( 5, WordPressOptionTagActivationRateLimiter::USER_HOURLY_LIMIT );
		self::assertSame( 10, WordPressOptionTagActivationRateLimiter::USER_DAILY_LIMIT );
		self::assertSame( 5, WordPressOptionTagActivationRateLimiter::EMAIL_HOURLY_LIMIT );
		self::assertSame( 10, WordPressOptionTagActivationRateLimiter::EMAIL_DAILY_LIMIT );
		self::assertSame( 30, WordPressOptionTagActivationRateLimiter::IP_HOURLY_LIMIT );
		self::assertSame( 100, WordPressOptionTagActivationRateLimiter::IP_DAILY_LIMIT );
		self::assertSame( 10, WordPressOptionTagActivationRateLimiter::TAG_HOURLY_LIMIT );
		self::assertSame( 100, WordPressOptionTagActivationRateLimiter::GLOBAL_MINUTE_LIMIT );
		self::assertSame( 2000, WordPressOptionTagActivationRateLimiter::GLOBAL_HOURLY_LIMIT );
	}

	/**
	 * Ineligible committed state resolves without consuming a budget.
	 */
	public function test_ineligible_state_skips_limit_and_mutation(): void {
		$fixture = $this->fixture( array( $this->active_record( 24 ) ), true );
		$result  = $fixture['service']->execute(
			TagId::from_canonical( 'A7R2W9' ),
			42,
			$this->digest( 'a' ),
			$this->digest( 'b' )
		);

		self::assertSame( TagActivationAttemptStatus::RESOLVED, $result->status );
		self::assertSame( PublicTagPageState::FINDER_ENTRY, $result->page->state );
		self::assertSame( 0, $fixture['limiter']->calls );
		self::assertSame( 0, $fixture['tags']->calls );
	}

	/**
	 * Throttling re-resolves committed state and performs no Tag mutation.
	 */
	public function test_throttled_attempt_returns_generic_status_without_mutation(): void {
		$fixture = $this->fixture(
			array( $this->unregistered_record(), $this->unregistered_record() ),
			false
		);
		$result  = $fixture['service']->execute(
			TagId::from_canonical( 'A7R2W9' ),
			42,
			$this->digest( 'a' ),
			$this->digest( 'b' )
		);

		self::assertSame( TagActivationAttemptStatus::THROTTLED, $result->status );
		self::assertSame( PublicTagPageState::ACTIVATION_ENTRY, $result->page->state );
		self::assertSame( 1, $fixture['limiter']->calls );
		self::assertSame( 0, $fixture['tags']->calls );
	}

	/**
	 * An allowed eligible attempt reserves once before atomic activation.
	 */
	public function test_allowed_attempt_reserves_then_converges_to_owner(): void {
		$fixture = $this->fixture(
			array( $this->unregistered_record(), $this->active_record( 42 ) ),
			true
		);
		$result  = $fixture['service']->execute(
			TagId::from_canonical( 'A7R2W9' ),
			42,
			$this->digest( 'a' ),
			$this->digest( 'b' )
		);

		self::assertSame( TagActivationAttemptStatus::RESOLVED, $result->status );
		self::assertSame( PublicTagPageState::OWNER_ENTRY, $result->page->state );
		self::assertSame( 1, $fixture['limiter']->calls );
		self::assertSame( 1, $fixture['tags']->calls );
		self::assertSame( 42, $fixture['limiter']->owner_id );
		self::assertSame( 'A7R2W9', $fixture['limiter']->tag_id );
	}

	/**
	 * Build an isolated rate-limited activation graph.
	 *
	 * @param array $records Committed state sequence.
	 * @param bool  $allowed Limiter decision.
	 * @phpstan-param list<PublicTagStateRecord> $records
	 * @return array{
	 *   service: RateLimitedTagActivation,
	 *   limiter: TagActivationRateLimiter&object{calls: int, owner_id: int|null, tag_id: string|null},
	 *   tags: TagActivationRepository&object{calls: int}
	 * }
	 */
	private function fixture( array $records, bool $allowed ): array {
		$flags   = new FixedFeatureFlagReader( true );
		$reader  = new class( $records ) implements PublicTagStateReader {
			/**
			 * Read calls.
			 *
			 * @var int
			 */
			private int $calls = 0;

			/**
			 * Create the sequenced state reader.
			 *
			 * @param array $records State sequence.
			 * @phpstan-param list<PublicTagStateRecord> $records
			 */
			public function __construct( private readonly array $records ) {
			}

			/**
			 * Return the next committed state.
			 *
			 * @param TagId $tag_id Canonical Tag ID.
			 */
			public function find( TagId $tag_id ): ?PublicTagStateRecord {
				unset( $tag_id );
				$index = min( $this->calls, count( $this->records ) - 1 );
				++$this->calls;

				return $this->records[ $index ] ?? null;
			}
		};
		$pages   = new ResolvePublicTagPage(
			$reader,
			$flags,
			new PublicTagPagePolicy( new TagActivationAvailabilityPolicy() )
		);
		$tags    = new class() implements TagActivationRepository {
			/**
			 * Activation calls.
			 *
			 * @var int
			 */
			public int $calls = 0;

			/**
			 * Record one successful activation.
			 *
			 * @param TagId             $tag_id Canonical Tag ID.
			 * @param int               $owner_id Server-derived Owner ID.
			 * @param DateTimeImmutable $now Current UTC time.
			 */
			public function activate(
				TagId $tag_id,
				int $owner_id,
				DateTimeImmutable $now
			): TagActivationResult {
				unset( $tag_id, $owner_id, $now );
				++$this->calls;

				return TagActivationResult::ACTIVATED;
			}
		};
		$limiter = new class( $allowed ) implements TagActivationRateLimiter {
			/**
			 * Reservation calls.
			 *
			 * @var int
			 */
			public int $calls = 0;

			/**
			 * Last Owner ID.
			 *
			 * @var int|null
			 */
			public ?int $owner_id = null;

			/**
			 * Last canonical Tag ID.
			 *
			 * @var string|null
			 */
			public ?string $tag_id = null;

			/**
			 * Create the fixed limiter.
			 *
			 * @param bool $allowed Reservation result.
			 */
			public function __construct( private readonly bool $allowed ) {
			}

			/**
			 * Record one reservation.
			 *
			 * @param int               $owner_id Server-derived Owner ID.
			 * @param LookupDigest      $email_lookup Keyed email digest.
			 * @param LookupDigest      $ip_lookup Keyed direct-peer IP digest.
			 * @param TagId             $tag_id Canonical Tag ID.
			 * @param DateTimeImmutable $now Current UTC time.
			 */
			public function reserve(
				int $owner_id,
				LookupDigest $email_lookup,
				LookupDigest $ip_lookup,
				TagId $tag_id,
				DateTimeImmutable $now
			): bool {
				unset( $email_lookup, $ip_lookup, $now );
				++$this->calls;
				$this->owner_id = $owner_id;
				$this->tag_id   = $tag_id->value;

				return $this->allowed;
			}
		};
		$clock   = new FixedClock( new DateTimeImmutable( '2026-07-31 12:00:00', new DateTimeZone( 'UTC' ) ) );

		return array(
			'service' => new RateLimitedTagActivation(
				$limiter,
				new ActivateTagAndResolvePage(
					new ActivateTag(
						$tags,
						new InMemoryEventRepository(),
						new ImmediateTransactionManager(),
						$flags,
						$clock
					),
					$pages
				),
				$pages,
				$clock
			),
			'limiter' => $limiter,
			'tags'    => $tags,
		);
	}

	/**
	 * Build one canonical keyed digest fixture.
	 *
	 * @param string $character Repeated hexadecimal character.
	 */
	private function digest( string $character ): LookupDigest {
		return LookupDigest::from_digest( str_repeat( $character, 64 ) );
	}

	/**
	 * Build an eligible unregistered state.
	 */
	private function unregistered_record(): PublicTagStateRecord {
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

	/**
	 * Build a valid active state.
	 *
	 * @param int $owner_id Committed Owner ID.
	 */
	private function active_record( int $owner_id ): PublicTagStateRecord {
		return new PublicTagStateRecord(
			$owner_id,
			TagType::CLASSIC_TAG,
			'Travel bag',
			TagStatus::ACTIVE,
			false,
			null,
			new DateTimeImmutable( '2026-07-31 12:00:00', new DateTimeZone( 'UTC' ) ),
			BatchStatus::RELEASED,
			true
		);
	}
}
