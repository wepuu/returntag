<?php
/**
 * Schema inspection failure integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Infrastructure\Migration\MigrationException;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaInspector;
use WP_UnitTestCase;
use wpdb;

/**
 * Verifies that metadata-query failures cannot be classified as absent tables.
 */
final class SchemaInspectorFailureTest extends WP_UnitTestCase {
	/**
	 * A failed database-name query must stop before table creation is considered.
	 */
	public function test_database_name_query_failure_fails_closed(): void {
		$database = $this->getMockBuilder( wpdb::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_var' ) )
			->getMock();

		$database->method( 'get_var' )->willReturnCallback(
			static function () use ( $database ): null {
				$database->last_error = 'Synthetic schema inspection failure.';

				return null;
			}
		);

		$inspector = new WordPressSchemaInspector( $database );

		$this->expectException( MigrationException::class );
		$this->expectExceptionMessage( 'Schema inspection failed.' );

		$inspector->inspect_table( 'safe_returntag_table', 'InnoDB', '', array(), array() );
	}

	/**
	 * A failed table metadata query must not masquerade as an absent table.
	 */
	public function test_table_query_failure_is_not_classified_as_absent(): void {
		$database = $this->getMockBuilder( wpdb::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_var', 'get_row', 'prepare' ) )
			->getMock();

		$database->method( 'get_var' )->willReturn( 'wordpress_test' );
		$database->method( 'prepare' )->willReturn( 'SELECT synthetic_table_metadata' );
		$database->method( 'get_row' )->willReturnCallback(
			static function () use ( $database ): null {
				$database->last_error = 'Synthetic information_schema failure.';

				return null;
			}
		);

		$inspector = new WordPressSchemaInspector( $database );

		$this->expectException( MigrationException::class );
		$this->expectExceptionMessage( 'Schema inspection failed.' );

		$inspector->inspect_table( 'safe_returntag_table', 'InnoDB', '', array(), array() );
	}
}
