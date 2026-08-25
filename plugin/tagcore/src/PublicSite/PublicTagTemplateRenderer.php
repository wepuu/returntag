<?php
/**
 * Public Tag standalone template renderer.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use ReturnTag\TagCore\Account\AccountUrlProvider;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPage;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPageState;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagType;
use RuntimeException;

/**
 * Maps a pre-decided page to translatable presentation copy.
 */
final readonly class PublicTagTemplateRenderer {
	/**
	 * Same-site Account URL generator.
	 *
	 * @var AccountUrlProvider
	 */
	private AccountUrlProvider $account_urls;

	/**
	 * Create the renderer.
	 *
	 * @param string                  $plugin_dir Absolute TagCore plugin directory.
	 * @param AccountUrlProvider|null $account_urls Same-site Owner Account URLs.
	 */
	public function __construct( private string $plugin_dir, ?AccountUrlProvider $account_urls = null ) {
		$this->account_urls = $account_urls ?? new AccountUrlProvider();
	}

	/**
	 * Render one standalone page to the active response.
	 *
	 * @param PublicTagPage              $page Privacy-minimized Application view model.
	 * @param ActivationOtpFormView|null $activation_form Optional OTP form.
	 * @param FinderReportFormView|null  $finder_report_form Optional Finder Report form.
	 * @param TagId|null                 $tag_id Canonical Tag ID for state-specific same-site actions.
	 */
	public function render( PublicTagPage $page, ?ActivationOtpFormView $activation_form = null, ?FinderReportFormView $finder_report_form = null, ?TagId $tag_id = null ): void {
		echo $this->render_to_string( $page, $activation_form, $finder_report_form, $tag_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The standalone template escapes every value for its output context.
	}

	/**
	 * Render one page into a testable HTML string.
	 *
	 * @param PublicTagPage              $page Privacy-minimized Application view model.
	 * @param ActivationOtpFormView|null $activation_form Optional OTP form.
	 * @param FinderReportFormView|null  $finder_report_form Optional Finder Report form.
	 * @param TagId|null                 $tag_id Canonical Tag ID for state-specific same-site actions.
	 * @throws RuntimeException When the packaged template cannot be read.
	 */
	public function render_to_string( PublicTagPage $page, ?ActivationOtpFormView $activation_form = null, ?FinderReportFormView $finder_report_form = null, ?TagId $tag_id = null ): string {
		$template = $this->plugin_dir . '/templates/public/tag-page.php';

		if ( ! is_readable( $template ) ) {
			throw new RuntimeException( 'The public Tag template is unavailable.' );
		}

		$view = $this->present( $page, $activation_form, $finder_report_form, $tag_id );

		ob_start();
		require $template;
		$output = ob_get_clean();

		return $output;
	}

	/**
	 * Map state and approved public values to presentation-only copy.
	 *
	 * @param PublicTagPage              $page Privacy-minimized Application view model.
	 * @param ActivationOtpFormView|null $activation_form Optional OTP form.
	 * @param FinderReportFormView|null  $finder_report_form Optional Finder Report form.
	 * @param TagId|null                 $tag_id Canonical Tag ID for state-specific same-site actions.
	 */
	private function present( PublicTagPage $page, ?ActivationOtpFormView $activation_form, ?FinderReportFormView $finder_report_form, ?TagId $tag_id ): PublicTagPageView {
		$copy = match ( $page->state ) {
			PublicTagPageState::INVALID => array(
				__( 'Tag recovery', 'tagcore' ),
				__( 'We could not find this ForgeTag', 'tagcore' ),
				__( 'Check the six-character Tag ID, then scan or enter it again.', 'tagcore' ),
			),
			PublicTagPageState::SERVICE_UNAVAILABLE => array(
				__( 'Tag recovery', 'tagcore' ),
				__( 'Tag service is temporarily unavailable', 'tagcore' ),
				__( 'We cannot check this ForgeTag right now. Please try again in a moment.', 'tagcore' ),
			),
			PublicTagPageState::ACTIVATION_UNAVAILABLE => array(
				__( 'Tag activation', 'tagcore' ),
				__( 'This Tag is not ready to activate', 'tagcore' ),
				__( 'Activation is temporarily unavailable. Please try again later.', 'tagcore' ),
			),
			PublicTagPageState::ACTIVATION_ENTRY => array(
				__( 'Tag activation', 'tagcore' ),
				__( 'Activate your ForgeTag', 'tagcore' ),
				__( 'This Tag is ready for its owner. Secure email verification is the next step.', 'tagcore' ),
			),
			PublicTagPageState::OWNER_ENTRY => array(
				__( 'Your ForgeTag', 'tagcore' ),
				__( 'This ForgeTag is yours', 'tagcore' ),
				__( 'Activation is complete. You are signed in as the current owner and can manage this Tag securely.', 'tagcore' ),
			),
			PublicTagPageState::FINDER_ENTRY => array(
				__( 'Found an item?', 'tagcore' ),
				__( 'Thank you for helping return this item', 'tagcore' ),
				__( 'Send a private recovery report without sharing either party\'s email address.', 'tagcore' ),
			),
			PublicTagPageState::FINDER_UNAVAILABLE => array(
				__( 'Tag recovery', 'tagcore' ),
				__( 'Owner contact is temporarily unavailable', 'tagcore' ),
				__( 'This ForgeTag is registered, but private contact is paused. Please try again later.', 'tagcore' ),
			),
			PublicTagPageState::SUSPENDED => array(
				__( 'Tag recovery', 'tagcore' ),
				__( 'This Tag service is suspended', 'tagcore' ),
				__( 'ForgeTag cannot provide activation or owner contact for this Tag right now.', 'tagcore' ),
			),
			PublicTagPageState::RETIRED => array(
				__( 'Tag recovery', 'tagcore' ),
				__( 'This Tag is no longer in service', 'tagcore' ),
				__( 'This ForgeTag has been permanently retired and cannot be activated or contacted.', 'tagcore' ),
			),
		};

		$owner_action = PublicTagPageState::OWNER_ENTRY === $page->state && null !== $tag_id;

		return new PublicTagPageView(
			'returntag-public--' . str_replace( '_', '-', $page->state->value ),
			$page->state,
			$copy[0],
			$copy[1],
			$copy[2],
			$owner_action ? __( 'Manage this tag', 'tagcore' ) : __( 'Return to homepage', 'tagcore' ),
			$owner_action ? $this->account_urls->tag( $tag_id ) : home_url( '/' ),
			$this->product_type_label( $page->tag_type ),
			$page->public_label,
			$page->lost_mode,
			$page->lost_message,
			$activation_form,
			$this->smart_tag_guide( $page ),
			$finder_report_form
		);
	}

	/**
	 * Build the static Smart Tag boundary guide for eligible activation only.
	 *
	 * @param PublicTagPage $page Privacy-minimized Application view model.
	 */
	private function smart_tag_guide( PublicTagPage $page ): ?SmartTagGuideView {
		if (
			PublicTagPageState::ACTIVATION_ENTRY !== $page->state
			|| TagType::SMART_TAG !== $page->tag_type
		) {
			return null;
		}

		return new SmartTagGuideView(
			__( 'Two separate recovery systems', 'tagcore' ),
			__( 'Your Smart Tag uses two separate recovery systems. Location tracking is managed in Apple Find My or the compatible finding app. ForgeTag does not access your Apple, Google, or location data. Activate QR recovery below so anyone with a phone can privately contact you.', 'tagcore' ),
			__( 'Smart finding network', 'tagcore' ),
			__( 'Pairing, location, sound, and device features stay in Apple Find My or the compatible finding app.', 'tagcore' ),
			__( 'ForgeTag QR recovery', 'tagcore' ),
			__( 'QR recovery works independently and lets anyone with a phone contact you privately.', 'tagcore' ),
			__( 'ForgeTag does not verify pairing or read Apple, Google, device, battery, or location data.', 'tagcore' )
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
