<?php
/**
 * Render-ready activation OTP form values.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

/**
 * Contains no email address or Tag identifier outside the canonical action URL.
 */
final readonly class ActivationOtpFormView {
	/**
	 * Create the form view.
	 *
	 * @param string                 $action_url Canonical same-site form target.
	 * @param string                 $nonce WordPress CSRF token.
	 * @param ActivationOtpFormState $state Safe feedback state.
	 */
	public function __construct(
		public string $action_url,
		public string $nonce,
		public ActivationOtpFormState $state
	) {
	}
}
