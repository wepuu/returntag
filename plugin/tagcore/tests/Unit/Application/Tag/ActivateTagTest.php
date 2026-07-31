<?php
/**
 * RT-307 atomic Tag activation tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Tag;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Tag\ActivateTag;
use ReturnTag\TagCore\Application\Tag\TagActivationEventIdentityPolicy;
use ReturnTag\TagCore\Application\Tag\TagActivationRepository;
use ReturnTag\TagCore\Application\Tag\TagActivationResult;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedFeatureFlagReader;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\ImmediateTransactionManager;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryEventRepository;

/**
 * Verifies orchestration, idempotency, and privacy-safe audit behavior.
 */
final class ActivateTagTest extends TestCase {
	/**
	 * Successful activation appends exactly one metadata-free Event.
	 */
	public function test_successful_activation_is_transactional_and_audited(): void {
		$repository   = $this->repository( TagActivationResult::ACTIVATED );
		$events       = new InMemoryEventRepository();
		$transactions = new ImmediateTransactionManager();
		$now          = new DateTimeImmutable( '2026-07-31 12:00:00', new DateTimeZone( 'UTC' ) );
		$use_case     = new ActivateTag(
			$repository,
			$events,
			$transactions,
			new FixedFeatureFlagReader( true ),
			new FixedClock( $now )
		);

		self::assertSame(
			TagActivationResult::ACTIVATED,
			$use_case->execute( TagId::from_canonical( 'A7R2W9' ), 42 )
		);
		self::assertSame( 1, $transactions->calls );
		self::assertSame( 1, $repository->calls );
		self::assertCount( 1, $events->records );
		self::assertSame( TagActivationEventIdentityPolicy::TAG_ACTIVATED, $events->records[0]->data->event_type );
		self::assertSame( 'user', $events->records[0]->data->actor_type );
		self::assertSame( 42, $events->records[0]->data->actor_id );
		self::assertSame( 'tag', $events->records[0]->data->target_type );
		self::assertSame( 'A7R2W9', $events->records[0]->data->target_id );
		self::assertSame( 'success', $events->records[0]->data->event_result );
		self::assertNull( $events->records[0]->data->metadata->json() );
	}

	/**
	 * Non-mutating outcomes do not duplicate an activation Event.
	 *
	 * @param TagActivationResult $result Persistence outcome.
	 * @dataProvider non_mutating_result_provider
	 */
	public function test_non_mutating_outcomes_append_no_event( TagActivationResult $result ): void {
		$events   = new InMemoryEventRepository();
		$use_case = new ActivateTag(
			$this->repository( $result ),
			$events,
			new ImmediateTransactionManager(),
			new FixedFeatureFlagReader( true ),
			new FixedClock( new DateTimeImmutable( '2026-07-31 12:00:00', new DateTimeZone( 'UTC' ) ) )
		);

		self::assertSame( $result, $use_case->execute( TagId::from_canonical( 'A7R2W9' ), 42 ) );
		self::assertSame( array(), $events->records );
	}

	/**
	 * Provide committed non-write outcomes.
	 *
	 * @return iterable<string, array{TagActivationResult}>
	 */
	public function non_mutating_result_provider(): iterable {
		yield 'same owner retry' => array( TagActivationResult::ALREADY_OWNED );
		yield 'state changed' => array( TagActivationResult::STATE_CHANGED );
		yield 'storage unavailable' => array( TagActivationResult::UNAVAILABLE );
	}

	/**
	 * The global incident control stops work before the transaction.
	 */
	public function test_global_activation_control_fails_closed(): void {
		$repository   = $this->repository( TagActivationResult::ACTIVATED );
		$events       = new InMemoryEventRepository();
		$transactions = new ImmediateTransactionManager();
		$use_case     = new ActivateTag(
			$repository,
			$events,
			$transactions,
			new FixedFeatureFlagReader( false ),
			new FixedClock( new DateTimeImmutable( '2026-07-31 12:00:00', new DateTimeZone( 'UTC' ) ) )
		);

		self::assertSame(
			TagActivationResult::UNAVAILABLE,
			$use_case->execute( TagId::from_canonical( 'A7R2W9' ), 42 )
		);
		self::assertSame( 0, $repository->calls );
		self::assertSame( 0, $transactions->calls );
		self::assertSame( array(), $events->records );
	}

	/**
	 * User-supplied invalid actor identifiers are rejected.
	 */
	public function test_invalid_owner_is_rejected(): void {
		$use_case = new ActivateTag(
			$this->repository( TagActivationResult::ACTIVATED ),
			new InMemoryEventRepository(),
			new ImmediateTransactionManager(),
			new FixedFeatureFlagReader( true ),
			new FixedClock( new DateTimeImmutable( '2026-07-31 12:00:00', new DateTimeZone( 'UTC' ) ) )
		);

		$this->expectException( InvalidArgumentException::class );
		$use_case->execute( TagId::from_canonical( 'A7R2W9' ), 0 );
	}

	/**
	 * Create one recording activation Repository.
	 *
	 * @param TagActivationResult $result Persistence outcome.
	 * @return TagActivationRepository&object{calls: int}
	 */
	private function repository( TagActivationResult $result ): TagActivationRepository {
		return new class( $result ) implements TagActivationRepository {
			/**
			 * Activation calls.
			 *
			 * @var int
			 */
			public int $calls = 0;

			/**
			 * Create the recording Repository.
			 *
			 * @param TagActivationResult $result Persistence outcome.
			 */
			public function __construct( private readonly TagActivationResult $result ) {
			}

			/**
			 * Return the configured activation outcome.
			 *
			 * @param TagId             $tag_id Canonical public Tag ID.
			 * @param int               $owner_id Server-derived WordPress User ID.
			 * @param DateTimeImmutable $now Current UTC time.
			 */
			public function activate(
				TagId $tag_id,
				int $owner_id,
				DateTimeImmutable $now
			): TagActivationResult {
				unset( $tag_id, $owner_id, $now );
				++$this->calls;

				return $this->result;
			}
		};
	}
}
