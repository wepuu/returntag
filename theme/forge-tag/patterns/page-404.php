<?php
/**
 * Title: ForgeTag page not found
 * Slug: forge-tag/page-404
 * Categories: featured
 * Inserter: no
 *
 * @package ForgeTag
 */

declare(strict_types=1);
?>
<!-- wp:group {"align":"wide","className":"forge-state-panel forge-state-panel--404","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide forge-state-panel forge-state-panel--404">
	<!-- wp:paragraph {"className":"forge-home-eyebrow forge-state-panel__eyebrow"} -->
	<p class="forge-home-eyebrow forge-state-panel__eyebrow"><?php echo esc_html_x( 'PAGE NOT FOUND', '404 page eyebrow', 'forge-tag' ); ?></p>
	<!-- /wp:paragraph -->
	<!-- wp:heading {"level":1,"className":"forge-state-panel__title"} -->
	<h1 class="wp-block-heading forge-state-panel__title"><?php echo esc_html_x( 'This page has moved or does not exist', '404 page heading', 'forge-tag' ); ?></h1>
	<!-- /wp:heading -->
	<!-- wp:paragraph {"className":"forge-state-panel__summary"} -->
	<p class="forge-state-panel__summary"><?php echo esc_html_x( 'Check the address, return to the homepage, or choose a ForgeTag recovery action.', '404 page guidance', 'forge-tag' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:group {"className":"forge-entry-actions forge-state-panel__actions","layout":{"type":"flex","flexWrap":"wrap"}} -->
	<div class="wp-block-group forge-entry-actions forge-state-panel__actions">
		<!-- wp:buttons -->
		<div class="wp-block-buttons"><!-- wp:button -->
		<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html_x( 'Return to homepage', '404 page action', 'forge-tag' ); ?></a></div>
		<!-- /wp:button --></div>
		<!-- /wp:buttons -->
		<!-- wp:tagcore/tag-entry-link {"intent":"activate","className":"is-style-secondary"} /-->
		<!-- wp:tagcore/tag-entry-link {"intent":"report","className":"is-style-secondary"} /-->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
