<?php
/**
 * RT-301 through RT-303 public route integration coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use ReturnTag\TagCore\Application\Auth\RequestActivationOtp;
use ReturnTag\TagCore\Application\Auth\ActivationOtpVerificationResult;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Auth\WordPressAccountEmailPolicy;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\Persistence\Record\NewAuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPage;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPagePolicy;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPageState;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Application\Tag\TagIdInputNormalizer;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbActivationOtpRequestStore;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAuthChallengeRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbPublicTagStateReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerActivationOtpScheduler;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerFinderReportOwnerNotificationScheduler;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerFinderReportProcessingScheduler;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerFinderEmailOtpScheduler;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionActivationOtpRateLimiter;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionActivationOtpVerificationRateLimiter;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionTagActivationRateLimiter;
use ReturnTag\TagCore\Infrastructure\Security\ActivationOtpSecrets;
use ReturnTag\TagCore\Infrastructure\Security\SodiumActivationOtpProtector;
use ReturnTag\TagCore\Infrastructure\WordPressOptionFeatureFlagReader;
use ReturnTag\TagCore\PublicSite\ActivationOtpFormHandler;
use ReturnTag\TagCore\PublicSite\ActivationOtpFormState;
use ReturnTag\TagCore\PublicSite\ActivationOtpFormView;
use ReturnTag\TagCore\PublicSite\FinderReportFormState;
use ReturnTag\TagCore\PublicSite\FinderReportFormView;
use ReturnTag\TagCore\PublicSite\FinderEmailFormState;
use ReturnTag\TagCore\PublicSite\FinderEmailFormView;
use ReturnTag\TagCore\PublicSite\PublicRewriteLifecycle;
use ReturnTag\TagCore\PublicSite\PublicTagResponsePolicy;
use ReturnTag\TagCore\PublicSite\PublicTagRouteController;
use ReturnTag\TagCore\PublicSite\PublicTagTemplateRenderer;
use WP_Rewrite;
use WP_UnitTestCase;
use wpdb;

/**
 * Exercises canonical routing, state resolution, privacy, and isolated rendering.
 */
final class PublicTagRouteTest extends WP_UnitTestCase {
	/**
	 * Route instance under test.
	 *
	 * @var PublicTagRouteController
	 */
	private PublicTagRouteController $route;

	/**
	 * Standalone renderer under test.
	 *
	 * @var PublicTagTemplateRenderer
	 */
	private PublicTagTemplateRenderer $renderer;

	/**
	 * Trusted product table names.
	 *
	 * @var TableNames
	 */
	private TableNames $tables;

	/**
	 * Original test-site permalink structure.
	 *
	 * @var string
	 */
	private string $original_permalink_structure;

	/**
	 * Build a current Schema and predictable permalink environment.
	 */
	protected function setUp(): void {
		global $wpdb, $wp_rewrite;

		parent::setUp();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		self::assertInstanceOf( wpdb::class, $wpdb );
		self::assertInstanceOf( WP_Rewrite::class, $wp_rewrite );

		$this->clear_schema( $wpdb );
		$registry = ( new MigrationRegistryFactory( $wpdb ) )->create();
		$runner   = new MigrationRunner(
			$registry,
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $wpdb, get_current_blog_id(), 0 )
		);
		self::assertSame( 14, $runner->migrate()->ending_version );

		update_option( FeatureFlag::GLOBAL_ACTIVATION->value, '1', false );
		update_option( FeatureFlag::FINDER_CONTACT->value, '1', false );

		$this->tables   = new TableNames( $wpdb->prefix );
		$gateway        = new WpdbGateway( $wpdb );
		$states         = new WpdbPublicTagStateReader( $gateway, $this->tables, new DatabaseDateTimeCodec() );
		$pages          = new ResolvePublicTagPage(
			$states,
			new WordPressOptionFeatureFlagReader(),
			new PublicTagPagePolicy( new TagActivationAvailabilityPolicy() )
		);
		$schema_state   = new SchemaState( new WordPressSchemaVersionStore(), $registry );
		$this->renderer = new PublicTagTemplateRenderer( RETURNTAG_TAGCORE_DIR );
		$this->route    = new PublicTagRouteController(
			RETURNTAG_TAGCORE_DIR,
			new PublicTagResponsePolicy(),
			new TagIdInputNormalizer(),
			$pages,
			$schema_state,
			$this->renderer,
			new ActivationOtpFormHandler(
				null,
				null,
				new class() implements AuthenticatedSession {
					/**
					 * Return an anonymous test identity.
					 */
					public function current_user_id(): ?int {
						return null;
					}

					/**
					 * Ignore the unused test session operation.
					 *
					 * @param int $user_id Unused WordPress User ID.
					 */
					public function authenticate( int $user_id ): void {
						unset( $user_id );
					}
				},
				new WordPressAccountEmailPolicy(),
				null,
				null,
				null
			)
		);

		$this->original_permalink_structure = (string) $wp_rewrite->permalink_structure;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$this->route->register_rewrite_rule();
		flush_rewrite_rules( false );
	}

	/**
	 * Remove only isolated fixtures, flags, and rewrite state.
	 */
	protected function tearDown(): void {
		global $wpdb, $wp_rewrite;

		$this->route->unregister_rewrite_rule();

		if ( $wp_rewrite instanceof WP_Rewrite ) {
			$wp_rewrite->set_permalink_structure( $this->original_permalink_structure );
		}

		flush_rewrite_rules( false );
		delete_option( FeatureFlag::GLOBAL_ACTIVATION->value );
		delete_option( FeatureFlag::FINDER_CONTACT->value );
		$this->clear_rate_limit_options( $wpdb );
		wp_set_current_user( 0 );
		$this->clear_schema( $wpdb );

		parent::tearDown();
	}

	/**
	 * The rewrite accepts exactly one non-empty path segment.
	 */
	public function test_route_matches_exactly_one_tag_segment(): void {
		self::assertSame( 1, preg_match( '#^' . PublicTagRouteController::REWRITE_PATTERN . '#', 't/A7R2W9' ) );
		self::assertSame( 1, preg_match( '#^' . PublicTagRouteController::REWRITE_PATTERN . '#', 't/raw-value/' ) );
		self::assertSame( 0, preg_match( '#^' . PublicTagRouteController::REWRITE_PATTERN . '#', 't/' ) );
		self::assertSame( 0, preg_match( '#^' . PublicTagRouteController::REWRITE_PATTERN . '#', 't/A7R2W9/extra' ) );
	}

	/**
	 * WordPress resolves and canonicalizes the raw route segment.
	 */
	public function test_wordpress_resolves_the_public_route(): void {
		$this->go_to( home_url( '/t/a7-r2w9/' ) );

		self::assertSame( 'a7-r2w9', get_query_var( PublicTagRouteController::QUERY_VAR ) );
		self::assertTrue( $this->route->is_public_tag_request() );
		self::assertSame( 'A7R2W9', $this->route->normalized_tag_id()?->value );
		self::assertSame( home_url( '/t/A7R2W9' ), $this->route->canonical_redirect_url( 'GET' ) );
		self::assertSame( home_url( '/t/A7R2W9' ), $this->route->canonical_redirect_url( 'HEAD' ) );
		self::assertNull( $this->route->canonical_redirect_url( 'POST' ) );
	}

	/**
	 * A public query-var value cannot impersonate the matched scan route.
	 */
	public function test_query_var_without_matching_rewrite_is_not_a_public_tag_request(): void {
		$this->go_to( home_url( '/' ) );
		set_query_var( PublicTagRouteController::QUERY_VAR, 'A7R2W9' );

		self::assertFalse( $this->route->is_public_tag_request() );
	}

	/**
	 * Invalid input returns the generic invalid page without a database query.
	 */
	public function test_invalid_input_does_not_query_or_disclose_validation_detail(): void {
		global $wpdb;

		$this->go_to( home_url( '/t/A7R2W0/' ) );
		$before = $wpdb->num_queries;
		$page   = $this->route->resolve_page();

		self::assertSame( $before, $wpdb->num_queries );
		self::assertSame( PublicTagPageState::INVALID, $page->state );
		self::assertNull( $this->route->canonical_redirect_url( 'GET' ) );
		self::assertStringNotContainsString( 'A7R2W0', $this->renderer->render_to_string( $page ) );
	}

	/**
	 * Unknown canonical IDs use the same privacy-minimized invalid page.
	 */
	public function test_unknown_tag_is_invalid(): void {
		$this->go_to( home_url( '/t/A7R2W9/' ) );

		$page = $this->route->resolve_page();

		self::assertSame( PublicTagPageState::INVALID, $page->state );
		self::assertStringContainsString( 'We could not find this ForgeTag', $this->renderer->render_to_string( $page ) );
	}

	/**
	 * Unregistered Tags reflect release and activation controls.
	 */
	public function test_unregistered_tag_state_uses_batch_and_global_controls(): void {
		$this->insert_tag( 'A7R2W9', 'unregistered', 'released', true );
		$this->go_to( home_url( '/t/A7R2W9/' ) );

		self::assertSame( PublicTagPageState::ACTIVATION_ENTRY, $this->route->resolve_page()->state );

		update_option( FeatureFlag::GLOBAL_ACTIVATION->value, '0', false );
		self::assertSame( PublicTagPageState::ACTIVATION_UNAVAILABLE, $this->route->resolve_page()->state );
	}

	/**
	 * Activation entry renders labelled request and verification forms.
	 */
	public function test_activation_entry_form_is_accessible_and_does_not_reflect_email(): void {
		$html = $this->renderer->render_to_string(
			PublicTagPage::activation_entry( TagType::CLASSIC_TAG ),
			new ActivationOtpFormView(
				home_url( '/t/A7R2W9' ),
				'test-nonce',
				ActivationOtpFormState::READY
			)
		);

		self::assertStringContainsString( '<label for="returntag-activation-email">Email address</label>', $html );
		self::assertStringContainsString( 'autocomplete="email"', $html );
		self::assertStringContainsString( 'autocomplete="one-time-code"', $html );
		self::assertStringContainsString( 'pattern="[0-9]{6}"', $html );
		self::assertStringContainsString( 'value="request_code"', $html );
		self::assertStringContainsString( 'value="verify_code"', $html );
		self::assertStringContainsString( 'Email me a code', $html );
		self::assertStringContainsString( 'Verify code', $html );
		self::assertStringContainsString( 'aria-label="Activation progress"', $html );
		self::assertMatchesRegularExpression( '/<li[^>]*aria-current="step"[^>]*><span>1<\/span>Verify email<\/li>/', $html );
		self::assertStringNotContainsString( 'owner@example.test', $html );
	}

	/**
	 * Server feedback advances the same activation page without exposing a challenge identifier.
	 */
	public function test_activation_progress_is_derived_from_safe_server_state(): void {
		$code_html     = $this->renderer->render_to_string(
			PublicTagPage::activation_entry( TagType::CLASSIC_TAG ),
			new ActivationOtpFormView(
				home_url( '/t/A7R2W9' ),
				'test-nonce',
				ActivationOtpFormState::REQUEST_ACCEPTED
			)
		);
		$activate_html = $this->renderer->render_to_string(
			PublicTagPage::activation_entry( TagType::CLASSIC_TAG ),
			new ActivationOtpFormView(
				home_url( '/t/A7R2W9' ),
				'test-nonce',
				ActivationOtpFormState::AUTHENTICATED
			)
		);

		self::assertMatchesRegularExpression( '/<li class="is-complete"[^>]*><span>1<\/span>Verify email<\/li>/', $code_html );
		self::assertMatchesRegularExpression( '/<li[^>]*aria-current="step"[^>]*><span>2<\/span>Confirm code<\/li>/', $code_html );
		self::assertMatchesRegularExpression( '/<li[^>]*aria-current="step"[^>]*><span>3<\/span>Activate tag<\/li>/', $activate_html );
		self::assertStringContainsString( 'New customers get an account after verification', $code_html );
		self::assertStringNotContainsString( 'challenge', $code_html );
	}

	/**
	 * Smart Tag activation explains the independent systems without integration claims.
	 */
	public function test_smart_tag_activation_renders_static_parallel_system_guide(): void {
		$html = $this->renderer->render_to_string(
			PublicTagPage::activation_entry( TagType::SMART_TAG ),
			new ActivationOtpFormView(
				home_url( '/t/A7R2W9' ),
				'test-nonce',
				ActivationOtpFormState::READY
			)
		);

		self::assertStringContainsString( 'id="returntag-smart-guide-title"', $html );
		self::assertStringContainsString( 'Two separate recovery systems', $html );
		self::assertStringContainsString( 'Location tracking is managed in Apple Find My or the compatible finding app.', $html );
		self::assertStringContainsString( 'ForgeTag QR recovery', $html );
		self::assertStringContainsString( 'QR recovery works independently', $html );
		self::assertStringContainsString( 'ForgeTag does not verify pairing', $html );
		self::assertStringContainsString( 'Email me a code', $html );
		self::assertStringNotContainsString( 'Connected to Apple', $html );
		self::assertStringNotContainsString( 'Apple pairing verified', $html );
		self::assertStringNotContainsString( 'Current location', $html );
		self::assertStringNotContainsString( 'Last seen location', $html );
		self::assertStringNotContainsString( 'Battery reported by Apple', $html );
		self::assertStringNotContainsString( 'Google account connected', $html );
		self::assertStringNotContainsString( 'owner_pairing_ack_at', $html );
		self::assertStringNotContainsString( 'https://', $html );
	}

	/**
	 * The static Smart Tag guide never leaks into other product or route states.
	 */
	public function test_smart_tag_guide_is_limited_to_smart_activation_entry(): void {
		$pages = array(
			PublicTagPage::activation_entry( TagType::STICKER ),
			PublicTagPage::activation_entry( TagType::CLASSIC_TAG ),
			PublicTagPage::activation_unavailable( TagType::SMART_TAG ),
			PublicTagPage::owner_entry( TagType::SMART_TAG ),
			PublicTagPage::finder_entry( TagType::SMART_TAG, 'Blue bag', false, null ),
			PublicTagPage::finder_unavailable( TagType::SMART_TAG ),
			PublicTagPage::invalid(),
			PublicTagPage::suspended(),
			PublicTagPage::retired(),
			PublicTagPage::service_unavailable(),
		);

		foreach ( $pages as $page ) {
			self::assertStringNotContainsString(
				'returntag-public__smart-guide',
				$this->renderer->render_to_string( $page )
			);
		}
	}

	/**
	 * Authenticated visitors see one working activation form without identity fields.
	 */
	public function test_authenticated_activation_entry_shows_activation_form(): void {
		$html = $this->renderer->render_to_string(
			PublicTagPage::activation_entry( TagType::CLASSIC_TAG ),
			new ActivationOtpFormView(
				home_url( '/t/A7R2W9' ),
				'test-nonce',
				ActivationOtpFormState::AUTHENTICATED
			)
		);

		self::assertStringContainsString( 'You are signed in', $html );
		self::assertStringContainsString( 'Review the final action below to activate this ForgeTag.', $html );
		self::assertStringContainsString( '<form', $html );
		self::assertStringContainsString( 'value="activate_tag"', $html );
		self::assertStringContainsString( 'value="test-nonce"', $html );
		self::assertStringContainsString( 'Activate my tag', $html );
		self::assertStringNotContainsString( 'Email address', $html );
		self::assertStringNotContainsString( 'Six-digit code', $html );
	}

	/**
	 * Generic activation failure retains the same working form and no PII.
	 */
	public function test_authenticated_activation_error_is_generic_and_retryable(): void {
		$html = $this->renderer->render_to_string(
			PublicTagPage::activation_entry( TagType::CLASSIC_TAG ),
			new ActivationOtpFormView(
				home_url( '/t/A7R2W9' ),
				'test-nonce',
				ActivationOtpFormState::ACTIVATION_ERROR
			)
		);

		self::assertStringContainsString( 'We could not activate this Tag right now.', $html );
		self::assertStringContainsString( 'role="alert"', $html );
		self::assertStringContainsString( 'Activate my tag', $html );
		self::assertStringNotContainsString( 'owner@example.test', $html );
	}

	/**
	 * Replacement consumes the old challenge and dispatch claim is idempotent.
	 */
	public function test_activation_otp_replacement_and_dispatch_claim_are_idempotent(): void {
		$protector = $this->otp_protector();
		$tag_id    = TagId::from_canonical( 'A7R2W9' );
		$email     = new EmailAddress( 'owner@example.test' );
		$now       = new DateTimeImmutable( '2026-07-30 00:00:00', new DateTimeZone( 'UTC' ) );
		$store     = $this->otp_store();
		$first     = $store->create_replacing( $this->otp_challenge( $protector, $tag_id, $email, $now ) );
		$second    = $store->create_replacing(
			$this->otp_challenge( $protector, $tag_id, $email, $now->add( new DateInterval( 'PT61S' ) ) )
		);

		self::assertNotNull( $store->find_by_id( $first->challenge_id )?->data->consumed_at );
		self::assertSame( 2, $store->count_recent_for_email( $protector->email_lookup( $email ), $now ) );
		$issued = $store->claim_for_dispatch(
			$second->challenge_id,
			$protector->hash_code( '123456' ),
			$now->add( new DateInterval( 'PT10M' ) ),
			$now
		);

		self::assertNotNull( $issued );
		self::assertSame( 1, $issued->data->send_count );
		self::assertNull(
			$store->claim_for_dispatch(
				$second->challenge_id,
				$protector->hash_code( '654321' ),
				$now->add( new DateInterval( 'PT10M' ) ),
				$now
			)
		);
	}

	/**
	 * Issued challenges enforce attempts, expiry, one-time use, and issuance.
	 */
	public function test_activation_otp_verification_is_atomic_and_one_time(): void {
		$protector = $this->otp_protector();
		$tag_id    = TagId::from_canonical( 'A7R2W9' );
		$email     = new EmailAddress( 'owner@example.test' );
		$now       = new DateTimeImmutable( '2026-07-30 00:00:00', new DateTimeZone( 'UTC' ) );
		$store     = $this->otp_store();
		$unissued  = $store->create_replacing( $this->otp_challenge( $protector, $tag_id, $email, $now ) );
		$lookup    = $protector->email_lookup( $email );
		$matches   = static fn( OtpHash $hash ): bool => $protector->verify_code( '123456', $hash );

		self::assertFalse( $store->has_verifiable_latest( $tag_id, $lookup, $now, 5 ) );
		self::assertSame(
			ActivationOtpVerificationResult::INVALID,
			$store->verify_latest( $tag_id, $lookup, $now, 5, $matches )
		);
		self::assertSame( 0, $store->find_by_id( $unissued->challenge_id )?->data->attempt_count );

		self::assertNotNull(
			$store->claim_for_dispatch(
				$unissued->challenge_id,
				$protector->hash_code( '123456' ),
				$now->add( new DateInterval( 'PT10M' ) ),
				$now
			)
		);
		self::assertTrue( $store->has_verifiable_latest( $tag_id, $lookup, $now, 5 ) );

		$wrong = static fn( OtpHash $hash ): bool => $protector->verify_code( '654321', $hash );

		for ( $attempt = 1; $attempt <= 5; ++$attempt ) {
			self::assertSame(
				ActivationOtpVerificationResult::INVALID,
				$store->verify_latest( $tag_id, $lookup, $now, 5, $wrong )
			);
			self::assertSame( $attempt, $store->find_by_id( $unissued->challenge_id )?->data->attempt_count );
		}

		self::assertFalse( $store->has_verifiable_latest( $tag_id, $lookup, $now, 5 ) );
		self::assertSame(
			ActivationOtpVerificationResult::INVALID,
			$store->verify_latest( $tag_id, $lookup, $now, 5, $matches )
		);

		$issued_at = $now->add( new DateInterval( 'PT1M' ) );
		$second    = $store->create_replacing( $this->otp_challenge( $protector, $tag_id, $email, $issued_at ) );
		self::assertNotNull(
			$store->claim_for_dispatch(
				$second->challenge_id,
				$protector->hash_code( '123456' ),
				$issued_at->add( new DateInterval( 'PT10M' ) ),
				$issued_at
			)
		);

		self::assertSame(
			ActivationOtpVerificationResult::VERIFIED,
			$store->verify_latest( $tag_id, $lookup, $issued_at, 5, $matches )
		);
		$verified = $store->find_by_id( $second->challenge_id );
		self::assertEquals( $issued_at, $verified?->data->verified_at );
		self::assertEquals( $issued_at, $verified?->data->consumed_at );
		self::assertFalse( $store->has_verifiable_latest( $tag_id, $lookup, $issued_at, 5 ) );
		self::assertSame(
			ActivationOtpVerificationResult::INVALID,
			$store->verify_latest( $tag_id, $lookup, $issued_at, 5, $matches )
		);

		$expired_at = $issued_at->add( new DateInterval( 'PT11M' ) );
		$expired    = $store->create_replacing( $this->otp_challenge( $protector, $tag_id, $email, $expired_at ) );
		self::assertNotNull(
			$store->claim_for_dispatch(
				$expired->challenge_id,
				$protector->hash_code( '123456' ),
				$expired_at->add( new DateInterval( 'PT1S' ) ),
				$expired_at
			)
		);
		self::assertSame(
			ActivationOtpVerificationResult::INVALID,
			$store->verify_latest(
				$tag_id,
				$lookup,
				$expired_at->add( new DateInterval( 'PT2S' ) ),
				5,
				$matches
			)
		);
	}

	/**
	 * Action Scheduler persists exactly one numeric challenge argument.
	 */
	public function test_activation_otp_scheduler_persists_only_challenge_id(): void {
		$scheduler = new ActionSchedulerActivationOtpScheduler();
		$args      = array( 'challenge_id' => 42 );
		$scheduler->schedule( 42 );

		$action_id = \ActionScheduler::store()->query_action(
			array(
				'hook'   => ActionSchedulerActivationOtpScheduler::HOOK,
				'args'   => $args,
				'group'  => ActionSchedulerActivationOtpScheduler::GROUP,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			)
		);
		self::assertIsInt( $action_id );
		self::assertSame( $args, \ActionScheduler::store()->fetch_action( $action_id )->get_args() );

		as_unschedule_action(
			ActionSchedulerActivationOtpScheduler::HOOK,
			$args,
			ActionSchedulerActivationOtpScheduler::GROUP
		);
	}

	/**
	 * Durable buckets reject a second same-email request in one minute.
	 */
	public function test_activation_otp_rate_limiter_is_atomic_for_email(): void {
		global $wpdb;

		$limiter = new WordPressOptionActivationOtpRateLimiter( $wpdb, get_current_blog_id() );
		$now     = new DateTimeImmutable( '2026-07-30 00:00:00', new DateTimeZone( 'UTC' ) );
		$ip      = LookupDigest::from_digest( hash( 'sha256', 'ip-fixture' ) );
		$email   = LookupDigest::from_digest( hash( 'sha256', 'email-fixture' ) );
		$tag_id  = TagId::from_canonical( 'A7R2W9' );

		self::assertTrue( $limiter->reserve( $ip, $email, $tag_id, $now ) );
		self::assertFalse( $limiter->reserve( $ip, $email, $tag_id, $now->add( new DateInterval( 'PT1S' ) ) ) );
	}

	/**
	 * Verification budgets use a separate durable namespace and higher ceiling.
	 */
	public function test_activation_otp_verification_rate_limiter_is_separate(): void {
		global $wpdb;

		$limiter = new WordPressOptionActivationOtpVerificationRateLimiter( $wpdb, get_current_blog_id() );
		$now     = new DateTimeImmutable( '2026-07-30 00:00:00', new DateTimeZone( 'UTC' ) );
		$ip      = LookupDigest::from_digest( hash( 'sha256', 'verification-ip-fixture' ) );
		$email   = LookupDigest::from_digest( hash( 'sha256', 'verification-email-fixture' ) );
		$tag_id  = TagId::from_canonical( 'A7R2W9' );

		for ( $attempt = 0; $attempt < 10; ++$attempt ) {
			self::assertTrue( $limiter->reserve_public( $ip, $tag_id, $now ) );
			self::assertTrue( $limiter->reserve_email( $email, $now ) );
		}

		self::assertFalse( $limiter->reserve_public( $ip, $tag_id, $now ) );
		self::assertFalse( $limiter->reserve_email( $email, $now ) );
	}

	/**
	 * User and keyed-email scopes enforce five hourly and ten daily attempts.
	 *
	 * @param string $fixed_scope Scope held constant across attempts.
	 * @dataProvider activation_identity_scope_provider
	 */
	public function test_activation_identity_limits_are_balanced( string $fixed_scope ): void {
		global $wpdb;

		$limiter = new WordPressOptionTagActivationRateLimiter( $wpdb, get_current_blog_id() );
		$start   = new DateTimeImmutable( '2026-07-30 00:00:00', new DateTimeZone( 'UTC' ) );

		for ( $attempt = 0; $attempt < 10; ++$attempt ) {
			$time = $start->add( new DateInterval( $attempt < 5 ? 'PT0H' : 'PT2H' ) );
			self::assertTrue( $this->reserve_activation_attempt( $limiter, $fixed_scope, $attempt, $time ) );
		}

		self::assertFalse(
			$this->reserve_activation_attempt(
				$limiter,
				$fixed_scope,
				10,
				$start->add( new DateInterval( 'PT4H' ) )
			)
		);
	}

	/**
	 * Provide server-derived identity scopes with identical approved limits.
	 *
	 * @return iterable<string, array{string}>
	 */
	public function activation_identity_scope_provider(): iterable {
		yield 'WordPress User' => array( 'user' );
		yield 'keyed email' => array( 'email' );
	}

	/**
	 * Direct-peer IP allows thirty hourly and one hundred daily attempts.
	 */
	public function test_activation_ip_limits_are_balanced(): void {
		global $wpdb;

		$limiter = new WordPressOptionTagActivationRateLimiter( $wpdb, get_current_blog_id() );
		$start   = new DateTimeImmutable( '2026-07-30 00:00:00', new DateTimeZone( 'UTC' ) );

		for ( $attempt = 0; $attempt < 100; ++$attempt ) {
			$hour = intdiv( $attempt, 30 ) * 2;
			self::assertTrue(
				$this->reserve_activation_attempt(
					$limiter,
					'ip',
					$attempt,
					$start->add( new DateInterval( 'PT' . $hour . 'H' ) )
				)
			);
		}

		self::assertFalse(
			$this->reserve_activation_attempt(
				$limiter,
				'ip',
				100,
				$start->add( new DateInterval( 'PT8H' ) )
			)
		);
	}

	/**
	 * One Tag allows ten activation attempts per hour.
	 */
	public function test_activation_tag_limit_is_ten_per_hour(): void {
		global $wpdb;

		$limiter = new WordPressOptionTagActivationRateLimiter( $wpdb, get_current_blog_id() );
		$now     = new DateTimeImmutable( '2026-07-30 00:00:00', new DateTimeZone( 'UTC' ) );

		for ( $attempt = 0; $attempt < 10; ++$attempt ) {
			self::assertTrue( $this->reserve_activation_attempt( $limiter, 'tag', $attempt, $now ) );
		}

		self::assertFalse( $this->reserve_activation_attempt( $limiter, 'tag', 10, $now ) );
	}

	/**
	 * Site-wide activation allows one hundred attempts per minute.
	 */
	public function test_activation_global_limit_is_one_hundred_per_minute(): void {
		global $wpdb;

		$limiter = new WordPressOptionTagActivationRateLimiter( $wpdb, get_current_blog_id() );
		$now     = new DateTimeImmutable( '2026-07-30 00:00:00', new DateTimeZone( 'UTC' ) );

		for ( $attempt = 0; $attempt < 100; ++$attempt ) {
			self::assertTrue( $this->reserve_activation_attempt( $limiter, 'global', $attempt, $now ) );
		}

		self::assertFalse( $this->reserve_activation_attempt( $limiter, 'global', 100, $now ) );
	}

	/**
	 * Activation Options store only hashed bucket names, counts, and expiry.
	 */
	public function test_activation_limit_storage_contains_no_raw_scope_values(): void {
		global $wpdb;

		$limiter = new WordPressOptionTagActivationRateLimiter( $wpdb, get_current_blog_id() );
		$email   = LookupDigest::from_digest( hash( 'sha256', 'private-email-scope' ) );
		$ip      = LookupDigest::from_digest( hash( 'sha256', 'private-ip-scope' ) );

		self::assertTrue(
			$limiter->reserve(
				424242,
				$email,
				$ip,
				TagId::from_canonical( 'A7R2W9' ),
				new DateTimeImmutable( '2026-07-30 00:00:00', new DateTimeZone( 'UTC' ) )
			)
		);

		$like  = $wpdb->esc_like( WordPressOptionTagActivationRateLimiter::OPTION_PREFIX ) . '%';
		$query = $wpdb->prepare( 'SELECT option_name, option_value, autoload FROM %i WHERE option_name LIKE %s', $wpdb->options, $like );
		self::assertIsString( $query );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above; isolated inspection of plugin-owned test Options.
		$rows = $wpdb->get_results( $query, ARRAY_A );
		self::assertCount( 9, $rows );

		foreach ( $rows as $row ) {
			self::assertMatchesRegularExpression(
				'/^returntag_activation_rate_[0-9]+_[a-f0-9]{64}$/D',
				(string) $row['option_name']
			);
			self::assertSame( 'off', $row['autoload'] );
			$value = maybe_unserialize( $row['option_value'] );
			self::assertIsArray( $value );
			self::assertSame(
				array( 'count', 'expires_at' ),
				array_keys( $value )
			);
		}

		$stored = wp_json_encode( $rows );
		self::assertIsString( $stored );
		self::assertStringNotContainsString( 'A7R2W9', $stored );
		self::assertStringNotContainsString( $email->value, $stored );
		self::assertStringNotContainsString( $ip->value, $stored );
	}

	/**
	 * Active owner identity comes only from the server-side WordPress session.
	 */
	public function test_active_owner_and_finder_experiences_are_separated(): void {
		$owner_id = self::factory()->user->create(
			array(
				'user_email'   => 'private-owner-rt309@example.test',
				'display_name' => 'Private RT309 Owner',
			)
		);
		$this->insert_tag(
			'A7R2W9',
			'active',
			'suspended',
			false,
			$owner_id,
			'2026-07-30 00:00:00',
			'Blue backpack',
			true,
			'Please leave it with airport security.'
		);
		$this->go_to( home_url( '/t/A7R2W9/' ) );

		wp_set_current_user( $owner_id );
		$owner_page = $this->route->resolve_page();
		self::assertSame( PublicTagPageState::OWNER_ENTRY, $owner_page->state );
		self::assertNull( $owner_page->public_label );

		wp_set_current_user( 0 );
		$finder_page = $this->route->resolve_page();
		self::assertSame( PublicTagPageState::FINDER_ENTRY, $finder_page->state );
		self::assertSame( 'Blue backpack', $finder_page->public_label );
		self::assertSame( 'Please leave it with airport security.', $finder_page->lost_message );

		$html = $this->renderer->render_to_string( $finder_page );
		self::assertStringContainsString( 'Blue backpack', $html );
		self::assertStringContainsString( 'Marked as lost', $html );
		self::assertStringNotContainsString( 'private-owner-rt309@example.test', $html );
		self::assertStringNotContainsString( 'Private RT309 Owner', $html );
		self::assertStringNotContainsString( 'owner_id', $html );
		self::assertStringNotContainsString( 'A7R2W9', $html );
	}

	/** Active owners receive a Tag-specific Account deep link and no Finder form. */
	public function test_owner_entry_links_to_the_matching_tag_detail(): void {
		$html = $this->renderer->render_to_string(
			PublicTagPage::owner_entry( TagType::CLASSIC_TAG ),
			null,
			null,
			TagId::from_canonical( 'A7R2W9' )
		);

		self::assertStringContainsString( 'This ForgeTag is yours', $html );
		self::assertStringContainsString( 'Manage this tag', $html );
		self::assertStringContainsString( '/account/tags/A7R2W9/', $html );
		self::assertStringNotContainsString( 'returntag-public__finder-form', $html );
	}

	/** Finder intake renders only the approved optional-message and required-photo contract. */
	public function test_finder_report_form_uses_private_evidence_contract(): void {
		$html = $this->renderer->render_to_string(
			PublicTagPage::finder_entry( TagType::CLASSIC_TAG, 'Travel bag', true, 'Please help return this item.' ),
			null,
			new FinderReportFormView(
				home_url( '/t/A7R2W9' ),
				'nonce-value',
				'signed-token',
				FinderReportFormState::READY
			)
		);

		self::assertStringContainsString( 'Message for the owner', $html );
		self::assertStringContainsString( 'optional', $html );
		self::assertStringContainsString( 'name="returntag_finder_photo"', $html );
		self::assertStringContainsString( 'type="file"', $html );
		self::assertStringContainsString( 'required', $html );
		self::assertStringContainsString( 'Report details', $html );
		self::assertStringContainsString( 'Review and send', $html );
		self::assertStringContainsString( 'Send report for review', $html );
		self::assertStringNotContainsString( 'finder_email', $html );
		self::assertStringNotContainsString( 'finder_name', $html );
		self::assertStringNotContainsString( 'name="country"', $html );
		self::assertStringNotContainsString( 'name="city"', $html );
		self::assertStringNotContainsString( 'Owner verified', $html );
	}

	/** Finder presentation explains the recoverable fail-closed state when intake is unavailable. */
	public function test_finder_entry_without_runtime_shows_safe_unavailable_feedback(): void {
		$html = $this->renderer->render_to_string(
			PublicTagPage::finder_entry( TagType::CLASSIC_TAG, 'Travel bag', false, null )
		);

		self::assertStringContainsString( 'Private reporting is temporarily unavailable', $html );
		self::assertStringContainsString( 'Please keep the item secure and try again later.', $html );
		self::assertStringNotContainsString( 'returntag-public__finder-form', $html );
	}

	/** Accepted reports offer optional private continuation without exposing identity. */
	public function test_finder_report_success_offers_private_email_verification(): void {
		$html = $this->renderer->render_to_string(
			PublicTagPage::finder_entry( TagType::CLASSIC_TAG, 'Travel bag', false, null ),
			null,
			new FinderReportFormView(
				home_url( '/t/A7R2W9' ),
				'nonce-value',
				'opaque-continuation',
				FinderReportFormState::ACCEPTED,
				new FinderEmailFormView(
					home_url( '/t/A7R2W9' ),
					'email-nonce',
					'opaque-continuation',
					FinderEmailFormState::READY
				)
			)
		);

		self::assertStringContainsString( 'Continue privately', $html );
		self::assertStringContainsString( 'name="returntag_finder_email"', $html );
		self::assertStringContainsString( 'name="returntag_finder_email_code"', $html );
		self::assertStringContainsString( 'opaque-continuation', $html );
		self::assertStringNotContainsString( 'finder@example.test', $html );
		self::assertStringNotContainsString( 'conversation_id', $html );
	}

	/** Finder email OTP queue payload contains only the internal challenge ID. */
	public function test_finder_email_otp_queue_carries_only_challenge_id(): void {
		$scheduler = new ActionSchedulerFinderEmailOtpScheduler();
		$args      = array( 'challenge_id' => 987656 );

		$scheduler->schedule( 987656 );
		$action_id = \ActionScheduler::store()->query_action(
			array(
				'hook'   => ActionSchedulerFinderEmailOtpScheduler::HOOK,
				'args'   => $args,
				'group'  => ActionSchedulerFinderEmailOtpScheduler::GROUP,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			)
		);

		self::assertIsInt( $action_id );
		self::assertSame( $args, \ActionScheduler::store()->fetch_action( $action_id )->get_args() );
		as_unschedule_action( ActionSchedulerFinderEmailOtpScheduler::HOOK, $args, ActionSchedulerFinderEmailOtpScheduler::GROUP );
	}

	/** Finder intake preflight requires a working report-ID-only queue. */
	public function test_finder_report_queue_is_available_and_carries_only_internal_id(): void {
		$scheduler = new ActionSchedulerFinderReportProcessingScheduler();
		$args      = array( 'finder_report_id' => 987654 );

		self::assertTrue( $scheduler->is_available() );
		$scheduler->schedule( 987654, 60 );

		$action_id = \ActionScheduler::store()->query_action(
			array(
				'hook'   => ActionSchedulerFinderReportProcessingScheduler::HOOK,
				'args'   => $args,
				'group'  => ActionSchedulerFinderReportProcessingScheduler::GROUP,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			)
		);

		self::assertIsInt( $action_id );
		self::assertGreaterThan( 0, $action_id );
		self::assertSame( $args, \ActionScheduler::store()->fetch_action( $action_id )->get_args() );

		as_unschedule_action(
			ActionSchedulerFinderReportProcessingScheduler::HOOK,
			$args,
			ActionSchedulerFinderReportProcessingScheduler::GROUP
		);
	}

	/** Owner notifications use a unique report-ID-only queue contract. */
	public function test_finder_owner_notification_queue_carries_only_internal_id(): void {
		$scheduler = new ActionSchedulerFinderReportOwnerNotificationScheduler();
		$args      = array( 'finder_report_id' => 987655 );

		self::assertTrue( $scheduler->is_available() );
		$scheduler->schedule( 987655 );

		$action_id = \ActionScheduler::store()->query_action(
			array(
				'hook'   => ActionSchedulerFinderReportOwnerNotificationScheduler::HOOK,
				'args'   => $args,
				'group'  => ActionSchedulerFinderReportOwnerNotificationScheduler::GROUP,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			)
		);

		self::assertIsInt( $action_id );
		self::assertGreaterThan( 0, $action_id );
		self::assertSame( $args, \ActionScheduler::store()->fetch_action( $action_id )->get_args() );

		as_unschedule_action(
			ActionSchedulerFinderReportOwnerNotificationScheduler::HOOK,
			$args,
			ActionSchedulerFinderReportOwnerNotificationScheduler::GROUP
		);
	}

	/**
	 * Finder pause removes public item and Lost Mode content.
	 */
	public function test_finder_flag_fails_closed_without_optional_public_fields(): void {
		$this->insert_tag(
			'A7R2W9',
			'active',
			'released',
			true,
			42,
			'2026-07-30 00:00:00',
			'Camera',
			true,
			'Leave it at the front desk.'
		);
		update_option( FeatureFlag::FINDER_CONTACT->value, '0', false );
		$this->go_to( home_url( '/t/A7R2W9/' ) );

		$page = $this->route->resolve_page();
		$html = $this->renderer->render_to_string( $page );

		self::assertSame( PublicTagPageState::FINDER_UNAVAILABLE, $page->state );
		self::assertStringNotContainsString( 'Camera', $html );
		self::assertStringNotContainsString( 'front desk', $html );
	}

	/**
	 * Public text is escaped at render time and private fields are never selected.
	 */
	public function test_finder_content_is_escaped_without_private_item_name(): void {
		$this->insert_tag(
			'A7R2W9',
			'active',
			'released',
			true,
			42,
			'2026-07-30 00:00:00',
			'<script>alert("label")</script>',
			true,
			'<img src=x onerror=alert("lost")>'
		);
		$this->go_to( home_url( '/t/A7R2W9/' ) );

		$html = $this->renderer->render_to_string( $this->route->resolve_page() );

		self::assertStringContainsString( '&lt;script&gt;alert(&quot;label&quot;)&lt;/script&gt;', $html );
		self::assertStringContainsString( '&lt;img src=x onerror=alert(&quot;lost&quot;)&gt;', $html );
		self::assertStringNotContainsString( '<script>', $html );
		self::assertStringNotContainsString( '<img src=x', $html );
		self::assertStringNotContainsString( 'PRIVATE-ITEM-NAME', $html );
	}

	/**
	 * Tag-level service states remain distinct.
	 */
	public function test_suspended_and_retired_states_are_distinct(): void {
		$this->insert_tag( 'A7R2W8', 'suspended', 'released', true );
		$this->insert_tag( 'A7R2W9', 'retired', 'released', true );

		$this->go_to( home_url( '/t/A7R2W8/' ) );
		self::assertSame( PublicTagPageState::SUSPENDED, $this->route->resolve_page()->state );

		$this->go_to( home_url( '/t/A7R2W9/' ) );
		self::assertSame( PublicTagPageState::RETIRED, $this->route->resolve_page()->state );
	}

	/**
	 * Missing Batch or stale Schema returns the generic service page.
	 */
	public function test_data_and_schema_inconsistency_fail_closed(): void {
		global $wpdb;

		$wpdb->insert(
			$this->tables->tags(),
			$this->tag_row( 'A7R2W9', 999999, 'unregistered' ),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		$this->go_to( home_url( '/t/A7R2W9/' ) );
		self::assertSame( PublicTagPageState::SERVICE_UNAVAILABLE, $this->route->resolve_page()->state );

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
		self::assertSame( PublicTagPageState::SERVICE_UNAVAILABLE, $this->route->resolve_page()->state );
	}

	/**
	 * RT-301 does not override unrelated theme canonicalization.
	 */
	public function test_unrelated_requests_keep_theme_canonicalization(): void {
		$this->go_to( home_url( '/not-a-tag-route/' ) );

		self::assertFalse( $this->route->is_public_tag_request() );
		self::assertSame(
			'https://example.test/canonical',
			$this->route->disable_canonical_redirect(
				'https://example.test/canonical',
				'https://example.test/not-a-tag-route'
			)
		);
	}

	/**
	 * Lifecycle activation and deactivation persist and remove the exact route.
	 */
	public function test_lifecycle_refreshes_rewrite_rules_without_network_mutation(): void {
		$lifecycle = new PublicRewriteLifecycle( RETURNTAG_TAGCORE_FILE, $this->route );

		$lifecycle->deactivate();
		$rules = get_option( 'rewrite_rules', array() );
		self::assertIsArray( $rules );
		self::assertArrayNotHasKey( PublicTagRouteController::REWRITE_PATTERN, $rules );

		$lifecycle->activate();
		$rules = get_option( 'rewrite_rules', array() );
		self::assertIsArray( $rules );
		self::assertArrayHasKey( PublicTagRouteController::REWRITE_PATTERN, $rules );

		$lifecycle->activate( true );
		self::assertSame( $rules, get_option( 'rewrite_rules', array() ) );
	}

	/**
	 * Insert one synthetic Batch and Tag fixture.
	 *
	 * @param string      $tag_id Canonical Tag ID.
	 * @param string      $tag_status Canonical Tag status.
	 * @param string      $batch_status Canonical Batch status.
	 * @param bool        $batch_activation_enabled Batch activation control.
	 * @param int|null    $owner_id Optional owner ID.
	 * @param string|null $activated_at Optional activation timestamp.
	 * @param string|null $public_label Optional public label.
	 * @param bool        $lost_mode Lost Mode state.
	 * @param string|null $lost_message Optional Lost Mode message.
	 */
	private function insert_tag(
		string $tag_id,
		string $tag_status,
		string $batch_status,
		bool $batch_activation_enabled,
		?int $owner_id = null,
		?string $activated_at = null,
		?string $public_label = null,
		bool $lost_mode = false,
		?string $lost_message = null
	): void {
		global $wpdb;

		$wpdb->insert(
			$this->tables->batches(),
			array(
				'batch_code'         => 'RT303-' . $tag_id,
				'tag_type'           => 'classic_tag',
				'model_code'         => null,
				'smart_network'      => 'none',
				'manufacturer'       => null,
				'sales_channel'      => null,
				'requested_quantity' => 1,
				'generated_quantity' => 1,
				'batch_status'       => $batch_status,
				'activation_enabled' => $batch_activation_enabled ? 1 : 0,
				'notes'              => null,
				'created_by'         => 1,
				'created_at'         => '2026-07-30 00:00:00',
				'updated_at'         => '2026-07-30 00:00:00',
			)
		);
		self::assertGreaterThan( 0, $wpdb->insert_id );

		$row                 = $this->tag_row( $tag_id, $wpdb->insert_id, $tag_status );
		$row['owner_id']     = $owner_id;
		$row['activated_at'] = $activated_at;
		$row['public_label'] = $public_label;
		$row['lost_mode']    = $lost_mode ? 1 : 0;
		$row['lost_message'] = $lost_message;
		$row['item_name']    = 'PRIVATE-ITEM-NAME';

		self::assertSame( 1, $wpdb->insert( $this->tables->tags(), $row ) );
	}

	/**
	 * Build one required Tag row with no real personal data.
	 *
	 * @param string $tag_id Canonical Tag ID.
	 * @param int    $batch_id Stored Batch ID.
	 * @param string $tag_status Canonical Tag status.
	 * @return array<string, int|string|null>
	 */
	private function tag_row( string $tag_id, int $batch_id, string $tag_status ): array {
		return array(
			'tag_id'     => $tag_id,
			'batch_id'   => $batch_id,
			'tag_type'   => 'classic_tag',
			'tag_status' => $tag_status,
			'lost_mode'  => 0,
			'created_at' => '2026-07-30 00:00:00',
			'updated_at' => '2026-07-30 00:00:00',
			'item_name'  => 'PRIVATE-ITEM-NAME',
		);
	}

	/**
	 * Build the production Schema-8 OTP store.
	 */
	private function otp_store(): WpdbActivationOtpRequestStore {
		global $wpdb;

		$gateway = new WpdbGateway( $wpdb );
		$dates   = new DatabaseDateTimeCodec();

		return new WpdbActivationOtpRequestStore(
			$gateway,
			$this->tables,
			$dates,
			new WpdbAuthChallengeRepository( $gateway, $this->tables, $dates ),
			new WpdbTransactionManager( $wpdb )
		);
	}

	/**
	 * Build one unissued challenge fixture.
	 *
	 * @param SodiumActivationOtpProtector $protector Test crypto.
	 * @param TagId                        $tag_id Public Tag.
	 * @param EmailAddress                 $email Canonical email.
	 * @param DateTimeImmutable            $now Creation time.
	 */
	private function otp_challenge(
		SodiumActivationOtpProtector $protector,
		TagId $tag_id,
		EmailAddress $email,
		DateTimeImmutable $now
	): NewAuthChallengeRecord {
		return new NewAuthChallengeRecord(
			RequestActivationOtp::PURPOSE,
			RequestActivationOtp::SUBJECT_TYPE,
			$tag_id->value,
			$protector->encrypt_email( $email, $tag_id ),
			$protector->email_lookup( $email ),
			$protector->placeholder_hash(),
			0,
			0,
			$protector->ip_lookup( '192.0.2.4' ),
			$now->add( new DateInterval( 'PT10M' ) ),
			null,
			null,
			$now
		);
	}

	/**
	 * Build deterministic test-only crypto.
	 */
	private function otp_protector(): SodiumActivationOtpProtector {
		return new SodiumActivationOtpProtector(
			ActivationOtpSecrets::from_keys(
				str_repeat( 'e', 32 ),
				str_repeat( 'l', 32 ),
				str_repeat( 'p', 32 )
			)
		);
	}

	/**
	 * Reserve one attempt while holding only the selected scope constant.
	 *
	 * @param WordPressOptionTagActivationRateLimiter $limiter Limiter under test.
	 * @param string                                  $fixed_scope Fixed scope name.
	 * @param int                                     $attempt Attempt sequence.
	 * @param DateTimeImmutable                       $now Current test time.
	 */
	private function reserve_activation_attempt(
		WordPressOptionTagActivationRateLimiter $limiter,
		string $fixed_scope,
		int $attempt,
		DateTimeImmutable $now
	): bool {
		$owner_id = 'user' === $fixed_scope ? 42 : $attempt + 1;
		$email    = LookupDigest::from_digest(
			hash( 'sha256', 'email:' . ( 'email' === $fixed_scope ? 'fixed' : (string) $attempt ) )
		);
		$ip       = LookupDigest::from_digest(
			hash( 'sha256', 'ip:' . ( 'ip' === $fixed_scope ? 'fixed' : (string) $attempt ) )
		);
		$tag_id   = 'tag' === $fixed_scope
			? TagId::from_canonical( 'A7R2W9' )
			: $this->tag_id_for_attempt( $attempt );

		return $limiter->reserve( $owner_id, $email, $ip, $tag_id, $now );
	}

	/**
	 * Build a deterministic canonical Tag ID for one bounded test attempt.
	 *
	 * @param int $attempt Attempt sequence.
	 */
	private function tag_id_for_attempt( int $attempt ): TagId {
		$value = str_repeat( TagId::ALPHABET[0], TagId::LENGTH );

		for ( $position = TagId::LENGTH - 1; $position >= 0; --$position ) {
			$value[ $position ] = TagId::ALPHABET[ $attempt % strlen( TagId::ALPHABET ) ];
			$attempt            = intdiv( $attempt, strlen( TagId::ALPHABET ) );
		}

		return TagId::from_canonical( $value );
	}

	/**
	 * Delete only expired-test limiter options.
	 *
	 * @param wpdb $database Test database.
	 */
	private function clear_rate_limit_options( wpdb $database ): void {
		foreach ( array( WordPressOptionActivationOtpRateLimiter::OPTION_PREFIX, WordPressOptionActivationOtpVerificationRateLimiter::OPTION_PREFIX, WordPressOptionTagActivationRateLimiter::OPTION_PREFIX ) as $prefix ) {
			$like = $database->esc_like( $prefix ) . '%';
			$sql  = $database->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s', $database->options, $like );

			if ( ! is_string( $sql ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above for isolated plugin-owned fixture cleanup.
			foreach ( $database->get_col( $sql ) as $option_name ) {
				if ( is_string( $option_name ) ) {
					delete_option( $option_name );
				}
			}
		}
	}

	/**
	 * Remove only trusted ReturnTag tables from the isolated test database.
	 *
	 * @param wpdb $database Database adapter.
	 */
	private function clear_schema( wpdb $database ): void {
		$names = new TableNames( $database->prefix );

		foreach ( array( $names->events(), $names->access_tokens(), $names->messages(), $names->conversations(), $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated cleanup with trusted identifiers.
			$database->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
