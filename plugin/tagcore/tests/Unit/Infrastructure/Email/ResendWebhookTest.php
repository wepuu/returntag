<?php
/**
 * Resend webhook verification and mapping tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Email;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;
use ReturnTag\TagCore\Infrastructure\Email\ResendWebhookMapper;
use ReturnTag\TagCore\Infrastructure\Email\ResendWebhookVerifier;

/** Covers raw-body authentication and the fixed provider event map. */
final class ResendWebhookTest extends TestCase {
	/** A valid signature passes while modified and expired requests fail. */
	public function test_verifies_exact_recent_raw_body(): void {
		$now       = new DateTimeImmutable( '2026-08-26T12:00:00Z' );
		$clock     = new class( $now ) implements Clock {
			/**
			 * Create a fixed clock.
			 *
			 * @param DateTimeImmutable $value Fixed UTC time.
			 */
			public function __construct( private readonly DateTimeImmutable $value ) {}
			/** Return fixed UTC time. */
			public function now(): DateTimeImmutable {
				return $this->value;
			}
		};
		$key       = 'synthetic-signing-key-material-32';
		$secret    = 'whsec_' . base64_encode( $key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Synthetic Svix fixture.
		$body      = '{"type":"email.delivered"}';
		$event_id  = 'evt_synthetic_1';
		$time      = (string) $now->getTimestamp();
		$signature = 'v1,' . base64_encode( hash_hmac( 'sha256', $event_id . '.' . $time . '.' . $body, $key, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Synthetic Svix fixture.
		$verifier  = new ResendWebhookVerifier( $secret, $clock );

		self::assertTrue( $verifier->verify( $event_id, $time, $signature, $body ) );
		self::assertFalse( $verifier->verify( $event_id, $time, $signature, $body . ' ' ) );
		self::assertFalse( $verifier->verify( $event_id, (string) ( $now->getTimestamp() - 301 ), $signature, $body ) );
	}

	/** Delivery events map canonically while tracking events map to null. */
	public function test_maps_delivery_and_ignores_tracking_events(): void {
		$mapper    = new ResendWebhookMapper();
		$delivered = $mapper->map( 'evt_1', '{"type":"email.delivered","created_at":"2026-08-26T12:00:00Z","data":{"email_id":"email_1"}}' );
		$opened    = $mapper->map( 'evt_2', '{"type":"email.opened","created_at":"2026-08-26T12:01:00Z","data":{"email_id":"email_1"}}' );

		self::assertSame( DeliveryStatus::DELIVERED, $delivered->mapped_status );
		self::assertNull( $opened->mapped_status );
		self::assertSame( 'email_1', $delivered->provider_message_id );
	}

	/** Unknown provider event names fail closed instead of becoming tracking events. */
	public function test_rejects_unknown_email_event(): void {
		$this->expectException( \InvalidArgumentException::class );
		( new ResendWebhookMapper() )->map( 'evt_3', '{"type":"email.unknown","created_at":"2026-08-26T12:00:00Z","data":{"email_id":"email_1"}}' );
	}
}
