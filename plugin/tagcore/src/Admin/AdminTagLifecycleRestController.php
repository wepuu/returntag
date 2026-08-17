<?php
/**
 * Administrator Tag lifecycle REST adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Admin\AdminTagLifecycleAction;
use ReturnTag\TagCore\Application\Admin\AdminTagLifecycleState;
use ReturnTag\TagCore\Application\Admin\ManageAdminTagLifecycle;
use ReturnTag\TagCore\Application\Tag\TagIdInputNormalizer;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;
use Throwable;
use ValueError;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/** Maps confirmed lifecycle POST requests to the application service. */
final readonly class AdminTagLifecycleRestController {
	private const NAMESPACE = 'tagcore/v1';

	/**
	 * Create the REST adapter.
	 *
	 * @param ManageAdminTagLifecycle $lifecycle Lifecycle use case.
	 * @param TagIdInputNormalizer    $normalizer Canonical Tag ID normalizer.
	 * @param SchemaState             $schema_state Schema readiness.
	 */
	public function __construct(
		private ManageAdminTagLifecycle $lifecycle,
		private TagIdInputNormalizer $normalizer,
		private SchemaState $schema_state
	) {
	}

	/** Register four capability-bound mutation routes. */
	public function register_routes(): void {
		foreach ( AdminTagLifecycleAction::cases() as $action ) {
			register_rest_route(
				self::NAMESPACE,
				'/admin/tags/(?P<tag_id>[A-Za-z0-9 -]+)/' . $action->value,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => fn( WP_REST_Request $request ): WP_REST_Response|WP_Error => $this->change( $request, $action ),
					'permission_callback' => array( $this, 'authorize' ),
				)
			);
		}
	}

	/**
	 * Require the REST nonce and lifecycle-specific capability.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function authorize( WP_REST_Request $request ): bool|WP_Error {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'returntag_invalid_nonce', __( 'Your secure session expired. Refresh this page and try again.', 'tagcore' ), array( 'status' => 403 ) );
		}
		if ( ! current_user_can( Capability::MANAGE_TAG_LIFECYCLE ) ) {
			return new WP_Error( 'returntag_forbidden', __( 'You are not allowed to change Tag lifecycle state.', 'tagcore' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Execute one validated high-risk action.
	 *
	 * @param WP_REST_Request         $request REST request.
	 * @param AdminTagLifecycleAction $action Lifecycle action.
	 */
	private function change( WP_REST_Request $request, AdminTagLifecycleAction $action ): WP_REST_Response|WP_Error {
		if ( ! $this->schema_state->is_current() ) {
			return new WP_Error( 'returntag_schema_unavailable', __( 'TagCore database preparation is incomplete.', 'tagcore' ), array( 'status' => 503 ) );
		}

		try {
			$tag_value    = $request->get_param( 'tag_id' );
			$confirmation = $request->get_param( 'confirmation' );
			$status_value = $request->get_param( 'expected_status' );
			if ( ! is_string( $tag_value ) || ! is_string( $confirmation ) || ! is_string( $status_value ) || ! $request->has_param( 'expected_owner_id' ) ) {
				return new WP_Error( 'returntag_invalid_request', __( 'Review the exact confirmation values and try again.', 'tagcore' ), array( 'status' => 400 ) );
			}

			$tag            = $this->normalizer->normalize( $tag_value );
			$expected_owner = $this->nullable_positive_id( $request->get_param( 'expected_owner_id' ) );
			$target_user_id = AdminTagLifecycleAction::TRANSFER_OWNER === $action
				? $this->positive_id( $request->get_param( 'target_user_id' ) )
				: null;
			$expected       = new AdminTagLifecycleState( TagStatus::from( $status_value ), $expected_owner );
			$result         = $this->lifecycle->execute( $tag, $action, $confirmation, $expected, $target_user_id, get_current_user_id() );
			if ( ! $result->changed || null === $result->state ) {
				return $this->unavailable();
			}

			return $this->response(
				array(
					'tag_id'     => $tag->value,
					'action'     => $action->value,
					'tag_status' => $result->state->status->value,
					'owner_id'   => $result->state->owner_id,
				)
			);
		} catch ( InvalidArgumentException | ValueError ) {
			return new WP_Error( 'returntag_invalid_request', __( 'Review the exact confirmation values and try again.', 'tagcore' ), array( 'status' => 400 ) );
		} catch ( Throwable ) {
			return new WP_Error( 'returntag_admin_lifecycle_failed', __( 'TagCore could not complete this secure operation.', 'tagcore' ), array( 'status' => 503 ) );
		}
	}

	/**
	 * Apply private security headers to lifecycle responses.
	 *
	 * @param WP_HTTP_Response $response REST response.
	 * @param WP_REST_Server   $server REST server.
	 * @param WP_REST_Request  $request REST request.
	 */
	public function apply_security_headers( WP_HTTP_Response $response, WP_REST_Server $server, WP_REST_Request $request ): WP_HTTP_Response {
		unset( $server );
		if ( str_starts_with( $request->get_route(), '/' . self::NAMESPACE . '/admin/tags/' ) ) {
			$response->header( 'Cache-Control', 'no-store, private' );
			$response->header( 'Pragma', 'no-cache' );
			$response->header( 'Referrer-Policy', 'no-referrer' );
			$response->header( 'X-Content-Type-Options', 'nosniff' );
		}
		return $response;
	}

	/**
	 * Parse a strict positive integer.
	 *
	 * @param mixed $value External identifier value.
	 * @throws InvalidArgumentException When the value is invalid.
	 */
	private function positive_id( mixed $value ): int {
		if ( is_int( $value ) && $value > 0 ) {
			return $value;
		}
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
			throw new InvalidArgumentException( 'Identifier is invalid.' );
		}
		return (int) $value;
	}

	/**
	 * Parse a required nullable Owner User ID snapshot.
	 *
	 * @param mixed $value External nullable identifier value.
	 */
	private function nullable_positive_id( mixed $value ): ?int {
		return null === $value ? null : $this->positive_id( $value );
	}

	/**
	 * Create a private response.
	 *
	 * @param array<string, mixed> $data Response projection.
	 */
	private function response( array $data ): WP_REST_Response {
		$response = new WP_REST_Response( $data );
		$response->header( 'Cache-Control', 'no-store, private' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Referrer-Policy', 'no-referrer' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		return $response;
	}

	/** Return one non-enumerating stale or ineligible action response. */
	private function unavailable(): WP_Error {
		return new WP_Error( 'returntag_lifecycle_unavailable', __( 'This Tag changed or the requested action is unavailable. Reload the Tag and try again.', 'tagcore' ), array( 'status' => 409 ) );
	}
}
