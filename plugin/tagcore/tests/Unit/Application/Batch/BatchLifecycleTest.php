<?php
/**
 * RT-208 Batch lifecycle application tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Batch;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Batch\BatchLifecycleState;
use ReturnTag\TagCore\Application\Batch\BatchTagCounts;
use ReturnTag\TagCore\Application\Batch\ChangeBatchLifecycle;
use ReturnTag\TagCore\Application\Batch\Exception\BatchLifecycleConflict;
use ReturnTag\TagCore\Application\Batch\Exception\BatchLifecycleIntegrityViolation;
use ReturnTag\TagCore\Application\Batch\Exception\BatchLifecycleNotAllowed;
use ReturnTag\TagCore\Application\Batch\ReleaseBatch;
use ReturnTag\TagCore\Application\Batch\SuspendBatch;
use ReturnTag\TagCore\Application\Batch\VoidBatch;
use ReturnTag\TagCore\Domain\Batch\BatchLifecycleAction;
use ReturnTag\TagCore\Domain\Batch\BatchLifecyclePolicy;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedFeatureFlagReader;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\ImmediateTransactionManager;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryBatchLifecycleRepository;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryEventRepository;

/**
 * Verifies state policy, audit behavior, and activation controls.
 */
final class BatchLifecycleTest extends TestCase {
	/**
	 * Fixed UTC transition time.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $time;

	/**
	 * Prepare the deterministic clock.
	 */
	protected function setUp(): void {
		$this->time = new DateTimeImmutable( '2026-07-28 10:00:00', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * The Domain policy contains exactly the approved transition edges.
	 *
	 * @dataProvider transition_policy_provider
	 * @param BatchStatus          $status Current status.
	 * @param BatchLifecycleAction $action Requested action.
	 * @param bool                 $allowed Expected decision.
	 */
	public function test_transition_policy_is_explicit(
		BatchStatus $status,
		BatchLifecycleAction $action,
		bool $allowed
	): void {
		self::assertSame( $allowed, ( new BatchLifecyclePolicy() )->allows( $status, $action ) );
	}

	/**
	 * Provide approved and rejected lifecycle edges.
	 *
	 * @return array<string, array{BatchStatus, BatchLifecycleAction, bool}>
	 */
	public function transition_policy_provider(): array {
		return array(
			'release exported'   => array( BatchStatus::EXPORTED, BatchLifecycleAction::RELEASE, true ),
			'release suspended'  => array( BatchStatus::SUSPENDED, BatchLifecycleAction::RELEASE, true ),
			'release generated'  => array( BatchStatus::GENERATED, BatchLifecycleAction::RELEASE, false ),
			'suspend generated'  => array( BatchStatus::GENERATED, BatchLifecycleAction::SUSPEND, true ),
			'suspend exported'   => array( BatchStatus::EXPORTED, BatchLifecycleAction::SUSPEND, true ),
			'suspend released'   => array( BatchStatus::RELEASED, BatchLifecycleAction::SUSPEND, true ),
			'suspend generating' => array( BatchStatus::GENERATING, BatchLifecycleAction::SUSPEND, false ),
			'void generated'     => array( BatchStatus::GENERATED, BatchLifecycleAction::VOID, true ),
			'void exported'      => array( BatchStatus::EXPORTED, BatchLifecycleAction::VOID, true ),
			'void released'      => array( BatchStatus::RELEASED, BatchLifecycleAction::VOID, true ),
			'void suspended'     => array( BatchStatus::SUSPENDED, BatchLifecycleAction::VOID, true ),
			'void voided'        => array( BatchStatus::VOIDED, BatchLifecycleAction::VOID, false ),
		);
	}

	/**
	 * A complete audited export can be released atomically.
	 */
	public function test_release_enables_batch_and_appends_event(): void {
		$repository = $this->repository( BatchStatus::EXPORTED, false );
		$events     = new InMemoryEventRepository();
		$service    = new ReleaseBatch( $this->lifecycle( $repository, $events, true ) );

		$result = $service->execute( 7, 42, BatchStatus::EXPORTED );

		self::assertTrue( $result->changed );
		self::assertSame( BatchStatus::RELEASED, $result->state->batch_status );
		self::assertTrue( $result->state->activation_enabled );
		self::assertTrue( $result->effective_activation_enabled );
		self::assertCount( 1, $events->records );
		self::assertSame( 'batch_released', $events->records[0]->data->event_type );
	}

	/**
	 * Global containment remains authoritative after a valid release.
	 */
	public function test_release_does_not_override_disabled_global_flag(): void {
		$repository = $this->repository( BatchStatus::EXPORTED, false );
		$result     = ( new ReleaseBatch( $this->lifecycle( $repository, new InMemoryEventRepository(), false ) ) )
			->execute( 7, 42, BatchStatus::EXPORTED );

		self::assertTrue( $result->state->activation_enabled );
		self::assertFalse( $result->global_activation_enabled );
		self::assertFalse( $result->effective_activation_enabled );
	}

	/**
	 * Suspension disables new activation without changing Tag status counts.
	 */
	public function test_suspend_preserves_active_tag_counts(): void {
		$repository = new InMemoryBatchLifecycleRepository(
			$this->state( BatchStatus::RELEASED, true ),
			new BatchTagCounts( 8, 2, 0, 0 ),
			10
		);
		$events     = new InMemoryEventRepository();
		$result     = ( new SuspendBatch( $this->lifecycle( $repository, $events, true ) ) )
			->execute( 7, 42, BatchStatus::RELEASED );

		self::assertSame( BatchStatus::SUSPENDED, $result->state->batch_status );
		self::assertFalse( $result->state->activation_enabled );
		self::assertSame( 2, $result->tag_counts->active );
		self::assertSame( 'batch_suspended', $events->records[0]->data->event_type );
	}

	/**
	 * Void is terminal and keeps every generated identifier counted.
	 */
	public function test_void_is_terminal_and_never_deletes_inventory(): void {
		$repository = $this->repository( BatchStatus::SUSPENDED, false );
		$events     = new InMemoryEventRepository();
		$service    = new VoidBatch( $this->lifecycle( $repository, $events, true ) );
		$result     = $service->execute( 7, 42, BatchStatus::SUSPENDED );

		self::assertSame( BatchStatus::VOIDED, $result->state->batch_status );
		self::assertSame( 10, $result->tag_counts->total );
		self::assertFalse( $result->state->activation_enabled );
		self::assertSame( 'batch_voided', $events->records[0]->data->event_type );

		$this->expectException( BatchLifecycleNotAllowed::class );
		( new ReleaseBatch( $this->lifecycle( $repository, $events, true ) ) )
			->execute( 7, 42, BatchStatus::VOIDED );
	}

	/**
	 * Repeating the same target is idempotent and emits no duplicate Event.
	 */
	public function test_repeat_target_is_idempotent(): void {
		$repository = $this->repository( BatchStatus::RELEASED, true );
		$events     = new InMemoryEventRepository();
		$result     = ( new ReleaseBatch( $this->lifecycle( $repository, $events, true ) ) )
			->execute( 7, 42, BatchStatus::EXPORTED );

		self::assertFalse( $result->changed );
		self::assertSame( 0, $repository->transition_calls );
		self::assertCount( 0, $events->records );
	}

	/**
	 * Release fails closed when no matching audit export exists.
	 */
	public function test_release_requires_matching_audited_export(): void {
		$repository                   = $this->repository( BatchStatus::EXPORTED, false );
		$repository->export_row_count = null;

		$this->expectException( BatchLifecycleIntegrityViolation::class );
		( new ReleaseBatch( $this->lifecycle( $repository, new InMemoryEventRepository(), true ) ) )
			->execute( 7, 42, BatchStatus::EXPORTED );
	}

	/**
	 * Draft and generating Batches cannot enter incident or release states.
	 */
	public function test_draft_is_rejected_by_policy_before_completion_checks(): void {
		$repository = new InMemoryBatchLifecycleRepository(
			new BatchLifecycleState(
				7,
				'RT-208-UNIT',
				10,
				0,
				BatchStatus::DRAFT,
				false,
				$this->time
			),
			new BatchTagCounts( 0, 0, 0, 0 ),
			null
		);

		$this->expectException( BatchLifecycleNotAllowed::class );
		( new SuspendBatch( $this->lifecycle( $repository, new InMemoryEventRepository(), true ) ) )
			->execute( 7, 42, BatchStatus::DRAFT );
	}

	/**
	 * A stale expected status cannot overwrite a newer state.
	 */
	public function test_stale_expected_status_conflicts(): void {
		$repository = $this->repository( BatchStatus::EXPORTED, false );

		$this->expectException( BatchLifecycleConflict::class );
		( new ReleaseBatch( $this->lifecycle( $repository, new InMemoryEventRepository(), true ) ) )
			->execute( 7, 42, BatchStatus::SUSPENDED );
	}

	/**
	 * Build the shared transition service.
	 *
	 * @param InMemoryBatchLifecycleRepository $repository Batch fixture.
	 * @param InMemoryEventRepository          $events Event fixture.
	 * @param bool                             $global_enabled Global activation result.
	 */
	private function lifecycle(
		InMemoryBatchLifecycleRepository $repository,
		InMemoryEventRepository $events,
		bool $global_enabled
	): ChangeBatchLifecycle {
		return new ChangeBatchLifecycle(
			$repository,
			$events,
			new ImmediateTransactionManager(),
			new FixedFeatureFlagReader( $global_enabled ),
			new FixedClock( $this->time ),
			new BatchLifecyclePolicy()
		);
	}

	/**
	 * Build one complete lifecycle Repository fixture.
	 *
	 * @param BatchStatus $status Current status.
	 * @param bool        $activation_enabled Batch activation control.
	 */
	private function repository(
		BatchStatus $status,
		bool $activation_enabled
	): InMemoryBatchLifecycleRepository {
		return new InMemoryBatchLifecycleRepository(
			$this->state( $status, $activation_enabled ),
			new BatchTagCounts( 10, 0, 0, 0 ),
			10
		);
	}

	/**
	 * Build one complete lifecycle state.
	 *
	 * @param BatchStatus $status Current status.
	 * @param bool        $activation_enabled Batch activation control.
	 */
	private function state( BatchStatus $status, bool $activation_enabled ): BatchLifecycleState {
		return new BatchLifecycleState(
			7,
			'RT-208-UNIT',
			10,
			10,
			$status,
			$activation_enabled,
			$this->time
		);
	}
}
