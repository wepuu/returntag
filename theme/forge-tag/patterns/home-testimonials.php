<?php
/**
 * Title: ForgeTag customer reviews demo
 * Slug: forge-tag/home-testimonials
 * Categories: testimonials
 * Inserter: no
 *
 * @package ForgeTag
 */

declare(strict_types=1);

if ( ! in_array( wp_get_environment_type(), array( 'development', 'local' ), true ) ) {
	return;
}

$forge_tag_demo_reviews = array(
	array(
		'avatar'   => 'megan-r.png',
		'name'     => _x( 'Megan R.', 'Demo customer review display name', 'forge-tag' ),
		'metadata' => _x( 'Classic Tag · Verified Buyer · Amazon', 'Demo customer review product, purchase status, and source', 'forge-tag' ),
		'quote'    => _x( 'Setup took maybe two minutes, and the tag feels sturdy without being bulky. I also like that someone can contact me without my personal information being printed on the luggage.', 'Demo Classic Tag review excerpt', 'forge-tag' ),
	),
	array(
		'avatar'   => 'chris-d.png',
		'name'     => _x( 'Chris D.', 'Demo customer review display name', 'forge-tag' ),
		'metadata' => _x( 'Sticker · Verified Buyer · Walmart', 'Demo customer review product, purchase status, and source', 'forge-tag' ),
		'quote'    => _x( 'The sticker is low-profile, the QR code is easy to scan, and the activation process was straightforward. Hopefully I never need it, but it gives me some extra peace of mind.', 'Demo Sticker review excerpt', 'forge-tag' ),
	),
	array(
		'avatar'   => 'daniel-k.png',
		'name'     => _x( 'Daniel K.', 'Demo customer review display name', 'forge-tag' ),
		'metadata' => _x( 'ForgeTag Classic Tag · Verified Buyer · Amazon', 'Demo customer review product, purchase status, and source', 'forge-tag' ),
		'quote'    => _x( 'I received the message and got my bag back that evening. The process was simple, and neither of us had to post personal information publicly.', 'Demo Classic Tag recovery review excerpt', 'forge-tag' ),
	),
);
?>
<!-- wp:group {"tagName":"section","align":"full","className":"forge-home-section forge-home-testimonials","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull forge-home-section forge-home-testimonials">
	<!-- wp:group {"align":"wide","className":"forge-home-testimonials__inner","layout":{"type":"constrained"}} -->
	<div class="wp-block-group alignwide forge-home-testimonials__inner">
		<!-- wp:heading {"textAlign":"center","className":"forge-home-section-title forge-home-testimonials__title"} -->
		<h2 class="wp-block-heading has-text-align-center forge-home-section-title forge-home-testimonials__title"><?php echo esc_html_x( 'Customer stories', 'Homepage testimonials demo heading', 'forge-tag' ); ?></h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph {"align":"center","className":"forge-home-demo-note"} -->
		<p class="has-text-align-center forge-home-demo-note"><?php echo esc_html_x( 'Demo content · development environment', 'Homepage demo-content notice', 'forge-tag' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:group {"className":"forge-home-testimonials__grid","layout":{"type":"grid","columnCount":3,"minimumColumnWidth":null}} -->
		<div class="wp-block-group forge-home-testimonials__grid">
			<?php foreach ( $forge_tag_demo_reviews as $forge_tag_demo_review ) : ?>
				<!-- wp:group {"tagName":"figure","className":"forge-home-testimonial","layout":{"type":"constrained"}} -->
				<figure class="wp-block-group forge-home-testimonial">
					<div class="forge-home-testimonial__rating" role="img" aria-label="<?php echo esc_attr_x( 'Rated 5 out of 5', 'Demo customer review rating', 'forge-tag' ); ?>">
						<span class="forge-home-testimonial__stars" aria-hidden="true">
							<?php for ( $forge_tag_star = 0; $forge_tag_star < 5; $forge_tag_star++ ) : ?>
								<img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/star.svg' ) ); ?>" alt="" width="18" height="18">
							<?php endfor; ?>
						</span>
					</div>
					<!-- wp:quote {"className":"forge-home-testimonial__quote"} -->
					<blockquote class="wp-block-quote forge-home-testimonial__quote"><!-- wp:paragraph -->
					<p><?php echo esc_html( $forge_tag_demo_review['quote'] ); ?></p>
					<!-- /wp:paragraph --></blockquote>
					<!-- /wp:quote -->
					<!-- wp:group {"tagName":"figcaption","className":"forge-home-testimonial__caption","layout":{"type":"flex","flexWrap":"nowrap"}} -->
					<figcaption class="wp-block-group forge-home-testimonial__caption">
						<img class="forge-home-testimonial__avatar" src="<?php echo esc_url( get_theme_file_uri( 'assets/images/review-avatars/' . $forge_tag_demo_review['avatar'] ) ); ?>" alt="" width="256" height="256" loading="lazy" decoding="async" aria-hidden="true">
						<div class="forge-home-testimonial__identity">
							<p class="forge-home-testimonial__name"><?php echo esc_html( $forge_tag_demo_review['name'] ); ?></p>
							<p class="forge-home-testimonial__metadata"><?php echo esc_html( $forge_tag_demo_review['metadata'] ); ?></p>
						</div>
					</figcaption>
					<!-- /wp:group -->
				</figure>
				<!-- /wp:group -->
			<?php endforeach; ?>
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
