<?php
/**
 * Unit tests for trusted ReturnTag table names.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Migration;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Verifies active WordPress prefixes are preserved exactly.
 */
final class TableNamesTest extends TestCase {
	/**
	 * A non-default WordPress prefix must determine the physical table name.
	 */
	public function test_batches_table_uses_supplied_wordpress_prefix(): void {

		$table_names = new TableNames( 'rt_test_' );

		self::assertSame( 'rt_test_returntag_batches', $table_names->batches() );
	}

	/**
	 * The same non-default prefix must determine the physical tags table.
	 */
	public function test_tags_table_uses_supplied_wordpress_prefix(): void {
		$table_names = new TableNames( 'rt_test_' );

		self::assertSame( 'rt_test_returntag_tags', $table_names->tags() );
	}

	/**
	 * The same non-default prefix must determine the batch exports table.
	 */
	public function test_batch_exports_table_uses_supplied_wordpress_prefix(): void {

		$table_names = new TableNames( 'rt_test_' );

		self::assertSame( 'rt_test_returntag_batch_exports', $table_names->batch_exports() );
	}
}
