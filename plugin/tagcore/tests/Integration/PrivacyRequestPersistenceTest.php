<?php
/**
 * Schema 16 privacy request persistence coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\DenyAllEventMetadataPolicy;
use ReturnTag\TagCore\Application\Privacy\PrivacyRequestEventIdentityPolicy;
use ReturnTag\TagCore\Application\Privacy\PrivacyRequestSubject;
use ReturnTag\TagCore\Application\Privacy\PrivacyRequestWorkflow;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestError;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestReason;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestState;
use ReturnTag\TagCore\Domain\Privacy\PrivacyRequestType;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbEventRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbPrivacyRequestRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use WP_UnitTestCase;
use wpdb;

/** Verifies idempotency, the unfinished slot, transitions, and privacy shape. */
final class PrivacyRequestPersistenceTest extends WP_UnitTestCase {
	/** Install a clean Schema 16 before every test. */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->clear_schema( $wpdb );
		$registry = ( new MigrationRegistryFactory( $wpdb ) )->create();
		( new MigrationRunner( $registry, new WordPressSchemaVersionStore(), new WordPressAdvisoryMigrationLock( $wpdb, get_current_blog_id(), 0 ) ) )->migrate();
	}

	/** Remove every isolated TagCore table after each test. */
	protected function tearDown(): void {
		global $wpdb;
		$this->clear_schema( $wpdb );
		parent::tearDown();
	}

	/** One unfinished slot must converge repeats and release after completion. */
	public function test_request_slot_is_idempotent_and_released_only_after_completion(): void {
		global $wpdb;
		$repo    = $this->repository( $wpdb );
		$subject = new PrivacyRequestSubject( 'user', 42, str_repeat( 'a', 64 ) );
		$now     = $this->utc( '2026-08-28 04:00:00' );

		$first = $repo->begin( $subject, PrivacyRequestType::EXPORT, 'FORGETAG-PRIVACY-RETENTION-v1.0-20260827', str_repeat( 'b', 64 ), str_repeat( 'c', 64 ), $now );
		self::assertTrue( $first->created );
		self::assertSame( PrivacyRequestState::QUEUED, $first->request->state );

		$repeat = $repo->begin( $subject, PrivacyRequestType::EXPORT, 'FORGETAG-PRIVACY-RETENTION-v1.0-20260827', str_repeat( 'd', 64 ), str_repeat( 'c', 64 ), $now );
		self::assertFalse( $repeat->created );
		self::assertSame( $first->request->request_id, $repeat->request->request_id );

		$processing = $repo->claim( $first->request->request_id, 1, $now );
		self::assertNotNull( $processing );
		self::assertSame( 1, $processing->attempt_count );
		self::assertNull( $repo->claim( $first->request->request_id, 1, $now ) );

		$checkpoint = $repo->checkpoint( $first->request->request_id, 2, 'processing_claimed', $now );
		self::assertNotNull( $checkpoint );
		$required = $repo->require_action( $first->request->request_id, 3, PrivacyRequestReason::ACTIVE_TAG, $now );
		self::assertNotNull( $required );
		self::assertSame( PrivacyRequestState::ACTION_REQUIRED, $required->state );
		$queued = $repo->requeue( $first->request->request_id, 4, $now );
		self::assertNotNull( $queued );
		$retrying = $repo->claim( $first->request->request_id, 5, $now );
		self::assertNotNull( $retrying );
		$failed = $repo->fail( $first->request->request_id, 6, PrivacyRequestError::PROCESSING_ERROR, $now );
		self::assertNotNull( $failed );
		self::assertSame( PrivacyRequestState::FAILED, $failed->state );
		$retrying = $repo->claim( $first->request->request_id, 7, $now );
		self::assertNotNull( $retrying );
		self::assertSame( 3, $retrying->attempt_count );
		$completed = $repo->complete( $first->request->request_id, 8, $now );
		self::assertNotNull( $completed );
		self::assertNull( $completed->active_request_key );

		$next = $repo->begin( $subject, PrivacyRequestType::EXPORT, 'FORGETAG-PRIVACY-RETENTION-v1.0-20260827', str_repeat( 'e', 64 ), str_repeat( 'c', 64 ), $now );
		self::assertTrue( $next->created );
		self::assertNotSame( $first->request->request_id, $next->request->request_id );
	}

	/** Finder rows and the table shape must omit private payload fields. */
	public function test_finder_identity_and_table_shape_contain_no_private_payload_columns(): void {
		global $wpdb;
		$repo    = $this->repository( $wpdb );
		$subject = new PrivacyRequestSubject( 'finder', null, str_repeat( 'f', 64 ) );
		$start   = $repo->begin( $subject, PrivacyRequestType::ERASURE, 'FORGETAG-PRIVACY-RETENTION-v1.0-20260827', str_repeat( '1', 64 ), str_repeat( '2', 64 ), $this->utc( '2026-08-28 04:00:00' ) );

		self::assertNull( $start->request->subject->user_id );
		$table   = ( new TableNames( $wpdb->prefix ) )->privacy_requests();
		$columns = array_map( 'strtolower', $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) ) );
		foreach ( array( 'email', 'ip', 'evidence', 'message', 'token', 'payload', 'note' ) as $forbidden ) {
			self::assertSame( array(), array_values( array_filter( $columns, static fn( string $column ): bool => str_contains( $column, $forbidden ) ) ) );
		}
	}

	/** Real orchestration must commit fixed, metadata-free Events with state. */
	public function test_workflow_commits_request_and_metadata_free_events_atomically(): void {
		global $wpdb;
		$gateway  = new WpdbGateway( $wpdb );
		$tables   = new TableNames( $wpdb->prefix );
		$dates    = new DatabaseDateTimeCodec();
		$now      = $this->utc( '2026-08-28 04:30:00' );
		$flags    = new class() implements FeatureFlagReader {
			/**
			 * Enable only the two dormant privacy controls for this isolated test.
			 *
			 * @param FeatureFlag $feature_flag Requested control.
			 */
			public function is_enabled( FeatureFlag $feature_flag ): bool {
				return in_array( $feature_flag, array( FeatureFlag::PRIVACY_REQUEST_INTAKE, FeatureFlag::PRIVACY_REQUEST_PROCESSING ), true );
			}
		};
		$clock    = new class( $now ) implements Clock {
			/**
			 * Create one fixed UTC Clock.
			 *
			 * @param DateTimeImmutable $now Fixed UTC time.
			 */
			public function __construct( private DateTimeImmutable $now ) {}

			/** Return the fixed UTC time. */
			public function now(): DateTimeImmutable {
				return $this->now;
			}
		};
		$workflow = new PrivacyRequestWorkflow(
			new WpdbPrivacyRequestRepository( $gateway, $tables, $dates ),
			new WpdbEventRepository( $gateway, $tables, $dates, new DenyAllEventMetadataPolicy(), new PrivacyRequestEventIdentityPolicy() ),
			new WpdbTransactionManager( $wpdb ),
			$flags,
			$clock
		);

		$start      = $workflow->start( new PrivacyRequestSubject( 'user', 42, str_repeat( '7', 64 ) ), PrivacyRequestType::EXPORT, str_repeat( '8', 64 ) );
		$processing = $workflow->claim( $start->request->request_id, $start->request->row_version );
		self::assertSame( PrivacyRequestState::PROCESSING, $processing->state );

		$events = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT event_type,actor_type,actor_id,target_type,target_id,event_result,correlation_id,metadata_json FROM %i WHERE target_type=%s AND target_id=%s ORDER BY event_id ASC',
				$tables->events(),
				'privacy_request',
				(string) $start->request->request_id
			),
			ARRAY_A
		);
		self::assertCount( 2, $events );
		self::assertSame( 'privacy_request_queued', $events[0]['event_type'] );
		self::assertSame( 'user', $events[0]['actor_type'] );
		self::assertSame( '42', (string) $events[0]['actor_id'] );
		self::assertSame( 'privacy_request_processing', $events[1]['event_type'] );
		self::assertSame( 'system', $events[1]['actor_type'] );
		foreach ( $events as $event ) {
			self::assertSame( 'privacy_request', $event['target_type'] );
			self::assertSame( (string) $start->request->request_id, $event['target_id'] );
			self::assertNull( $event['correlation_id'] );
			self::assertNull( $event['metadata_json'] );
		}
	}

	/**
	 * Build the real wpdb privacy request Repository.
	 *
	 * @param wpdb $database Active test database.
	 */
	private function repository( wpdb $database ): WpdbPrivacyRequestRepository {
		return new WpdbPrivacyRequestRepository( new WpdbGateway( $database ), new TableNames( $database->prefix ), new DatabaseDateTimeCodec() );
	}

	/**
	 * Build one fixed UTC timestamp.
	 *
	 * @param string $value Database datetime text.
	 */
	private function utc( string $value ): DateTimeImmutable {
		return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Remove only trusted TagCore test tables.
	 *
	 * @param wpdb $database Active test database.
	 */
	private function clear_schema( wpdb $database ): void {
		$names = new TableNames( $database->prefix );
		foreach ( array( $names->privacy_requests(), $names->email_webhook_events(), $names->email_deliveries(), $names->tag_transfers(), $names->finder_report_media(), $names->finder_reports(), $names->events(), $names->access_tokens(), $names->messages(), $names->conversations(), $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated trusted test cleanup.
			$database->query( "DROP TABLE IF EXISTS {$table_name}" );
		}
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
