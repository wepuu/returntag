<?php
/**
 * Dynamic Tag entry link block.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use WP_Block;

/**
 * Publishes the narrow Theme-to-TagCore manual-entry seam.
 */
final readonly class TagEntryLinkBlock {
	public const BLOCK_NAME = 'tagcore/tag-entry-link';

	public const STYLE_HANDLE = 'returntag-tagcore-public';

	public const SCRIPT_MODULE_HANDLE = 'returntag-tagcore-entry-module';

	public const EDITOR_SCRIPT_HANDLE = 'returntag-tagcore-entry-editor';

	/**
	 * Create the block adapter.
	 *
	 * @param string                         $plugin_dir Absolute TagCore plugin directory.
	 * @param TagEntryUrlProvider            $urls Same-site URL provider.
	 * @param ManualTagEntryTemplateRenderer $renderer Shared entry form renderer.
	 */
	public function __construct(
		private string $plugin_dir,
		private TagEntryUrlProvider $urls,
		private ManualTagEntryTemplateRenderer $renderer
	) {
	}

	/**
	 * Register the block during WordPress initialization.
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register' ), 20 );
	}

	/**
	 * Register metadata and the dynamic render callback.
	 */
	public function register(): void {
		self::register_assets( $this->plugin_dir );

		register_block_type_from_metadata(
			$this->plugin_dir . '/src/PublicSite/Block/tag-entry-link',
			array( 'render_callback' => array( $this, 'render' ) )
		);
	}

	/**
	 * Register plugin-owned assets for both standalone and embedded entry UI.
	 *
	 * @param string $plugin_dir Absolute TagCore plugin directory.
	 */
	public static function register_assets( string $plugin_dir ): void {
		$asset_file = $plugin_dir . '/build/public/public.ts.asset.php';
		$asset      = is_readable( $asset_file ) ? require $asset_file : array();
		$version    = is_array( $asset ) && isset( $asset['version'] ) && is_string( $asset['version'] )
			? $asset['version']
			: RETURNTAG_TAGCORE_VERSION;

		wp_register_style(
			self::STYLE_HANDLE,
			plugins_url( 'build/public/public.ts.css', $plugin_dir . '/tagcore.php' ),
			array(),
			$version
		);

		wp_register_script_module(
			self::SCRIPT_MODULE_HANDLE,
			plugins_url( 'build/public/public.ts.js', $plugin_dir . '/tagcore.php' ),
			array(),
			$version
		);

		$editor_asset_file = $plugin_dir . '/build/blocks/tag-entry-link/index.asset.php';
		$editor_asset      = is_readable( $editor_asset_file ) ? require $editor_asset_file : array();
		$editor_version    = is_array( $editor_asset ) && isset( $editor_asset['version'] ) && is_string( $editor_asset['version'] )
			? $editor_asset['version']
			: RETURNTAG_TAGCORE_VERSION;

		wp_register_script(
			self::EDITOR_SCRIPT_HANDLE,
			plugins_url( 'build/blocks/tag-entry-link/index.js', $plugin_dir . '/tagcore.php' ),
			array( 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n' ),
			$editor_version,
			true
		);
	}

	/**
	 * Render one ordinary same-site link plus a desktop dialog enhancement.
	 *
	 * @param array<string, mixed> $attributes Validated block attributes.
	 * @param string               $content Saved content; unused by this dynamic block.
	 * @param WP_Block             $block Parsed block instance.
	 */
	public function render( array $attributes, string $content, WP_Block $block ): string {
		unset( $content, $block );

		$value  = $attributes['intent'] ?? null;
		$intent = is_string( $value ) ? TagEntryIntent::tryFrom( $value ) : null;

		if ( null === $intent ) {
			return '';
		}

		$url         = $this->urls->entry_url( $intent );
		$label       = TagEntryIntent::ACTIVATE === $intent
			? __( 'Activate my tag', 'tagcore' )
			: __( 'Report a found tag', 'tagcore' );
		$title       = TagEntryIntent::ACTIVATE === $intent
			? __( 'Activate your ForgeTag', 'tagcore' )
			: __( 'Report a found ForgeTag', 'tagcore' );
		$description = TagEntryIntent::ACTIVATE === $intent
			? __( 'Enter the six-character ID printed on your tag.', 'tagcore' )
			: __( 'Enter the six-character ID printed on the tag you found.', 'tagcore' );
		$instance    = wp_unique_id( 'returntag-tag-entry-' );
		$dialog_id   = $instance . '-dialog';
		$title_id    = $instance . '-title';
		$intro_id    = $instance . '-description';
		$wrapper     = get_block_wrapper_attributes(
			array(
				'class'                    => 'returntag-entry-link',
				'data-returntag-tag-entry' => $intent->value,
			)
		);

		wp_enqueue_style( self::STYLE_HANDLE );
		wp_enqueue_script_module( self::SCRIPT_MODULE_HANDLE );

		return sprintf(
			'<div %1$s><a class="returntag-entry-link__trigger" href="%2$s" aria-haspopup="dialog" aria-controls="%3$s" data-returntag-tag-entry-trigger>%4$s</a><dialog id="%3$s" class="returntag-entry-dialog" aria-labelledby="%5$s" aria-describedby="%6$s" data-returntag-tag-entry-dialog><div class="returntag-entry-dialog__surface"><button class="returntag-entry-dialog__close" type="button" data-returntag-tag-entry-close>%7$s</button><p class="returntag-entry__eyebrow">%8$s</p><h2 id="%5$s">%9$s</h2><p id="%6$s" class="returntag-entry__introduction">%10$s</p>%11$s</div></dialog></div>',
			$wrapper,
			esc_url( $url ),
			esc_attr( $dialog_id ),
			esc_html( $label ),
			esc_attr( $title_id ),
			esc_attr( $intro_id ),
			esc_html__( 'Close', 'tagcore' ),
			esc_html__( 'Tag recovery', 'tagcore' ),
			esc_html( $title ),
			esc_html( $description ),
			$this->renderer->render_form_to_string( $intent, $url, ManualTagEntryFormState::READY, $instance . '-form' )
		);
	}
}
