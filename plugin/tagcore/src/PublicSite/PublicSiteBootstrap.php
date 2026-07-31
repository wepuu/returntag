<?php
/**
 * TagCore public-site composition root.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use ReturnTag\TagCore\Application\Auth\CompletePasswordlessAuthentication;
use ReturnTag\TagCore\Application\Auth\PasswordlessAccountEventIdentityPolicy;
use ReturnTag\TagCore\Application\Auth\RequestActivationOtp;
use ReturnTag\TagCore\Application\Auth\VerifyActivationOtp;
use ReturnTag\TagCore\Application\Auth\WordPressAccountEmailPolicy;
use ReturnTag\TagCore\Application\Persistence\DenyAllEventMetadataPolicy;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPagePolicy;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Application\Tag\TagActivationEventIdentityPolicy;
use ReturnTag\TagCore\Application\Tag\TagIdInputNormalizer;
use ReturnTag\TagCore\Application\Tag\ActivateTag;
use ReturnTag\TagCore\Application\Tag\ActivateTagAndResolvePage;
use ReturnTag\TagCore\Application\Tag\RateLimitedTagActivation;
use ReturnTag\TagCore\Infrastructure\Auth\WordPressAuthenticatedSession;
use ReturnTag\TagCore\Infrastructure\Auth\WordPressAuthenticatedUserEmailReader;
use ReturnTag\TagCore\Infrastructure\Auth\WordPressPasswordlessAccountProvisioner;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbActivationOtpRequestStore;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAuthChallengeRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbEventRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbPublicTagStateReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTagActivationRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerActivationOtpScheduler;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionActivationOtpRateLimiter;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionActivationOtpVerificationRateLimiter;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionTagActivationRateLimiter;
use ReturnTag\TagCore\Infrastructure\Security\ActivationOtpSecrets;
use ReturnTag\TagCore\Infrastructure\Security\SodiumActivationOtpProtector;
use ReturnTag\TagCore\Infrastructure\SystemClock;
use ReturnTag\TagCore\Infrastructure\WordPressOptionFeatureFlagReader;
use RuntimeException;
use wpdb;

/**
 * Wires the public Tag route into WordPress.
 */
final class PublicSiteBootstrap {
	/**
	 * Register the public route and its lifecycle hooks.
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
		$feature_flags = new WordPressOptionFeatureFlagReader();
		$schema_state  = new SchemaState(
			new WordPressSchemaVersionStore(),
			( new MigrationRegistryFactory( $wpdb ) )->create()
		);
		$pages         = new ResolvePublicTagPage(
			new WpdbPublicTagStateReader( $gateway, $tables, $dates ),
			$feature_flags,
			new PublicTagPagePolicy( new TagActivationAvailabilityPolicy() )
		);
		$session       = new WordPressAuthenticatedSession();
		$email_policy  = new WordPressAccountEmailPolicy();
		$otp_services  = self::otp_services(
			$wpdb,
			$gateway,
			$tables,
			$dates,
			$pages,
			$feature_flags,
			$session,
			$email_policy
		);
		$route         = new PublicTagRouteController(
			$plugin_dir,
			new PublicTagResponsePolicy(),
			new TagIdInputNormalizer(),
			$pages,
			$schema_state,
			new PublicTagTemplateRenderer( $plugin_dir ),
			new ActivationOtpFormHandler(
				$otp_services['request'],
				$otp_services['authenticate'],
				$session,
				$email_policy,
				$otp_services['activate'],
				new WordPressAuthenticatedUserEmailReader(),
				$otp_services['protector']
			)
		);

		$route->register_hooks();
		( new PublicRewriteLifecycle( $plugin_file, $route ) )->register_hooks();
	}

	/**
	 * Build OTP request and verification use cases when secrets are available.
	 *
	 * @param wpdb                             $database Active database connection.
	 * @param WpdbGateway                      $gateway Safe query gateway.
	 * @param TableNames                       $tables Trusted table names.
	 * @param DatabaseDateTimeCodec            $dates UTC codec.
	 * @param ResolvePublicTagPage             $pages Public state resolver.
	 * @param WordPressOptionFeatureFlagReader $feature_flags Operational controls.
	 * @param WordPressAuthenticatedSession    $session Native WordPress session adapter.
	 * @param WordPressAccountEmailPolicy      $email_policy WordPress account email boundary.
	 * @return array{
	 *   request: RequestActivationOtp|null,
	 *   authenticate: CompletePasswordlessAuthentication|null,
	 *   activate: RateLimitedTagActivation|null,
	 *   protector: SodiumActivationOtpProtector|null
	 * }
	 */
	private static function otp_services(
		wpdb $database,
		WpdbGateway $gateway,
		TableNames $tables,
		DatabaseDateTimeCodec $dates,
		ResolvePublicTagPage $pages,
		WordPressOptionFeatureFlagReader $feature_flags,
		WordPressAuthenticatedSession $session,
		WordPressAccountEmailPolicy $email_policy
	): array {
		try {
			$protector = new SodiumActivationOtpProtector( ActivationOtpSecrets::load() );
		} catch ( RuntimeException ) {
			return array(
				'request'      => null,
				'authenticate' => null,
				'activate'     => null,
				'protector'    => null,
			);
		}

		$challenges = new WpdbAuthChallengeRepository( $gateway, $tables, $dates );
		$store      = new WpdbActivationOtpRequestStore(
			$gateway,
			$tables,
			$dates,
			$challenges,
			new WpdbTransactionManager( $database )
		);

		$clock             = new SystemClock();
		$verification      = new VerifyActivationOtp(
			$pages,
			$feature_flags,
			$store,
			$protector,
			new WordPressOptionActivationOtpVerificationRateLimiter( $database, get_current_blog_id() ),
			$clock
		);
		$account_events    = new WpdbEventRepository(
			$gateway,
			$tables,
			$dates,
			new DenyAllEventMetadataPolicy(),
			new PasswordlessAccountEventIdentityPolicy()
		);
		$activation_events = new WpdbEventRepository(
			$gateway,
			$tables,
			$dates,
			new DenyAllEventMetadataPolicy(),
			new TagActivationEventIdentityPolicy()
		);
		$activation        = new ActivateTagAndResolvePage(
			new ActivateTag(
				new WpdbTagActivationRepository( $gateway, $tables, $dates ),
				$activation_events,
				new WpdbTransactionManager( $database ),
				$feature_flags,
				$clock
			),
			$pages
		);

		return array(
			'request'      => new RequestActivationOtp(
				$pages,
				$feature_flags,
				$store,
				$protector,
				new WordPressOptionActivationOtpRateLimiter( $database, get_current_blog_id() ),
				new ActionSchedulerActivationOtpScheduler(),
				$email_policy,
				$clock
			),
			'authenticate' => new CompletePasswordlessAuthentication(
				$verification,
				$protector,
				new WordPressPasswordlessAccountProvisioner( $database, $account_events ),
				$session,
				$email_policy,
				$clock
			),
			'activate'     => new RateLimitedTagActivation(
				new WordPressOptionTagActivationRateLimiter( $database, get_current_blog_id() ),
				$activation,
				$pages,
				$clock
			),
			'protector'    => $protector,
		);
	}
}
