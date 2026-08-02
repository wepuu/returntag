<?php
/**
 * Title: ForgeTag product families
 * Slug: forge-tag/home-products
 * Categories: featured
 * Inserter: no
 *
 * @package ForgeTag
 */

declare(strict_types=1);
?>
<!-- wp:group {"tagName":"section","anchor":"products","align":"full","className":"forge-home-section forge-home-products","layout":{"type":"constrained"}} -->
<section id="products" class="wp-block-group alignfull forge-home-section forge-home-products">
	<!-- wp:group {"align":"wide","className":"forge-home-section__inner","layout":{"type":"constrained"}} -->
	<div class="wp-block-group alignwide forge-home-section__inner">
		<!-- wp:heading {"textAlign":"center","className":"forge-home-section-title"} -->
		<h2 class="wp-block-heading has-text-align-center forge-home-section-title"><?php echo esc_html_x( 'One recovery path for the things you carry.', 'Homepage products heading', 'forge-tag' ); ?></h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph {"align":"center","className":"forge-home-section-summary"} -->
		<p class="has-text-align-center forge-home-section-summary"><?php echo esc_html_x( 'Choose the ForgeTag format that fits the item you want to protect.', 'Homepage products introduction', 'forge-tag' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:group {"className":"forge-home-products__grid","layout":{"type":"grid","columnCount":3,"minimumColumnWidth":null}} -->
		<div class="wp-block-group forge-home-products__grid">
			<!-- wp:group {"className":"forge-home-product forge-tag-card","layout":{"type":"constrained"}} -->
			<div class="wp-block-group forge-home-product forge-tag-card">
				<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"forge-home-product__media"} -->
				<figure class="wp-block-image size-full forge-home-product__media"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/product-sticker-safe.png' ) ); ?>" alt="<?php echo esc_attr_x( 'ForgeTag Sticker product sheet', 'Sticker product image alt text', 'forge-tag' ); ?>" width="1254" height="1254" loading="lazy" decoding="async"></figure>
				<!-- /wp:image -->
				<!-- wp:heading {"level":3} -->
				<h3 class="wp-block-heading">Sticker</h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html_x( 'A low-profile format for everyday essentials and smooth surfaces.', 'Sticker product description', 'forge-tag' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"forge-home-product forge-tag-card","layout":{"type":"constrained"}} -->
			<div class="wp-block-group forge-home-product forge-tag-card">
				<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"forge-home-product__media"} -->
				<figure class="wp-block-image size-full forge-home-product__media"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/product-classic-family-safe.png' ) ); ?>" alt="<?php echo esc_attr_x( 'Three ForgeTag Classic Tags in different shapes', 'Classic Tag product image alt text', 'forge-tag' ); ?>" width="1254" height="1254" loading="lazy" decoding="async"></figure>
				<!-- /wp:image -->
				<!-- wp:heading {"level":3} -->
				<h3 class="wp-block-heading">Classic Tag</h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html_x( 'A durable format for luggage, bags, keys, and other items on the move.', 'Classic Tag product description', 'forge-tag' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"forge-home-product forge-tag-card","layout":{"type":"constrained"}} -->
			<div class="wp-block-group forge-home-product forge-tag-card">
				<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"forge-home-product__media"} -->
				<figure class="wp-block-image size-full forge-home-product__media"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/product-smart-tag.png' ) ); ?>" alt="<?php echo esc_attr_x( 'Black ForgeTag Smart Tag', 'Smart Tag product image alt text', 'forge-tag' ); ?>" width="1254" height="1254" loading="lazy" decoding="async"></figure>
				<!-- /wp:image -->
				<!-- wp:heading {"level":3} -->
				<h3 class="wp-block-heading">Smart Tag</h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html_x( 'Compatible smart finding guidance and ForgeTag QR recovery remain separate systems.', 'Smart Tag product description', 'forge-tag' ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
