<?php
/**
 * Title: ForgeTag recovery confidence
 * Slug: forge-tag/home-confidence
 * Categories: featured
 * Inserter: no
 *
 * @package ForgeTag
 */

declare(strict_types=1);
?>
<!-- wp:group {"tagName":"section","align":"full","className":"forge-home-section forge-home-confidence","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull forge-home-section forge-home-confidence">
	<!-- wp:group {"align":"wide","className":"forge-home-confidence__inner","layout":{"type":"constrained"}} -->
	<div class="wp-block-group alignwide forge-home-confidence__inner">
		<!-- wp:paragraph {"align":"center","className":"forge-home-eyebrow forge-home-confidence__eyebrow"} -->
		<p class="has-text-align-center forge-home-eyebrow forge-home-confidence__eyebrow"><?php echo esc_html_x( 'RECOVERY, WITHOUT PUBLIC CONTACT DETAILS', 'Homepage recovery-confidence eyebrow', 'forge-tag' ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:heading {"textAlign":"center","className":"forge-home-section-title forge-home-confidence__title"} -->
		<h2 class="wp-block-heading has-text-align-center forge-home-section-title forge-home-confidence__title"><?php echo esc_html_x( 'Private by design, clear in the moment', 'Homepage recovery-confidence heading', 'forge-tag' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:group {"className":"forge-home-confidence__grid","layout":{"type":"grid","columnCount":3,"minimumColumnWidth":null}} -->
		<div class="wp-block-group forge-home-confidence__grid">
			<!-- wp:group {"tagName":"article","className":"forge-home-confidence__card","layout":{"type":"constrained"}} -->
			<article class="wp-block-group forge-home-confidence__card">
				<img class="forge-home-confidence__icon" src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/qr-code.svg' ) ); ?>" alt="" width="48" height="48" aria-hidden="true">
				<!-- wp:heading {"level":3} -->
				<h3 class="wp-block-heading"><?php echo esc_html_x( 'A finder opens the recovery page', 'Homepage recovery-confidence card heading', 'forge-tag' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html_x( 'A QR scan or six-character Tag ID opens in a phone browser. No ForgeTag app is required.', 'Homepage recovery-confidence card copy', 'forge-tag' ); ?></p>
				<!-- /wp:paragraph -->
			</article>
			<!-- /wp:group -->

			<!-- wp:group {"tagName":"article","className":"forge-home-confidence__card","layout":{"type":"constrained"}} -->
			<article class="wp-block-group forge-home-confidence__card">
				<img class="forge-home-confidence__icon" src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/mail-check.svg' ) ); ?>" alt="" width="48" height="48" aria-hidden="true">
				<!-- wp:heading {"level":3} -->
				<h3 class="wp-block-heading"><?php echo esc_html_x( 'Contact details stay private', 'Homepage recovery-confidence card heading', 'forge-tag' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html_x( 'The private relay lets an owner and verified finder exchange messages without showing either person’s email address.', 'Homepage recovery-confidence card copy', 'forge-tag' ); ?></p>
				<!-- /wp:paragraph -->
			</article>
			<!-- /wp:group -->

			<!-- wp:group {"tagName":"article","className":"forge-home-confidence__card","layout":{"type":"constrained"}} -->
			<article class="wp-block-group forge-home-confidence__card">
				<img class="forge-home-confidence__icon" src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/shield-check.svg' ) ); ?>" alt="" width="48" height="48" aria-hidden="true">
				<!-- wp:heading {"level":3} -->
				<h3 class="wp-block-heading"><?php echo esc_html_x( 'Smart finding stays separate', 'Homepage recovery-confidence card heading', 'forge-tag' ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html_x( 'Compatible finding networks run in their own apps. ForgeTag does not read network account, device, battery, pairing, or location data.', 'Homepage recovery-confidence card copy', 'forge-tag' ); ?></p>
				<!-- /wp:paragraph -->
			</article>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
