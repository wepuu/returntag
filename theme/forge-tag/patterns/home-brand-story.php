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

$forge_tag_demo_content = in_array(
	wp_get_environment_type(),
	array( 'development', 'local' ),
	true
);

$forge_tag_brand_eyebrow = $forge_tag_demo_content
	? _x( 'FROM A BRAND BUILT ON TRAVEL SECURITY', 'Homepage brand-story demo eyebrow', 'forge-tag' )
	: _x( 'BUILT FOR THE MOMENT SOMETHING GOES MISSING', 'Homepage brand-story eyebrow', 'forge-tag' );
$forge_tag_brand_heading = $forge_tag_demo_content
	? _x( 'From a brand built on travel security', 'Homepage brand-story demo heading', 'forge-tag' )
	: _x( 'A clear route back, without public contact details', 'Homepage brand-story heading', 'forge-tag' );
$forge_tag_brand_summary = $forge_tag_demo_content
	? _x( 'Since 2015, Forge has helped travelers protect what matters with TSA locks trusted by customers across Amazon and beyond. ForgeTag brings that same security mindset to item recovery and tracking.', 'Homepage brand-story demo summary', 'forge-tag' )
	: _x( 'ForgeTag keeps the recovery path simple: one public Tag ID, a browser-based page, and a private way for an owner and finder to exchange messages.', 'Homepage brand-story summary', 'forge-tag' );
$forge_tag_brand_proofs = $forge_tag_demo_content
	? array(
		array( 'calendar-days.svg', _x( '2015', 'Homepage demo brand proof value', 'forge-tag' ), _x( 'Founded', 'Homepage demo brand proof label', 'forge-tag' ) ),
		array( 'chart-no-axes-column-increasing.svg', _x( 'Millions', 'Homepage demo brand proof value', 'forge-tag' ), _x( 'Sold', 'Homepage demo brand proof label', 'forge-tag' ) ),
		array( 'shield-check.svg', _x( 'Trusted', 'Homepage demo brand proof value', 'forge-tag' ), _x( 'Travel Brand', 'Homepage demo brand proof label', 'forge-tag' ) ),
	)
	: array(
		array( 'key-round.svg', _x( 'One Tag ID', 'Homepage recovery fact value', 'forge-tag' ), _x( 'Six-character recovery route', 'Homepage recovery fact label', 'forge-tag' ) ),
		array( 'smartphone.svg', _x( 'Any phone', 'Homepage recovery fact value', 'forge-tag' ), _x( 'No ForgeTag app required', 'Homepage recovery fact label', 'forge-tag' ) ),
		array( 'shield-check.svg', _x( 'Email hidden', 'Homepage recovery fact value', 'forge-tag' ), _x( 'Private relay by default', 'Homepage recovery fact label', 'forge-tag' ) ),
	);
?>
<!-- wp:group {"tagName":"section","align":"full","className":"forge-home-section forge-home-brand-story","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull forge-home-section forge-home-brand-story">
	<!-- wp:group {"align":"wide","className":"forge-home-brand-story__layout","layout":{"type":"grid","columnCount":2,"minimumColumnWidth":null}} -->
	<div class="wp-block-group alignwide forge-home-brand-story__layout">
		<!-- wp:group {"className":"forge-home-brand-story__content","layout":{"type":"default"}} -->
		<div class="wp-block-group forge-home-brand-story__content">
			<!-- wp:paragraph {"className":"forge-home-eyebrow"} -->
			<p class="forge-home-eyebrow"><?php echo esc_html( $forge_tag_brand_eyebrow ); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"className":"forge-home-section-title"} -->
			<h2 class="wp-block-heading forge-home-section-title"><?php echo esc_html( $forge_tag_brand_heading ); ?></h2>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"className":"forge-home-brand-story__summary"} -->
			<p class="forge-home-brand-story__summary"><?php echo esc_html( $forge_tag_brand_summary ); ?></p>
			<!-- /wp:paragraph -->
			<?php if ( $forge_tag_demo_content ) : ?>
				<!-- wp:paragraph {"className":"forge-home-demo-note"} -->
				<p class="forge-home-demo-note"><?php echo esc_html_x( 'Demo content · development environment', 'Homepage demo-content notice', 'forge-tag' ); ?></p>
				<!-- /wp:paragraph -->
			<?php endif; ?>

			<!-- wp:list {"className":"forge-home-brand-story__proofs"} -->
			<ul class="forge-home-brand-story__proofs" role="list">
				<?php foreach ( $forge_tag_brand_proofs as $forge_tag_brand_proof ) : ?>
					<li class="forge-home-brand-story__proof">
						<img class="forge-home-brand-story__proof-icon" src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/' . $forge_tag_brand_proof[0] ) ); ?>" alt="" width="64" height="64" aria-hidden="true">
						<span class="forge-home-brand-story__proof-copy"><strong><?php echo esc_html( $forge_tag_brand_proof[1] ); ?></strong><span><?php echo esc_html( $forge_tag_brand_proof[2] ); ?></span></span>
					</li>
				<?php endforeach; ?>
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
