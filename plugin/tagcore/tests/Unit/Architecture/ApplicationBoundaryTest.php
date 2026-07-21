<?php
/**
 * Application layer dependency boundary tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Guards the Application layer from concrete platform dependencies.
 */
final class ApplicationBoundaryTest extends TestCase {
	/**
	 * Ensure Application PHP depends on contracts rather than WordPress APIs.
	 */
	public function test_application_php_does_not_call_platform_globals(): void {
		$application_dir = dirname( __DIR__, 3 ) . '/src/Application';
		$iterator        = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $application_dir, FilesystemIterator::SKIP_DOTS )
		);
		$php_files       = array();

		/**
		 * Current file from the recursive iterator.
		 *
		 * @var SplFileInfo $file
		 */
		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$php_files[] = $file->getPathname();
			}
		}

		foreach ( $php_files as $php_file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local Application source.
			$contents = file_get_contents( $php_file );
			self::assertIsString( $contents );

			foreach ( array( '$wpdb', 'wp_mail(', 'get_option(', 'update_option(', 'error_log(', 'wp_json_encode(', 'WC_Order' ) as $forbidden ) {
				self::assertStringNotContainsString( $forbidden, $contents, $php_file );
			}
		}

		self::assertNotEmpty( $php_files );
	}
}
