<?php
/**
 * Owner Account standalone template renderer.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

use ReturnTag\TagCore\Application\Account\OwnerTagCollection;
use ReturnTag\TagCore\Application\Account\OwnerTagDetail;
use ReturnTag\TagCore\Application\Account\OwnerConversationCollection;
use ReturnTag\TagCore\Application\Account\OwnerTestEmailResult;
use ReturnTag\TagCore\Application\Account\OwnerLifecycleResult;
use RuntimeException;

/**
 * Maps closed Account state to an escaped standalone template.
 */
final readonly class AccountTemplateRenderer {
	/**
	 * Create the renderer.
	 *
	 * @param string             $plugin_dir Absolute TagCore directory.
	 * @param AccountUrlProvider $urls Same-site URL provider.
	 */
	public function __construct(
		private string $plugin_dir,
		private AccountUrlProvider $urls
	) {
	}

	/**
	 * Render one Account response.
	 *
	 * @param AccountRoute                     $route Closed Account route.
	 * @param AccountFormResult                $form Safe form feedback.
	 * @param OwnerTagCollection|null          $collection Optional Owner Tag page.
	 * @param OwnerTagDetail|null              $detail Optional Owner Tag detail.
	 * @param AccountTagMutationFeedback|null  $tag_feedback Optional Tag mutation feedback.
	 * @param OwnerConversationCollection|null $conversations Optional current-Owner summaries.
	 * @param AccountConversationFeedback      $conversation_feedback Safe Conversation feedback.
	 * @param OwnerTestEmailResult|null        $test_email_result Optional Test Email outcome.
	 * @param OwnerLifecycleResult|null        $lifecycle_result Optional lifecycle outcome.
	 * @throws RuntimeException When the packaged template is unavailable.
	 */
	public function render(
		AccountRoute $route,
		AccountFormResult $form,
		?OwnerTagCollection $collection = null,
		?OwnerTagDetail $detail = null,
		?AccountTagMutationFeedback $tag_feedback = null,
		?OwnerConversationCollection $conversations = null,
		AccountConversationFeedback $conversation_feedback = AccountConversationFeedback::NONE,
		?OwnerTestEmailResult $test_email_result = null,
		?OwnerLifecycleResult $lifecycle_result = null
	): void {
		echo $this->render_to_string( $route, $form, $collection, $detail, $tag_feedback, $conversations, $conversation_feedback, $test_email_result, $lifecycle_result ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template escapes by output context.
	}

	/**
	 * Render one Account response to a testable string.
	 *
	 * @param AccountRoute                     $route Closed Account route.
	 * @param AccountFormResult                $form Safe form feedback.
	 * @param OwnerTagCollection|null          $collection Optional Owner Tag page.
	 * @param OwnerTagDetail|null              $detail Optional Owner Tag detail.
	 * @param AccountTagMutationFeedback|null  $tag_feedback Optional Tag mutation feedback.
	 * @param OwnerConversationCollection|null $conversations Optional current-Owner summaries.
	 * @param AccountConversationFeedback      $conversation_feedback Safe Conversation feedback.
	 * @param OwnerTestEmailResult|null        $test_email_result Optional Test Email outcome.
	 * @param OwnerLifecycleResult|null        $lifecycle_result Optional lifecycle outcome.
	 * @throws RuntimeException When the packaged template is unavailable.
	 */
	public function render_to_string(
		AccountRoute $route,
		AccountFormResult $form,
		?OwnerTagCollection $collection = null,
		?OwnerTagDetail $detail = null,
		?AccountTagMutationFeedback $tag_feedback = null,
		?OwnerConversationCollection $conversations = null,
		AccountConversationFeedback $conversation_feedback = AccountConversationFeedback::NONE,
		?OwnerTestEmailResult $test_email_result = null,
		?OwnerLifecycleResult $lifecycle_result = null
	): string {
		$template = $this->plugin_dir . '/templates/account/account.php';

		if ( ! is_readable( $template ) ) {
			throw new RuntimeException( 'The Owner Account template is unavailable.' );
		}

		$title = match ( $route ) {
			AccountRoute::SIGN_IN => __( 'Sign in to your ForgeTag account', 'tagcore' ),
			AccountRoute::OVERVIEW => __( 'My Tags', 'tagcore' ),
			AccountRoute::TAG => __( 'Tag details', 'tagcore' ),
			AccountRoute::CONVERSATIONS => __( 'Recovery conversations', 'tagcore' ),
			AccountRoute::TRANSFER => __( 'Accept Tag transfer', 'tagcore' ),
		};
		$view = new AccountPageView(
			$route,
			$title,
			AccountRoute::SIGN_IN === $route ? $this->urls->sign_in() : $this->urls->overview(),
			$this->urls->overview(),
			$this->urls,
			wp_create_nonce( AccountSignInFormHandler::NONCE_ACTION ),
			$form,
			null !== $detail && null !== $detail->tag
				? wp_create_nonce( AccountTagMutationFormHandler::NONCE_PREFIX . $detail->tag->data->tag_id )
				: '',
			$tag_feedback ?? new AccountTagMutationFeedback(),
			$collection,
			$detail,
			wp_create_nonce( AccountConversationFormHandler::NONCE_ACTION ),
			$conversation_feedback,
			$conversations,
			$test_email_result,
			wp_create_nonce( AccountTestEmailFormHandler::NONCE_ACTION ),
			$lifecycle_result,
			wp_create_nonce( AccountTransferFormHandler::NONCE_ACTION )
		);

		ob_start();
		require $template;
		$output = ob_get_clean();

		return $output;
	}
}
