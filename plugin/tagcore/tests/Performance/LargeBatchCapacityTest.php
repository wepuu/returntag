<?php
/**
 * RT-210 large Batch capacity acceptance.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Performance;

use ReturnTag\TagCore\Admin\Capability;
use ReturnTag\TagCore\Application\Batch\BatchTagInventoryCursor;
use ReturnTag\TagCore\Application\Batch\CreateBatchInput;
use ReturnTag\TagCore\Application\Batch\PublicTagUrlBuilder;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Tag\TagSearchCriteria;
use ReturnTag\TagCore\Application\Tag\TagSearchCursor;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Infrastructure\Export\TemporaryCsvBatchExportBuilder;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchExportSourceReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchGenerationProgressReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchLifecycleRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchTagInventoryReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTagSearchReader;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerBatchGenerationScheduler;
use ReturnTag\TagCore\Infrastructure\WordPress\CapabilityInstaller;
use ReturnTag\TagCore\Tests\Performance\Support\SyntheticCapacityFixture;
use RuntimeException;
use WP_REST_Request;
use WP_UnitTestCase;
use wpdb;

/**
 * Runs only through the dedicated performance configuration on the default wp-env.
 */
final class LargeBatchCapacityTest extends WP_UnitTestCase {
	private const BATCH_COUNT           = 10;
	private const QUERY_REPETITIONS     = 20;
	private const QUERY_P95_SECONDS     = 0.3;
	private const EXACT_TAG_P95_SECONDS = 0.2;
	private const CHUNK_P95_SECONDS     = 2.0;
	private const LIFECYCLE_P95_SECONDS = 2.0;
	private const EXPORT_SECONDS        = 90.0;
	private const EXPORT_MEMORY_BYTES   = 134217728;
	private const QUEUE_SMOKE_QUANTITY  = 10000;

	/**
	 * Authorized administrator fixture.
	 *
	 * @var int
	 */
	private int $administrator_id;

	/**
	 * Build a clean current Schema and administrator for each capacity scenario.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->clear_schema( $wpdb );

		$runner = new MigrationRunner(
			( new MigrationRegistryFactory( $wpdb ) )->create(),
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $wpdb, get_current_blog_id(), 0 )
		);
		self::assertSame( 13, $runner->migrate()->ending_version );

		$this->administrator_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->administrator_id );
		( new CapabilityInstaller( RETURNTAG_TAGCORE_FILE ) )->install();
		rest_get_server();
	}

	/**
	 * Remove only isolated test records and pending generation actions.
	 */
	protected function tearDown(): void {
		global $wpdb;

		$this->clear_generation_actions();
		$this->clear_schema( $wpdb );
		$role = get_role( 'administrator' );

		if ( null !== $role ) {
			$role->remove_cap( Capability::MANAGE_RETURNTAG );
			$role->remove_cap( Capability::MANAGE_BATCHES );
			$role->remove_cap( Capability::MANAGE_TAGS );
		}

		delete_option( CapabilityInstaller::OPTION_NAME );
		parent::tearDown();
	}

	/**
	 * Ten thousand Tags traverse the real queue handler in bounded 100-Tag chunks.
	 */
	public function test_generation_queue_handles_large_smoke_batch(): void {
		global $wpdb;

		$create = new WP_REST_Request( 'POST', '/tagcore/v1/batches' );
		$create->set_body_params(
			array(
				'batch_code'         => 'RT-210-QUEUE-SMOKE',
				'tag_type'           => 'classic_tag',
				'model_code'         => 'RT210-CAPACITY',
				'smart_network'      => 'none',
				'requested_quantity' => self::QUEUE_SMOKE_QUANTITY,
				'manufacturer'       => null,
				'sales_channel'      => 'direct',
				'notes'              => null,
			)
		);
		$created_response = rest_do_request( $create );
		$created          = $created_response->get_data();

		self::assertSame( 201, $created_response->get_status() );
		self::assertIsArray( $created );
		$batch_id = (int) $created['batch_id'];

		$start = new WP_REST_Request(
			'POST',
			'/tagcore/v1/batches/' . $batch_id . '/generation'
		);
		self::assertSame( 202, rest_do_request( $start )->get_status() );

		$durations = array();

		for ( $checkpoint = 0; $checkpoint < self::QUEUE_SMOKE_QUANTITY; $checkpoint += 100 ) {
			as_unschedule_all_actions(
				ActionSchedulerBatchGenerationScheduler::HOOK,
				array(
					'batch_id'      => $batch_id,
					'checkpoint'    => $checkpoint,
					'retry_attempt' => 0,
				),
				ActionSchedulerBatchGenerationScheduler::GROUP
			);
			$started = hrtime( true );
			do_action(
				ActionSchedulerBatchGenerationScheduler::HOOK,
				$batch_id,
				$checkpoint,
				0
			);
			$durations[] = ( hrtime( true ) - $started ) / 1_000_000_000;
		}

		$tables = new TableNames( $wpdb->prefix );
		$query  = $wpdb->prepare(
			'SELECT batch_status, generated_quantity FROM %i WHERE batch_id = %d',
			$tables->batches(),
			$batch_id
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Isolated capacity verification.
		$stored = $wpdb->get_row( $query, ARRAY_A );

		self::assertIsArray( $stored );
		self::assertSame( 'generated', $stored['batch_status'] );
		self::assertSame( (string) self::QUEUE_SMOKE_QUANTITY, (string) $stored['generated_quantity'] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Isolated capacity verification.
		self::assertSame(
			(string) self::QUEUE_SMOKE_QUANTITY,
			(string) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE batch_id = %d',
					$tables->tags(),
					$batch_id
				)
			)
		);
		$chunk_p95 = $this->percentile_95( $durations );
		$this->announce_metrics(
			'generation',
			array(
				'quantity'          => self::QUEUE_SMOKE_QUANTITY,
				'chunk_size'        => 100,
				'chunk_p95_seconds' => $chunk_p95,
			)
		);
		self::assertLessThanOrEqual( self::CHUNK_P95_SECONDS, $chunk_p95 );
	}

	/**
	 * One million Tags keep all approved read and export paths bounded.
	 */
	public function test_million_tag_read_and_export_profile(): void {
		global $wpdb;

		$tables  = new TableNames( $wpdb->prefix );
		$fixture = new SyntheticCapacityFixture( $wpdb, $tables );
		$batches = $fixture->create_dataset( self::BATCH_COUNT, CreateBatchInput::MAX_REQUESTED_QUANTITY );
		$target  = $batches[0];
		$gateway = new WpdbGateway( $wpdb );
		$dates   = new DatabaseDateTimeCodec();

		$inventory           = new WpdbBatchTagInventoryReader( $gateway, $tables, $dates );
		$mid_id              = TagId::from_canonical( $fixture->tag_id( 500000 ) );
		$inventory_first_p95 = $this->measure_p95(
			static fn() => $inventory->list_for_batch( $target, null, new PageSize() )
		);
		$inventory_next_p95  = $this->measure_p95(
			static fn() => $inventory->list_for_batch(
				$target,
				new BatchTagInventoryCursor( $mid_id ),
				new PageSize()
			)
		);

		self::assertLessThanOrEqual( self::QUERY_P95_SECONDS, $inventory_first_p95 );
		self::assertLessThanOrEqual( self::QUERY_P95_SECONDS, $inventory_next_p95 );

		$search   = new WpdbTagSearchReader( $gateway, $tables, $dates );
		$criteria = TagSearchCriteria::for_batch( 'RT-210-CAPACITY-01', null );
		$tag_id   = TagId::from_canonical( $fixture->tag_id( 750000 ) );

		$exact_tag_p95          = $this->measure_p95(
			static fn() => $search->search(
				TagSearchCriteria::for_tag_id( $tag_id ),
				null,
				new PageSize()
			)
		);
		$batch_search_first_p95 = $this->measure_p95(
			static fn() => $search->search( $criteria, null, new PageSize() )
		);
		$batch_search_next_p95  = $this->measure_p95(
			static fn() => $search->search(
				$criteria,
				new TagSearchCursor( $mid_id ),
				new PageSize()
			)
		);

		$progress     = new WpdbBatchGenerationProgressReader( $gateway, $tables, $dates );
		$progress_p95 = $this->measure_p95(
			static fn() => $progress->find( $target )
		);

		$lifecycle     = new WpdbBatchLifecycleRepository( $gateway, $tables, $dates );
		$lifecycle_p95 = $this->measure_p95(
			static fn() => $lifecycle->count_tags_by_status( $target )
		);
		$counts        = $lifecycle->count_tags_by_status( $target );
		self::assertSame(
			CreateBatchInput::MAX_REQUESTED_QUANTITY,
			$counts->total
		);

		$this->assert_capacity_explain( $wpdb, $tables, $target, $mid_id );
		$export = $this->assert_export_budget( $gateway, $tables, $dates, $target );

		$this->announce_metrics(
			'reads-and-export',
			array(
				'total_tags'                     => self::BATCH_COUNT * CreateBatchInput::MAX_REQUESTED_QUANTITY,
				'tags_per_batch'                 => CreateBatchInput::MAX_REQUESTED_QUANTITY,
				'inventory_first_p95_seconds'    => $inventory_first_p95,
				'inventory_next_p95_seconds'     => $inventory_next_p95,
				'exact_tag_p95_seconds'          => $exact_tag_p95,
				'batch_search_first_p95_seconds' => $batch_search_first_p95,
				'batch_search_next_p95_seconds'  => $batch_search_next_p95,
				'progress_p95_seconds'           => $progress_p95,
				'lifecycle_count_p95_seconds'    => $lifecycle_p95,
				'export_seconds'                 => $export['seconds'],
				'export_memory_bytes'            => $export['memory_bytes'],
			)
		);

		self::assertLessThanOrEqual( self::EXACT_TAG_P95_SECONDS, $exact_tag_p95 );
		self::assertLessThanOrEqual( self::QUERY_P95_SECONDS, $batch_search_first_p95 );
		self::assertLessThanOrEqual( self::QUERY_P95_SECONDS, $batch_search_next_p95 );
		self::assertLessThanOrEqual( self::EXACT_TAG_P95_SECONDS, $progress_p95 );
		self::assertLessThanOrEqual( self::LIFECYCLE_P95_SECONDS, $lifecycle_p95 );
	}

	/**
	 * Build the complete deterministic export without buffering it in memory.
	 *
	 * @param WpdbGateway           $gateway Database gateway.
	 * @param TableNames            $tables Trusted table names.
	 * @param DatabaseDateTimeCodec $dates UTC codec.
	 * @param int                   $batch_id Target Batch.
	 * @return array{seconds: float, memory_bytes: int} Capacity measurements.
	 */
	private function assert_export_budget(
		WpdbGateway $gateway,
		TableNames $tables,
		DatabaseDateTimeCodec $dates,
		int $batch_id
	): array {
		$batch = ( new WpdbBatchRepository( $gateway, $tables, $dates ) )->find_by_id( $batch_id );
		self::assertNotNull( $batch );

		$source  = new WpdbBatchExportSourceReader( $gateway, $tables );
		$urls    = new class() implements PublicTagUrlBuilder {
			/**
			 * Return one stable HTTPS fixture URL.
			 *
			 * @param TagId $tag_id Public fixture Tag ID.
			 */
			public function for_tag( TagId $tag_id ): string {
				return 'https://example.test/t/' . $tag_id->value;
			}
		};
		$builder = new TemporaryCsvBatchExportBuilder( $urls );

		memory_reset_peak_usage();
		$baseline = memory_get_usage( true );
		$started  = hrtime( true );
		$artifact = $builder->build( $batch, $source->iterate_for_batch( $batch_id ) );
		$duration = ( hrtime( true ) - $started ) / 1_000_000_000;
		$memory   = max( 0, memory_get_peak_usage( true ) - $baseline );

		try {
			self::assertSame( CreateBatchInput::MAX_REQUESTED_QUANTITY, $artifact->row_count() );
			self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/D', $artifact->checksum() );
			self::assertGreaterThan( 0, $artifact->byte_size() );
			self::assertLessThanOrEqual( self::EXPORT_SECONDS, $duration );
			self::assertLessThanOrEqual( self::EXPORT_MEMORY_BYTES, $memory );
		} finally {
			$artifact->cleanup();
		}

		return array(
			'seconds'      => $duration,
			'memory_bytes' => $memory,
		);
	}

	/**
	 * Verify that both hot Batch Tag query shapes expose indexed candidates.
	 *
	 * @param wpdb       $database Active isolated database.
	 * @param TableNames $tables Trusted table names.
	 * @param int        $batch_id Target Batch.
	 * @param TagId      $cursor Midpoint cursor.
	 * @throws RuntimeException When EXPLAIN cannot be prepared.
	 */
	private function assert_capacity_explain(
		wpdb $database,
		TableNames $tables,
		int $batch_id,
		TagId $cursor
	): void {
		$queries = array(
			$database->prepare(
				'EXPLAIN SELECT tag_id, tag_status, created_at FROM %i WHERE batch_id = %d ORDER BY tag_id ASC LIMIT %d',
				$tables->tags(),
				$batch_id,
				51
			),
			$database->prepare(
				'EXPLAIN SELECT tag_id, tag_status, created_at FROM %i WHERE batch_id = %d AND tag_id > %s ORDER BY tag_id ASC LIMIT %d',
				$tables->tags(),
				$batch_id,
				$cursor->value,
				51
			),
		);

		foreach ( $queries as $query ) {
			if ( ! is_string( $query ) ) {
				throw new RuntimeException( 'Capacity EXPLAIN could not be prepared.' );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Prepared isolated capacity EXPLAIN.
			$plan = $database->get_row( $query, ARRAY_A );
			self::assertIsArray( $plan );
			self::assertIsString( $plan['possible_keys'] ?? null );
			self::assertNotSame( '', $plan['possible_keys'] );
		}
	}

	/**
	 * Measure a warm query repeatedly and return the nearest-rank p95.
	 *
	 * @param callable(): mixed $operation Read operation.
	 */
	private function measure_p95( callable $operation ): float {
		$operation();
		$durations = array();

		for ( $run = 0; $run < self::QUERY_REPETITIONS; ++$run ) {
			$started = hrtime( true );
			$operation();
			$durations[] = ( hrtime( true ) - $started ) / 1_000_000_000;
		}

		return $this->percentile_95( $durations );
	}

	/**
	 * Return the nearest-rank 95th percentile.
	 *
	 * @param array $values Measurements in seconds.
	 * @phpstan-param list<float> $values
	 * @throws RuntimeException When no measurement is available.
	 */
	private function percentile_95( array $values ): float {
		if ( array() === $values ) {
			throw new RuntimeException( 'Capacity measurements are unavailable.' );
		}

		sort( $values, SORT_NUMERIC );
		$index = (int) ceil( 0.95 * count( $values ) ) - 1;

		return $values[ max( 0, $index ) ];
	}

	/**
	 * Print privacy-safe numeric evidence for the capacity report.
	 *
	 * @param string $profile Stable profile name.
	 * @param array  $metrics Numeric capacity measurements.
	 * @phpstan-param array<string, float|int> $metrics
	 */
	private function announce_metrics( string $profile, array $metrics ): void {
		$encoded = wp_json_encode(
			array(
				'profile' => $profile,
				'metrics' => $metrics,
			),
			JSON_UNESCAPED_SLASHES
		);

		self::assertIsString( $encoded );
		fwrite( STDOUT, "\nRT-210 capacity: {$encoded}\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Dedicated test evidence written only to the test process output.
	}

	/**
	 * Remove only pending RT-204 actions from the isolated site.
	 */
	private function clear_generation_actions(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions(
				ActionSchedulerBatchGenerationScheduler::HOOK,
				array(),
				ActionSchedulerBatchGenerationScheduler::GROUP
			);
		}
	}

	/**
	 * Remove only trusted ReturnTag tables and Schema state.
	 *
	 * @param wpdb $database Active isolated database.
	 */
	private function clear_schema( wpdb $database ): void {
		$names = new TableNames( $database->prefix );

		foreach ( array( $names->events(), $names->access_tokens(), $names->messages(), $names->conversations(), $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated test cleanup with trusted identifiers.
			$database->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
