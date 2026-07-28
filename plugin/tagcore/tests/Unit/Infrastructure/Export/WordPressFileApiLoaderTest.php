<?php
/**
 * WordPress file API loader tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Export;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Infrastructure\Export\WordPressFileApiLoader;

/**
 * Verifies that REST-like runtimes can load wp_tempnam().
 */
final class WordPressFileApiLoaderTest extends TestCase {
	/**
	 * Load the WordPress file API when wp-admin did not bootstrap it.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_loads_file_api_for_rest_runtime(): void {
		define( 'ABSPATH', dirname( __DIR__, 3 ) . '/Fixture/wordpress/' );

		self::assertFalse( function_exists( 'wp_tempnam' ) );

		( new WordPressFileApiLoader() )->ensure_loaded();

		self::assertTrue( function_exists( 'wp_tempnam' ) );
		$path = wp_tempnam( 'tagcore-batch-export.csv' );

		try {
			self::assertNotSame( '', $path );
			self::assertFileExists( $path );
		} finally {
			if ( '' !== $path && is_file( $path ) ) {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- WordPress is intentionally not loaded in this isolated regression fixture.
			}
		}
	}
}
