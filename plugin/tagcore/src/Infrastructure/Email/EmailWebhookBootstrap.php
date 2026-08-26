<?php
/**
 * Resend webhook and convergence composition root.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\Email\EmailDeliveryTransitionPolicy;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbEmailDeliveryRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use ReturnTag\TagCore\Infrastructure\SystemClock;
use Throwable;
use wpdb;

/** Registers only when Schema and the external webhook secret are available. */
final class EmailWebhookBootstrap {
	public const CONVERGENCE_HOOK  = 'returntag_converge_email_webhook_events';
	public const CONVERGENCE_GROUP = 'returntag-email-delivery';

	/** Register the signed REST endpoint and bounded recovery worker. */
	public static function register(): void {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return;
		}
		try {
			$tables = new TableNames( $wpdb->prefix );
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tables->email_webhook_events() ) ) !== $tables->email_webhook_events() ) {
				return;
			}
			$clock      = new SystemClock();
			$repository = new WpdbEmailDeliveryRepository( new WpdbGateway( $wpdb ), $tables, new DatabaseDateTimeCodec(), new WpdbTransactionManager( $wpdb ), new EmailDeliveryTransitionPolicy() );
			( new ResendWebhookRestController( new ResendWebhookVerifier( ResendConfiguration::webhook_secret(), $clock ), new ResendWebhookMapper(), $repository, $clock ) )->register();
			add_action(
				self::CONVERGENCE_HOOK,
				static function () use ( $repository, $clock ): void {
					$repository->converge_pending( $clock->now(), 100 );
				}
			);
			add_action(
				'action_scheduler_init',
				static function (): void {
					if ( function_exists( 'as_has_scheduled_action' ) && function_exists( 'as_schedule_recurring_action' ) && false === \as_has_scheduled_action( self::CONVERGENCE_HOOK, array(), self::CONVERGENCE_GROUP ) ) {
						\as_schedule_recurring_action( time() + 5 * MINUTE_IN_SECONDS, 5 * MINUTE_IN_SECONDS, self::CONVERGENCE_HOOK, array(), self::CONVERGENCE_GROUP, true, 20 );
					}
				}
			);
		} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Missing configuration intentionally leaves the boundary unavailable.
			// Missing or invalid configuration leaves the boundary unavailable.
		}
	}
}
