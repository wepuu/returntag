<?php
/**
 * Direct Resend transactional email adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Email\EmailDeliveryRepository;
use ReturnTag\TagCore\Application\Email\TransactionalEmail;
use ReturnTag\TagCore\Application\Email\TransactionalEmailGateway;
use ReturnTag\TagCore\Application\Email\TransactionalEmailResult;
use Throwable;

/** Sends through the documented HTTPS API and records only provider metadata. */
final readonly class ResendTransactionalEmailGateway implements TransactionalEmailGateway {
	private const ENDPOINT = 'https://api.resend.com/emails';

	/**
	 * Create the direct adapter.
	 *
	 * @param ResendConfiguration     $configuration Environment-only provider configuration.
	 * @param EmailDeliveryRepository $deliveries Metadata-only delivery repository.
	 * @param Clock                   $clock UTC clock.
	 */
	public function __construct(
		private ResendConfiguration $configuration,
		private EmailDeliveryRepository $deliveries,
		private Clock $clock
	) {}

	/**
	 * Submit one message and record provider acceptance.
	 *
	 * @param TransactionalEmail $email Private in-memory request.
	 */
	public function send( TransactionalEmail $email ): TransactionalEmailResult {
		$now = $this->clock->now();
		try {
			$start = $this->deliveries->begin( $email->idempotency_key, $email->purpose, 'resend', $now );
			if ( null !== $start->provider_message_id ) {
				return TransactionalEmailResult::accepted( $start->provider_message_id );
			}
			if ( ! $start->dispatch_allowed ) {
				return TransactionalEmailResult::failed();
			}

			$body = wp_json_encode( $this->payload( $email ), JSON_UNESCAPED_SLASHES );
			if ( ! is_string( $body ) ) {
				$this->deliveries->mark_failed( $start->delivery_id, $this->clock->now() );
				return TransactionalEmailResult::failed();
			}
			$response = wp_remote_post(
				self::ENDPOINT,
				array(
					'timeout'     => 15.0,
					'redirection' => 0,
					'headers'     => array(
						'Authorization'   => 'Bearer ' . $this->configuration->api_key,
						'Content-Type'    => 'application/json',
						'Idempotency-Key' => $email->idempotency_key,
					),
					'body'        => $body,
				)
			);

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				$this->deliveries->mark_failed( $start->delivery_id, $this->clock->now() );
				return TransactionalEmailResult::failed();
			}

			$decoded = json_decode( wp_remote_retrieve_body( $response ), true, 8, JSON_THROW_ON_ERROR );
			$id      = is_array( $decoded ) ? ( $decoded['id'] ?? null ) : null;
			if ( ! is_string( $id ) || 1 !== preg_match( '/^[A-Za-z0-9_-]{1,191}$/D', $id ) ) {
				$this->deliveries->mark_failed( $start->delivery_id, $this->clock->now() );
				return TransactionalEmailResult::failed();
			}

			if ( ! $this->deliveries->mark_sent( $start->delivery_id, $id, $this->clock->now() ) ) {
				return TransactionalEmailResult::failed();
			}

			return TransactionalEmailResult::accepted( $id );
		} catch ( Throwable ) {
			if ( isset( $start ) && $start->dispatch_allowed ) {
				try {
					$this->deliveries->mark_failed( $start->delivery_id, $this->clock->now() );
				} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Preserve the fixed fail-closed result.
				}
			}
			return TransactionalEmailResult::failed();
		}
	}

	/**
	 * Build the ephemeral documented API payload.
	 *
	 * @param TransactionalEmail $email Private in-memory request.
	 * @return array<string, mixed>
	 */
	private function payload( TransactionalEmail $email ): array {
		$payload = array(
			'from'    => sprintf( '%s <%s>', $this->configuration->from_name, $this->configuration->from_email ),
			'to'      => array( $email->recipient->value ),
			'subject' => $email->subject,
			'text'    => $email->text,
		);
		if ( null !== $email->html ) {
			$payload['html'] = $email->html;
		}
		if ( array() !== $email->attachments ) {
			$payload['attachments'] = array_map(
				static fn( $attachment ): array => array(
					'filename'   => $attachment->filename,
					'content'    => base64_encode( $attachment->content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required documented transport encoding.
					'content_id' => $attachment->content_id,
				),
				$email->attachments
			);
		}
		return $payload;
	}
}
