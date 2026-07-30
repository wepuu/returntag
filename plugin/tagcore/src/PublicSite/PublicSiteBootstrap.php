<?php
/**
 * TagCore public-site composition root.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use ReturnTag\TagCore\Application\Auth\RequestActivationOtp;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPagePolicy;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Application\Tag\TagIdInputNormalizer;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbActivationOtpRequestStore;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAuthChallengeRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbPublicTagStateReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerActivationOtpScheduler;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionActivationOtpRateLimiter;
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
		$otp_requests  = self::otp_requests( $wpdb, $gateway, $tables, $dates, $pages, $feature_flags );
		$route         = new PublicTagRouteController(
			$plugin_dir,
			new PublicTagResponsePolicy(),
			new TagIdInputNormalizer(),
			$pages,
			$schema_state,
			new PublicTagTemplateRenderer( $plugin_dir ),
			new ActivationOtpFormHandler( $otp_requests )
		);

		$route->register_hooks();
		( new PublicRewriteLifecycle( $plugin_file, $route ) )->register_hooks();
	}

	/**
	 * Build the OTP request use case only when external secrets are available.
	 *
	 * @param wpdb                             $database Active database connection.
	 * @param WpdbGateway                      $gateway Safe query gateway.
	 * @param TableNames                       $tables Trusted table names.
	 * @param DatabaseDateTimeCodec            $dates UTC codec.
	 * @param ResolvePublicTagPage             $pages Public state resolver.
	 * @param WordPressOptionFeatureFlagReader $feature_flags Operational controls.
	 */
	private static function otp_requests(
		wpdb $database,
		WpdbGateway $gateway,
		TableNames $tables,
		DatabaseDateTimeCodec $dates,
		ResolvePublicTagPage $pages,
		WordPressOptionFeatureFlagReader $feature_flags
	): ?RequestActivationOtp {
		try {
			$protector = new SodiumActivationOtpProtector( ActivationOtpSecrets::load() );
		} catch ( RuntimeException ) {
			return null;
		}

		$challenges = new WpdbAuthChallengeRepository( $gateway, $tables, $dates );
		$store      = new WpdbActivationOtpRequestStore(
			$gateway,
			$tables,
			$dates,
			$challenges,
			new WpdbTransactionManager( $database )
		);

		return new RequestActivationOtp(
			$pages,
			$feature_flags,
			$store,
			$protector,
			new WordPressOptionActivationOtpRateLimiter( $database, get_current_blog_id() ),
			new ActionSchedulerActivationOtpScheduler(),
			new SystemClock()
		);
	}
}
