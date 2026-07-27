<?php
/**
 * Repository query-plan integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use WP_UnitTestCase;
use wpdb;

/**
 * Verifies that bounded Repository query shapes expose their intended indexes.
 */
final class RepositoryQueryPlanTest extends WP_UnitTestCase {
	/**
	 * Build a clean Schema version 8 before every test.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->clear_schema( $wpdb );

		$registry = ( new MigrationRegistryFactory( $wpdb ) )->create();
		$runner   = new MigrationRunner(
			$registry,
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $wpdb, get_current_blog_id(), 0 )
		);

		self::assertSame( 8, $runner->migrate()->ending_version );
	}

	/**
	 * Remove isolated query-plan fixtures after every test.
	 */
	protected function tearDown(): void {
		global $wpdb;

		$this->clear_schema( $wpdb );
		parent::tearDown();
	}

	/**
	 * Every primary bounded list shape must expose its catalogued candidate index.
	 */
	public function test_repository_list_queries_expose_expected_candidate_indexes(): void {
		global $wpdb;

		$tables = new TableNames( $wpdb->prefix );

		$this->assert_possible_index(
			$wpdb,
			$wpdb->prepare(
				'SELECT batch_id, batch_code, tag_type, model_code, requested_quantity, generated_quantity, batch_status, activation_enabled, created_at FROM %i WHERE batch_id < %d ORDER BY batch_id DESC LIMIT %d',
				$tables->batches(),
				100,
				51
			),
			'PRIMARY'
		);
		$this->assert_possible_index(
			$wpdb,
			$wpdb->prepare(
				'SELECT * FROM %i WHERE batch_id = %d ORDER BY tag_status ASC, tag_id ASC LIMIT %d',
				$tables->tags(),
				1,
				51
			),
			'batch_id_status'
		);
		$this->assert_possible_index(
			$wpdb,
			$wpdb->prepare(
				'SELECT * FROM %i WHERE owner_id = %d ORDER BY tag_status ASC, tag_id ASC LIMIT %d',
				$tables->tags(),
				1,
				51
			),
			'owner_id_status'
		);
		$this->assert_possible_index(
			$wpdb,
			$wpdb->prepare(
				'SELECT * FROM %i WHERE batch_id = %d ORDER BY export_version DESC LIMIT %d',
				$tables->batch_exports(),
				1,
				51
			),
			'batch_export_version_unique'
		);
		$this->assert_possible_index(
			$wpdb,
			$wpdb->prepare(
				'SELECT * FROM %i WHERE purpose = %s AND email_lookup = %s ORDER BY created_at DESC, challenge_id DESC LIMIT 1',
				$tables->auth_challenges(),
				'owner_login',
				str_repeat( 'a', 64 )
			),
			'purpose_email_created_at'
		);
		$this->assert_possible_index(
			$wpdb,
			$wpdb->prepare(
				'SELECT * FROM %i WHERE conversation_id = %d ORDER BY message_id ASC LIMIT %d',
				$tables->messages(),
				1,
				51
			),
			'conversation_message'
		);
		$this->assert_possible_index(
			$wpdb,
			$wpdb->prepare(
				'SELECT * FROM %i WHERE target_type = %s AND target_id = %s ORDER BY created_at DESC, event_id DESC LIMIT %d',
				$tables->events(),
				'tag',
				'N7R2W9',
				51
			),
			'target_type_target_id_created_at'
		);
		$this->assert_possible_index(
			$wpdb,
			$wpdb->prepare(
				'SELECT * FROM %i WHERE correlation_id = %s ORDER BY event_id DESC LIMIT %d',
				$tables->events(),
				'rt110-query',
				51
			),
			'correlation_id'
		);
	}

	/**
	 * Assert that EXPLAIN reports one approved candidate without fixing a full plan.
	 *
	 * @param wpdb   $database WordPress database adapter.
	 * @param string $query Prepared SELECT query.
	 * @param string $expected_index Expected candidate index.
	 */
	private function assert_possible_index( wpdb $database, string $query, string $expected_index ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated EXPLAIN over a prepared query.
		$plan = $database->get_results( "EXPLAIN {$query}", ARRAY_A );

		self::assertNotEmpty( $plan );
		$possible_keys = $plan[0]['possible_keys'] ?? null;
		self::assertIsString( $possible_keys );
		self::assertContains( $expected_index, explode( ',', $possible_keys ) );
	}

	/**
	 * Remove only trusted ReturnTag tables from the isolated test database.
	 *
	 * @param wpdb $database WordPress database adapter.
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
