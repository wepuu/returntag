<?php
/**
 * Title: ForgeTag customer reviews
 * Slug: forge-tag/home-testimonials
 * Categories: testimonials
 * Inserter: no
 *
 * @package ForgeTag
 */

declare(strict_types=1);
?>
<!-- wp:group {"tagName":"section","align":"full","className":"forge-home-section forge-home-testimonials","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull forge-home-section forge-home-testimonials">
	<!-- wp:group {"align":"wide","className":"forge-home-testimonials__inner","layout":{"type":"constrained"}} -->
	<div class="wp-block-group alignwide forge-home-testimonials__inner">
		<!-- wp:heading {"textAlign":"center","className":"forge-home-section-title forge-home-testimonials__title"} -->
		<h2 class="wp-block-heading has-text-align-center forge-home-section-title forge-home-testimonials__title"><?php echo esc_html_x( 'Customer stories', 'Homepage testimonials heading', 'forge-tag' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:group {"className":"forge-home-testimonials__grid","layout":{"type":"grid","columnCount":3,"minimumColumnWidth":null}} -->
		<div class="wp-block-group forge-home-testimonials__grid">
			<!-- wp:group {"tagName":"figure","className":"forge-home-testimonial","layout":{"type":"constrained"}} -->
			<figure class="wp-block-group forge-home-testimonial">
				<div class="forge-home-testimonial__rating" role="img" aria-label="<?php echo esc_attr_x( 'Rated 5 out of 5', 'Customer review rating', 'forge-tag' ); ?>">
					<span class="forge-home-testimonial__stars" aria-hidden="true"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/star.svg' ) ); ?>" alt="" width="18" height="18"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/star.svg' ) ); ?>" alt="" width="18" height="18"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/star.svg' ) ); ?>" alt="" width="18" height="18"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/star.svg' ) ); ?>" alt="" width="18" height="18"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/star.svg' ) ); ?>" alt="" width="18" height="18"></span>
				</div>
				<!-- wp:quote {"className":"forge-home-testimonial__quote"} -->
				<blockquote class="wp-block-quote forge-home-testimonial__quote"><!-- wp:paragraph -->
				<p><?php echo esc_html_x( 'Setup took maybe two minutes, and the tag feels sturdy without being bulky. I also like that someone can contact me without my personal information being printed on the luggage.', 'Megan R. Classic Tag review excerpt', 'forge-tag' ); ?></p>
				<!-- /wp:paragraph --></blockquote>
				<!-- /wp:quote -->
				<!-- wp:group {"tagName":"figcaption","className":"forge-home-testimonial__caption","layout":{"type":"flex","flexWrap":"nowrap"}} -->
				<figcaption class="wp-block-group forge-home-testimonial__caption">
					<img class="forge-home-testimonial__avatar" src="<?php echo esc_url( get_theme_file_uri( 'assets/images/review-avatars/megan-r.png' ) ); ?>" alt="" width="256" height="256" loading="lazy" decoding="async" aria-hidden="true">
					<div class="forge-home-testimonial__identity">
						<p class="forge-home-testimonial__name"><?php echo esc_html_x( 'Megan R.', 'Customer review display name', 'forge-tag' ); ?></p>
						<p class="forge-home-testimonial__metadata"><?php echo esc_html_x( 'Classic Tag · Verified Buyer · Amazon', 'Customer review product, purchase status, and source', 'forge-tag' ); ?></p>
					</div>
				</figcaption>
				<!-- /wp:group -->
			</figure>
			<!-- /wp:group -->

			<!-- wp:group {"tagName":"figure","className":"forge-home-testimonial","layout":{"type":"constrained"}} -->
			<figure class="wp-block-group forge-home-testimonial">
				<div class="forge-home-testimonial__rating" role="img" aria-label="<?php echo esc_attr_x( 'Rated 5 out of 5', 'Customer review rating', 'forge-tag' ); ?>">
					<span class="forge-home-testimonial__stars" aria-hidden="true"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/star.svg' ) ); ?>" alt="" width="18" height="18"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/star.svg' ) ); ?>" alt="" width="18" height="18"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/star.svg' ) ); ?>" alt="" width="18" height="18"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/star.svg' ) ); ?>" alt="" width="18" height="18"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/star.svg' ) ); ?>" alt="" width="18" height="18"></span>
				</div>
				<!-- wp:quote {"className":"forge-home-testimonial__quote"} -->
				<blockquote class="wp-block-quote forge-home-testimonial__quote"><!-- wp:paragraph -->
				<p><?php echo esc_html_x( 'The sticker is low-profile, the QR code is easy to scan, and the activation process was straightforward. Hopefully I never need it, but it gives me some extra peace of mind.', 'Chris D. Sticker review excerpt', 'forge-tag' ); ?></p>
				<!-- /wp:paragraph --></blockquote>
				<!-- /wp:quote -->
				<!-- wp:group {"tagName":"figcaption","className":"forge-home-testimonial__caption","layout":{"type":"flex","flexWrap":"nowrap"}} -->
				<figcaption class="wp-block-group forge-home-testimonial__caption">
					<img class="forge-home-testimonial__avatar" src="<?php echo esc_url( get_theme_file_uri( 'assets/images/review-avatars/chris-d.png' ) ); ?>" alt="" width="256" height="256" loading="lazy" decoding="async" aria-hidden="true">
					<div class="forge-home-testimonial__identity">
						<p class="forge-home-testimonial__name"><?php echo esc_html_x( 'Chris D.', 'Customer review display name', 'forge-tag' ); ?></p>
						<p class="forge-home-testimonial__metadata"><?php echo esc_html_x( 'Sticker · Verified Buyer · Walmart', 'Customer review product, purchase status, and source', 'forge-tag' ); ?></p>
					</div>
				</figcaption>
				<!-- /wp:group -->
			</figure>
			<!-- /wp:group -->

			<!-- wp:group {"tagName":"figure","className":"forge-home-testimonial","layout":{"type":"constrained"}} -->
			<figure class="wp-block-group forge-home-testimonial">
				<div class="forge-home-testimonial__rating" role="img" aria-label="<?php echo esc_attr_x( 'Rated 5 out of 5', 'Customer review rating', 'forge-tag' ); ?>">
					<span class="forge-home-testimonial__stars" aria-hidden="true"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/star.svg' ) ); ?>" alt="" width="18" height="18"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/star.svg' ) ); ?>" alt="" width="18" height="18"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/star.svg' ) ); ?>" alt="" width="18" height="18"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/star.svg' ) ); ?>" alt="" width="18" height="18"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/star.svg' ) ); ?>" alt="" width="18" height="18"></span>
				</div>
				<!-- wp:quote {"className":"forge-home-testimonial__quote"} -->
				<blockquote class="wp-block-quote forge-home-testimonial__quote"><!-- wp:paragraph -->
				<p><?php echo esc_html_x( 'I received the message and got my bag back that evening. The process was simple, and neither of us had to post personal information publicly.', 'Daniel K. Classic Tag review excerpt', 'forge-tag' ); ?></p>
				<!-- /wp:paragraph --></blockquote>
				<!-- /wp:quote -->
				<!-- wp:group {"tagName":"figcaption","className":"forge-home-testimonial__caption","layout":{"type":"flex","flexWrap":"nowrap"}} -->
				<figcaption class="wp-block-group forge-home-testimonial__caption">
					<img class="forge-home-testimonial__avatar" src="<?php echo esc_url( get_theme_file_uri( 'assets/images/review-avatars/daniel-k.png' ) ); ?>" alt="" width="256" height="256" loading="lazy" decoding="async" aria-hidden="true">
					<div class="forge-home-testimonial__identity">
						<p class="forge-home-testimonial__name"><?php echo esc_html_x( 'Daniel K.', 'Customer review display name', 'forge-tag' ); ?></p>
						<p class="forge-home-testimonial__metadata"><?php echo esc_html_x( 'ForgeTag Classic Tag · Verified Buyer · Amazon', 'Customer review product, purchase status, and source', 'forge-tag' ); ?></p>
					</div>
				</figcaption>
				<!-- /wp:group -->
			</figure>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
