<?php
/**
 * Transactional email runtime composition.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\Email\EmailDeliveryTransitionPolicy;
use ReturnTag\TagCore\Application\Email\TransactionalEmailGateway;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbEmailDeliveryRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use ReturnTag\TagCore\Infrastructure\SystemClock;
use Throwable;
use wpdb;

/** Fails closed unless Schema 15 and external configuration are available. */
final class TransactionalEmailRuntimeFactory {
	/**
	 * Build the direct gateway or an explicit fail-closed adapter.
	 *
	 * @param wpdb $database Active WordPress database adapter.
	 */
	public static function create_or_unavailable( wpdb $database ): TransactionalEmailGateway {
		return self::create( $database ) ?? new UnavailableTransactionalEmailGateway();
	}

	/**
	 * Build the direct gateway or return null.
	 *
	 * @param wpdb $database Active WordPress database adapter.
	 */
	public static function create( wpdb $database ): ?TransactionalEmailGateway {
		try {
			$tables = new TableNames( $database->prefix );
			if ( $database->get_var( $database->prepare( 'SHOW TABLES LIKE %s', $tables->email_deliveries() ) ) !== $tables->email_deliveries() ) {
				return null;
			}
			$repository = new WpdbEmailDeliveryRepository( new WpdbGateway( $database ), $tables, new DatabaseDateTimeCodec(), new WpdbTransactionManager( $database ), new EmailDeliveryTransitionPolicy() );
			return new ResendTransactionalEmailGateway( ResendConfiguration::load(), $repository, new SystemClock() );
		} catch ( Throwable ) {
			return null;
		}
	}
}
