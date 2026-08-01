<?php
/**
 * Manual Tag entry presentation view.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

/**
 * Contains escaped-at-render presentation values only.
 */
final readonly class ManualTagEntryView {
	/**
	 * Create one render-ready view.
	 *
	 * @param TagEntryIntent          $intent Closed presentation intent.
	 * @param string                  $title Translated title.
	 * @param string                  $introduction Translated guidance.
	 * @param string                  $action_url Same-site form action.
	 * @param string                  $nonce Anonymous WordPress nonce.
	 * @param ManualTagEntryFormState $state Safe form state.
	 * @param string                  $form_id Unique accessible form identifier.
	 * @param bool                    $standalone Whether the view is standalone.
	 */
	public function __construct(
		public TagEntryIntent $intent,
		public string $title,
		public string $introduction,
		public string $action_url,
		public string $nonce,
		public ManualTagEntryFormState $state,
		public string $form_id,
		public bool $standalone = true
	) {
	}
}
