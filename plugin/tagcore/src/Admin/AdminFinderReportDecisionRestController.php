<?php
/**
 * Capability-separated Finder Report decision REST adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use ReturnTag\TagCore\Application\Admin\AdminFinderReportAction;
use ReturnTag\TagCore\Application\Admin\AdminFinderReportState;
use ReturnTag\TagCore\Application\Admin\ManageAdminFinderReportDecision;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;
use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;
use Throwable;
use ValueError;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/** Maps strict decision POST requests to the Application use case. */
final readonly class AdminFinderReportDecisionRestController {
	private const NAMESPACE = 'tagcore/v1';

	/**
	 * Create the REST adapter.
	 *
	 * @param ManageAdminFinderReportDecision $decisions Decision use case.
	 * @param SchemaState                     $schema_state Schema readiness.
	 */
	public function __construct( private ManageAdminFinderReportDecision $decisions, private SchemaState $schema_state ) {
	}

	/** Register the four capability-bound POST routes. */
	public function register_routes(): void {
		foreach ( AdminFinderReportAction::cases() as $action ) {
			register_rest_route(
				self::NAMESPACE,
				'/admin/finder-reports/(?P<id>[1-9][0-9]*)/' . $action->value,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => fn( WP_REST_Request $request ): WP_REST_Response|WP_Error => $this->change( $request, $action ),
					'permission_callback' => array( $this, 'authorize' ),
				)
			);
		}
	}
	/**
	 * Require a REST Nonce and decision capability.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function authorize( WP_REST_Request $request ): bool|WP_Error {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'returntag_invalid_nonce', __( 'Your secure session expired. Refresh this page and try again.', 'tagcore' ), array( 'status' => 403 ) ); }
		return current_user_can( Capability::MANAGE_FINDER_REPORT_DECISIONS ) ? true : new WP_Error( 'returntag_forbidden', __( 'You are not allowed to make Finder Report decisions.', 'tagcore' ), array( 'status' => 403 ) );
	}
	/**
	 * Execute one strict, confirmed decision request.
	 *
	 * @param WP_REST_Request         $request REST request.
	 * @param AdminFinderReportAction $action Requested action.
	 * @throws InvalidArgumentException When request values are invalid.
	 */
	private function change( WP_REST_Request $request, AdminFinderReportAction $action ): WP_REST_Response|WP_Error {
		if ( ! $this->schema_state->is_current() ) {
			return new WP_Error( 'returntag_schema_unavailable', __( 'TagCore database preparation is incomplete.', 'tagcore' ), array( 'status' => 503 ) ); }
		try {
			foreach ( array( 'id', 'confirmation', 'expected_report_status', 'expected_evidence_status', 'expected_notification_status', 'expected_has_conversation', 'expected_expires_at', 'expected_retention_until', 'expected_hold_until', 'expected_has_review_evidence' ) as $key ) {
				if ( ! $request->has_param( $key ) ) {
					throw new InvalidArgumentException( 'Missing state.' ); }
			}
			$id           = $this->positive_id( $request->get_param( 'id' ) );
			$confirmation = $request->get_param( 'confirmation' );
			if ( ! is_string( $confirmation ) ) {
				throw new InvalidArgumentException( 'Confirmation is invalid.' ); }
			$notification = $request->get_param( 'expected_notification_status' );
			if ( null !== $notification && ( ! is_string( $notification ) || ! in_array( $notification, array( 'queued', 'sent', 'delivered', 'deferred', 'failed', 'bounced', 'complained' ), true ) ) ) {
				throw new InvalidArgumentException( 'Notification is invalid.' ); }
			$expected = new AdminFinderReportState(
				FinderReportStatus::from( $this->required_string( $request->get_param( 'expected_report_status' ) ) ),
				FinderEvidenceStatus::from( $this->required_string( $request->get_param( 'expected_evidence_status' ) ) ),
				$notification,
				$this->required_bool( $request->get_param( 'expected_has_conversation' ) ) ? 1 : null,
				$this->required_date( $request->get_param( 'expected_expires_at' ) ),
				$this->required_date( $request->get_param( 'expected_retention_until' ) ),
				$this->nullable_date( $request->get_param( 'expected_hold_until' ) ),
				$this->required_bool( $request->get_param( 'expected_has_review_evidence' ) )
			);
			$result   = $this->decisions->execute( $id, $action, $confirmation, $expected, get_current_user_id() );
			if ( ! $result->changed || null === $result->state ) {
				return $this->unavailable(); }
			return $this->response(
				array(
					'finder_report_id'    => $id,
					'action'              => $action->value,
					'report_status'       => $result->state->report_status->value,
					'evidence_status'     => $result->state->evidence_status->value,
					'notification_status' => $result->state->notification_status,
					'has_conversation'    => null !== $result->state->conversation_id,
					'hold_until'          => $result->state->hold_until?->format( 'Y-m-d H:i:s' ),
					'hold_active'         => $result->state->hold_active( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) ),
				)
			);
		} catch ( InvalidArgumentException | ValueError ) {
			return new WP_Error( 'returntag_invalid_request', __( 'Review the exact confirmation values and try again.', 'tagcore' ), array( 'status' => 400 ) );
		} catch ( Throwable ) {
			return new WP_Error( 'returntag_finder_decision_failed', __( 'TagCore could not complete this secure operation.', 'tagcore' ), array( 'status' => 503 ) );
		}
	}
	/**
	 * Apply private response headers to decision routes.
	 *
	 * @param WP_HTTP_Response $response REST response.
	 * @param WP_REST_Server   $server REST server.
	 * @param WP_REST_Request  $request REST request.
	 */
	public function apply_security_headers( WP_HTTP_Response $response, WP_REST_Server $server, WP_REST_Request $request ): WP_HTTP_Response {
		unset( $server );
		if ( str_starts_with( $request->get_route(), '/' . self::NAMESPACE . '/admin/finder-reports/' ) ) {
			foreach ( array(
				'Cache-Control'          => 'no-store, private',
				'Pragma'                 => 'no-cache',
				'Referrer-Policy'        => 'no-referrer',
				'X-Content-Type-Options' => 'nosniff',
			) as $name => $value ) {
				$response->header( $name, $value ); }
		}
		return $response;
	}
	/**
	 * Parse one strict positive identifier.
	 *
	 * @param mixed $value External value.
	 * @throws InvalidArgumentException When invalid.
	 */
	private function positive_id( mixed $value ): int {
		if ( is_int( $value ) && $value > 0 ) {
			return $value;
		} if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
			throw new InvalidArgumentException( 'ID invalid.' );
		} return (int) $value; }
	/**
	 * Parse one required Boolean snapshot.
	 *
	 * @param mixed $value External value.
	 * @throws InvalidArgumentException When invalid.
	 */
	private function required_bool( mixed $value ): bool {
		if ( ! is_bool( $value ) ) {
			throw new InvalidArgumentException( 'Boolean invalid.' );
		} return $value; }
	/**
	 * Parse one required string snapshot.
	 *
	 * @param mixed $value External value.
	 * @throws InvalidArgumentException When invalid.
	 */
	private function required_string( mixed $value ): string {
		if ( ! is_string( $value ) || '' === $value ) {
			throw new InvalidArgumentException( 'Value invalid.' );
		}

		return $value;
	}

	/**
	 * Parse one required exact UTC database timestamp.
	 *
	 * @param mixed $value External value.
	 * @throws InvalidArgumentException When invalid.
	 */
	private function required_date( mixed $value ): DateTimeImmutable {
		$date = $this->nullable_date( $value );
		if ( null === $date ) {
			throw new InvalidArgumentException( 'Date invalid.' );
		}

		return $date;
	}

	/**
	 * Parse one nullable exact UTC database timestamp.
	 *
	 * @param mixed $value External value.
	 * @throws InvalidArgumentException When invalid.
	 */
	private function nullable_date( mixed $value ): ?DateTimeImmutable {
		if ( null === $value ) {
			return null;
		}

		$date = is_string( $value ) ? DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) ) : false;
		if ( false === $date || $date->format( 'Y-m-d H:i:s' ) !== $value ) {
			throw new InvalidArgumentException( 'Date invalid.' );
		}

		return $date;
	}

	/**
	 * Create a private JSON response.
	 *
	 * @param array<string, mixed> $data Safe response projection.
	 */
	private function response( array $data ): WP_REST_Response {
		$response = new WP_REST_Response( $data );
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

	/** Return a non-enumerating conflict response. */
	private function unavailable(): WP_Error {
		return new WP_Error( 'returntag_finder_decision_unavailable', __( 'This Finder Report changed or the requested action is unavailable. Reload it and try again.', 'tagcore' ), array( 'status' => 409 ) );
	}
}
