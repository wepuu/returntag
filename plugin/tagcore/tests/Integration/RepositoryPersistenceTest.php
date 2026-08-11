<?php
/**
 * RT-109 Repository integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use ReturnTag\TagCore\Application\Account\MutateOwnerTag;
use ReturnTag\TagCore\Application\Account\OwnerTagLostState;
use ReturnTag\TagCore\Application\Account\OwnerTagMetadata;
use ReturnTag\TagCore\Application\Account\OwnerTagMutationEventIdentityPolicy;
use ReturnTag\TagCore\Application\Account\OwnerTagMutationRateLimiter;
use ReturnTag\TagCore\Application\Account\OwnerTagMutationResult;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\DenyAllEventIdentityPolicy;
use ReturnTag\TagCore\Application\Persistence\DenyAllEventMetadataPolicy;
use ReturnTag\TagCore\Application\Persistence\EventIdentityPolicy;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceConstraintViolationException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Record\BatchRecord;
use ReturnTag\TagCore\Application\Persistence\Record\ConversationRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewAccessTokenRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewAuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewBatchExportRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewBatchRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewConversationRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewMessageRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewTagRecord;
use ReturnTag\TagCore\Application\Persistence\Record\TagRecord;
use ReturnTag\TagCore\Application\Persistence\Value\AccessTokenDigest;
use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\MessageCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Conversation\ConversationStatus;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;
use ReturnTag\TagCore\Domain\Tag\SmartNetwork;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAccessTokenRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAccountOtpStore;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAuthChallengeRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchExportRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbConversationRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbEventRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbMessageRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbOwnerTagReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbOwnerTagMutationStore;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTagRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use RuntimeException;
use WP_UnitTestCase;
use wpdb;

/**
 * Verifies typed persistence against the complete Schema version 10.
 */
final class RepositoryPersistenceTest extends WP_UnitTestCase {
	/** Stage 2 writes and metadata-free Events share one current-Owner transaction. */
	public function test_owner_tag_mutations_are_atomic_owner_scoped_and_audited(): void {
		global $wpdb;

		$repositories = $this->repositories( $wpdb );
		$batch        = $this->insert_batch( $repositories['batches'], 'RT317-MUTATION' );
		$tag          = $this->insert_tag( $repositories['tags'], $batch, 'A7R2W9', 42 );
		$gateway      = new WpdbGateway( $wpdb );
		$tables       = new TableNames( $wpdb->prefix );
		$dates        = new DatabaseDateTimeCodec();
		$events       = new WpdbEventRepository(
			$gateway,
			$tables,
			$dates,
			new DenyAllEventMetadataPolicy(),
			new OwnerTagMutationEventIdentityPolicy()
		);
		$service      = new MutateOwnerTag(
			$this->account_session( 42 ),
			$this->owner_account_flags(),
			new WpdbOwnerTagMutationStore( $gateway, $tables, $dates ),
			$this->owner_tag_limiter(),
			$events,
			new WpdbTransactionManager( $wpdb ),
			$this->fixed_clock( '2026-08-10 12:00:00' )
		);

		self::assertSame(
			OwnerTagMutationResult::UPDATED,
			$service->update_metadata( TagId::from_canonical( 'A7R2W9' ), new OwnerTagMetadata( 'Work laptop', 'Silver laptop' ) )
		);
		self::assertSame(
			OwnerTagMutationResult::UPDATED,
			$service->update_lost_state( TagId::from_canonical( 'A7R2W9' ), new OwnerTagLostState( true, 'Please leave it with airport security.' ) )
		);
		$stored = $repositories['tags']->find_by_tag_id( $tag->data->tag_id );
		self::assertSame( 'Work laptop', $stored?->data->item_name );
		self::assertSame( 'Silver laptop', $stored?->data->public_label );
		self::assertTrue( $stored?->data->lost_mode );
		self::assertSame( 'Please leave it with airport security.', $stored?->data->lost_message );

		$page = $events->list_by_target( 'tag', 'A7R2W9', null, new PageSize() );
		self::assertCount( 2, $page->items );
		foreach ( $page->items as $event ) {
			self::assertNull( $event->data->metadata->json() );
			self::assertSame( 42, $event->data->actor_id );
		}

		$unauthorized = new MutateOwnerTag(
			$this->account_session( 43 ),
			$this->owner_account_flags(),
			new WpdbOwnerTagMutationStore( $gateway, $tables, $dates ),
			$this->owner_tag_limiter(),
			$events,
			new WpdbTransactionManager( $wpdb ),
			$this->fixed_clock( '2026-08-10 12:01:00' )
		);
		self::assertSame(
			OwnerTagMutationResult::UNAVAILABLE,
			$unauthorized->update_metadata( TagId::from_canonical( 'A7R2W9' ), new OwnerTagMetadata( 'Stolen edit', 'Stolen edit' ) )
		);
		self::assertSame( 'Work laptop', $repositories['tags']->find_by_tag_id( 'A7R2W9' )?->data->item_name );
		self::assertCount( 2, $events->list_by_target( 'tag', 'A7R2W9', null, new PageSize() )->items );

		$smart_batch = $this->insert_batch( $repositories['batches'], 'RT317-SMART', TagType::SMART_TAG );
		$this->insert_tag( $repositories['tags'], $smart_batch, 'B7R2W9', 42 );
		self::assertSame( OwnerTagMutationResult::UPDATED, $service->acknowledge_smart_setup( TagId::from_canonical( 'B7R2W9' ) ) );
		self::assertSame( OwnerTagMutationResult::UNCHANGED, $service->acknowledge_smart_setup( TagId::from_canonical( 'B7R2W9' ) ) );
		self::assertNotNull( $repositories['tags']->find_by_tag_id( 'B7R2W9' )?->data->owner_pairing_ack_at );
		self::assertCount( 1, $events->list_by_target( 'tag', 'B7R2W9', null, new PageSize() )->items );

		$this->insert_tag( $repositories['tags'], $batch, 'C7R2W9', 42, TagStatus::SUSPENDED );
		self::assertSame(
			OwnerTagMutationResult::UNAVAILABLE,
			$service->update_lost_state( TagId::from_canonical( 'C7R2W9' ), new OwnerTagLostState( true, 'Please contact me through ForgeTag.' ) )
		);
		self::assertCount( 0, $events->list_by_target( 'tag', 'C7R2W9', null, new PageSize() )->items );
	}
	/**
	 * Build a clean Schema version 10 before every test.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->clear_schema( $wpdb );
		$this->migrate( $wpdb );
	}

	/**
	 * Remove isolated Repository fixtures after every test.
	 */
	protected function tearDown(): void {
		global $wpdb;

		$this->clear_schema( $wpdb );
		parent::tearDown();
	}

	/**
	 * All eight Repositories must round-trip typed records without real PII.
	 */
	public function test_all_repositories_round_trip_typed_records(): void {
		global $wpdb;

		$repositories = $this->repositories( $wpdb );
		$batch        = $this->insert_batch( $repositories['batches'], 'RT109-ROUNDTRIP' );
		$tag          = $this->insert_tag( $repositories['tags'], $batch, 'N7R2W9', 42 );

		self::assertSame( $batch->batch_id, $repositories['batches']->find_by_code( 'RT109-ROUNDTRIP' )?->batch_id );
		self::assertSame( 'N7R2W9', $repositories['tags']->find_by_tag_id( 'N7R2W9' )?->data->tag_id );

		$owner_tags = new WpdbOwnerTagReader(
			new WpdbGateway( $wpdb ),
			new TableNames( $wpdb->prefix ),
			new DatabaseDateTimeCodec()
		);
		self::assertSame( 'N7R2W9', $owner_tags->list_for_owner( 42, null, new PageSize() )->items[0]->data->tag_id );
		self::assertSame( 'N7R2W9', $owner_tags->find_for_owner( 42, TagId::from_canonical( 'N7R2W9' ) )?->data->tag_id );
		self::assertNull( $owner_tags->find_for_owner( 43, TagId::from_canonical( 'N7R2W9' ) ) );

		$export = $repositories['exports']->append(
			new NewBatchExportRecord(
				$batch->batch_id,
				1,
				1,
				'csv',
				str_repeat( 'a', 64 ),
				7,
				$this->utc( '2026-07-24 00:01:00' )
			)
		);
		self::assertSame( $export->export_id, $repositories['exports']->find_by_batch_and_version( $batch->batch_id, 1 )?->export_id );

		$email_ciphertext = "rt109-email-envelope\0bytes";
		$challenge        = $repositories['challenges']->insert(
			new NewAuthChallengeRecord(
				'owner_login',
				'tag',
				'N7R2W9',
				EmailCiphertext::from_encrypted_bytes( $email_ciphertext ),
				LookupDigest::from_digest( str_repeat( 'b', 64 ) ),
				OtpHash::from_password_hash( '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.' ),
				0,
				1,
				null,
				$this->utc( '2026-07-24 00:12:00' ),
				null,
				null,
				$this->utc( '2026-07-24 00:02:00' )
			)
		);
		$stored_challenge = $repositories['challenges']->find_latest_for_purpose_and_lookup(
			'owner_login',
			LookupDigest::from_digest( str_repeat( 'b', 64 ) )
		);
		self::assertSame( $challenge->challenge_id, $stored_challenge?->challenge_id );
		self::assertSame( $email_ciphertext, $stored_challenge?->data->email_ciphertext->value );

		$conversation = $this->insert_conversation( $repositories['conversations'], $tag );
		$body         = "rt109-message-envelope\0bytes";
		$message      = $repositories['messages']->append(
			new NewMessageRecord(
				$conversation->conversation_id,
				MessageSenderRole::FINDER,
				MessageCiphertext::from_encrypted_bytes( $body ),
				DeliveryStatus::QUEUED,
				null,
				null,
				$this->utc( '2026-07-24 00:04:00' )
			)
		);
		$message_page = $repositories['messages']->list_by_conversation( $conversation->conversation_id, null, new PageSize( 10 ) );
		self::assertSame( $message->message_id, $message_page->items[0]->message_id );
		self::assertSame( $body, $message_page->items[0]->data->body_ciphertext->value );

		$token = $repositories['tokens']->insert(
			new NewAccessTokenRecord(
				$conversation->conversation_id,
				'conversation_reply',
				MessageSenderRole::OWNER,
				AccessTokenDigest::from_digest( str_repeat( 'c', 64 ) ),
				$this->utc( '2026-07-25 00:00:00' ),
				null,
				null,
				$this->utc( '2026-07-24 00:05:00' )
			)
		);
		self::assertSame(
			$token->token_id,
			$repositories['tokens']->find_by_hash( AccessTokenDigest::from_digest( str_repeat( 'c', 64 ) ) )?->token_id
		);

		$event      = $repositories['events']->append(
			new NewEventRecord(
				'tag_activated',
				'user',
				42,
				'tag',
				'N7R2W9',
				'success',
				'rt109-roundtrip',
				EventMetadata::none(),
				$this->utc( '2026-07-24 00:06:00' )
			)
		);
		$event_page = $repositories['events']->list_by_target( 'tag', 'N7R2W9', null, new PageSize( 10 ) );
		self::assertSame( $event->event_id, $event_page->items[0]->event_id );
		self::assertNull( $event_page->items[0]->data->metadata->json() );
		self::assertSame(
			$event->event_id,
			$repositories['events']->list_by_correlation( 'rt109-roundtrip', null, new PageSize( 10 ) )->items[0]->event_id
		);
	}

	/** Account challenges remain purpose-isolated and verify atomically. */
	public function test_account_otp_store_claims_and_consumes_latest_challenge(): void {
		global $wpdb;

		$gateway    = new WpdbGateway( $wpdb );
		$tables     = new TableNames( $wpdb->prefix );
		$dates      = new DatabaseDateTimeCodec();
		$challenges = new WpdbAuthChallengeRepository( $gateway, $tables, $dates );
		$store      = new WpdbAccountOtpStore(
			$gateway,
			$tables,
			$dates,
			$challenges,
			new WpdbTransactionManager( $wpdb )
		);
		$lookup     = LookupDigest::from_digest( str_repeat( 'c', 64 ) );
		$created    = $store->create_replacing(
			new NewAuthChallengeRecord(
				'account_otp',
				'account',
				$lookup->value,
				EmailCiphertext::from_encrypted_bytes( 'account-email-envelope' ),
				$lookup,
				OtpHash::from_password_hash( '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.' ),
				0,
				0,
				null,
				$this->utc( '2026-07-24 00:10:00' ),
				null,
				null,
				$this->utc( '2026-07-24 00:00:00' )
			)
		);

		self::assertSame( 1, $store->count_recent_for_email( $lookup, $this->utc( '2026-07-23 23:59:00' ) ) );
		self::assertNotNull(
			$store->claim_for_dispatch(
				$created->challenge_id,
				OtpHash::from_password_hash( '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.' ),
				$this->utc( '2026-07-24 00:11:00' ),
				$this->utc( '2026-07-24 00:01:00' )
			)
		);
		self::assertTrue( $store->has_verifiable_latest( $lookup, $this->utc( '2026-07-24 00:02:00' ), 5 ) );
		self::assertSame(
			\ReturnTag\TagCore\Application\Auth\ActivationOtpVerificationResult::VERIFIED,
			$store->verify_latest( $lookup, $this->utc( '2026-07-24 00:02:00' ), 5, static fn( OtpHash $hash ): bool => '' !== $hash->value )
		);
		self::assertFalse( $store->has_verifiable_latest( $lookup, $this->utc( '2026-07-24 00:02:00' ), 5 ) );
	}

	/**
	 * Cursor pagination must be bounded, deterministic, and gap-free.
	 */
	public function test_repository_cursor_pagination_is_stable(): void {
		global $wpdb;

		$repositories = $this->repositories( $wpdb );
		$batch        = $this->insert_batch( $repositories['batches'], 'RT109-PAGING' );
		$this->insert_tag( $repositories['tags'], $batch, 'N7R2W8', 55, TagStatus::ACTIVE );
		$this->insert_tag( $repositories['tags'], $batch, 'N7R2W9', 55, TagStatus::ACTIVE );
		$this->insert_tag( $repositories['tags'], $batch, 'N7R2WA', null, TagStatus::UNREGISTERED );

		$first = $repositories['tags']->list_by_batch( $batch->batch_id, null, new PageSize( 2 ) );
		self::assertCount( 2, $first->items );
		self::assertNotNull( $first->next_cursor );

		$second = $repositories['tags']->list_by_batch( $batch->batch_id, $first->next_cursor, new PageSize( 2 ) );
		self::assertCount( 1, $second->items );
		self::assertNull( $second->next_cursor );

		$ids = array_map(
			static fn( TagRecord $record ): string => $record->data->tag_id,
			array_merge( $first->items, $second->items )
		);
		self::assertSame( array( 'N7R2W8', 'N7R2W9', 'N7R2WA' ), $ids );

		$event_ids = array();

		foreach ( array( 1, 2, 3 ) as $sequence ) {
			$event_ids[] = $repositories['events']->append(
				new NewEventRecord(
					'tag_activated',
					'user',
					55,
					'tag',
					'N7R2W8',
					'success',
					'rt109-paging',
					EventMetadata::none(),
					$this->utc( "2026-07-24 00:0{$sequence}:00" )
				)
			)->event_id;
		}

		$first_events  = $repositories['events']->list_by_correlation( 'rt109-paging', null, new PageSize( 2 ) );
		$second_events = $repositories['events']->list_by_correlation( 'rt109-paging', $first_events->next_cursor, new PageSize( 2 ) );
		$paged_ids     = array_map(
			static fn( $record ): int => $record->event_id,
			array_merge( $first_events->items, $second_events->items )
		);

		self::assertSame( array_reverse( $event_ids ), $paged_ids );
		self::assertNotNull( $first_events->next_cursor );
		self::assertNull( $second_events->next_cursor );
	}

	/**
	 * Restricted Repository writes must reject missing and inconsistent references.
	 */
	public function test_repository_integrity_checks_fail_closed(): void {
		global $wpdb;

		$repositories = $this->repositories( $wpdb );
		$batch        = $this->insert_batch( $repositories['batches'], 'RT109-INTEGRITY' );

		try {
			$this->insert_tag( $repositories['tags'], new BatchRecord( $batch->batch_id + 999, $batch->data ), 'N7R2W9', 42 );
			self::fail( 'Expected a missing Batch reference to fail.' );
		} catch ( PersistenceConstraintViolationException ) {
			self::assertTrue( true );
		}

		try {
			$repositories['messages']->append(
				new NewMessageRecord(
					999999,
					MessageSenderRole::SYSTEM,
					MessageCiphertext::from_encrypted_bytes( 'opaque-message' ),
					DeliveryStatus::QUEUED,
					null,
					null,
					$this->utc( '2026-07-24 00:00:00' )
				)
			);
			self::fail( 'Expected a missing Conversation reference to fail.' );
		} catch ( PersistenceConstraintViolationException ) {
			self::assertTrue( true );
		}

		try {
			$this->insert_batch( $repositories['batches'], 'RT109-INTEGRITY' );
			self::fail( 'Expected the unique Batch Code constraint to fail.' );
		} catch ( PersistenceException $exception ) {
			self::assertSame( 'Persistence operation failed.', $exception->getMessage() );
		}
	}

	/**
	 * Unknown stored enum data must fail instead of receiving a fallback value.
	 */
	public function test_unknown_stored_enum_value_fails_mapping(): void {
		global $wpdb;

		$repositories = $this->repositories( $wpdb );
		$batch        = $this->insert_batch( $repositories['batches'], 'RT109-UNKNOWN' );
		$table        = ( new TableNames( $wpdb->prefix ) )->batches();
		$query        = $wpdb->prepare( 'UPDATE %i SET batch_status = %s WHERE batch_id = %d', $table, 'mystery', $batch->batch_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Deliberate isolated malformed-row fixture.
		$wpdb->query( $query );

		$this->expectException( PersistenceMappingException::class );
		$repositories['batches']->find_by_id( $batch->batch_id );
	}

	/**
	 * Sensitive stored values must be revalidated instead of trusted on read.
	 */
	public function test_plaintext_otp_in_hash_column_fails_mapping(): void {
		global $wpdb;

		$repositories = $this->repositories( $wpdb );
		$challenge    = $repositories['challenges']->insert(
			new NewAuthChallengeRecord(
				'owner_login',
				'tag',
				'N7R2W9',
				EmailCiphertext::from_encrypted_bytes( "rt109-email-envelope\0bytes" ),
				LookupDigest::from_digest( str_repeat( 'b', 64 ) ),
				OtpHash::from_password_hash( '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.' ),
				0,
				1,
				null,
				$this->utc( '2026-07-24 00:12:00' ),
				null,
				null,
				$this->utc( '2026-07-24 00:02:00' )
			)
		);
		$table        = ( new TableNames( $wpdb->prefix ) )->auth_challenges();
		$query        = $wpdb->prepare( 'UPDATE %i SET code_hash = %s WHERE challenge_id = %d', $table, '123456', $challenge->challenge_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Deliberate isolated malformed-row fixture.
		$wpdb->query( $query );

		$this->expectException( PersistenceMappingException::class );
		$repositories['challenges']->find_by_id( $challenge->challenge_id );
	}

	/**
	 * Event writes must remain disabled without an explicit identity allowlist.
	 */
	public function test_event_identity_policy_fails_closed(): void {
		global $wpdb;

		$repository = new WpdbEventRepository(
			new WpdbGateway( $wpdb ),
			new TableNames( $wpdb->prefix ),
			new DatabaseDateTimeCodec(),
			new DenyAllEventMetadataPolicy(),
			new DenyAllEventIdentityPolicy()
		);

		$this->expectException( PersistenceConstraintViolationException::class );
		$repository->append(
			new NewEventRecord(
				'tag_activated',
				'user',
				42,
				'tag',
				'N7R2W9',
				'success',
				'rt109-denied',
				EventMetadata::none(),
				$this->utc( '2026-07-24 00:06:00' )
			)
		);
	}

	/**
	 * Prepared values must keep injection-shaped input inside the value boundary.
	 */
	public function test_injection_shaped_lookup_does_not_change_query_structure(): void {
		global $wpdb;

		$repositories = $this->repositories( $wpdb );
		$this->insert_batch( $repositories['batches'], 'RT109-SAFE' );

		self::assertNull( $repositories['batches']->find_by_code( "RT109-SAFE' OR 1=1 -- " ) );
		self::assertSame(
			array(),
			$repositories['events']->list_by_target( 'tag', "N7R2W9' OR 1=1 -- ", null, new PageSize( 10 ) )->items
		);
		self::assertNotNull( $repositories['batches']->find_by_code( 'RT109-SAFE' ) );
	}

	/**
	 * Transaction callback failures and nested attempts must roll back.
	 */
	public function test_transaction_manager_rolls_back_and_rejects_nesting(): void {
		global $wpdb;

		$repositories = $this->repositories( $wpdb );
		$transactions = new WpdbTransactionManager( $wpdb );

		try {
			$transactions->transactional(
				function () use ( $repositories ): void {
					$this->insert_batch( $repositories['batches'], 'RT109-ROLLBACK' );
					throw new RuntimeException( 'Synthetic transaction failure.' );
				}
			);
			self::fail( 'Expected the synthetic transaction failure.' );
		} catch ( RuntimeException ) {
			self::assertNull( $repositories['batches']->find_by_code( 'RT109-ROLLBACK' ) );
		}

		try {
			$transactions->transactional(
				static fn() => $transactions->transactional( static fn(): bool => true )
			);
			self::fail( 'Expected nested transactions to be rejected.' );
		} catch ( LogicException ) {
			self::assertTrue( true );
		}

		self::assertNull( $repositories['batches']->find_by_code( 'RT109-ROLLBACK' ) );
	}

	/**
	 * Repository identifiers must follow a non-default WordPress prefix.
	 */
	public function test_repositories_support_non_default_prefix(): void {
		$database = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$result   = $database->set_prefix( 'rt109_' );

		self::assertNotWPError( $result );
		$this->clear_schema( $database );

		try {
			$this->migrate( $database );
			$repositories = $this->repositories( $database );
			$batch        = $this->insert_batch( $repositories['batches'], 'RT109-PREFIX' );

			self::assertSame( $batch->batch_id, $repositories['batches']->find_by_code( 'RT109-PREFIX' )?->batch_id );
			self::assertSame( 'rt109_returntag_batches', ( new TableNames( $database->prefix ) )->batches() );
		} finally {
			$this->clear_schema( $database );
		}
	}

	/**
	 * Build all concrete RT-109 Repository adapters.
	 *
	 * @param wpdb $database WordPress database adapter.
	 * @return array{
	 *   batches: WpdbBatchRepository,
	 *   tags: WpdbTagRepository,
	 *   exports: WpdbBatchExportRepository,
	 *   challenges: WpdbAuthChallengeRepository,
	 *   conversations: WpdbConversationRepository,
	 *   messages: WpdbMessageRepository,
	 *   tokens: WpdbAccessTokenRepository,
	 *   events: WpdbEventRepository
	 * }
	 */
	private function repositories( wpdb $database ): array {
		$gateway = new WpdbGateway( $database );
		$tables  = new TableNames( $database->prefix );
		$dates   = new DatabaseDateTimeCodec();

		return array(
			'batches'       => new WpdbBatchRepository( $gateway, $tables, $dates ),
			'tags'          => new WpdbTagRepository( $gateway, $tables, $dates ),
			'exports'       => new WpdbBatchExportRepository( $gateway, $tables, $dates ),
			'challenges'    => new WpdbAuthChallengeRepository( $gateway, $tables, $dates ),
			'conversations' => new WpdbConversationRepository( $gateway, $tables, $dates ),
			'messages'      => new WpdbMessageRepository( $gateway, $tables, $dates ),
			'tokens'        => new WpdbAccessTokenRepository( $gateway, $tables, $dates ),
			'events'        => new WpdbEventRepository(
				$gateway,
				$tables,
				$dates,
				new DenyAllEventMetadataPolicy(),
				$this->event_identity_policy()
			),
		);
	}

	/**
	 * Insert one synthetic Batch fixture.
	 *
	 * @param WpdbBatchRepository $repository Batch Repository.
	 * @param string              $batch_code Unique Batch code.
	 * @param TagType             $tag_type Product family.
	 */
	private function insert_batch( WpdbBatchRepository $repository, string $batch_code, TagType $tag_type = TagType::CLASSIC_TAG ): BatchRecord {
		return $repository->insert(
			new NewBatchRecord(
				$batch_code,
				$tag_type,
				'RT109-MODEL',
				SmartNetwork::NONE,
				'Synthetic Manufacturer',
				'direct',
				10,
				0,
				BatchStatus::DRAFT,
				false,
				'No production data.',
				7,
				$this->utc( '2026-07-24 00:00:00' ),
				$this->utc( '2026-07-24 00:00:00' )
			)
		);
	}

	/**
	 * Insert one synthetic Tag fixture.
	 *
	 * @param WpdbTagRepository $repository Tag Repository.
	 * @param BatchRecord       $batch Batch fixture.
	 * @param string            $tag_id Public Tag ID fixture.
	 * @param int|null          $owner_id Synthetic owner ID.
	 * @param TagStatus         $status Persisted Tag status.
	 */
	private function insert_tag(
		WpdbTagRepository $repository,
		BatchRecord $batch,
		string $tag_id,
		?int $owner_id,
		TagStatus $status = TagStatus::ACTIVE
	): TagRecord {
		return $repository->insert(
			new NewTagRecord(
				$tag_id,
				$batch->batch_id,
				$owner_id,
				$batch->data->tag_type,
				$batch->data->model_code,
				null,
				'Synthetic Tag',
				$status,
				false,
				null,
				null,
				null === $owner_id ? null : $this->utc( '2026-07-24 00:00:00' ),
				null,
				null,
				$this->utc( '2026-07-24 00:00:00' ),
				$this->utc( '2026-07-24 00:00:00' )
			)
		);
	}

	/**
	 * Insert one synthetic Conversation fixture.
	 *
	 * @param WpdbConversationRepository $repository Conversation Repository.
	 * @param TagRecord                  $tag Owned Tag fixture.
	 */
	private function insert_conversation( WpdbConversationRepository $repository, TagRecord $tag ): ConversationRecord {
		self::assertNotNull( $tag->data->owner_id );

		return $repository->insert(
			new NewConversationRecord(
				$tag->data->tag_id,
				$tag->data->owner_id,
				EmailCiphertext::from_encrypted_bytes( "rt109-finder-envelope\0bytes" ),
				LookupDigest::from_digest( str_repeat( 'd', 64 ) ),
				null,
				ConversationStatus::PENDING_VERIFICATION,
				$this->utc( '2026-08-24 00:00:00' ),
				$this->utc( '2026-07-24 00:03:00' ),
				$this->utc( '2026-07-24 00:03:00' )
			)
		);
	}

	/**
	 * Apply the production Migration chain in an isolated test database.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function migrate( wpdb $database ): void {
		$registry = ( new MigrationRegistryFactory( $database ) )->create();
		$runner   = new MigrationRunner(
			$registry,
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
		);

		self::assertSame( 13, $runner->migrate()->ending_version );
	}

	/**
	 * Return one fixed authenticated Account session.
	 *
	 * @param int $owner_id Synthetic Owner identifier.
	 */
	private function account_session( int $owner_id ): AuthenticatedSession {
		return new class( $owner_id ) implements AuthenticatedSession {
			/**
			 * Create the fixed session.
			 *
			 * @param int $owner_id Synthetic Owner identifier.
			 */
			public function __construct( private readonly int $owner_id ) {
			}

			/** Return the synthetic Owner. */
			public function current_user_id(): ?int {
				return $this->owner_id;
			}

			/**
			 * Authentication is outside this fixture.
			 *
			 * @param int $user_id Ignored User identifier.
			 */
			public function authenticate( int $user_id ): void {
				unset( $user_id );
			}
		};
	}

	/** Return one enabled Owner Account control. */
	private function owner_account_flags(): FeatureFlagReader {
		return new class() implements FeatureFlagReader {
			/**
			 * Enable only the Owner Account control.
			 *
			 * @param FeatureFlag $feature_flag Requested control.
			 */
			public function is_enabled( FeatureFlag $feature_flag ): bool {
				return FeatureFlag::OWNER_ACCOUNT === $feature_flag;
			}
		};
	}

	/** Return one permissive test-only mutation limiter. */
	private function owner_tag_limiter(): OwnerTagMutationRateLimiter {
		return new class() implements OwnerTagMutationRateLimiter {
			/**
			 * Allow the synthetic mutation.
			 *
			 * @param int               $owner_id Synthetic Owner identifier.
			 * @param TagId             $tag_id Synthetic Tag identifier.
			 * @param DateTimeImmutable $now Fixed current time.
			 */
			public function reserve( int $owner_id, TagId $tag_id, DateTimeImmutable $now ): bool {
				unset( $owner_id, $tag_id, $now );

				return true;
			}
		};
	}

	/**
	 * Return one fixed UTC Clock.
	 *
	 * @param string $value Database-shaped UTC time.
	 */
	private function fixed_clock( string $value ): Clock {
		return new class( $this->utc( $value ) ) implements Clock {
			/**
			 * Create the fixed Clock.
			 *
			 * @param DateTimeImmutable $now Fixed UTC time.
			 */
			public function __construct( private readonly DateTimeImmutable $now ) {
			}

			/** Return the fixed UTC time. */
			public function now(): DateTimeImmutable {
				return $this->now;
			}
		};
	}

	/**
	 * Return a strict synthetic UTC timestamp.
	 *
	 * @param string $value Database-shaped UTC time.
	 */
	private function utc( string $value ): DateTimeImmutable {
		return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Return a strict test-only Event identity policy.
	 */
	private function event_identity_policy(): EventIdentityPolicy {
		return new class() implements EventIdentityPolicy {
			/**
			 * Approve only the synthetic Tag activation identity shape.
			 *
			 * @param string      $event_type Event classification.
			 * @param string      $actor_type Actor classification.
			 * @param int|null    $actor_id Internal actor identifier.
			 * @param string      $target_type Target classification.
			 * @param string      $target_id Opaque target identifier.
			 * @param string|null $correlation_id Operation correlation identifier.
			 */
			public function allows(
				string $event_type,
				string $actor_type,
				?int $actor_id,
				string $target_type,
				string $target_id,
				?string $correlation_id
			): bool {
				return 'tag_activated' === $event_type
					&& 'user' === $actor_type
					&& null !== $actor_id
					&& 'tag' === $target_type
					&& 1 === preg_match( '/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}$/D', $target_id )
					&& is_string( $correlation_id )
					&& str_starts_with( $correlation_id, 'rt109-' );
			}
		};
	}

	/**
	 * Remove only trusted ReturnTag tables from the isolated test database.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	private function clear_schema( wpdb $database ): void {
		$names = new TableNames( $database->prefix );

		foreach ( array( $names->events(), $names->access_tokens(), $names->messages(), $names->conversations(), $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated test cleanup with trusted identifiers.
			$database->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
