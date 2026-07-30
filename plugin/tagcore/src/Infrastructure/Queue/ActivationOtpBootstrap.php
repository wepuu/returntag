<?php
/**
 * Activation OTP Worker composition root.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use DateInterval;
use ReturnTag\TagCore\Application\Auth\DispatchActivationOtp;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPagePolicy;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Infrastructure\Email\WordPressActivationOtpEmailSender;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbActivationOtpRequestStore;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAuthChallengeRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbPublicTagStateReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use ReturnTag\TagCore\Infrastructure\Random\PhpActivationOtpCodeGenerator;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionActivationOtpRateLimiter;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionActivationOtpVerificationRateLimiter;
use ReturnTag\TagCore\Infrastructure\Security\ActivationOtpSecrets;
use ReturnTag\TagCore\Infrastructure\Security\SodiumActivationOtpProtector;
use ReturnTag\TagCore\Infrastructure\SystemClock;
use ReturnTag\TagCore\Infrastructure\WordPressOptionFeatureFlagReader;
use RuntimeException;
use wpdb;

/**
 * Registers challenge-ID-only dispatch and bounded rate-limit cleanup.
 */
final class ActivationOtpBootstrap {
	public const CLEANUP_HOOK = 'returntag_cleanup_activation_otp_rate_limits';

	public const CLEANUP_GROUP = 'returntag-activation-otp-maintenance';

	/**
	 * Register the Worker and maintenance hooks for the current site.
	 */
	public static function register(): void {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			return;
		}

		$request_limiter      = new WordPressOptionActivationOtpRateLimiter( $wpdb, get_current_blog_id() );
		$verification_limiter = new WordPressOptionActivationOtpVerificationRateLimiter( $wpdb, get_current_blog_id() );
		$tables               = new TableNames( $wpdb->prefix );
		$gateway              = new WpdbGateway( $wpdb );
		$dates                = new DatabaseDateTimeCodec();
		$challenges           = new WpdbAuthChallengeRepository( $gateway, $tables, $dates );
		$store                = new WpdbActivationOtpRequestStore(
			$gateway,
			$tables,
			$dates,
			$challenges,
			new WpdbTransactionManager( $wpdb )
		);
		add_action(
			self::CLEANUP_HOOK,
			static function () use ( $request_limiter, $verification_limiter, $store ): void {
				$request_limiter->cleanup_expired();
				$verification_limiter->cleanup_expired();
				$before = ( new SystemClock() )->now()->sub( new DateInterval( 'P7D' ) );

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

		try {
			$protector = new SodiumActivationOtpProtector( ActivationOtpSecrets::load() );
		} catch ( RuntimeException ) {
			return;
		}
		$flags    = new WordPressOptionFeatureFlagReader();
		$pages    = new ResolvePublicTagPage(
			new WpdbPublicTagStateReader( $gateway, $tables, $dates ),
			$flags,
			new PublicTagPagePolicy( new TagActivationAvailabilityPolicy() )
		);
		$dispatch = new DispatchActivationOtp(
			$pages,
			$flags,
			$store,
			$protector,
			new PhpActivationOtpCodeGenerator(),
			new WordPressActivationOtpEmailSender(),
			new SystemClock()
		);

		( new ActivationOtpActionHandler( $dispatch ) )->register();
	}
}
