<?php
/**
 * Signed Resend webhook REST boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Email\EmailDeliveryRepository;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

/** Authenticates raw requests before persisting allowlisted metadata. */
final readonly class ResendWebhookRestController {
	/**
	 * Create the controller.
	 *
	 * @param ResendWebhookVerifier   $verifier Raw-body signature verifier.
	 * @param ResendWebhookMapper     $mapper Allowlist mapper.
	 * @param EmailDeliveryRepository $deliveries Metadata-only repository.
	 * @param Clock                   $clock UTC clock.
	 */
	public function __construct(
		private ResendWebhookVerifier $verifier,
		private ResendWebhookMapper $mapper,
		private EmailDeliveryRepository $deliveries,
		private Clock $clock
	) {}

	/** Register the public signed endpoint. */
	public function register(): void {
		add_action(
			'rest_api_init',
			function (): void {
				register_rest_route(
					'returntag/v1',
					'/email/webhooks/resend',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'handle' ),
						'permission_callback' => '__return_true',
					)
				);
			}
		);
	}

	/**
	 * Verify, map, deduplicate, and converge one request.
	 *
	 * @param WP_REST_Request $request Raw WordPress REST request.
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$raw       = $request->get_body();
		$event_id  = $request->get_header( 'svix-id' ) ?? '';
		$timestamp = $request->get_header( 'svix-timestamp' ) ?? '';
		$signature = $request->get_header( 'svix-signature' ) ?? '';
		if ( ! $this->verifier->verify( $event_id, $timestamp, $signature, $raw ) ) {
			return new WP_REST_Response( array( 'code' => 'invalid_webhook' ), 401 );
		}
		try {
			$event = $this->mapper->map( $event_id, $raw );
			$this->deliveries->ingest( $event, $this->clock->now() );
			return new WP_REST_Response( array( 'code' => 'accepted' ), 202 );
		} catch ( \InvalidArgumentException | \JsonException ) {
			return new WP_REST_Response( array( 'code' => 'invalid_payload' ), 400 );
		} catch ( Throwable ) {
			return new WP_REST_Response( array( 'code' => 'temporarily_unavailable' ), 503 );
		}
	}
}
