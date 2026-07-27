<?php
/**
 * Background Batch generation composition root.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Queue;

use ReturnTag\TagCore\Application\Batch\BatchEventIdentityPolicy;
use ReturnTag\TagCore\Application\Batch\GenerateBatchChunk;
use ReturnTag\TagCore\Application\Persistence\DenyAllEventMetadataPolicy;
use ReturnTag\TagCore\Application\Tag\InsertGeneratedTag;
use ReturnTag\TagCore\Application\Tag\RandomTagIdGenerator;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchGenerationRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbEventRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTagRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use ReturnTag\TagCore\Infrastructure\Random\PhpSecureRandomIntegerSource;
use ReturnTag\TagCore\Infrastructure\SystemClock;
use wpdb;

/**
 * Registers the RT-204 worker for web, Cron, and WP-CLI execution.
 */
final class BatchGenerationBootstrap {
	/**
	 * Register the background worker for the current site.
	 */
	public static function register(): void {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			return;
		}

		$tables     = new TableNames( $wpdb->prefix );
		$gateway    = new WpdbGateway( $wpdb );
		$dates      = new DatabaseDateTimeCodec();
		$generation = new WpdbBatchGenerationRepository( $gateway, $tables, $dates );
		$tags       = new WpdbTagRepository( $gateway, $tables, $dates );
		$events     = new WpdbEventRepository(
			$gateway,
			$tables,
			$dates,
			new DenyAllEventMetadataPolicy(),
			new BatchEventIdentityPolicy()
		);
		$scheduler  = new ActionSchedulerBatchGenerationScheduler();
		$insert_tag = new InsertGeneratedTag(
			new RandomTagIdGenerator( new PhpSecureRandomIntegerSource() ),
			$tags
		);
		$generate   = new GenerateBatchChunk(
			$generation,
			$insert_tag,
			$events,
			new WpdbTransactionManager( $wpdb ),
			$scheduler,
			new SystemClock()
		);

		( new BatchGenerationActionHandler( $generate, $scheduler ) )->register();
	}
}
