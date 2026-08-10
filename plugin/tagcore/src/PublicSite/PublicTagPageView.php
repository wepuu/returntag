<?php
/**
 * Render-ready public Tag page values.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

/**
 * Contains translated strings and already-approved optional public fields.
 */
final readonly class PublicTagPageView {
	/**
	 * Create a render-ready public page.
	 *
	 * @param string                     $body_class HTML body modifier.
	 * @param string                     $eyebrow Short section label.
	 * @param string                     $title Page heading.
	 * @param string                     $message Supporting copy.
	 * @param string                     $action_label Working homepage action label.
	 * @param string                     $action_url Working homepage action URL.
	 * @param string|null                $product_type_label Translated public product type.
	 * @param string|null                $public_label Approved Finder-safe public label.
	 * @param bool                       $lost_mode Whether to render the Lost Mode callout.
	 * @param string|null                $lost_message Approved Finder-safe Lost Mode message.
	 * @param ActivationOtpFormView|null $activation_form Optional activation OTP form.
	 * @param SmartTagGuideView|null     $smart_tag_guide Optional static Smart Tag guide.
	 * @param FinderReportFormView|null  $finder_report_form Optional Finder Report form.
	 */
	public function __construct(
		public string $body_class,
		public string $eyebrow,
		public string $title,
		public string $message,
		public string $action_label,
		public string $action_url,
		public ?string $product_type_label = null,
		public ?string $public_label = null,
		public bool $lost_mode = false,
		public ?string $lost_message = null,
		public ?ActivationOtpFormView $activation_form = null,
		public ?SmartTagGuideView $smart_tag_guide = null,
		public ?FinderReportFormView $finder_report_form = null
	) {
	}
}
