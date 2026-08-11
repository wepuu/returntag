<?php
/**
 * Render-ready Owner Account page data.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

use ReturnTag\TagCore\Application\Account\OwnerTagCollection;
use ReturnTag\TagCore\Application\Account\OwnerTagDetail;
use ReturnTag\TagCore\Application\Account\OwnerConversationCollection;

/**
 * Immutable presentation data for one Account response.
 */
final readonly class AccountPageView {
	/**
	 * Create one render-ready Account view.
	 *
	 * @param AccountRoute                     $route Closed Account route.
	 * @param string                           $title Translated page title.
	 * @param string                           $action_url Same-site form action.
	 * @param string                           $overview_url Same-site overview URL.
	 * @param AccountUrlProvider               $urls Same-site URL provider.
	 * @param string                           $nonce Anonymous sign-in Nonce.
	 * @param AccountFormResult                $form Safe form feedback.
	 * @param string                           $tag_nonce Current Tag mutation Nonce.
	 * @param AccountTagMutationFeedback       $tag_feedback Safe Tag mutation feedback.
	 * @param OwnerTagCollection|null          $collection Optional current-Owner collection.
	 * @param OwnerTagDetail|null              $detail Optional current-Owner detail.
	 * @param string                           $conversation_nonce Conversation continuation Nonce.
	 * @param AccountConversationFeedback      $conversation_feedback Safe Conversation feedback.
	 * @param OwnerConversationCollection|null $conversations Optional current-Owner summaries.
	 */
	public function __construct(
		public AccountRoute $route,
		public string $title,
		public string $action_url,
		public string $overview_url,
		public AccountUrlProvider $urls,
		public string $nonce,
		public AccountFormResult $form,
		public string $tag_nonce,
		public AccountTagMutationFeedback $tag_feedback,
		public ?OwnerTagCollection $collection = null,
		public ?OwnerTagDetail $detail = null,
		public string $conversation_nonce = '',
		public AccountConversationFeedback $conversation_feedback = AccountConversationFeedback::NONE,
		public ?OwnerConversationCollection $conversations = null
	) {
	}
}
