<?php
/**
 * RT-337 email delivery projection integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\Email\EmailDeliveryTransitionPolicy;
use ReturnTag\TagCore\Application\Email\EmailWebhookEvent;
use ReturnTag\TagCore\Application\Email\TransactionalEmail;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;
use ReturnTag\TagCore\Infrastructure\Email\ResendConfiguration;
use ReturnTag\TagCore\Infrastructure\Email\ResendTransactionalEmailGateway;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbEmailDeliveryRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use WP_UnitTestCase;
use wpdb;

/** Verifies idempotency, deduplication, and out-of-order state convergence. */
final class EmailDeliveryProjectionTest extends WP_UnitTestCase {
	/** Install a clean current schema. */
	protected function setUp(): void {
		global $wpdb;
		parent::setUp();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->clear_schema( $wpdb );
		do_action( 'activate_' . plugin_basename( RETURNTAG_TAGCORE_FILE ), false );
	}

	/** Remove isolated records and tables. */
	protected function tearDown(): void {
		global $wpdb;
		$this->clear_schema( $wpdb );
		parent::tearDown();
	}

	/** Provider acceptance remains sent until a verified delivery event converges. */
	public function test_idempotent_send_and_webhook_convergence(): void {
		global $wpdb;
		$repository = $this->repository( $wpdb );
		$sent_at    = new DateTimeImmutable( '2026-08-26T12:00:00Z' );
		$key        = hash( 'sha256', 'synthetic-delivery-1' );

		$start = $repository->begin( $key, 'owner_test', 'resend', $sent_at );
		self::assertTrue( $start->dispatch_allowed );
		self::assertTrue( $repository->mark_sent( $start->delivery_id, 'email_synthetic_1', $sent_at ) );
		self::assertFalse( $repository->begin( $key, 'owner_test', 'resend', $sent_at )->dispatch_allowed );

		$delivered = new EmailWebhookEvent( 'resend', 'event_synthetic_1', 'email_synthetic_1', 'email.delivered', DeliveryStatus::DELIVERED, $sent_at->modify( '+2 minutes' ) );
		self::assertTrue( $repository->ingest( $delivered, $sent_at->modify( '+3 minutes' ) ) );
		self::assertFalse( $repository->ingest( $delivered, $sent_at->modify( '+4 minutes' ) ) );

		$tables   = new TableNames( $wpdb->prefix );
		$delivery = $wpdb->get_row( $wpdb->prepare( 'SELECT delivery_status,delivered_at FROM %i WHERE delivery_id=%d', $tables->email_deliveries(), $start->delivery_id ), ARRAY_A );
		$events   = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE provider_event_id=%s', $tables->email_webhook_events(), 'event_synthetic_1' ) );
		self::assertSame( 'delivered', $delivery['delivery_status'] );
		self::assertSame( '2026-08-26 12:02:00', $delivery['delivered_at'] );
		self::assertSame( '1', (string) $events );

		$older = new EmailWebhookEvent( 'resend', 'event_synthetic_2', 'email_synthetic_1', 'email.delivery_delayed', DeliveryStatus::DEFERRED, $sent_at->modify( '+1 minute' ) );
		$repository->ingest( $older, $sent_at->modify( '+5 minutes' ) );
		self::assertSame( 'delivered', $wpdb->get_var( $wpdb->prepare( 'SELECT delivery_status FROM %i WHERE delivery_id=%d', $tables->email_deliveries(), $start->delivery_id ) ) );
	}

	/** Tracking events are retained as minimal processed metadata without changing state. */
	public function test_tracking_event_is_ignored_without_raw_payload_storage(): void {
		global $wpdb;
		$now        = new DateTimeImmutable( '2026-08-26T12:00:00Z' );
		$repository = $this->repository( $wpdb );
		$repository->ingest( new EmailWebhookEvent( 'resend', 'event_opened_1', 'email_unknown', 'email.opened', null, $now ), $now );

		$tables = new TableNames( $wpdb->prefix );
		$row    = $wpdb->get_row( $wpdb->prepare( 'SELECT mapped_status,processed_at FROM %i WHERE provider_event_id=%s', $tables->email_webhook_events(), 'event_opened_1' ), ARRAY_A );
		self::assertNull( $row['mapped_status'] );
		self::assertSame( '2026-08-26 12:00:00', $row['processed_at'] );
		self::assertNotContains( 'payload', $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $tables->email_webhook_events() ) ) );
	}

	/** The direct adapter sends once and persists only provider metadata. */
	public function test_direct_resend_adapter_is_idempotent_and_private(): void {
		global $wpdb;
		$now        = new DateTimeImmutable( '2026-08-26T12:00:00Z' );
		$http_calls = 0;
		$request    = array();
		$filter     = static function ( $preempt, array $arguments, string $url ) use ( &$http_calls, &$request ) {
			++$http_calls;
			$request = array(
				'url'       => $url,
				'arguments' => $arguments,
			);
			return array(
				'headers'  => array(),
				'body'     => '{"id":"email_synthetic_http"}',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			$clock   = new class( $now ) implements Clock {
				/**
				 * Create a fixed clock.
				 *
				 * @param DateTimeImmutable $now Fixed UTC time.
				 */
				public function __construct( private readonly DateTimeImmutable $now ) {}

				/** Return the fixed UTC time. */
				public function now(): DateTimeImmutable {
					return $this->now;
				}
			};
			$gateway = new ResendTransactionalEmailGateway(
				new ResendConfiguration( 're_' . str_repeat( 'a', 24 ), 'sender@example.test', 'ForgeTag' ),
				$this->repository( $wpdb ),
				$clock
			);
			$email   = new TransactionalEmail(
				'owner_test',
				hash( 'sha256', 'synthetic-http-idempotency' ),
				new EmailAddress( 'recipient@example.test' ),
				'Synthetic subject',
				'Synthetic body'
			);

			$first  = $gateway->send( $email );
			$second = $gateway->send( $email );
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}

		self::assertTrue( $first->accepted );
		self::assertSame( 'email_synthetic_http', $first->provider_message_id );
		self::assertTrue( $second->accepted );
		self::assertSame( 1, $http_calls );
		self::assertSame( 'https://api.resend.com/emails', $request['url'] );
		self::assertSame( hash( 'sha256', 'synthetic-http-idempotency' ), $request['arguments']['headers']['Idempotency-Key'] );
		$payload = json_decode( (string) $request['arguments']['body'], true, 8, JSON_THROW_ON_ERROR );
		self::assertSame( array( 'recipient@example.test' ), $payload['to'] );
		self::assertArrayNotHasKey( 'reply_to', $payload );
		self::assertArrayNotHasKey( 'cc', $payload );
		self::assertArrayNotHasKey( 'bcc', $payload );

		$tables  = new TableNames( $wpdb->prefix );
		$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $tables->email_deliveries() ) );
		self::assertNotContains( 'recipient', $columns );
		self::assertNotContains( 'subject', $columns );
		self::assertNotContains( 'body', $columns );
		self::assertSame( 'sent', $wpdb->get_var( $wpdb->prepare( 'SELECT delivery_status FROM %i WHERE provider_message_id=%s', $tables->email_deliveries(), 'email_synthetic_http' ) ) );
	}

	/**
	 * Build the production repository over isolated tables.
	 *
	 * @param wpdb $database Active test database.
	 */
	private function repository( wpdb $database ): WpdbEmailDeliveryRepository {
		return new WpdbEmailDeliveryRepository( new WpdbGateway( $database ), new TableNames( $database->prefix ), new DatabaseDateTimeCodec(), new WpdbTransactionManager( $database ), new EmailDeliveryTransitionPolicy() );
	}

	/**
	 * Remove only trusted ReturnTag schema.
	 *
	 * @param wpdb $database Active test database.
	 */
	private function clear_schema( wpdb $database ): void {
		$names = new TableNames( $database->prefix );
		foreach ( array( $names->email_webhook_events(), $names->email_deliveries(), $names->tag_transfers(), $names->finder_report_media(), $names->finder_reports(), $names->events(), $names->access_tokens(), $names->messages(), $names->conversations(), $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated cleanup with trusted identifiers.
			$database->query( "DROP TABLE IF EXISTS {$table}" );
		}
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
