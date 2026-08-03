<?php
/**
 * Title: Two recovery paths
 * Slug: forge-tag/home-recovery-paths
 * Categories: featured
 * Inserter: no
 *
 * @package ForgeTag
 */

declare(strict_types=1);
?>
<!-- wp:group {"tagName":"section","anchor":"recovery","align":"full","className":"forge-home-section forge-home-recovery","layout":{"type":"constrained"}} -->
<section id="recovery" class="wp-block-group alignfull forge-home-section forge-home-recovery">
	<!-- wp:group {"align":"wide","className":"forge-home-recovery__inner","layout":{"type":"grid","columnCount":2,"minimumColumnWidth":null}} -->
	<div class="wp-block-group alignwide forge-home-recovery__inner">
		<!-- wp:group {"className":"forge-home-recovery__intro","layout":{"type":"constrained"}} -->
		<div class="wp-block-group forge-home-recovery__intro">
			<!-- wp:paragraph {"className":"forge-home-eyebrow"} -->
			<p class="forge-home-eyebrow"><?php echo esc_html_x( 'Two independent systems', 'Homepage recovery eyebrow', 'forge-tag' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"className":"forge-home-section-title"} -->
			<h2 class="wp-block-heading forge-home-section-title"><?php echo esc_html_x( 'Two ways to help find what matters.', 'Homepage recovery heading', 'forge-tag' ); ?></h2>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"forge-home-section-summary"} -->
			<p class="forge-home-section-summary"><?php echo esc_html_x( 'A compatible smart finding network and ForgeTag QR recovery can support the same item without sharing account, device, pairing, battery, or location data.', 'Homepage recovery introduction', 'forge-tag' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"forge-home-recovery__paths","layout":{"type":"constrained"}} -->
		<div class="wp-block-group forge-home-recovery__paths">
			<!-- wp:group {"className":"forge-home-recovery__path forge-tag-card","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
			<div class="wp-block-group forge-home-recovery__path forge-tag-card">
				<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"forge-home-icon"} -->
				<figure class="wp-block-image size-full forge-home-icon"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/smartphone.svg' ) ); ?>" alt="" width="48" height="48"></figure>
				<!-- /wp:image -->
				<!-- wp:group {"layout":{"type":"constrained"}} -->
				<div class="wp-block-group">
					<!-- wp:heading {"level":3} -->
					<h3 class="wp-block-heading"><?php echo esc_html_x( 'Compatible smart finding network', 'Homepage recovery path', 'forge-tag' ); ?></h3>
					<!-- /wp:heading -->
					<!-- wp:paragraph -->
					<p><?php echo esc_html_x( 'Use the compatible network’s own app and guidance. ForgeTag does not verify pairing or read network data.', 'Homepage recovery path description', 'forge-tag' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"forge-home-recovery__path forge-tag-card","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
			<div class="wp-block-group forge-home-recovery__path forge-tag-card">
				<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"forge-home-icon"} -->
				<figure class="wp-block-image size-full forge-home-icon"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/qr-code.svg' ) ); ?>" alt="" width="48" height="48"></figure>
				<!-- /wp:image -->
				<!-- wp:group {"layout":{"type":"constrained"}} -->
				<div class="wp-block-group">
					<!-- wp:heading {"level":3} -->
					<h3 class="wp-block-heading"><?php echo esc_html_x( 'ForgeTag QR recovery', 'Homepage recovery path', 'forge-tag' ); ?></h3>
					<!-- /wp:heading -->
					<!-- wp:paragraph -->
					<p><?php echo esc_html_x( 'A finder scans the printed QR code to open the private recovery experience. No ForgeTag app is required.', 'Homepage recovery path description', 'forge-tag' ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
