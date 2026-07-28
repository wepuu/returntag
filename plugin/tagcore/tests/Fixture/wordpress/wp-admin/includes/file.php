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
	 */
	function wp_tempnam( string $filename = '' ): string {
		unset( $filename );
		$path = tempnam( sys_get_temp_dir(), 'returntag-test-' );

		return false === $path ? '' : $path;
	}
}
