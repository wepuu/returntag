<?php
/**
 * Bounded manual retention scheduling and privacy-safe status.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAdminGovernanceReader;
use RuntimeException;
use Throwable;

/** Reuses existing bounded cleanup hooks behind one guarded Action Scheduler job. */
final class RetentionTaskManager {
	public const MANUAL_HOOK       = 'returntag_run_admin_retention_task';
	public const MANUAL_GROUP      = 'returntag-admin-retention';
	private const LAST_RUN_OPTION  = 'returntag_admin_retention_last_run';
	private const RUN_CLAIM_PREFIX = 'returntag_admin_retention_run_claim_';
	private const EVENT_TARGETS    = array(
		'activation-otp'  => 'activation_cleanup',
		'account-otp'     => 'account_cleanup',
		'finder-email'    => 'finder_rate_cleanup',
		'finder-evidence' => 'finder_evidence_cleanup',
	);

	/**
	 * Create the retention coordinator.
	 *
	 * @param RetentionTaskCatalog      $catalog Fixed tasks.
	 * @param WpdbAdminGovernanceReader $reader Backlog projections.
	 * @param EventRepository           $events Privacy-safe Event repository.
	 */
	public function __construct( private RetentionTaskCatalog $catalog, private WpdbAdminGovernanceReader $reader, private EventRepository $events ) {}

	/** Register the bounded wrapper action. */
	public function register_hooks(): void {
		add_action( self::MANUAL_HOOK, array( $this, 'run' ), 10, 1 );
	}

	/**
	 * Return privacy-safe health for every fixed task.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function status(): array {
		$last  = get_option( self::LAST_RUN_OPTION, array() );
		$last  = is_array( $last ) ? $last : array();
		$now   = gmdate( 'Y-m-d H:i:s' );
		$items = array();
		foreach ( $this->catalog->tasks() as $id => $task ) {
			$available = function_exists( 'as_has_scheduled_action' ) && function_exists( 'as_next_scheduled_action' );
			$claim     = $this->claim_status( $id );
			$pending   = ( $available && false !== \as_has_scheduled_action( self::MANUAL_HOOK, array( 'task_id' => $id ), self::MANUAL_GROUP ) );
			$next      = $available ? \as_next_scheduled_action( $task['hook'], array(), $task['group'] ) : false;
			$backlog   = $this->reader->retention_backlog( $id, $now );
			$items[]   = array(
				'task_id'         => $id,
				'name'            => __( $task['name'], 'tagcore' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Fixed catalog.
				'description'     => __( $task['description'], 'tagcore' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Fixed catalog.
				'policy'          => __( $task['policy'], 'tagcore' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Fixed catalog.
				'schedule_health' => $available && false !== $next ? 'healthy' : 'unavailable',
				'last_run_at'     => isset( $last[ $id ]['time'] ) && is_string( $last[ $id ]['time'] ) ? $last[ $id ]['time'] : null,
				'last_result'     => isset( $last[ $id ]['result'] ) && in_array( $last[ $id ]['result'], array( 'completed', 'failed' ), true ) ? $last[ $id ]['result'] : null,
				'next_run_at'     => is_int( $next ) && $next > 0 ? gmdate( DATE_ATOM, $next ) : null,
				'current_status'  => in_array( $claim, array( 'pending', 'running' ), true ) ? $claim : ( $pending ? 'pending' : 'idle' ),
				'pending_count'   => $backlog > 1000 ? '1000+' : (string) $backlog,
			);
		}
		return $items;
	}

	/**
	 * Queue one unique bounded cleanup run.
	 *
	 * @param string $task_id Fixed Task ID.
	 * @param int    $operator_id Authorized operator User ID.
	 * @throws InvalidArgumentException When another run is pending.
	 * @throws RuntimeException When the queue is unavailable.
	 */
	public function enqueue( string $task_id, int $operator_id ): void {
		$this->task( $task_id );
		if ( $operator_id < 1 || ! function_exists( 'as_enqueue_async_action' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
			throw new RuntimeException( 'Retention queue is unavailable.' );
		}
		$args = array( 'task_id' => $task_id );
		if ( false !== \as_has_scheduled_action( self::MANUAL_HOOK, $args, self::MANUAL_GROUP ) ) {
			throw new InvalidArgumentException( 'Retention task is already scheduled.' );
		}
		$this->acquire_claim( $task_id );
		try {
			$this->append( 'retention_task_run_requested', 'user', $operator_id, $task_id, 'queued' );
			$action_id = \as_enqueue_async_action( self::MANUAL_HOOK, $args, self::MANUAL_GROUP, true, 20 );
			if ( ! is_int( $action_id ) || $action_id < 1 ) {
				throw new RuntimeException( 'Retention queue is unavailable.' );
			}
		} catch ( Throwable $exception ) {
			$this->remember( $task_id, 'failed' );
			try {
				$this->append( 'retention_task_run_failed', 'system', null, $task_id, 'queue_failed' );
			} finally {
				$this->release_claim( $task_id );
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Wrapped, never rendered.
			throw new RuntimeException( 'Retention queue is unavailable.', 0, $exception );
		}
	}

	/**
	 * Run one claimed task and record one terminal Event.
	 *
	 * @param string $task_id Fixed Task ID.
	 * @throws RuntimeException When the cleanup dependency fails.
	 */
	public function run( string $task_id ): void {
		$task = $this->task( $task_id );
		if ( 'pending' !== $this->claim_status( $task_id ) ) {
			return;
		}
		update_option( $this->claim_option( $task_id ), 'running', false );
		try {
			do_action( $task['hook'] );
			$this->remember( $task_id, 'completed' );
			$this->append( 'retention_task_run_completed', 'system', null, $task_id, 'completed' );
			$this->release_claim( $task_id );
		} catch ( Throwable $exception ) {
			$this->remember( $task_id, 'failed' );
			try {
				$this->append( 'retention_task_run_failed', 'system', null, $task_id, 'dependency_failed' );
			} finally {
				$this->release_claim( $task_id );
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Wrapped, never rendered.
			throw new RuntimeException( 'Retention task failed.', 0, $exception );
		}
	}

	/**
	 * Resolve one allowlisted task.
	 *
	 * @param string $task_id Fixed Task ID.
	 * @return array{name: string, description: string, policy: string, hook: non-empty-string, group: string}
	 * @throws InvalidArgumentException When the Task ID is invalid.
	 */
	private function task( string $task_id ): array {
		$tasks = $this->catalog->tasks();
		if ( ! isset( $tasks[ $task_id ] ) ) {
			throw new InvalidArgumentException( 'Retention task is invalid.' );
		}
		return $tasks[ $task_id ];
	}

	/**
	 * Append one metadata-free retention Event.
	 *
	 * @param string   $event Event type.
	 * @param string   $actor_type Actor classification.
	 * @param int|null $actor_id Optional operator ID.
	 * @param string   $task_id Fixed Task ID.
	 * @param string   $result Fixed result code.
	 * @throws InvalidArgumentException When the Task ID is not allowlisted.
	 */
	private function append( string $event, string $actor_type, ?int $actor_id, string $task_id, string $result ): void {
		$target_id = self::EVENT_TARGETS[ $task_id ] ?? null;
		if ( null === $target_id ) {
			throw new InvalidArgumentException( 'Retention task is invalid.' );
		}
		$this->events->append( new NewEventRecord( $event, $actor_type, $actor_id, 'retention_task', $target_id, $result, null, EventMetadata::none(), new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) ) );
	}

	/**
	 * Store one privacy-safe terminal status.
	 *
	 * @param string $task_id Fixed Task ID.
	 * @param string $result Fixed result code.
	 */
	private function remember( string $task_id, string $result ): void {
		$value             = get_option( self::LAST_RUN_OPTION, array() );
		$value             = is_array( $value ) ? $value : array();
		$value[ $task_id ] = array(
			'time'   => gmdate( DATE_ATOM ),
			'result' => $result,
		);
		update_option( self::LAST_RUN_OPTION, $value, false );
	}

	/**
	 * Acquire one atomic, task-scoped run claim.
	 *
	 * @param string $task_id Fixed Task ID.
	 * @throws InvalidArgumentException When another run owns the claim.
	 */
	private function acquire_claim( string $task_id ): void {
		if ( ! add_option( $this->claim_option( $task_id ), 'pending', '', false ) ) {
			throw new InvalidArgumentException( 'Retention task is already scheduled.' );
		}
	}

	/**
	 * Return one privacy-safe run-claim status.
	 *
	 * @param string $task_id Fixed Task ID.
	 */
	private function claim_status( string $task_id ): ?string {
		$value = get_option( $this->claim_option( $task_id ), null );
		return is_string( $value ) && in_array( $value, array( 'pending', 'running' ), true ) ? $value : null;
	}

	/**
	 * Release one terminal task claim.
	 *
	 * @param string $task_id Fixed Task ID.
	 */
	private function release_claim( string $task_id ): void {
		delete_option( $this->claim_option( $task_id ) );
	}

	/**
	 * Return the private option name for one fixed Task ID.
	 *
	 * @param string $task_id Fixed Task ID.
	 */
	private function claim_option( string $task_id ): string {
		return self::RUN_CLAIM_PREFIX . $task_id;
	}
}
