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
		add_editor_style(
			array(
				'assets/css/foundation.css',
				'assets/css/home.css',
				'assets/css/commerce.css',
			)
		);
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

		$home_stylesheet_path    = get_theme_file_path( 'assets/css/home.css' );
		$home_stylesheet_version = is_file( $home_stylesheet_path )
			? (string) filemtime( $home_stylesheet_path )
			: (string) $theme->get( 'Version' );

		wp_enqueue_style(
			'forge-tag-home',
			get_theme_file_uri( 'assets/css/home.css' ),
			array( 'forge-tag-foundation' ),
			$home_stylesheet_version
		);

		$is_commerce_surface =
			( function_exists( 'is_woocommerce' ) && is_woocommerce() ) ||
			( function_exists( 'is_cart' ) && is_cart() ) ||
			( function_exists( 'is_checkout' ) && is_checkout() );

		if ( $is_commerce_surface ) {
			$commerce_stylesheet_path    = get_theme_file_path( 'assets/css/commerce.css' );
			$commerce_stylesheet_version = is_file( $commerce_stylesheet_path )
				? (string) filemtime( $commerce_stylesheet_path )
				: (string) $theme->get( 'Version' );

			wp_enqueue_style(
				'forge-tag-commerce',
				get_theme_file_uri( 'assets/css/commerce.css' ),
				array( 'forge-tag-home' ),
				$commerce_stylesheet_version
			);
		}
	}
);

add_filter(
	'document_title_parts',
	static function ( array $parts ): array {
		$site_title = _x( 'ForgeTag', 'Consumer-facing site title', 'forge-tag' );

		if ( is_front_page() ) {
			return array( 'title' => $site_title );
		}

		$parts['site'] = $site_title;

		return $parts;
	}
);
