<?php
/**
 * TagCore administration composition root.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use ReturnTag\TagCore\Application\Batch\BatchEventIdentityPolicy;
use ReturnTag\TagCore\Application\Admin\AdminTagLifecycleEventIdentityPolicy;
use ReturnTag\TagCore\Application\Admin\AdminTagLifecycleEventMetadataPolicy;
use ReturnTag\TagCore\Application\Admin\AdminTagLifecyclePolicy;
use ReturnTag\TagCore\Application\Admin\ManageAdminTagLifecycle;
use ReturnTag\TagCore\Application\Batch\ChangeBatchLifecycle;
use ReturnTag\TagCore\Application\Batch\CreateBatch;
use ReturnTag\TagCore\Application\Batch\ExportBatchCsv;
use ReturnTag\TagCore\Application\Batch\GetBatch;
use ReturnTag\TagCore\Application\Batch\GetBatchGenerationProgress;
use ReturnTag\TagCore\Application\Batch\GetBatchLifecycle;
use ReturnTag\TagCore\Application\Batch\ListBatchTagInventory;
use ReturnTag\TagCore\Application\Batch\ListBatchExports;
use ReturnTag\TagCore\Application\Batch\ListBatches;
use ReturnTag\TagCore\Application\Batch\ReleaseBatch;
use ReturnTag\TagCore\Application\Batch\StartBatchGeneration;
use ReturnTag\TagCore\Application\Batch\SuspendBatch;
use ReturnTag\TagCore\Application\Batch\VoidBatch;
use ReturnTag\TagCore\Application\Persistence\DenyAllEventMetadataPolicy;
use ReturnTag\TagCore\Application\Tag\SearchTags;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Application\Tag\TagIdInputNormalizer;
use ReturnTag\TagCore\Application\Tag\TagSearchInputNormalizer;
use ReturnTag\TagCore\Domain\Batch\BatchLifecyclePolicy;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Export\TemporaryCsvBatchExportBuilder;
use ReturnTag\TagCore\Infrastructure\Export\WordPressPublicTagUrlBuilder;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchExportRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchExportSourceReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchExportWorkflowRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchGenerationRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchGenerationProgressReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchLifecycleRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchTagInventoryReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbEventRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTagSearchReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAdminOperationsReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAdminTagLifecycleStore;
use ReturnTag\TagCore\Infrastructure\FinderReport\AdminSensitivePreviewFactory;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerBatchGenerationScheduler;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerBatchGenerationMonitor;
use ReturnTag\TagCore\Infrastructure\SystemClock;
use ReturnTag\TagCore\Infrastructure\WordPressOptionFeatureFlagReader;
use ReturnTag\TagCore\Infrastructure\WordPress\CapabilityInstaller;
use wpdb;

/**
 * Wires Batch administration adapters through RT-208 to application services.
 */
final class AdminBootstrap {
	/**
	 * Register Batch administration hooks for the current WordPress site.
	 *
	 * @param string $plugin_file Absolute plugin bootstrap path.
	 */
	public static function register( string $plugin_file ): void {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			return;
		}

		$tables               = new TableNames( $wpdb->prefix );
		$gateway              = new WpdbGateway( $wpdb );
		$dates                = new DatabaseDateTimeCodec();
		$batches              = new WpdbBatchRepository( $gateway, $tables, $dates );
		$generation           = new WpdbBatchGenerationRepository( $gateway, $tables, $dates );
		$events               = new WpdbEventRepository(
			$gateway,
			$tables,
			$dates,
			new DenyAllEventMetadataPolicy(),
			new BatchEventIdentityPolicy()
		);
		$transactions         = new WpdbTransactionManager( $wpdb );
		$schema_state         = new SchemaState(
			new WordPressSchemaVersionStore(),
			( new MigrationRegistryFactory( $wpdb ) )->create()
		);
		$create               = new CreateBatch( $batches, $events, $transactions, new SystemClock() );
		$start                = new StartBatchGeneration(
			$generation,
			$events,
			$transactions,
			new ActionSchedulerBatchGenerationScheduler(),
			new SystemClock()
		);
		$list                 = new ListBatches( $batches );
		$get                  = new GetBatch( $batches );
		$get_progress         = new GetBatchGenerationProgress(
			new WpdbBatchGenerationProgressReader( $gateway, $tables, $dates ),
			new ActionSchedulerBatchGenerationMonitor()
		);
		$list_tags            = new ListBatchTagInventory(
			$batches,
			new WpdbBatchTagInventoryReader( $gateway, $tables, $dates )
		);
		$exports              = new WpdbBatchExportRepository( $gateway, $tables, $dates );
		$export               = new ExportBatchCsv(
			$batches,
			new WpdbBatchExportSourceReader( $gateway, $tables ),
			new TemporaryCsvBatchExportBuilder( new WordPressPublicTagUrlBuilder() ),
			new WpdbBatchExportWorkflowRepository( $gateway, $tables, $dates ),
			$exports,
			$events,
			$transactions,
			new SystemClock()
		);
		$list_exports         = new ListBatchExports( $batches, $exports );
		$lifecycle_repository = new WpdbBatchLifecycleRepository( $gateway, $tables, $dates );
		$feature_flags        = new WordPressOptionFeatureFlagReader();
		$lifecycle            = new ChangeBatchLifecycle(
			$lifecycle_repository,
			$events,
			$transactions,
			$feature_flags,
			new SystemClock(),
			new BatchLifecyclePolicy()
		);

		( new CapabilityInstaller( $plugin_file ) )->register_hooks();
		( new BatchAdminPage( dirname( $plugin_file ), $schema_state ) )->register_hooks();
		( new TagAdminPage( dirname( $plugin_file ), $schema_state ) )->register_hooks();
		( new OperationsAdminPage( dirname( $plugin_file ), $schema_state ) )->register_hooks();

		$controller = new BatchRestController(
			$create,
			$start,
			$get_progress,
			$list_tags,
			$export,
			$list_exports,
			$list,
			$get,
			new BatchTagInventoryCursorCodec(),
			new BatchExportCursorCodec(),
			$schema_state
		);
		add_action( 'rest_api_init', array( $controller, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $controller, 'apply_no_store_headers' ), 10, 3 );
		add_filter( 'rest_pre_serve_request', array( $controller, 'serve_csv_download' ), 10, 4 );

		$tag_search_controller = new TagSearchRestController(
			new SearchTags( new WpdbTagSearchReader( $gateway, $tables, $dates ) ),
			new TagSearchInputNormalizer( new TagIdInputNormalizer() ),
			new TagSearchCursorCodec(),
			$schema_state,
			$feature_flags,
			new TagActivationAvailabilityPolicy()
		);
		add_action( 'rest_api_init', array( $tag_search_controller, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $tag_search_controller, 'apply_no_store_headers' ), 10, 3 );

		$operations_controller = new AdminOperationsRestController(
			new WpdbAdminOperationsReader( $gateway, $tables, $wpdb->users ),
			new TagSearchInputNormalizer( new TagIdInputNormalizer() ),
			new AdminOperationsCursorCodec(),
			$schema_state,
			AdminSensitivePreviewFactory::create( $wpdb ),
			$feature_flags,
			new TagActivationAvailabilityPolicy()
		);
		add_action( 'rest_api_init', array( $operations_controller, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $operations_controller, 'apply_security_headers' ), 10, 3 );
		add_filter( 'rest_pre_serve_request', array( $operations_controller, 'serve_evidence' ), 10, 4 );

		$lifecycle_metadata         = new AdminTagLifecycleEventMetadataPolicy();
		$admin_lifecycle            = new ManageAdminTagLifecycle(
			new WpdbAdminTagLifecycleStore(
				$gateway,
				$tables,
				$wpdb->users,
				$dates,
				$transactions,
				new WpdbEventRepository(
					$gateway,
					$tables,
					$dates,
					$lifecycle_metadata,
					new AdminTagLifecycleEventIdentityPolicy()
				),
				$lifecycle_metadata,
				new AdminTagLifecyclePolicy()
			),
			$feature_flags,
			new SystemClock()
		);
		$admin_lifecycle_controller = new AdminTagLifecycleRestController(
			$admin_lifecycle,
			new TagIdInputNormalizer(),
			$schema_state
		);
		add_action( 'rest_api_init', array( $admin_lifecycle_controller, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $admin_lifecycle_controller, 'apply_security_headers' ), 10, 3 );

		$lifecycle_controller = new BatchLifecycleRestController(
			new GetBatchLifecycle( $lifecycle_repository, $feature_flags ),
			new ReleaseBatch( $lifecycle ),
			new SuspendBatch( $lifecycle ),
			new VoidBatch( $lifecycle ),
			$schema_state
		);
		add_action( 'rest_api_init', array( $lifecycle_controller, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $lifecycle_controller, 'apply_no_store_headers' ), 10, 3 );
	}
}
