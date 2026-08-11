<?php
/**
 * TagCore Owner Account composition root.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

use DateInterval;
use ReturnTag\TagCore\Application\Account\ContinueOwnerConversation;
use ReturnTag\TagCore\Application\Account\MutateOwnerTag;
use ReturnTag\TagCore\Application\Account\OwnerTagMutationEventIdentityPolicy;
use ReturnTag\TagCore\Application\Account\ReadOwnerConversations;
use ReturnTag\TagCore\Application\Account\ReadOwnerTag;
use ReturnTag\TagCore\Application\Account\ReadOwnerTags;
use ReturnTag\TagCore\Application\Auth\CompleteAccountPasswordlessAuthentication;
use ReturnTag\TagCore\Application\Auth\DispatchAccountOtp;
use ReturnTag\TagCore\Application\Auth\PasswordlessAccountEventIdentityPolicy;
use ReturnTag\TagCore\Application\Auth\RequestAccountOtp;
use ReturnTag\TagCore\Application\Auth\WordPressAccountEmailPolicy;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayEventIdentityPolicy;
use ReturnTag\TagCore\Application\Persistence\DenyAllEventMetadataPolicy;
use ReturnTag\TagCore\Infrastructure\Auth\WordPressAuthenticatedSession;
use ReturnTag\TagCore\Infrastructure\Auth\WordPressPasswordlessAccountProvisioner;
use ReturnTag\TagCore\Infrastructure\Email\WordPressAccountOtpEmailSender;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAccountOtpStore;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAuthChallengeRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbConversationRelayStore;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbEventRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbOwnerConversationReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbOwnerTagReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbOwnerTagMutationStore;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use ReturnTag\TagCore\Infrastructure\Queue\AccountOtpActionHandler;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerAccountOtpScheduler;
use ReturnTag\TagCore\Infrastructure\Random\PhpActivationOtpCodeGenerator;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionAccountOtpRateLimiter;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionOwnerTagMutationRateLimiter;
use ReturnTag\TagCore\Infrastructure\Security\ActivationOtpSecrets;
use ReturnTag\TagCore\Infrastructure\Security\ConversationRelaySecrets;
use ReturnTag\TagCore\Infrastructure\Security\SodiumAccountOtpProtector;
use ReturnTag\TagCore\Infrastructure\Security\SodiumConversationRelayProtector;
use ReturnTag\TagCore\Infrastructure\SystemClock;
use ReturnTag\TagCore\Infrastructure\WordPressOptionFeatureFlagReader;
use RuntimeException;
use wpdb;

/**
 * Wires Account routes, OTP services, persistence, and maintenance.
 */
final class AccountBootstrap {
	public const CLEANUP_HOOK = 'returntag_cleanup_account_otp';

	public const CLEANUP_GROUP = 'returntag-account-otp-maintenance';

	/**
	 * Register the Owner Account runtime for the current site.
	 *
	 * @param string $plugin_file Absolute TagCore bootstrap file.
	 */
	public static function register( string $plugin_file ): void {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			return;
		}

		$plugin_dir    = dirname( $plugin_file );
		$gateway       = new WpdbGateway( $wpdb );
		$tables        = new TableNames( $wpdb->prefix );
		$dates         = new DatabaseDateTimeCodec();
		$flags         = new WordPressOptionFeatureFlagReader();
		$session       = new WordPressAuthenticatedSession();
		$owner_tags    = new WpdbOwnerTagReader( $gateway, $tables, $dates );
		$conversations = new WpdbOwnerConversationReader( $gateway, $tables, $dates );
		$urls          = new AccountUrlProvider();
		$request_guard = new AccountFormRequestGuard();
		$request       = null;
		$authenticate  = null;
		$challenges    = new WpdbAuthChallengeRepository( $gateway, $tables, $dates );
		$store         = new WpdbAccountOtpStore(
			$gateway,
			$tables,
			$dates,
			$challenges,
			new WpdbTransactionManager( $wpdb )
		);
		$limiter       = new WordPressOptionAccountOtpRateLimiter( $wpdb, get_current_blog_id() );
		$clock         = new SystemClock();
		$transactions  = new WpdbTransactionManager( $wpdb );
		$tag_limiter   = new WordPressOptionOwnerTagMutationRateLimiter( $wpdb, get_current_blog_id() );
		$continuation  = null;

		self::register_cleanup( $store, $limiter, $tag_limiter, $clock );

		try {
			$protector = new SodiumAccountOtpProtector( ActivationOtpSecrets::load() );
		} catch ( RuntimeException ) {
			$protector = null;
		}

		if ( null !== $protector ) {
			$events = new WpdbEventRepository(
				$gateway,
				$tables,
				$dates,
				new DenyAllEventMetadataPolicy(),
				new PasswordlessAccountEventIdentityPolicy()
			);

			$request      = new RequestAccountOtp(
				$flags,
				$store,
				$protector,
				$limiter,
				new ActionSchedulerAccountOtpScheduler(),
				new WordPressAccountEmailPolicy(),
				$clock
			);
			$authenticate = new CompleteAccountPasswordlessAuthentication(
				$flags,
				$store,
				$protector,
				$limiter,
				new WordPressPasswordlessAccountProvisioner( $wpdb, $events ),
				$session,
				new WordPressAccountEmailPolicy(),
				$clock
			);
			$dispatch     = new DispatchAccountOtp(
				$flags,
				$store,
				$protector,
				new PhpActivationOtpCodeGenerator(),
				new WordPressAccountOtpEmailSender(),
				$clock
			);

			( new AccountOtpActionHandler( $dispatch ) )->register();
		}

		try {
			$relay_protector = new SodiumConversationRelayProtector( ConversationRelaySecrets::load() );
			$relay_events    = new WpdbEventRepository(
				$gateway,
				$tables,
				$dates,
				new DenyAllEventMetadataPolicy(),
				new ConversationRelayEventIdentityPolicy()
			);
			$continuation    = new ContinueOwnerConversation(
				$session,
				$flags,
				new WpdbConversationRelayStore( $gateway, $tables, $dates, $transactions, $relay_events ),
				$relay_protector,
				$clock
			);
		} catch ( RuntimeException ) {
			$continuation = null;
		}

		$route = new AccountRouteController(
			$plugin_dir,
			$flags,
			$session,
			new ReadOwnerTags( $session, $flags, $owner_tags ),
			new ReadOwnerTag( $session, $flags, $owner_tags ),
			new ReadOwnerConversations( $session, $flags, $conversations, $clock ),
			new AccountSignInFormHandler( $request, $authenticate, $request_guard ),
			new AccountTagMutationFormHandler(
				new MutateOwnerTag(
					$session,
					$flags,
					new WpdbOwnerTagMutationStore( $gateway, $tables, $dates ),
					$tag_limiter,
					new WpdbEventRepository(
						$gateway,
						$tables,
						$dates,
						new DenyAllEventMetadataPolicy(),
						new OwnerTagMutationEventIdentityPolicy()
					),
					$transactions,
					$clock
				),
				$request_guard
			),
			new AccountConversationFormHandler( $continuation, $request_guard ),
			new AccountSecureReplySessionCookie(),
			new AccountTemplateRenderer( $plugin_dir, $urls ),
			$urls,
			new AccountResponsePolicy()
		);

		$route->register_hooks();
		( new AccountRewriteLifecycle( $plugin_file, $route ) )->register_hooks();
	}

	/**
	 * Register bounded Account challenge and limiter cleanup.
	 *
	 * @param WpdbAccountOtpStore                        $store Account challenge Store.
	 * @param WordPressOptionAccountOtpRateLimiter       $limiter Account rate limiter.
	 * @param WordPressOptionOwnerTagMutationRateLimiter $tag_limiter Owner Tag mutation limiter.
	 * @param SystemClock                                $clock UTC clock.
	 */
	private static function register_cleanup(
		WpdbAccountOtpStore $store,
		WordPressOptionAccountOtpRateLimiter $limiter,
		WordPressOptionOwnerTagMutationRateLimiter $tag_limiter,
		SystemClock $clock
	): void {
		add_action(
			self::CLEANUP_HOOK,
			static function () use ( $store, $limiter, $tag_limiter, $clock ): void {
				$limiter->cleanup_expired();
				$tag_limiter->cleanup_expired();
				$before = $clock->now()->sub( new DateInterval( 'P7D' ) );

				for ( $chunk = 0; $chunk < 10; ++$chunk ) {
					if ( 500 !== $store->cleanup_expired( $before, 500 ) ) {
						break;
					}
				}
			}
		);
		add_action(
			'action_scheduler_init',
			static function (): void {
				if (
					function_exists( 'as_has_scheduled_action' )
					&& function_exists( 'as_schedule_recurring_action' )
					&& false === \as_has_scheduled_action( self::CLEANUP_HOOK, array(), self::CLEANUP_GROUP )
				) {
					\as_schedule_recurring_action(
						time() + DAY_IN_SECONDS,
						DAY_IN_SECONDS,
						self::CLEANUP_HOOK,
						array(),
						self::CLEANUP_GROUP,
						true,
						30
					);
				}
			}
		);
	}
}
