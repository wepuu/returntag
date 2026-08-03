<?php
/**
 * Title: ForgeTag site footer
 * Slug: forge-tag/site-footer
 * Categories: footer
 * Block Types: core/template-part/footer
 * Inserter: no
 *
 * @package ForgeTag
 */

declare(strict_types=1);
?>
<!-- wp:group {"align":"full","className":"forge-site-footer","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull forge-site-footer">
	<!-- wp:group {"align":"wide","className":"forge-site-footer__inner","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"top"}} -->
	<div class="wp-block-group alignwide forge-site-footer__inner">
		<!-- wp:group {"className":"forge-site-footer__brand","layout":{"type":"constrained"}} -->
		<div class="wp-block-group forge-site-footer__brand">
			<!-- wp:paragraph {"className":"forge-site-footer__wordmark"} -->
			<p class="forge-site-footer__wordmark">ForgeTag</p>
			<!-- /wp:paragraph -->
			<!-- wp:paragraph {"className":"forge-site-footer__tagline"} -->
			<p class="forge-site-footer__tagline"><?php echo esc_html_x( 'Private QR recovery for everyday belongings.', 'Footer brand description', 'forge-tag' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:navigation {"ariaLabel":"Footer navigation","overlayMenu":"never","className":"forge-site-footer__navigation","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"right"}} -->
			<!-- wp:navigation-link {"label":<?php echo wp_json_encode( _x( 'How it works', 'Footer navigation label', 'forge-tag' ) ); ?>,"url":<?php echo wp_json_encode( home_url( '/#how-it-works' ) ); ?>,"kind":"custom"} /-->
			<!-- wp:navigation-link {"label":<?php echo wp_json_encode( _x( 'Products', 'Footer navigation label', 'forge-tag' ) ); ?>,"url":<?php echo wp_json_encode( home_url( '/#products' ) ); ?>,"kind":"custom"} /-->
			<!-- wp:navigation-link {"label":<?php echo wp_json_encode( _x( 'Recovery', 'Footer navigation label', 'forge-tag' ) ); ?>,"url":<?php echo wp_json_encode( home_url( '/#recovery' ) ); ?>,"kind":"custom"} /-->
			<!-- wp:navigation-link {"label":<?php echo wp_json_encode( _x( 'Privacy', 'Footer navigation label', 'forge-tag' ) ); ?>,"url":<?php echo wp_json_encode( home_url( '/#privacy' ) ); ?>,"kind":"custom"} /-->
		<!-- /wp:navigation -->
	</div>
	<!-- /wp:group -->

	<!-- wp:paragraph {"align":"wide","className":"forge-site-footer__note"} -->
	<p class="alignwide forge-site-footer__note"><?php echo esc_html_x( 'ForgeTag QR recovery works independently from compatible smart finding networks.', 'Footer system boundary note', 'forge-tag' ); ?></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
