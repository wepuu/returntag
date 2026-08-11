<?php
/**
 * RT-307 atomic Tag activation integration coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use ReturnTag\TagCore\Application\Auth\WordPressAccountEmailPolicy;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\Persistence\DenyAllEventIdentityPolicy;
use ReturnTag\TagCore\Application\Persistence\DenyAllEventMetadataPolicy;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceConstraintViolationException;
use ReturnTag\TagCore\Application\Persistence\EventIdentityPolicy;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPagePolicy;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPageState;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Application\Tag\ActivateTag;
use ReturnTag\TagCore\Application\Tag\ActivateTagAndResolvePage;
use ReturnTag\TagCore\Application\Tag\RateLimitedTagActivation;
use ReturnTag\TagCore\Application\Tag\TagActivationAttemptStatus;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Application\Tag\TagActivationEventIdentityPolicy;
use ReturnTag\TagCore\Application\Tag\TagActivationResult;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Auth\WordPressAuthenticatedSession;
use ReturnTag\TagCore\Infrastructure\Auth\WordPressAuthenticatedUserEmailReader;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbEventRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbPublicTagStateReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTagActivationRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionTagActivationRateLimiter;
use ReturnTag\TagCore\Infrastructure\Security\ActivationOtpSecrets;
use ReturnTag\TagCore\Infrastructure\Security\SodiumActivationOtpProtector;
use ReturnTag\TagCore\Infrastructure\WordPressOptionFeatureFlagReader;
use ReturnTag\TagCore\PublicSite\ActivationOtpFormHandler;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use WP_UnitTestCase;
use wpdb;

/**
 * Exercises the Schema-8 conditional write and Event transaction.
 */
final class TagActivationTest extends WP_UnitTestCase {
	/**
	 * Current isolated table names.
	 *
	 * @var TableNames
	 */
	private TableNames $tables;

	/**
	 * Fixed activation time.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $now;

	/**
	 * Build a current isolated Schema.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		self::assertInstanceOf( wpdb::class, $wpdb );
		$this->clear_schema( $wpdb );
		$runner = new MigrationRunner(
			( new MigrationRegistryFactory( $wpdb ) )->create(),
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $wpdb, get_current_blog_id(), 0 )
		);
		self::assertSame( 13, $runner->migrate()->ending_version );
		update_option( FeatureFlag::GLOBAL_ACTIVATION->value, '1', false );
		update_option( FeatureFlag::FINDER_CONTACT->value, '1', false );
		$this->tables = new TableNames( $wpdb->prefix );
		$this->now    = new DateTimeImmutable( '2026-07-31 12:00:00', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Remove only isolated ReturnTag data.
	 */
	protected function tearDown(): void {
		global $wpdb;

		delete_option( FeatureFlag::GLOBAL_ACTIVATION->value );
		delete_option( FeatureFlag::FINDER_CONTACT->value );
		$this->clear_activation_rate_limits( $wpdb );
		wp_set_current_user( 0 );
		$this->clear_schema( $wpdb );
		parent::tearDown();
	}

	/**
	 * An eligible Tag changes owner and appends exactly one Event.
	 */
	public function test_activation_is_atomic_and_idempotent(): void {
		global $wpdb;

		$this->insert_tag( 'A7R2W9', 'released', true );
		$service = $this->service( new TagActivationEventIdentityPolicy() );

		self::assertSame(
			TagActivationResult::ACTIVATED,
			$service->execute( TagId::from_canonical( 'A7R2W9' ), 42 )
		);
		self::assertSame(
			TagActivationResult::ALREADY_OWNED,
			$service->execute( TagId::from_canonical( 'A7R2W9' ), 42 )
		);

		$tag = $wpdb->get_row(
			$wpdb->prepare( 'SELECT owner_id, tag_status, activated_at, updated_at FROM %i WHERE tag_id = %s', $this->tables->tags(), 'A7R2W9' ),
			ARRAY_A
		);
		self::assertIsArray( $tag );
		self::assertSame( '42', (string) $tag['owner_id'] );
		self::assertSame( 'active', $tag['tag_status'] );
		self::assertSame( '2026-07-31 12:00:00', $tag['activated_at'] );
		self::assertSame( '2026-07-31 12:00:00', $tag['updated_at'] );

		$events = $wpdb->get_results(
			$wpdb->prepare( 'SELECT event_type, actor_type, actor_id, target_type, target_id, event_result, metadata_json FROM %i', $this->tables->events() ),
			ARRAY_A
		);
		self::assertCount( 1, $events );
		self::assertSame( 'tag_activated', $events[0]['event_type'] );
		self::assertSame( 'user', $events[0]['actor_type'] );
		self::assertSame( '42', (string) $events[0]['actor_id'] );
		self::assertSame( 'tag', $events[0]['target_type'] );
		self::assertSame( 'A7R2W9', $events[0]['target_id'] );
		self::assertSame( 'success', $events[0]['event_result'] );
		self::assertNull( $events[0]['metadata_json'] );
	}

	/**
	 * Another committed owner is reported only as changed state.
	 */
	public function test_existing_other_owner_is_not_overwritten(): void {
		global $wpdb;

		$this->insert_tag( 'A7R2W9', 'released', true, 24, '2026-07-31 11:00:00' );

		self::assertSame(
			TagActivationResult::STATE_CHANGED,
			$this->service( new TagActivationEventIdentityPolicy() )
				->execute( TagId::from_canonical( 'A7R2W9' ), 42 )
		);

		$tag = $wpdb->get_row(
			$wpdb->prepare( 'SELECT owner_id, activated_at FROM %i WHERE tag_id = %s', $this->tables->tags(), 'A7R2W9' ),
			ARRAY_A
		);
		self::assertIsArray( $tag );
		self::assertSame( '24', (string) $tag['owner_id'] );
		self::assertSame( '2026-07-31 11:00:00', $tag['activated_at'] );
		self::assertSame( '0', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->tables->events() ) ) );
	}

	/**
	 * Batch controls are part of the authoritative write predicate.
	 */
	public function test_non_released_or_disabled_batch_cannot_activate(): void {
		global $wpdb;

		$this->insert_tag( 'A7R2W9', 'exported', false );

		self::assertSame(
			TagActivationResult::STATE_CHANGED,
			$this->service( new TagActivationEventIdentityPolicy() )
				->execute( TagId::from_canonical( 'A7R2W9' ), 42 )
		);
		self::assertSame( 'unregistered', $wpdb->get_var( $wpdb->prepare( 'SELECT tag_status FROM %i WHERE tag_id = %s', $this->tables->tags(), 'A7R2W9' ) ) );
	}

	/**
	 * Event rejection rolls the Tag write back.
	 */
	public function test_event_failure_rolls_back_activation(): void {
		global $wpdb;

		$this->insert_tag( 'A7R2W9', 'released', true );

		try {
			$this->service( new DenyAllEventIdentityPolicy() )
				->execute( TagId::from_canonical( 'A7R2W9' ), 42 );
			self::fail( 'The denied activation Event should fail.' );
		} catch ( PersistenceConstraintViolationException ) {
			self::assertSame( 'unregistered', $wpdb->get_var( $wpdb->prepare( 'SELECT tag_status FROM %i WHERE tag_id = %s', $this->tables->tags(), 'A7R2W9' ) ) );
			self::assertNull( $wpdb->get_var( $wpdb->prepare( 'SELECT owner_id FROM %i WHERE tag_id = %s', $this->tables->tags(), 'A7R2W9' ) ) );
		}
	}

	/**
	 * A committed first Owner and a later different actor use existing routes.
	 */
	public function test_activation_outcomes_converge_to_owner_then_finder(): void {
		$tag_id  = TagId::from_canonical( 'A7R2W9' );
		$service = $this->convergence_service();

		$this->insert_tag( $tag_id->value, 'released', true );

		self::assertSame(
			PublicTagPageState::OWNER_ENTRY,
			$service->execute( $tag_id, 42 )->state
		);
		self::assertSame(
			PublicTagPageState::FINDER_ENTRY,
			$service->execute( $tag_id, 24 )->state
		);
	}

	/**
	 * A missing Tag uses the existing generic invalid state after zero-row write.
	 */
	public function test_missing_activation_converges_to_invalid_page(): void {
		self::assertSame(
			PublicTagPageState::INVALID,
			$this->convergence_service()
				->execute( TagId::from_canonical( 'A7R2W9' ), 42 )
				->state
		);
	}

	/**
	 * The authenticated form rejects CSRF evidence then activates with server identity.
	 */
	public function test_authenticated_form_uses_nonce_session_email_and_direct_ip(): void {
		global $wpdb;

		$user_id = self::factory()->user->create(
			array( 'user_email' => 'owner-rt309@example.test' )
		);
		$tag_id  = TagId::from_canonical( 'A7R2W9' );
		$pages   = $this->public_pages();
		$clock   = new FixedClock( $this->now );
		$crypto  = new SodiumActivationOtpProtector(
			ActivationOtpSecrets::from_keys(
				str_repeat( 'e', 32 ),
				str_repeat( 'l', 32 ),
				str_repeat( 'p', 32 )
			)
		);
		$handler = new ActivationOtpFormHandler(
			null,
			null,
			new WordPressAuthenticatedSession(),
			new WordPressAccountEmailPolicy(),
			new RateLimitedTagActivation(
				new WordPressOptionTagActivationRateLimiter( $wpdb, get_current_blog_id() ),
				new ActivateTagAndResolvePage(
					$this->service( new TagActivationEventIdentityPolicy() ),
					$pages
				),
				$pages,
				$clock
			),
			new WordPressAuthenticatedUserEmailReader(),
			$crypto
		);

		$this->insert_tag( $tag_id->value, 'released', true );
		wp_set_current_user( $user_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test restores the complete synthetic request after exercising handler-owned nonce validation.
		$previous_post   = $_POST;
		$previous_server = $_SERVER;

		try {
			$_SERVER['REMOTE_ADDR']         = '192.0.2.40';
			$_SERVER['HTTP_ORIGIN']         = home_url( '/' );
			$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
			$_POST                          = array(
				ActivationOtpFormHandler::ACTION_FIELD => ActivationOtpFormHandler::ACTIVATE_ACTION,
				ActivationOtpFormHandler::NONCE_FIELD  => 'invalid-nonce',
			);

			self::assertNull( $handler->activate( $tag_id ) );
			self::assertSame( 'unregistered', $wpdb->get_var( $wpdb->prepare( 'SELECT tag_status FROM %i WHERE tag_id = %s', $this->tables->tags(), $tag_id->value ) ) );

			$_POST[ ActivationOtpFormHandler::NONCE_FIELD ] = wp_create_nonce( ActivationOtpFormHandler::NONCE_ACTION );
			$_SERVER['HTTP_SEC_FETCH_SITE']                 = 'cross-site';
			self::assertNull( $handler->activate( $tag_id ) );

			$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
			$result                         = $handler->activate( $tag_id );

			self::assertNotNull( $result );
			self::assertSame( TagActivationAttemptStatus::RESOLVED, $result->status );
			self::assertSame( PublicTagPageState::OWNER_ENTRY, $result->page->state );
			self::assertSame( (string) $user_id, (string) $wpdb->get_var( $wpdb->prepare( 'SELECT owner_id FROM %i WHERE tag_id = %s', $this->tables->tags(), $tag_id->value ) ) );
		} finally {
			$_POST   = $previous_post;
			$_SERVER = $previous_server;
		}
	}

	/**
	 * Build the production use-case graph with a selectable Event policy.
	 *
	 * @param EventIdentityPolicy $identity_policy Event identity policy.
	 */
	private function service( EventIdentityPolicy $identity_policy ): ActivateTag {
		global $wpdb;

		$gateway = new WpdbGateway( $wpdb );
		$dates   = new DatabaseDateTimeCodec();

		return new ActivateTag(
			new WpdbTagActivationRepository( $gateway, $this->tables, $dates ),
			new WpdbEventRepository(
				$gateway,
				$this->tables,
				$dates,
				new DenyAllEventMetadataPolicy(),
				$identity_policy
			),
			new WpdbTransactionManager( $wpdb ),
			new WordPressOptionFeatureFlagReader(),
			new FixedClock( $this->now )
		);
	}

	/**
	 * Build production activation plus committed public-state resolution.
	 */
	private function convergence_service(): ActivateTagAndResolvePage {
		return new ActivateTagAndResolvePage(
			$this->service( new TagActivationEventIdentityPolicy() ),
			$this->public_pages()
		);
	}

	/**
	 * Build the production committed public-state resolver.
	 */
	private function public_pages(): ResolvePublicTagPage {
		global $wpdb;

		return new ResolvePublicTagPage(
			new WpdbPublicTagStateReader( new WpdbGateway( $wpdb ), $this->tables, new DatabaseDateTimeCodec() ),
			new WordPressOptionFeatureFlagReader(),
			new PublicTagPagePolicy( new TagActivationAvailabilityPolicy() )
		);
	}

	/**
	 * Insert one Batch and Tag fixture.
	 *
	 * @param string      $tag_id Canonical public Tag ID.
	 * @param string      $batch_status Canonical Batch status.
	 * @param bool        $activation_enabled Batch activation control.
	 * @param int|null    $owner_id Existing owner identifier.
	 * @param string|null $activated_at Existing activation timestamp.
	 */
	private function insert_tag(
		string $tag_id,
		string $batch_status,
		bool $activation_enabled,
		?int $owner_id = null,
		?string $activated_at = null
	): void {
		global $wpdb;

		self::assertSame(
			1,
			$wpdb->insert(
				$this->tables->batches(),
				array(
					'batch_code'         => 'RT307-' . $tag_id,
					'tag_type'           => 'classic_tag',
					'model_code'         => null,
					'smart_network'      => 'none',
					'manufacturer'       => null,
					'sales_channel'      => null,
					'requested_quantity' => 1,
					'generated_quantity' => 1,
					'batch_status'       => $batch_status,
					'activation_enabled' => $activation_enabled ? 1 : 0,
					'notes'              => null,
					'created_by'         => 1,
					'created_at'         => '2026-07-31 10:00:00',
					'updated_at'         => '2026-07-31 10:00:00',
				)
			)
		);
		$batch_id = (int) $wpdb->insert_id;
		self::assertGreaterThan( 0, $batch_id );

		self::assertSame(
			1,
			$wpdb->insert(
				$this->tables->tags(),
				array(
					'tag_id'       => $tag_id,
					'batch_id'     => $batch_id,
					'owner_id'     => $owner_id,
					'tag_type'     => 'classic_tag',
					'tag_status'   => null === $owner_id ? 'unregistered' : 'active',
					'lost_mode'    => 0,
					'activated_at' => $activated_at,
					'created_at'   => '2026-07-31 10:00:00',
					'updated_at'   => '2026-07-31 10:00:00',
				)
			)
		);
	}

	/**
	 * Remove trusted ReturnTag tables from the isolated test database.
	 *
	 * @param wpdb $database Active isolated WordPress database.
	 */
	private function clear_schema( wpdb $database ): void {
		$names = new TableNames( $database->prefix );

		foreach ( array( $names->events(), $names->access_tokens(), $names->messages(), $names->conversations(), $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated cleanup with trusted identifiers.
			$database->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}

	/**
	 * Remove isolated RT-309 limiter options.
	 *
	 * @param wpdb $database Active isolated WordPress database.
	 */
	private function clear_activation_rate_limits( wpdb $database ): void {
		$like  = $database->esc_like( WordPressOptionTagActivationRateLimiter::OPTION_PREFIX ) . '%';
		$query = $database->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s', $database->options, $like );

		if ( ! is_string( $query ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above; isolated cleanup of plugin-owned Options.
		foreach ( $database->get_col( $query ) as $option_name ) {
			if ( is_string( $option_name ) ) {
				delete_option( $option_name );
			}
		}
	}
}
