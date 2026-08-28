<?php
/**
 * Privacy request workflow coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Privacy;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Pagination\CorrelationEventCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\CorrelationEventPage;
use ReturnTag\TagCore\Application\Persistence\Pagination\EventCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\EventPage;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Record\EventRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Application\Privacy\Exception\PrivacyRequestConflict;
use ReturnTag\TagCore\Application\Privacy\Exception\PrivacyRequestUnavailable;
use ReturnTag\TagCore\Application\Privacy\PrivacyRequestRecord;
use ReturnTag\TagCore\Application\Privacy\PrivacyRequestRepository;
use ReturnTag\TagCore\Application\Privacy\PrivacyRequestStart;
use ReturnTag\TagCore\Application\Privacy\PrivacyRequestSubject;
use ReturnTag\TagCore\Application\Privacy\PrivacyRequestWorkflow;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestError;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestReason;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestState;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestType;

/** Verifies flags, idempotent Events, transitions, and stale-write behavior. */
final class PrivacyRequestWorkflowTest extends TestCase {
	/**
	 * Fixed UTC test time.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $now;

	/** Create one fixed UTC time before every test. */
	protected function setUp(): void {
		$this->now = new DateTimeImmutable( '2026-08-28 05:00:00', new DateTimeZone( 'UTC' ) );
	}

	/** Disabled intake must fail before persistence. */
	public function test_intake_fails_closed_before_persistence(): void {
		$requests = $this->createMock( PrivacyRequestRepository::class );
		$requests->expects( self::never() )->method( 'begin' );

		$workflow = $this->workflow( $requests, $this->events(), array() );
		$this->expectException( PrivacyRequestUnavailable::class );
		$workflow->start( $this->subject(), PrivacyRequestType::EXPORT, str_repeat( 'b', 64 ) );
	}

	/** A newly persisted request appends one metadata-free Event. */
	public function test_new_request_appends_one_metadata_free_event(): void {
		$request  = $this->record();
		$requests = $this->createMock( PrivacyRequestRepository::class );
		$requests->expects( self::once() )->method( 'begin' )->willReturn( new PrivacyRequestStart( $request, true ) );
		$events = $this->events(
			function ( NewEventRecord $event ): void {
				self::assertSame( 'privacy_request_queued', $event->event_type );
				self::assertSame( 'user', $event->actor_type );
				self::assertSame( 42, $event->actor_id );
				self::assertSame( 'privacy_request', $event->target_type );
				self::assertSame( '7', $event->target_id );
				self::assertNull( $event->metadata->json() );
			}
		);

		$start = $this->workflow( $requests, $events, array( FeatureFlag::PRIVACY_REQUEST_INTAKE ) )->start( $this->subject(), PrivacyRequestType::EXPORT, str_repeat( 'b', 64 ) );
		self::assertTrue( $start->created );
	}

	/** Resolving an existing unfinished request does not duplicate Events. */
	public function test_existing_request_does_not_duplicate_event(): void {
		$requests = $this->createMock( PrivacyRequestRepository::class );
		$requests->method( 'begin' )->willReturn( new PrivacyRequestStart( $this->record(), false ) );
		$events = $this->createMock( EventRepository::class );
		$events->expects( self::never() )->method( 'append' );

		$start = $this->workflow( $requests, $events, array( FeatureFlag::PRIVACY_REQUEST_INTAKE ) )->start( $this->subject(), PrivacyRequestType::EXPORT, str_repeat( 'b', 64 ) );
		self::assertFalse( $start->created );
	}

	/** A processing claim is conditional and audited. */
	public function test_processing_transition_is_conditional_and_audited(): void {
		$processing = $this->record( PrivacyRequestState::PROCESSING, 2 );
		$requests   = $this->createMock( PrivacyRequestRepository::class );
		$requests->expects( self::once() )->method( 'claim' )->with( 7, 1, $this->now )->willReturn( $processing );
		$events = $this->events( static fn( NewEventRecord $event ) => self::assertSame( 'privacy_request_processing', $event->event_type ) );

		$result = $this->workflow( $requests, $events, array( FeatureFlag::PRIVACY_REQUEST_PROCESSING ) )->claim( 7, 1 );
		self::assertSame( PrivacyRequestState::PROCESSING, $result->state );
	}

	/** A stale transition fails without writing an Event. */
	public function test_stale_transition_rolls_back_without_event(): void {
		$requests = $this->createMock( PrivacyRequestRepository::class );
		$requests->method( 'complete' )->willReturn( null );
		$events = $this->createMock( EventRepository::class );
		$events->expects( self::never() )->method( 'append' );

		$this->expectException( PrivacyRequestConflict::class );
		$this->workflow( $requests, $events, array( FeatureFlag::PRIVACY_REQUEST_PROCESSING ) )->complete( 7, 1 );
	}

	/**
	 * Build one isolated workflow.
	 *
	 * @param PrivacyRequestRepository $requests Request Repository fake.
	 * @param EventRepository          $events Event Repository fake.
	 * @param array                    $enabled Enabled controls.
	 * @phpstan-param list<FeatureFlag> $enabled
	 */
	private function workflow( PrivacyRequestRepository $requests, EventRepository $events, array $enabled ): PrivacyRequestWorkflow {
		$flags        = new class( $enabled ) implements FeatureFlagReader {
			/**
			 * Create one fixed Feature Flag reader.
			 *
			 * @param array $enabled Enabled controls.
			 * @phpstan-param list<FeatureFlag> $enabled
			 */
			public function __construct( private array $enabled ) {}
			/**
			 * Read one fixed Feature Flag value.
			 *
			 * @param FeatureFlag $feature_flag Requested control.
			 */
			public function is_enabled( FeatureFlag $feature_flag ): bool {
				return in_array( $feature_flag, $this->enabled, true );
			}
		};
		$transactions = new class() implements TransactionManager {
			/**
			 * Execute one operation synchronously.
			 *
			 * @param callable $operation Operation callback.
			 */
			public function transactional( callable $operation ): mixed {
				return $operation();
			}
		};
		$clock        = new class( $this->now ) implements Clock {
			/**
			 * Create one fixed Clock.
			 *
			 * @param DateTimeImmutable $now Fixed UTC time.
			 */
			public function __construct( private DateTimeImmutable $now ) {}
			/** Return the fixed UTC time. */
			public function now(): DateTimeImmutable {
				return $this->now;
			}
		};

		return new PrivacyRequestWorkflow( $requests, $events, $transactions, $flags, $clock );
	}

	/**
	 * Build one append-only Event Repository fake.
	 *
	 * @param callable(NewEventRecord):void|null $assertion Optional Event assertion.
	 */
	private function events( ?callable $assertion = null ): EventRepository {
		return new class( $assertion ) implements EventRepository {
			/**
			 * Create one append-only Event fake.
			 *
			 * @param callable(NewEventRecord):void|null $assertion Optional Event assertion.
			 */
			public function __construct( private mixed $assertion ) {}
			/**
			 * Append one Event.
			 *
			 * @param NewEventRecord $record Event to append.
			 */
			public function append( NewEventRecord $record ): EventRecord {
				if ( null !== $this->assertion ) {
					( $this->assertion )( $record );
				}
				return new EventRecord( 1, $record );
			}
			/**
			 * Reject unused target history reads.
			 *
			 * @param string      $target_type Target type.
			 * @param string      $target_id Target identifier.
			 * @param EventCursor $cursor Optional cursor.
			 * @param PageSize    $page_size Requested page size.
			 * @throws \LogicException This fake does not support reads.
			 */
			public function list_by_target( string $target_type, string $target_id, ?EventCursor $cursor, PageSize $page_size ): EventPage {
				unset( $target_type, $target_id, $cursor, $page_size );
				throw new \LogicException( 'Not used.' );
			}
			/**
			 * Reject unused correlation history reads.
			 *
			 * @param string                 $correlation_id Correlation identifier.
			 * @param CorrelationEventCursor $cursor Optional cursor.
			 * @param PageSize               $page_size Requested page size.
			 * @throws \LogicException This fake does not support reads.
			 */
			public function list_by_correlation( string $correlation_id, ?CorrelationEventCursor $cursor, PageSize $page_size ): CorrelationEventPage {
				unset( $correlation_id, $cursor, $page_size );
				throw new \LogicException( 'Not used.' );
			}
		};
	}

	/** Build one privacy-safe User requester. */
	private function subject(): PrivacyRequestSubject {
		return new PrivacyRequestSubject( 'user', 42, str_repeat( 'a', 64 ) );
	}

	/**
	 * Build one strict request projection.
	 *
	 * @param PrivacyRequestState $state Requested state.
	 * @param int                 $row_version Requested row version.
	 */
	private function record( PrivacyRequestState $state = PrivacyRequestState::QUEUED, int $row_version = 1 ): PrivacyRequestRecord {
		return new PrivacyRequestRecord(
			7,
			$this->subject(),
			PrivacyRequestType::EXPORT,
			$state,
			PrivacyRequestWorkflow::POLICY_VERSION,
			str_repeat( 'b', 64 ),
			PrivacyRequestState::COMPLETED === $state ? null : str_repeat( 'c', 64 ),
			null,
			PrivacyRequestState::PROCESSING === $state ? 1 : 0,
			$row_version,
			null,
			null,
			$this->now,
			$this->now,
			PrivacyRequestState::PROCESSING === $state ? $this->now : null,
			null,
			PrivacyRequestState::COMPLETED === $state ? $this->now : null,
			null
		);
	}
}
