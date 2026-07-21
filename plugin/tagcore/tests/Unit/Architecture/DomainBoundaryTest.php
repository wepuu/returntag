<?php
/**
 * Guards the framework-independent Domain layer.
 *
 * @package ReturnTag\TagCore\Tests\Unit\Architecture
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Enforces the framework-independent Domain dependency boundary.
 */
final class DomainBoundaryTest extends TestCase {
	/**
	 * Ensure Domain PHP does not directly call platform integrations.
	 */
	public function test_domain_php_does_not_call_platform_globals(): void {
		$domain_dir = dirname( __DIR__, 3 ) . '/src/Domain';
		$iterator   = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $domain_dir, FilesystemIterator::SKIP_DOTS )
		);
		$php_files  = array();

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
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local Domain source.
			$contents = file_get_contents( $php_file );
			self::assertIsString( $contents );

			foreach ( array( '$wpdb', 'wp_mail(', 'get_option(', 'update_option(', 'WC_Order' ) as $forbidden ) {
				self::assertStringNotContainsString( $forbidden, $contents, $php_file );
			}
		}

		self::assertDirectoryExists( $domain_dir );
	}
}
