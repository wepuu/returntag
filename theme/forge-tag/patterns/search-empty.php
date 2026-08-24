<?php
/**
 * Title: ForgeTag search empty state
 * Slug: forge-tag/search-empty
 * Categories: featured
 * Inserter: no
 *
 * @package ForgeTag
 */

declare(strict_types=1);
?>
<!-- wp:group {"className":"forge-state-panel forge-state-panel--search-empty","layout":{"type":"constrained"}} -->
<div class="wp-block-group forge-state-panel forge-state-panel--search-empty">
	<!-- wp:heading {"level":2,"className":"forge-state-panel__title"} -->
	<h2 class="wp-block-heading forge-state-panel__title"><?php echo esc_html_x( 'No matching pages', 'Search empty-state heading', 'forge-tag' ); ?></h2>
	<!-- /wp:heading -->
	<!-- wp:paragraph {"className":"forge-state-panel__summary"} -->
	<p class="forge-state-panel__summary"><?php echo esc_html_x( 'Try a shorter phrase, check the spelling, or return to the homepage.', 'Search empty-state guidance', 'forge-tag' ); ?></p>
	<!-- /wp:paragraph -->
	<!-- wp:search {"label":<?php echo wp_json_encode( _x( 'Search again', 'Search empty-state field label', 'forge-tag' ) ); ?>,"showLabel":true,"placeholder":<?php echo wp_json_encode( _x( 'Search ForgeTag', 'Search field placeholder', 'forge-tag' ) ); ?>,"buttonText":<?php echo wp_json_encode( _x( 'Search', 'Search form button', 'forge-tag' ) ); ?>,"buttonUseIcon":true} /-->
	<!-- wp:buttons -->
	<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-secondary"} -->
	<div class="wp-block-button is-style-secondary"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html_x( 'Return to homepage', 'Search empty-state action', 'forge-tag' ); ?></a></div>
	<!-- /wp:button --></div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
