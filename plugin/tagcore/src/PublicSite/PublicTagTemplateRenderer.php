<?php
/**
 * Public Tag standalone template renderer.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use ReturnTag\TagCore\Application\PublicTag\PublicTagPage;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPageState;
use ReturnTag\TagCore\Domain\Tag\TagType;
use RuntimeException;

/**
 * Maps a pre-decided page to translatable presentation copy.
 */
final readonly class PublicTagTemplateRenderer {
	/**
	 * Create the renderer.
	 *
	 * @param string $plugin_dir Absolute TagCore plugin directory.
	 */
	public function __construct( private string $plugin_dir ) {
	}

	/**
	 * Render one standalone page to the active response.
	 *
	 * @param PublicTagPage $page Privacy-minimized Application view model.
	 */
	public function render( PublicTagPage $page ): void {
		echo $this->render_to_string( $page ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The standalone template escapes every value for its output context.
	}

	/**
	 * Render one page into a testable HTML string.
	 *
	 * @param PublicTagPage $page Privacy-minimized Application view model.
	 * @throws RuntimeException When the packaged template cannot be read.
	 */
	public function render_to_string( PublicTagPage $page ): string {
		$template = $this->plugin_dir . '/templates/public/tag-page.php';

		if ( ! is_readable( $template ) ) {
			throw new RuntimeException( 'The public Tag template is unavailable.' );
		}

		$view = $this->present( $page );

		ob_start();
		require $template;
		$output = ob_get_clean();

		return $output;
	}

	/**
	 * Map state and approved public values to presentation-only copy.
	 *
	 * @param PublicTagPage $page Privacy-minimized Application view model.
	 */
	private function present( PublicTagPage $page ): PublicTagPageView {
		$copy = match ( $page->state ) {
			PublicTagPageState::INVALID => array(
				__( 'Tag recovery', 'tagcore' ),
				__( 'We could not find this ReturnTag', 'tagcore' ),
				__( 'Check the six-character Tag ID, then scan or enter it again.', 'tagcore' ),
			),
			PublicTagPageState::SERVICE_UNAVAILABLE => array(
				__( 'Tag recovery', 'tagcore' ),
				__( 'Tag service is temporarily unavailable', 'tagcore' ),
				__( 'We cannot check this ReturnTag right now. Please try again in a moment.', 'tagcore' ),
			),
			PublicTagPageState::ACTIVATION_UNAVAILABLE => array(
				__( 'Tag activation', 'tagcore' ),
				__( 'This Tag is not ready to activate', 'tagcore' ),
				__( 'Activation is temporarily unavailable. Please try again later.', 'tagcore' ),
			),
			PublicTagPageState::ACTIVATION_ENTRY => array(
				__( 'Tag activation', 'tagcore' ),
				__( 'Activate this ReturnTag', 'tagcore' ),
				__( 'This Tag is ready for its owner. Secure email verification is the next step.', 'tagcore' ),
			),
			PublicTagPageState::OWNER_ENTRY => array(
				__( 'Your ReturnTag', 'tagcore' ),
				__( 'This ReturnTag is yours', 'tagcore' ),
				__( 'You are signed in as this Tag owner. Account management will be available here.', 'tagcore' ),
			),
			PublicTagPageState::FINDER_ENTRY => array(
				__( 'Found an item?', 'tagcore' ),
				__( 'Help return this item', 'tagcore' ),
				__( 'This ReturnTag is registered. Private owner contact will be available here.', 'tagcore' ),
			),
			PublicTagPageState::FINDER_UNAVAILABLE => array(
				__( 'Tag recovery', 'tagcore' ),
				__( 'Owner contact is temporarily unavailable', 'tagcore' ),
				__( 'This ReturnTag is registered, but private contact is paused. Please try again later.', 'tagcore' ),
			),
			PublicTagPageState::SUSPENDED => array(
				__( 'Tag recovery', 'tagcore' ),
				__( 'This Tag service is suspended', 'tagcore' ),
				__( 'ReturnTag cannot provide activation or owner contact for this Tag right now.', 'tagcore' ),
			),
			PublicTagPageState::RETIRED => array(
				__( 'Tag recovery', 'tagcore' ),
				__( 'This Tag is no longer in service', 'tagcore' ),
				__( 'This ReturnTag has been permanently retired and cannot be activated or contacted.', 'tagcore' ),
			),
		};

		return new PublicTagPageView(
			'returntag-public--' . str_replace( '_', '-', $page->state->value ),
			$copy[0],
			$copy[1],
			$copy[2],
			__( 'Return to homepage', 'tagcore' ),
			home_url( '/' ),
			$this->product_type_label( $page->tag_type ),
			$page->public_label,
			$page->lost_mode,
			$page->lost_message
		);
	}

	/**
	 * Translate one canonical product type without exposing its stored value.
	 *
	 * @param TagType|null $tag_type Optional public product type.
	 */
	private function product_type_label( ?TagType $tag_type ): ?string {
		return match ( $tag_type ) {
			TagType::STICKER => __( 'Sticker', 'tagcore' ),
			TagType::CLASSIC_TAG => __( 'Classic Tag', 'tagcore' ),
			TagType::SMART_TAG => __( 'Smart Tag', 'tagcore' ),
			null => null,
		};
	}
}
