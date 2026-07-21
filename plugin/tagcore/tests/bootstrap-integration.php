<?php
/**
 * Bootstrap the WordPress integration test suite and load TagCore.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

$returntag_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( false === $returntag_tests_dir || '' === $returntag_tests_dir ) {
	throw new RuntimeException( 'WP_TESTS_DIR must point to the WordPress PHPUnit test library.' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once $returntag_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__ ) . '/tagcore.php';
	}
);

require_once $returntag_tests_dir . '/includes/bootstrap.php';
