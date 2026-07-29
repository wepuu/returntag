<?php
/**
 * Batch lifecycle administration REST adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use ReturnTag\TagCore\Application\Batch\BatchLifecycleResult;
use ReturnTag\TagCore\Application\Batch\Exception\BatchLifecycleConflict;
use ReturnTag\TagCore\Application\Batch\Exception\BatchLifecycleIntegrityViolation;
use ReturnTag\TagCore\Application\Batch\Exception\BatchLifecycleNotAllowed;
use ReturnTag\TagCore\Application\Batch\Exception\BatchLifecycleNotFound;
use ReturnTag\TagCore\Application\Batch\GetBatchLifecycle;
use ReturnTag\TagCore\Application\Batch\ReleaseBatch;
use ReturnTag\TagCore\Application\Batch\SuspendBatch;
use ReturnTag\TagCore\Application\Batch\VoidBatch;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;
use Throwable;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Maps authorized lifecycle reads and commands to application services.
 */
final readonly class BatchLifecycleRestController {
	private const NAMESPACE = 'tagcore/v1';

	/**
	 * Create the controller.
	 *
	 * @param GetBatchLifecycle $get_lifecycle Lifecycle query.
	 * @param ReleaseBatch      $release_batch Release command.
	 * @param SuspendBatch      $suspend_batch Suspend command.
	 * @param VoidBatch         $void_batch Void command.
	 * @param SchemaState       $schema_state Schema readiness.
	 */
	public function __construct(
		private GetBatchLifecycle $get_lifecycle,
		private ReleaseBatch $release_batch,
		private SuspendBatch $suspend_batch,
		private VoidBatch $void_batch,
		private SchemaState $schema_state
	) {
	}

	/**
	 * Register lifecycle routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/batches/(?P<batch_id>[1-9][0-9]*)/lifecycle',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'authorize' ),
			)
		);

		foreach ( array( 'release', 'suspend', 'void' ) as $action ) {
			register_rest_route(
				self::NAMESPACE,
				'/batches/(?P<batch_id>[1-9][0-9]*)/' . $action,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, $action . '_item' ),
					'permission_callback' => array( $this, 'authorize' ),
				)
			);
		}
	}

	/**
	 * Require the dedicated Batch capability.
	 */
	public function authorize(): bool|WP_Error {
		if ( current_user_can( Capability::MANAGE_BATCHES ) ) {
			return true;
		}

		return new WP_Error(
			'returntag_forbidden',
			__( 'You are not allowed to manage ReturnTag batches.', 'tagcore' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Apply no-store headers to every lifecycle response.
	 *
	 * @param WP_HTTP_Response $response REST response.
	 * @param WP_REST_Server   $server REST server.
	 * @param WP_REST_Request  $request REST request.
	 */
	public function apply_no_store_headers(
		WP_HTTP_Response $response,
		WP_REST_Server $server,
		WP_REST_Request $request
	): WP_HTTP_Response {
		unset( $server );

		if ( str_starts_with( $request->get_route(), '/' . self::NAMESPACE . '/batches/' ) ) {
			$response->header( 'Cache-Control', 'no-store, private' );
			$response->header( 'Pragma', 'no-cache' );
		}

		return $response;
	}

	/**
	 * Return current lifecycle controls and aggregate Tag counts.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->schema_state->is_current() ) {
			return $this->schema_unavailable();
		}

		$batch_id = $this->positive_integer( $request->get_param( 'batch_id' ) );

		if ( null === $batch_id ) {
			return $this->invalid_request(
				__( 'The Batch identifier is invalid.', 'tagcore' )
			);
		}

		try {
			return $this->response( $this->get_lifecycle->execute( $batch_id ) );
		} catch ( BatchLifecycleNotFound ) {
			return $this->not_found();
		} catch ( BatchLifecycleIntegrityViolation ) {
			return $this->integrity_failure();
		} catch ( Throwable ) {
			return $this->operation_failed();
		}
	}

	/**
	 * Release an exported or safely suspended Batch.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function release_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->change_item( $request, 'release' );
	}

	/**
	 * Suspend new activation for one eligible Batch.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function suspend_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->change_item( $request, 'suspend' );
	}

	/**
	 * Permanently void one eligible Batch.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function void_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->change_item( $request, 'void' );
	}

	/**
	 * Validate and execute one lifecycle mutation.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @param string          $action Trusted fixed action.
	 */
	private function change_item(
		WP_REST_Request $request,
		string $action
	): WP_REST_Response|WP_Error {
		if ( ! $this->schema_state->is_current() ) {
			return $this->schema_unavailable();
		}

		$batch_id = $this->positive_integer( $request->get_param( 'batch_id' ) );
		$expected = $request->get_param( 'expected_status' );
		$status   = is_string( $expected ) ? BatchStatus::tryFrom( $expected ) : null;
		$actor_id = get_current_user_id();

		if ( null === $batch_id || null === $status || $actor_id < 1 ) {
			return $this->invalid_request(
				__( 'The Batch lifecycle request is invalid.', 'tagcore' )
			);
		}

		try {
			if ( 'void' === $action ) {
				$current      = $this->get_lifecycle->execute( $batch_id );
				$confirmation = $request->get_param( 'batch_code_confirmation' );

				if ( ! is_string( $confirmation ) || $confirmation !== $current->state->batch_code ) {
					return $this->invalid_request(
						__( 'Enter the exact Batch Code to confirm this permanent action.', 'tagcore' )
					);
				}

				$result = $this->void_batch->execute( $batch_id, $actor_id, $status );
			} elseif ( 'suspend' === $action ) {
				$result = $this->suspend_batch->execute( $batch_id, $actor_id, $status );
			} else {
				$result = $this->release_batch->execute( $batch_id, $actor_id, $status );
			}

			return $this->response( $result );
		} catch ( BatchLifecycleNotFound ) {
			return $this->not_found();
		} catch ( BatchLifecycleConflict ) {
			return new WP_Error(
				'returntag_batch_lifecycle_conflict',
				__( 'The Batch changed before this action completed. Reload and review its current status.', 'tagcore' ),
				array( 'status' => 409 )
			);
		} catch ( BatchLifecycleNotAllowed ) {
			return new WP_Error(
				'returntag_batch_lifecycle_not_allowed',
				__( 'The current Batch status does not allow this action.', 'tagcore' ),
				array( 'status' => 409 )
			);
		} catch ( BatchLifecycleIntegrityViolation ) {
			return $this->integrity_failure();
		} catch ( Throwable ) {
			return $this->operation_failed();
		}
	}

	/**
	 * Map one lifecycle result to a stable REST projection.
	 *
	 * @param BatchLifecycleResult $result Application result.
	 */
	private function response( BatchLifecycleResult $result ): WP_REST_Response {
		$response = new WP_REST_Response(
			array(
				'batch_id'                     => $result->state->batch_id,
				'batch_code'                   => $result->state->batch_code,
				'batch_status'                 => $result->state->batch_status->value,
				'activation_enabled'           => $result->state->activation_enabled,
				'global_activation_enabled'    => $result->global_activation_enabled,
				'effective_activation_enabled' => $result->effective_activation_enabled,
				'release_ready'                => $result->release_ready,
				'tag_counts'                   => array(
					'total'        => $result->tag_counts->total,
					'unregistered' => $result->tag_counts->unregistered,
					'active'       => $result->tag_counts->active,
					'suspended'    => $result->tag_counts->suspended,
					'retired'      => $result->tag_counts->retired,
				),
				'updated_at'                   => $result->state->updated_at->format( DATE_ATOM ),
				'changed'                      => $result->changed,
			)
		);
		$response->header( 'Cache-Control', 'no-store, private' );
		$response->header( 'Pragma', 'no-cache' );

		return $response;
	}

	/**
	 * Return a safe invalid-request response.
	 *
	 * @param string $message Translated safe message.
	 */
	private function invalid_request( string $message ): WP_Error {
		return new WP_Error(
			'returntag_invalid_batch_lifecycle_request',
			$message,
			array( 'status' => 400 )
		);
	}

	/**
	 * Return a safe missing-Batch response.
	 */
	private function not_found(): WP_Error {
		return new WP_Error(
			'returntag_batch_not_found',
			__( 'The requested Batch was not found.', 'tagcore' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Return a safe integrity response without database details.
	 */
	private function integrity_failure(): WP_Error {
		return new WP_Error(
			'returntag_batch_lifecycle_integrity',
			__( 'The Batch could not be changed because its manufacturing records require review.', 'tagcore' ),
			array( 'status' => 409 )
		);
	}

	/**
	 * Return a safe unexpected-failure response.
	 */
	private function operation_failed(): WP_Error {
		return new WP_Error(
			'returntag_batch_lifecycle_failed',
			__( 'TagCore could not complete the Batch lifecycle action.', 'tagcore' ),
			array( 'status' => 500 )
		);
	}

	/**
	 * Parse one strict positive integer.
	 *
	 * @param mixed $value Request value.
	 */
	private function positive_integer( mixed $value ): ?int {
		if ( is_int( $value ) && $value > 0 ) {
			return $value;
		}

		if ( is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
			return (int) $value;
		}

		return null;
	}

	/**
	 * Return a fail-closed Schema response.
	 */
	private function schema_unavailable(): WP_Error {
		return new WP_Error(
			'returntag_schema_unavailable',
			__( 'TagCore is not ready because its database schema is unavailable.', 'tagcore' ),
			array( 'status' => 503 )
		);
	}
}
