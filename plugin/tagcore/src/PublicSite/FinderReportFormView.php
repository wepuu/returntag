<?php
/**
 * Render-ready Finder Report form values.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

/** Contains only transport tokens and a privacy-safe state. */
final readonly class FinderReportFormView {
	/**
	 * Create the view model.
	 *
	 * @param string                   $action_url Canonical same-site action.
	 * @param string                   $nonce WordPress nonce.
	 * @param string                   $submission_token Signed idempotency token.
	 * @param FinderReportFormState    $state Privacy-safe form state.
	 * @param FinderEmailFormView|null $email_form Optional private continuation form.
	 */
	public function __construct(
		public string $action_url,
		public string $nonce,
		public string $submission_token,
		public FinderReportFormState $state,
		public ?FinderEmailFormView $email_form = null
	) {
	}
}
