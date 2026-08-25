<?php
/**
 * Privacy-safe result collector for the RT-336 staging correlation probe.
 *
 * @package ReturnTag\Spike\Rt336
 */

declare(strict_types=1);

namespace ReturnTag\Spike\Rt336;

/**
 * Collects only the minimum evidence needed to prove provider-ID correlation.
 */
final class ResendCorrelationProbe {

	private const PROVIDER_HEADER = 'x-msg-id';

	/**
	 * Whether the WP Mail SMTP after-send hook fired.
	 *
	 * @var bool
	 */
	private bool $hook_observed = false;

	/**
	 * Whether the selected provider identified itself as Resend.
	 *
	 * @var bool
	 */
	private bool $resend_mailer_observed = false;

	/**
	 * Valid provider identifiers observed during this one probe send.
	 *
	 * @var list<string>
	 */
	private array $provider_ids = array();

	/**
	 * Whether a matching header contained an invalid value.
	 *
	 * @var bool
	 */
	private bool $invalid_provider_id = false;

	/**
	 * Capture the documented WP Mail SMTP after-send hook arguments.
	 *
	 * @param mixed $mailer      Provider mailer object.
	 * @param mixed $mailcatcher WP Mail SMTP mailcatcher object.
	 */
	public function capture( mixed $mailer, mixed $mailcatcher ): void {
		$this->hook_observed = true;

		if ( ! is_object( $mailer ) || ! method_exists( $mailer, 'get_mailer_name' ) ) {
			return;
		}

		$this->resend_mailer_observed = 'resend' === strtolower( (string) $mailer->get_mailer_name() );

		if ( ! $this->resend_mailer_observed || ! is_object( $mailcatcher ) || ! method_exists( $mailcatcher, 'getCustomHeaders' ) ) {
			return;
		}

		$headers = $mailcatcher->getCustomHeaders();
		if ( ! is_array( $headers ) ) {
			$this->invalid_provider_id = true;

			return;
		}

		foreach ( $headers as $header ) {
			if ( ! is_array( $header ) || ! isset( $header[0] ) || self::PROVIDER_HEADER !== strtolower( (string) $header[0] ) ) {
				continue;
			}

			if ( ! isset( $header[1] ) || ! is_scalar( $header[1] ) ) {
				$this->invalid_provider_id = true;

				continue;
			}

			$provider_id = trim( (string) $header[1] );
			if ( ! self::valid_provider_id( $provider_id ) ) {
				$this->invalid_provider_id = true;

				continue;
			}

			$this->provider_ids[] = $provider_id;
		}
	}

	/**
	 * Build privacy-safe JSON-ready evidence.
	 *
	 * @param bool $wp_mail_accepted Whether WordPress accepted the send call.
	 *
	 * @return array<string, bool|int|string|null>
	 */
	public function result( bool $wp_mail_accepted ): array {
		$status = $this->status( $wp_mail_accepted );
		$id     = 'correlated' === $status ? $this->provider_ids[0] : null;

		return array(
			'schema'                   => 'returntag.rt336.resend-correlation.v1',
			'status'                   => $status,
			'wp_mail_accepted'         => $wp_mail_accepted,
			'after_send_hook_observed' => $this->hook_observed,
			'resend_mailer_observed'   => $this->resend_mailer_observed,
			'provider_id_count'        => count( $this->provider_ids ),
			'provider_id_length'       => null === $id ? null : strlen( $id ),
			'provider_id_sha256'       => null === $id ? null : hash( 'sha256', $id ),
		);
	}

	/**
	 * Resolve the probe outcome without exposing provider data.
	 *
	 * @param bool $wp_mail_accepted Whether WordPress accepted the send call.
	 */
	private function status( bool $wp_mail_accepted ): string {
		if ( ! $wp_mail_accepted ) {
			return 'send_failed';
		}

		if ( ! $this->hook_observed ) {
			return 'hook_not_observed';
		}

		if ( ! $this->resend_mailer_observed ) {
			return 'unexpected_mailer';
		}

		if ( $this->invalid_provider_id ) {
			return 'invalid_provider_id';
		}

		if ( 0 === count( $this->provider_ids ) ) {
			return 'provider_id_missing';
		}

		if ( 1 !== count( $this->provider_ids ) ) {
			return 'provider_id_ambiguous';
		}

		return 'correlated';
	}

	/**
	 * Validate the provider identifier for safe hashing and bounded handling.
	 *
	 * @param string $provider_id Candidate provider identifier.
	 */
	private static function valid_provider_id( string $provider_id ): bool {
		return '' !== $provider_id
			&& strlen( $provider_id ) <= 191
			&& 1 === preg_match( '/^[\x21-\x7E]+$/D', $provider_id );
	}
}
