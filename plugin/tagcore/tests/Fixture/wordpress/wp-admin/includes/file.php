<?php
/**
 * Minimal WordPress file API fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

if ( ! function_exists( 'wp_tempnam' ) ) {
	/**
	 * Create a private temporary test file.
	 *
	 * @param string $filename Filename hint.
	 * @param string $dir Explicit temporary directory.
	 */
	function wp_tempnam( string $filename = '', string $dir = '' ): string {
		unset( $filename );
		$path = tempnam( '' === $dir ? sys_get_temp_dir() : $dir, 'returntag-test-' );

		return false === $path ? '' : $path;
	}
}
