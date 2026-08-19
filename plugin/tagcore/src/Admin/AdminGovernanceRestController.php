<?php
/**
 * Internal governance console REST adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use ReturnTag\TagCore\Application\Admin\AuditEventSearchNormalizer;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAdminGovernanceReader;
use Throwable;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/** Maps Audit and retention governance operations to minimized responses. */
final readonly class AdminGovernanceRestController {
	private const NAMESPACE = 'tagcore/v1';

	/**
	 * Create the governance adapter.
	 *
	 * @param WpdbAdminGovernanceReader  $reader Safe governance projections.
	 * @param AuditEventSearchNormalizer $normalizer Audit request normalizer.
	 * @param AdminOperationsCursorCodec $cursors Criteria-bound cursors.
	 * @param RetentionTaskManager       $retention Retention task coordinator.
	 * @param FeatureFlagReader          $feature_flags Operational controls.
	 * @param SchemaState                $schema_state Schema readiness.
	 */
	public function __construct(
		private WpdbAdminGovernanceReader $reader,
		private AuditEventSearchNormalizer $normalizer,
		private AdminOperationsCursorCodec $cursors,
		private RetentionTaskManager $retention,
		private FeatureFlagReader $feature_flags,
		private SchemaState $schema_state
	) {}

	/** Register the internal governance route set. */
	public function register_routes(): void {
		$this->route( '/admin/audit-events/search', WP_REST_Server::CREATABLE, 'search_audit_events', Capability::VIEW_AUDIT_LOGS );
		$this->route( '/admin/retention/tasks', WP_REST_Server::READABLE, 'retention_tasks', Capability::MANAGE_RETENTION );
		$this->route( '/admin/retention/tasks/(?P<task>[a-z-]+)/run', WP_REST_Server::CREATABLE, 'run_retention_task', Capability::MANAGE_RETENTION );
	}

	/**
	 * Register one capability-bound route.
	 *
	 * @param string $path Route path.
	 * @param string $methods Allowed methods.
	 * @param string $callback Controller callback.
	 * @param string $capability Required capability.
	 * @throws InvalidArgumentException When the internal route path is invalid.
	 */
	private function route( string $path, string $methods, string $callback, string $capability ): void {
		if ( '' === $path || '0' === $path ) {
			throw new InvalidArgumentException( 'Governance route path is invalid.' );
		}
		register_rest_route(
			self::NAMESPACE,
			$path,
			array(
				'methods'             => $methods,
				'callback'            => array( $this, $callback ),
				'permission_callback' => fn( WP_REST_Request $request ): bool|WP_Error => $this->authorize( $request, $capability ),
			)
		);
	}

	/**
	 * Require one REST Nonce and route capability.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @param string          $capability Required capability.
	 */
	private function authorize( WP_REST_Request $request, string $capability ): bool|WP_Error {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'returntag_invalid_nonce', __( 'Your secure session expired. Refresh this page and try again.', 'tagcore' ), array( 'status' => 403 ) );
		}
		return current_user_can( $capability ) ? true : new WP_Error( 'returntag_forbidden', __( 'You are not allowed to use this governance view.', 'tagcore' ), array( 'status' => 403 ) );
	}

	/**
	 * Search the metadata-free global Audit Log.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function search_audit_events( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->guard(
			function () use ( $request ): WP_REST_Response {
				$criteria    = $this->normalizer->normalize( $request->get_params(), new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );
				$limit       = $this->page_size( $request->get_param( 'per_page' ) );
				$before_time = null;
				$before_id   = null;
				$cursor      = $request->get_param( 'cursor' );
				if ( is_string( $cursor ) && '' !== $cursor ) {
					$position = $this->cursors->decode( 'audit_events', $criteria, $cursor );
					if ( 1 !== preg_match( '/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\|([1-9][0-9]*)$/D', $position, $matches ) ) {
						throw new InvalidArgumentException( 'Audit cursor is invalid.' );
					}
					$before_time = $matches[1];
					$before_id   = (int) $matches[2];
				}
				$items = $this->reader->search_audit_events( $criteria, $before_time, $before_id, $limit + 1 );
				$more  = count( $items ) > $limit;
				if ( $more ) {
					array_pop( $items );
				}
				foreach ( $items as &$item ) {
					$item['event_id']       = (int) $item['event_id'];
					$item['actor_id']       = null === $item['actor_id'] ? null : (int) $item['actor_id'];
					$item['actor_user_url'] = current_user_can( Capability::VIEW_USERS ) && 'user' === $item['actor_type'] && null !== $item['actor_id'] ? admin_url( 'admin.php?page=' . OperationsAdminPage::USERS_SLUG . '&user_id=' . $item['actor_id'] ) : null;
				}
				unset( $item );
				$last = $more ? $items[ count( $items ) - 1 ] : null;
				return $this->response(
					array(
						'items'       => $items,
						'next_cursor' => is_array( $last ) ? $this->cursors->encode( 'audit_events', $criteria, $last['created_at'] . '|' . $last['event_id'] ) : null,
					)
				);
			}
		);
	}

	/** Return the fixed retention policy and schedule health projection. */
	public function retention_tasks(): WP_REST_Response|WP_Error {
		return $this->guard(
			fn(): WP_REST_Response => $this->response(
				array(
					'items'              => $this->retention->status(),
					'manual_run_enabled' => $this->feature_flags->is_enabled( FeatureFlag::ADMIN_RETENTION_RUN ),
				)
			)
		);
	}

	/**
	 * Queue one bounded retention batch.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function run_retention_task( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->guard(
			function () use ( $request ): WP_REST_Response {
				$task         = $request->get_param( 'task' );
				$confirmation = $request->get_param( 'confirmation' );
				if ( ! is_string( $task ) || ! is_string( $confirmation ) || ! hash_equals( $task, $confirmation ) ) {
					throw new InvalidArgumentException( 'Retention confirmation is invalid.' );
				}
				if ( ! $this->feature_flags->is_enabled( FeatureFlag::ADMIN_RETENTION_RUN ) ) {
					throw new InvalidArgumentException( 'Retention runs are disabled.' );
				}
				$this->retention->enqueue( $task, get_current_user_id() );
				return $this->response(
					array(
						'task_id' => $task,
						'status'  => 'pending',
					),
					202
				);
			}
		);
	}

	/**
	 * Apply private response headers to governance routes.
	 *
	 * @param WP_HTTP_Response $response REST response.
	 * @param WP_REST_Server   $server REST server.
	 * @param WP_REST_Request  $request REST request.
	 */
	public function apply_security_headers( WP_HTTP_Response $response, WP_REST_Server $server, WP_REST_Request $request ): WP_HTTP_Response {
		unset( $server );
		if ( str_starts_with( $request->get_route(), '/' . self::NAMESPACE . '/admin/' ) ) {
			foreach ( array(
				'Cache-Control'          => 'no-store, private',
				'Pragma'                 => 'no-cache',
				'Referrer-Policy'        => 'no-referrer',
				'X-Content-Type-Options' => 'nosniff',
			) as $name => $value ) {
				$response->header( $name, $value );
			}
		}
		return $response;
	}

	/**
	 * Parse the bounded page size.
	 *
	 * @param mixed $value External page size.
	 * @throws InvalidArgumentException When the value is invalid.
	 */
	private function page_size( mixed $value ): int {
		$value = $value ?? 50;
		$id    = is_int( $value ) ? $value : ( is_string( $value ) && ctype_digit( $value ) ? (int) $value : 0 );
		if ( $id < 1 || $id > 100 ) {
			throw new InvalidArgumentException( 'Page size is invalid.' );
		}
		return $id;
	}

	/**
	 * Enforce Schema readiness and fixed error mapping.
	 *
	 * @param callable(): (WP_REST_Response|WP_Error) $operation Guarded operation.
	 */
	private function guard( callable $operation ): WP_REST_Response|WP_Error {
		if ( ! $this->schema_state->is_current() ) {
			return new WP_Error( 'returntag_schema_unavailable', __( 'TagCore database preparation is incomplete.', 'tagcore' ), array( 'status' => 503 ) );
		}
		try {
			return $operation();
		} catch ( InvalidArgumentException ) {
			return new WP_Error( 'returntag_invalid_request', __( 'Review the exact governance values and try again.', 'tagcore' ), array( 'status' => 400 ) );
		} catch ( Throwable ) {
			return new WP_Error( 'returntag_admin_governance_failed', __( 'TagCore could not complete this governance operation.', 'tagcore' ), array( 'status' => 503 ) );
		}
	}

	/**
	 * Create one private no-store response.
	 *
	 * @param mixed $data Response data.
	 * @param int   $status HTTP status.
	 */
	private function response( mixed $data, int $status = 200 ): WP_REST_Response {
		$response = new WP_REST_Response( $data, $status );
		foreach ( array(
			'Cache-Control'          => 'no-store, private',
			'Pragma'                 => 'no-cache',
			'Referrer-Policy'        => 'no-referrer',
			'X-Content-Type-Options' => 'nosniff',
		) as $name => $value ) {
			$response->header( $name, $value );
		}
		return $response;
	}
}
