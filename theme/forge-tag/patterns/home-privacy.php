<?php
/**
 * Title: ForgeTag privacy principles
 * Slug: forge-tag/home-privacy
 * Categories: featured
 * Inserter: no
 *
 * @package ForgeTag
 */

declare(strict_types=1);
?>
<!-- wp:group {"tagName":"section","anchor":"privacy","align":"full","className":"forge-home-section forge-home-privacy","layout":{"type":"constrained"}} -->
<section id="privacy" class="wp-block-group alignfull forge-home-section forge-home-privacy">
	<!-- wp:group {"align":"wide","className":"forge-home-privacy__inner","layout":{"type":"constrained"}} -->
	<div class="wp-block-group alignwide forge-home-privacy__inner">
		<!-- wp:paragraph {"align":"wide","className":"forge-home-eyebrow"} -->
		<p class="alignwide forge-home-eyebrow"><?php echo esc_html_x( 'Private by design', 'Homepage privacy eyebrow', 'forge-tag' ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:heading {"align":"wide","className":"forge-home-section-title"} -->
		<h2 class="wp-block-heading alignwide forge-home-section-title"><?php echo esc_html_x( 'A clear recovery path without exposing private contact details.', 'Homepage privacy heading', 'forge-tag' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:group {"align":"wide","className":"forge-home-privacy__grid","layout":{"type":"grid","columnCount":3,"minimumColumnWidth":null}} -->
		<div class="wp-block-group alignwide forge-home-privacy__grid">
			<!-- wp:group {"className":"forge-home-privacy__principle","layout":{"type":"constrained"}} -->
			<div class="wp-block-group forge-home-privacy__principle">
				<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"forge-home-icon"} -->
				<figure class="wp-block-image size-full forge-home-icon"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/mail-check.svg' ) ); ?>" alt="" width="48" height="48"></figure>
				<!-- /wp:image -->
				<!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><?php echo esc_html_x( 'Email addresses stay private', 'Homepage privacy principle', 'forge-tag' ); ?></h3><!-- /wp:heading -->
				<!-- wp:paragraph --><p><?php echo esc_html_x( 'Owners and finders do not see one another’s email address.', 'Homepage privacy principle description', 'forge-tag' ); ?></p><!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"forge-home-privacy__principle","layout":{"type":"constrained"}} -->
			<div class="wp-block-group forge-home-privacy__principle">
				<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"forge-home-icon"} -->
				<figure class="wp-block-image size-full forge-home-icon"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/smartphone.svg' ) ); ?>" alt="" width="48" height="48"></figure>
				<!-- /wp:image -->
				<!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><?php echo esc_html_x( 'No Finder app required', 'Homepage privacy principle', 'forge-tag' ); ?></h3><!-- /wp:heading -->
				<!-- wp:paragraph --><p><?php echo esc_html_x( 'A standard phone browser can open the QR recovery experience.', 'Homepage privacy principle description', 'forge-tag' ); ?></p><!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"forge-home-privacy__principle","layout":{"type":"constrained"}} -->
			<div class="wp-block-group forge-home-privacy__principle">
				<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"forge-home-icon"} -->
				<figure class="wp-block-image size-full forge-home-icon"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/shield-check.svg' ) ); ?>" alt="" width="48" height="48"></figure>
				<!-- /wp:image -->
				<!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><?php echo esc_html_x( 'QR recovery stays independent', 'Homepage privacy principle', 'forge-tag' ); ?></h3><!-- /wp:heading -->
				<!-- wp:paragraph --><p><?php echo esc_html_x( 'ForgeTag does not need external account, device, pairing, battery, or location data.', 'Homepage privacy principle description', 'forge-tag' ); ?></p><!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
