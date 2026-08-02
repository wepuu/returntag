<?php
/**
 * Title: ForgeTag home hero
 * Slug: forge-tag/home-hero
 * Categories: featured
 * Inserter: no
 *
 * @package ForgeTag
 */

declare(strict_types=1);
?>
<!-- wp:group {"tagName":"section","align":"full","className":"forge-home-hero forge-home-hero--with-media","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull forge-home-hero forge-home-hero--with-media">
	<!-- wp:group {"align":"wide","className":"forge-home-hero__layout","layout":{"type":"grid","columnCount":2,"minimumColumnWidth":null}} -->
	<div class="wp-block-group alignwide forge-home-hero__layout">
		<!-- wp:group {"className":"forge-home-hero__inner","layout":{"type":"constrained","justifyContent":"left"}} -->
		<div class="wp-block-group forge-home-hero__inner">
		<!-- wp:paragraph {"className":"forge-home-eyebrow"} -->
		<p class="forge-home-eyebrow"><?php echo esc_html_x( 'For travel. For every day.', 'Homepage hero eyebrow', 'forge-tag' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":1,"className":"forge-home-hero__title"} -->
		<h1 class="wp-block-heading forge-home-hero__title"><?php echo esc_html_x( 'Help what matters find its way back.', 'Homepage hero title', 'forge-tag' ); ?></h1>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"forge-home-hero__summary"} -->
		<p class="forge-home-hero__summary"><?php echo esc_html_x( 'Activate your tag. If it is found, a scan opens a private way to get in touch without exposing either person’s email address.', 'Homepage hero description', 'forge-tag' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:group {"className":"forge-entry-actions forge-home-hero__actions","layout":{"type":"flex","flexWrap":"wrap"}} -->
		<div class="wp-block-group forge-entry-actions forge-home-hero__actions">
			<!-- wp:tagcore/tag-entry-link {"intent":"activate"} /-->
			<!-- wp:tagcore/tag-entry-link {"intent":"report","className":"is-style-secondary"} /-->
		</div>
		<!-- /wp:group -->

		<!-- wp:paragraph {"className":"forge-home-hero__anchor"} -->
		<p class="forge-home-hero__anchor"><a href="#how-it-works"><?php echo esc_html_x( 'How it works', 'Homepage hero anchor link', 'forge-tag' ); ?></a></p>
		<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"forge-home-hero__media"} -->
		<figure class="wp-block-image size-full forge-home-hero__media"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/product-classic-family-safe.png' ) ); ?>" alt="<?php echo esc_attr_x( 'Three ForgeTag Classic Tags in different shapes', 'Homepage hero product image alt text', 'forge-tag' ); ?>" width="1254" height="1254" fetchpriority="high" decoding="async"></figure>
		<!-- /wp:image -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
