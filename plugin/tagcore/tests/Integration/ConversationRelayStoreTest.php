<?php
/**
 * Secure Reply persistence integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayEventIdentityPolicy;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayIdentity;
use ReturnTag\TagCore\Application\Conversation\ConversationSafetyAction;
use ReturnTag\TagCore\Application\Persistence\DenyAllEventMetadataPolicy;
use ReturnTag\TagCore\Application\Persistence\Value\AccessTokenDigest;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\MessageCiphertext;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbConversationRelayStore;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbEventRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbOwnerConversationReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionConversationMessageRateLimiter;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionFinderEmailRateLimiter;
use WP_UnitTestCase;
use wpdb;

/** Verifies atomic link, session, limit, ownership, and dispatch contracts. */
final class ConversationRelayStoreTest extends WP_UnitTestCase {
	/**
	 * Trusted dynamically prefixed table names.
	 *
	 * @var TableNames
	 */
	private TableNames $tables;

	/** Build a fresh Schema 12 relay fixture. */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->tables = new TableNames( $wpdb->prefix );
		$this->clear_schema( $wpdb );
		$this->runner( $wpdb )->migrate();
		$this->insert_relay_fixture( $wpdb );
	}

	/** Remove the isolated product schema. */
	protected function tearDown(): void {
		global $wpdb;

		$this->clear_schema( $wpdb );
		parent::tearDown();
	}

	/** Link exchange is one-time, rotates sessions, and invalidates on transfer. */
	public function test_link_exchange_rotates_session_and_rechecks_current_owner(): void {
		$store          = $this->store();
		$now            = $this->now();
		$link_one       = $this->digest( '1' );
		$session_one    = $this->digest( '2' );
		$link_two       = $this->digest( '3' );
		$session_two    = $this->digest( '4' );
		$session_expiry = $now->modify( '+30 minutes' );

		$store->issue_link( 1, 'owner_secure_reply', MessageSenderRole::OWNER, $link_one, $now->modify( '+24 hours' ), $now );
		self::assertEquals( new ConversationRelayIdentity( 1, MessageSenderRole::OWNER ), $store->exchange_link( $link_one, $session_one, $now, $session_expiry ) );
		self::assertNull( $store->exchange_link( $link_one, $this->digest( '5' ), $now, $session_expiry ) );
		self::assertEquals( new ConversationRelayIdentity( 1, MessageSenderRole::OWNER ), $store->resolve_session( $session_one, $now ) );

		$store->issue_link( 1, 'owner_secure_reply', MessageSenderRole::OWNER, $link_two, $now->modify( '+24 hours' ), $now );
		self::assertEquals( new ConversationRelayIdentity( 1, MessageSenderRole::OWNER ), $store->exchange_link( $link_two, $session_two, $now, $session_expiry ) );
		self::assertNull( $store->resolve_session( $session_one, $now ) );
		self::assertNotNull( $store->resolve_session( $session_two, $now ) );
		self::assertNull( $store->resolve_session( $session_two, $now->modify( '+31 minutes' ) ) );

		global $wpdb;
		self::assertSame( 1, $wpdb->update( $this->tables->conversations(), array( 'conversation_status' => 'closed' ), array( 'conversation_id' => 1 ), array( '%s' ), array( '%d' ) ) );
		self::assertNull( $store->resolve_session( $session_two, $now ) );
		self::assertSame( 1, $wpdb->update( $this->tables->conversations(), array( 'conversation_status' => 'open' ), array( 'conversation_id' => 1 ), array( '%s' ), array( '%d' ) ) );
		self::assertSame( 1, $wpdb->update( $this->tables->tags(), array( 'owner_id' => 77 ), array( 'tag_id' => 'A7R2W9' ), array( '%d' ), array( '%s' ) ) );
		self::assertNull( $store->resolve_session( $session_two, $now ) );
	}

	/** Account continuation rotates Owner sessions and reuses the full relay graph. */
	public function test_account_continuation_is_atomic_and_current_owner_bound(): void {
		$store       = $this->store();
		$now         = $this->now();
		$session_one = $this->digest( '6' );
		$session_two = $this->digest( '7' );

		self::assertTrue( $store->issue_owner_session( 1, 42, $session_one, $now->modify( '+30 minutes' ), $now ) );
		self::assertEquals( new ConversationRelayIdentity( 1, MessageSenderRole::OWNER ), $store->resolve_session( $session_one, $now ) );
		self::assertFalse( $store->issue_owner_session( 1, 43, $session_two, $now->modify( '+30 minutes' ), $now ) );
		self::assertTrue( $store->issue_owner_session( 1, 42, $session_two, $now->modify( '+30 minutes' ), $now ) );
		self::assertNull( $store->resolve_session( $session_one, $now ) );
		self::assertNotNull( $store->resolve_session( $session_two, $now ) );

		global $wpdb;
		self::assertSame( 1, $wpdb->update( $this->tables->tags(), array( 'owner_id' => 77 ), array( 'tag_id' => 'A7R2W9' ), array( '%d' ), array( '%s' ) ) );
		self::assertFalse( $store->issue_owner_session( 1, 42, $this->digest( '8' ), $now->modify( '+30 minutes' ), $now ) );
	}

	/** Account projection contains only bounded status and activity metadata. */
	public function test_account_projection_is_privacy_minimized_and_current_owner_bound(): void {
		global $wpdb;

		$reader = new WpdbOwnerConversationReader( new WpdbGateway( $wpdb ), $this->tables, new DatabaseDateTimeCodec() );
		$items  = $reader->list_for_owner( 42, $this->now() );

		self::assertCount( 1, $items );
		self::assertSame(
			array( 'conversation_id', 'status', 'last_activity_at', 'created_at', 'can_continue' ),
			array_keys( get_object_vars( $items[0] ) )
		);
		self::assertTrue( $items[0]->can_continue );
		self::assertSame( array(), $reader->list_for_owner( 43, $this->now() ) );

		self::assertSame( 1, $wpdb->update( $this->tables->tags(), array( 'owner_id' => 77 ), array( 'tag_id' => 'A7R2W9' ), array( '%d' ), array( '%s' ) ) );
		self::assertSame( array(), $reader->list_for_owner( 42, $this->now() ) );
	}

	/** System rows do not consume the strict 10-per-role and 20-total limits. */
	public function test_human_message_limits_are_atomic_and_audited(): void {
		global $wpdb;

		$store  = $this->store();
		$now    = $this->now();
		$cipher = MessageCiphertext::from_encrypted_bytes( 'opaque-message-envelope' );
		$owner  = new ConversationRelayIdentity( 1, MessageSenderRole::OWNER );
		$finder = new ConversationRelayIdentity( 1, MessageSenderRole::FINDER );

		self::assertNotNull( $store->ensure_access_message( 1, $cipher, $now ) );
		for ( $index = 0; $index < 10; ++$index ) {
			self::assertNotNull( $store->append_human_message( $owner, $cipher, $now ) );
			self::assertNotNull( $store->append_human_message( $finder, $cipher, $now ) );
		}

		self::assertNull( $store->append_human_message( $owner, $cipher, $now ) );
		self::assertNull( $store->append_human_message( $finder, $cipher, $now ) );
		self::assertCount( 20, $store->list_human_messages( $owner, $now ) );
		self::assertSame( '21', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE conversation_id = %d', $this->tables->messages(), 1 ) ) );
		self::assertSame( '10', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event_type = %s', $this->tables->events(), 'finder_message_submitted' ) ) );
		self::assertSame( '0', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event_type = %s', $this->tables->events(), 'owner_reply_sent' ) ) );
	}

	/** Dispatch may be claimed once and stale ambiguous work becomes terminal. */
	public function test_dispatch_claims_once_and_stale_work_fails_without_requeue(): void {
		global $wpdb;

		$store    = $this->store();
		$now      = $this->now();
		$old      = $now->modify( '-2 hours' );
		$cipher   = MessageCiphertext::from_encrypted_bytes( 'opaque-message-envelope' );
		$identity = new ConversationRelayIdentity( 1, MessageSenderRole::FINDER );
		$first    = $store->append_human_message( $identity, $cipher, $now );
		self::assertNotNull( $first );
		self::assertContains( $first->message_id, $store->pending_message_ids( 10 ) );
		self::assertNotNull( $store->claim_dispatch( $first->message_id, $now ) );
		self::assertNull( $store->claim_dispatch( $first->message_id, $now ) );
		self::assertTrue( $store->mark_sent( $first->message_id, $now ) );
		self::assertFalse( $store->mark_sent( $first->message_id, $now ) );
		self::assertSame( '1', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event_type = %s', $this->tables->events(), 'finder_message_submitted' ) ) );
		self::assertSame( '0', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event_type = %s', $this->tables->events(), 'owner_reply_sent' ) ) );

		$owner_reply = $store->append_human_message( new ConversationRelayIdentity( 1, MessageSenderRole::OWNER ), $cipher, $now );
		self::assertNotNull( $owner_reply );
		self::assertSame( '0', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event_type = %s', $this->tables->events(), 'owner_reply_sent' ) ) );
		self::assertNotNull( $store->claim_dispatch( $owner_reply->message_id, $now ) );
		self::assertTrue( $store->mark_sent( $owner_reply->message_id, $now ) );
		self::assertSame( '1', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event_type = %s', $this->tables->events(), 'owner_reply_sent' ) ) );

		$second = $store->append_human_message( $identity, $cipher, $old );
		self::assertNotNull( $second );
		self::assertNotNull( $store->claim_dispatch( $second->message_id, $old ) );
		self::assertSame( 1, $store->fail_stale_claims( $now->modify( '-1 hour' ), $now, 10 ) );
		self::assertSame( DeliveryStatus::FAILED->value, $wpdb->get_var( $wpdb->prepare( 'SELECT delivery_status FROM %i WHERE message_id = %d', $this->tables->messages(), $second->message_id ) ) );
		self::assertNotContains( $second->message_id, $store->pending_message_ids( 10 ) );
	}

	/** Conversation scope is reserved atomically even across distinct sessions and peers. */
	public function test_message_rate_limiter_enforces_conversation_scope(): void {
		global $wpdb;

		$limiter = new WordPressOptionConversationMessageRateLimiter( new WordPressOptionFinderEmailRateLimiter( $wpdb, get_current_blog_id() ) );
		$now     = $this->now();
		for ( $index = 0; $index < 10; ++$index ) {
			self::assertTrue( $limiter->reserve( $this->lookup( 'session-' . $index ), $this->lookup( 'peer-' . $index ), 1, $now ) );
		}
		self::assertFalse( $limiter->reserve( $this->lookup( 'session-overflow' ), $this->lookup( 'peer-overflow' ), 1, $now ) );
		self::assertTrue( $limiter->reserve( $this->lookup( 'session-other' ), $this->lookup( 'peer-other' ), 2, $now ) );
	}

	/** Owner report-block atomically terminates access, queues, and audit state. */
	public function test_owner_report_block_converges_terminal_state_atomically(): void {
		global $wpdb;

		$store          = $this->store();
		$now            = $this->now();
		$owner          = new ConversationRelayIdentity( 1, MessageSenderRole::OWNER );
		$finder         = new ConversationRelayIdentity( 1, MessageSenderRole::FINDER );
		$owner_session  = $this->digest( '2' );
		$finder_session = $this->digest( '4' );
		$cipher         = MessageCiphertext::from_encrypted_bytes( 'opaque-message-envelope' );

		$store->issue_link( 1, 'owner_secure_reply', MessageSenderRole::OWNER, $this->digest( '1' ), $now->modify( '+24 hours' ), $now );
		$store->issue_link( 1, 'finder_continue_conversation', MessageSenderRole::FINDER, $this->digest( '3' ), $now->modify( '+24 hours' ), $now );
		self::assertNotNull( $store->exchange_link( $this->digest( '1' ), $owner_session, $now, $now->modify( '+30 minutes' ) ) );
		self::assertNotNull( $store->exchange_link( $this->digest( '3' ), $finder_session, $now, $now->modify( '+30 minutes' ) ) );

		$claimed = $store->append_human_message( $finder, $cipher, $now );
		$queued  = $store->append_human_message( $owner, $cipher, $now );
		self::assertNotNull( $claimed );
		self::assertNotNull( $queued );
		self::assertNotNull( $store->claim_dispatch( $claimed->message_id, $now ) );

		self::assertTrue( $store->apply_safety_action( $owner, ConversationSafetyAction::OWNER_REPORT_BLOCK, $now ) );
		self::assertSame( 'blocked', $wpdb->get_var( $wpdb->prepare( 'SELECT conversation_status FROM %i WHERE conversation_id=%d', $this->tables->conversations(), 1 ) ) );
		self::assertSame( '0', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE conversation_id=%d AND revoked_at IS NULL', $this->tables->access_tokens(), 1 ) ) );
		self::assertSame( '0', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE conversation_id=%d AND delivery_status=%s', $this->tables->messages(), 1, DeliveryStatus::QUEUED->value ) ) );
		self::assertSame( '2', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE conversation_id=%d AND delivery_status=%s', $this->tables->messages(), 1, DeliveryStatus::FAILED->value ) ) );
		self::assertSame( '1', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event_type=%s AND event_result=%s', $this->tables->events(), 'conversation_reported', 'blocked' ) ) );
		self::assertNull( $store->resolve_session( $owner_session, $now ) );
		self::assertNull( $store->resolve_session( $finder_session, $now ) );
		self::assertFalse( $store->mark_sent( $claimed->message_id, $now ) );
		self::assertFalse( $store->apply_safety_action( $owner, ConversationSafetyAction::OWNER_REPORT_BLOCK, $now ) );
		self::assertSame( '1', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event_type=%s', $this->tables->events(), 'conversation_reported' ) ) );
		self::assertSame( array(), $store->pending_message_ids( 10 ) );

		$this->expectException( \RuntimeException::class );
		$store->issue_link( 1, 'owner_secure_reply', MessageSenderRole::OWNER, $this->digest( '5' ), $now->modify( '+24 hours' ), $now );
	}

	/** Finder can close, while the Owner cannot invoke the Finder action. */
	public function test_finder_close_is_role_specific_and_idempotent(): void {
		global $wpdb;

		$store  = $this->store();
		$now    = $this->now();
		$owner  = new ConversationRelayIdentity( 1, MessageSenderRole::OWNER );
		$finder = new ConversationRelayIdentity( 1, MessageSenderRole::FINDER );

		self::assertFalse( $store->apply_safety_action( $owner, ConversationSafetyAction::FINDER_CLOSE, $now ) );
		self::assertSame( 'open', $wpdb->get_var( $wpdb->prepare( 'SELECT conversation_status FROM %i WHERE conversation_id=%d', $this->tables->conversations(), 1 ) ) );
		self::assertTrue( $store->apply_safety_action( $finder, ConversationSafetyAction::FINDER_CLOSE, $now ) );
		self::assertSame( 'closed', $wpdb->get_var( $wpdb->prepare( 'SELECT conversation_status FROM %i WHERE conversation_id=%d', $this->tables->conversations(), 1 ) ) );
		self::assertFalse( $store->apply_safety_action( $finder, ConversationSafetyAction::FINDER_CLOSE, $now ) );
		self::assertSame( '1', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event_type=%s AND event_result=%s', $this->tables->events(), 'conversation_closed', 'closed' ) ) );
	}

	/** Return the production relay store. */
	private function store(): WpdbConversationRelayStore {
		global $wpdb;

		$gateway = new WpdbGateway( $wpdb );
		$dates   = new DatabaseDateTimeCodec();
		$events  = new WpdbEventRepository( $gateway, $this->tables, $dates, new DenyAllEventMetadataPolicy(), new ConversationRelayEventIdentityPolicy() );
		return new WpdbConversationRelayStore( $gateway, $this->tables, $dates, new WpdbTransactionManager( $wpdb ), $events );
	}

	/**
	 * Create one deterministic privacy-safe lookup digest.
	 *
	 * @param string $scope Synthetic scope.
	 */
	private function lookup( string $scope ): LookupDigest {
		return LookupDigest::from_digest( hash( 'sha256', $scope ) );
	}

	/**
	 * Return a production full-schema runner.
	 *
	 * @param wpdb $database Database adapter.
	 */
	private function runner( wpdb $database ): MigrationRunner {
		return new MigrationRunner(
			( new MigrationRegistryFactory( $database ) )->create(),
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
		);
	}

	/**
	 * Insert one synthetic active, verified, notified Conversation graph.
	 *
	 * @param wpdb $database Database adapter.
	 */
	private function insert_relay_fixture( wpdb $database ): void {
		self::assertSame(
			1,
			$database->insert(
				$this->tables->batches(),
				array(
					'batch_code'         => 'RT315-RELAY',
					'tag_type'           => 'classic_tag',
					'smart_network'      => 'none',
					'requested_quantity' => 1,
					'generated_quantity' => 1,
					'batch_status'       => 'released',
					'activation_enabled' => 1,
					'created_by'         => 1,
					'created_at'         => '2026-08-10 00:00:00',
					'updated_at'         => '2026-08-10 00:00:00',
				)
			)
		);
		$batch_id = (int) $database->insert_id;
		self::assertSame(
			1,
			$database->insert(
				$this->tables->tags(),
				array(
					'tag_id'       => 'A7R2W9',
					'batch_id'     => $batch_id,
					'owner_id'     => 42,
					'tag_type'     => 'classic_tag',
					'tag_status'   => 'active',
					'lost_mode'    => 0,
					'activated_at' => '2026-08-10 00:00:00',
					'created_at'   => '2026-08-10 00:00:00',
					'updated_at'   => '2026-08-10 00:00:00',
				)
			)
		);
		self::assertSame(
			1,
			$database->insert(
				$this->tables->conversations(),
				array(
					'conversation_id'         => 1,
					'tag_id'                  => 'A7R2W9',
					'owner_id_snapshot'       => 42,
					'finder_email_ciphertext' => 'opaque-finder-email',
					'finder_email_lookup'     => str_repeat( 'a', 64 ),
					'finder_verified_at'      => '2026-08-10 00:00:00',
					'conversation_status'     => 'open',
					'expires_at'              => '2026-09-10 00:00:00',
					'last_activity_at'        => '2026-08-10 00:00:00',
					'created_at'              => '2026-08-10 00:00:00',
				)
			)
		);
		self::assertSame(
			1,
			$database->insert(
				$this->tables->finder_reports(),
				array(
					'finder_report_id'          => 1,
					'conversation_id'           => 1,
					'tag_id'                    => 'A7R2W9',
					'owner_id_at_submission'    => 42,
					'report_status'             => 'notified',
					'evidence_status'           => 'ready',
					'owner_notification_status' => 'sent',
					'owner_notified_at'         => '2026-08-10 00:00:00',
					'expires_at'                => '2026-09-10 00:00:00',
					'created_at'                => '2026-08-10 00:00:00',
					'updated_at'                => '2026-08-10 00:00:00',
				)
			)
		);
	}

	/**
	 * Build a deterministic canonical digest.
	 *
	 * @param string $character One hexadecimal fixture character.
	 */
	private function digest( string $character ): AccessTokenDigest {
		return AccessTokenDigest::from_digest( str_repeat( $character, 64 ) );
	}

	/** Return the fixed UTC test instant. */
	private function now(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-08-10 12:00:00', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Remove only dynamically prefixed ReturnTag tables and Schema state.
	 *
	 * @param wpdb $database Database adapter.
	 */
	private function clear_schema( wpdb $database ): void {
		foreach ( array( $this->tables->finder_report_media(), $this->tables->finder_reports(), $this->tables->events(), $this->tables->access_tokens(), $this->tables->messages(), $this->tables->conversations(), $this->tables->auth_challenges(), $this->tables->batch_exports(), $this->tables->tags(), $this->tables->batches() ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated trusted test cleanup.
			$database->query( "DROP TABLE IF EXISTS {$table}" );
		}
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
