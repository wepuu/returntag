<?php
/**
 * ForgeTag theme bootstrap.
 *
 * @package ForgeTag
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'forge-tag', get_template_directory() . '/languages' );
		add_theme_support( 'editor-styles' );
		add_editor_style( 'assets/css/foundation.css' );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$theme = wp_get_theme();

		wp_enqueue_style(
			'forge-tag-foundation',
			get_theme_file_uri( 'assets/css/foundation.css' ),
			array(),
			(string) $theme->get( 'Version' )
		);
	}
);
