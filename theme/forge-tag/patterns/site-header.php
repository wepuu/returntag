<?php
/**
 * Title: ForgeTag site header
 * Slug: forge-tag/site-header
 * Categories: header
 * Block Types: core/template-part/header
 * Inserter: no
 *
 * @package ForgeTag
 */

declare(strict_types=1);
?>
<!-- wp:group {"align":"full","className":"forge-site-header","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull forge-site-header">
	<!-- wp:group {"align":"wide","className":"forge-site-header__inner","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group alignwide forge-site-header__inner">
		<!-- wp:html -->
		<a class="forge-site-header__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr_x( 'ForgeTag home', 'Header Logo link label', 'forge-tag' ); ?>"><img class="forge-tag-brand-mark" src="<?php echo esc_url( get_theme_file_uri( 'assets/images/forge-logo.png' ) ); ?>" alt="ForgeTag" width="150" height="29"></a>
		<!-- /wp:html -->

		<!-- wp:navigation {"ariaLabel":"Primary navigation","overlayMenu":"mobile","className":"forge-site-header__navigation","layout":{"type":"flex","justifyContent":"center"}} -->
			<!-- wp:navigation-link {"label":<?php echo wp_json_encode( _x( 'How it works', 'Header navigation label', 'forge-tag' ) ); ?>,"url":<?php echo wp_json_encode( home_url( '/#how-it-works' ) ); ?>,"kind":"custom"} /-->
			<!-- wp:navigation-link {"label":<?php echo wp_json_encode( _x( 'Products', 'Header navigation label', 'forge-tag' ) ); ?>,"url":<?php echo wp_json_encode( home_url( '/#products' ) ); ?>,"kind":"custom"} /-->
			<!-- wp:navigation-link {"label":<?php echo wp_json_encode( _x( 'Recovery', 'Header navigation label', 'forge-tag' ) ); ?>,"url":<?php echo wp_json_encode( home_url( '/#recovery' ) ); ?>,"kind":"custom"} /-->
		<!-- /wp:navigation -->

		<!-- wp:group {"className":"forge-site-header__actions","layout":{"type":"flex","flexWrap":"nowrap"}} -->
		<div class="wp-block-group forge-site-header__actions">
			<!-- wp:tagcore/tag-entry-link {"intent":"report","className":"is-style-secondary forge-site-header__report"} /-->
			<!-- wp:tagcore/tag-entry-link {"intent":"activate","className":"forge-site-header__activate"} /-->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
