<?php
/**
 * Title: How ForgeTag works
 * Slug: forge-tag/home-process
 * Categories: featured
 * Inserter: no
 *
 * @package ForgeTag
 */

declare(strict_types=1);
?>
<!-- wp:group {"tagName":"section","anchor":"how-it-works","align":"full","className":"forge-home-process","layout":{"type":"constrained"}} -->
<section id="how-it-works" class="wp-block-group alignfull forge-home-process">
	<!-- wp:group {"align":"wide","className":"forge-home-process__panel forge-tag-card","layout":{"type":"constrained"}} -->
	<div class="wp-block-group alignwide forge-home-process__panel forge-tag-card">
		<!-- wp:heading {"textAlign":"center","className":"forge-home-section-title"} -->
		<h2 class="wp-block-heading has-text-align-center forge-home-section-title"><?php echo esc_html_x( 'How ForgeTag works', 'Homepage process heading', 'forge-tag' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:group {"align":"wide","className":"forge-return-route","layout":{"type":"grid","columnCount":3,"minimumColumnWidth":null}} -->
		<div class="wp-block-group alignwide forge-return-route">
			<!-- wp:group {"className":"forge-return-route__step","layout":{"type":"constrained"}} -->
			<div class="wp-block-group forge-return-route__step">
				<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"forge-home-icon"} -->
				<figure class="wp-block-image size-full forge-home-icon"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/package.svg' ) ); ?>" alt="" width="48" height="48"></figure>
				<!-- /wp:image -->
				<!-- wp:paragraph {"className":"forge-return-route__number"} -->
				<p class="forge-return-route__number" aria-hidden="true">1</p>
				<!-- /wp:paragraph -->
				<!-- wp:heading {"level":3} -->
				<h3 class="wp-block-heading"><?php echo esc_html_x( 'Activate your tag', 'Homepage process step', 'forge-tag' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph {"className":"forge-return-route__description"} -->
				<p class="forge-return-route__description"><?php echo esc_html_x( 'Enter the six-character Tag ID printed on your ForgeTag to begin activation.', 'Homepage process description', 'forge-tag' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"forge-return-route__step","layout":{"type":"constrained"}} -->
			<div class="wp-block-group forge-return-route__step">
				<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"forge-home-icon"} -->
				<figure class="wp-block-image size-full forge-home-icon"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/qr-code.svg' ) ); ?>" alt="" width="48" height="48"></figure>
				<!-- /wp:image -->
				<!-- wp:paragraph {"className":"forge-return-route__number"} -->
				<p class="forge-return-route__number" aria-hidden="true">2</p>
				<!-- /wp:paragraph -->
				<!-- wp:heading {"level":3} -->
				<h3 class="wp-block-heading"><?php echo esc_html_x( 'A finder scans the QR code', 'Homepage process step', 'forge-tag' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph {"className":"forge-return-route__description"} -->
				<p class="forge-return-route__description"><?php echo esc_html_x( 'Their phone browser opens the ForgeTag recovery page. No ForgeTag app is required.', 'Homepage process description', 'forge-tag' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"forge-return-route__step","layout":{"type":"constrained"}} -->
			<div class="wp-block-group forge-return-route__step">
				<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"forge-home-icon"} -->
				<figure class="wp-block-image size-full forge-home-icon"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/mail-check.svg' ) ); ?>" alt="" width="48" height="48"></figure>
				<!-- /wp:image -->
				<!-- wp:paragraph {"className":"forge-return-route__number"} -->
				<p class="forge-return-route__number" aria-hidden="true">3</p>
				<!-- /wp:paragraph -->
				<!-- wp:heading {"level":3} -->
				<h3 class="wp-block-heading"><?php echo esc_html_x( 'ForgeTag provides a private contact channel', 'Homepage process step', 'forge-tag' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph {"className":"forge-return-route__description"} -->
				<p class="forge-return-route__description"><?php echo esc_html_x( 'Verified messages can be relayed without showing either person’s email address.', 'Homepage process description', 'forge-tag' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
