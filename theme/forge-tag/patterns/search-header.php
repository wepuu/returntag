<?php
/**
 * Title: ForgeTag search heading
 * Slug: forge-tag/search-header
 * Categories: featured
 * Inserter: no
 *
 * @package ForgeTag
 */

declare(strict_types=1);
?>
<!-- wp:group {"align":"wide","className":"forge-content-surface__header forge-search__header","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide forge-content-surface__header forge-search__header">
	<!-- wp:paragraph {"className":"forge-home-eyebrow"} -->
	<p class="forge-home-eyebrow"><?php echo esc_html_x( 'SEARCH', 'Search results eyebrow', 'forge-tag' ); ?></p>
	<!-- /wp:paragraph -->
	<!-- wp:query-title {"type":"search","showPrefix":true} /-->
</div>
<!-- /wp:group -->
