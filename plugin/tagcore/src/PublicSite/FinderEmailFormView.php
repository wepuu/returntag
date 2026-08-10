<?php
/**
 * Finder email verification view model.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

/** Contains only same-site transport tokens and privacy-safe state. */
final readonly class FinderEmailFormView {
	/**
	 * Create the view model.
	 *
	 * @param string               $action_url Canonical same-site action.
	 * @param string               $nonce WordPress nonce.
	 * @param string               $continuation_token Opaque report continuation token.
	 * @param FinderEmailFormState $state Privacy-safe form state.
	 */
	public function __construct( public string $action_url, public string $nonce, public string $continuation_token, public FinderEmailFormState $state ) {
	}
}
