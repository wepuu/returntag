<?php
/**
 * Title: Forge travel-security brand story
 * Slug: forge-tag/home-brand-story
 * Categories: featured
 * Inserter: no
 *
 * @package ForgeTag
 */

declare(strict_types=1);
?>
<!-- wp:group {"tagName":"section","align":"full","className":"forge-home-section forge-home-brand-story","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull forge-home-section forge-home-brand-story">
	<!-- wp:group {"align":"wide","className":"forge-home-brand-story__layout","layout":{"type":"grid","columnCount":2,"minimumColumnWidth":null}} -->
	<div class="wp-block-group alignwide forge-home-brand-story__layout">
		<!-- wp:group {"className":"forge-home-brand-story__content","layout":{"type":"default"}} -->
		<div class="wp-block-group forge-home-brand-story__content">
			<!-- wp:paragraph {"className":"forge-home-eyebrow"} -->
			<p class="forge-home-eyebrow"><?php echo esc_html_x( 'FROM A BRAND BUILT ON TRAVEL SECURITY', 'Homepage brand-story eyebrow', 'forge-tag' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"className":"forge-home-section-title"} -->
			<h2 class="wp-block-heading forge-home-section-title"><?php echo esc_html_x( 'From a brand built on travel security', 'Homepage brand-story heading', 'forge-tag' ); ?></h2>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"forge-home-brand-story__summary"} -->
			<p class="forge-home-brand-story__summary"><?php echo esc_html_x( 'Since 2015, Forge has helped travelers protect what matters with TSA locks trusted by customers across Amazon and beyond. ForgeTag brings that same security mindset to item recovery and tracking.', 'Homepage brand-story summary', 'forge-tag' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:list {"className":"forge-home-brand-story__proofs"} -->
			<ul class="forge-home-brand-story__proofs" role="list">
				<li class="forge-home-brand-story__proof">
					<img class="forge-home-brand-story__proof-icon" src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/calendar-days.svg' ) ); ?>" alt="" width="64" height="64" aria-hidden="true">
					<span class="forge-home-brand-story__proof-copy"><strong><?php echo esc_html_x( '2015', 'Homepage brand proof value', 'forge-tag' ); ?></strong><span><?php echo esc_html_x( 'Founded', 'Homepage brand proof label', 'forge-tag' ); ?></span></span>
				</li>
				<li class="forge-home-brand-story__proof">
					<img class="forge-home-brand-story__proof-icon" src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/chart-no-axes-column-increasing.svg' ) ); ?>" alt="" width="64" height="64" aria-hidden="true">
					<span class="forge-home-brand-story__proof-copy"><strong><?php echo esc_html_x( 'Millions', 'Homepage brand proof value', 'forge-tag' ); ?></strong><span><?php echo esc_html_x( 'Sold', 'Homepage brand proof label', 'forge-tag' ); ?></span></span>
				</li>
				<li class="forge-home-brand-story__proof">
					<img class="forge-home-brand-story__proof-icon" src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/shield-check.svg' ) ); ?>" alt="" width="64" height="64" aria-hidden="true">
					<span class="forge-home-brand-story__proof-copy"><strong><?php echo esc_html_x( 'Trusted', 'Homepage brand proof value', 'forge-tag' ); ?></strong><span><?php echo esc_html_x( 'Travel Brand', 'Homepage brand proof label', 'forge-tag' ); ?></span></span>
				</li>
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:group -->

		<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"forge-home-brand-story__media"} -->
		<figure class="wp-block-image size-full forge-home-brand-story__media"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/forge-travel-lock-family.png' ) ); ?>" alt="<?php echo esc_attr_x( 'Three black Forge travel locks with combination, cable, and keyed closures', 'Forge travel-lock family image alt text', 'forge-tag' ); ?>" width="3377" height="2424" loading="lazy" decoding="async"></figure>
		<!-- /wp:image -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
