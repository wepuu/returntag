<?php
/**
 * WordPress Owner Account route adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

use ReturnTag\TagCore\Application\Account\OwnerConversationAccessState;
use ReturnTag\TagCore\Application\Account\OwnerConversationCollection;
use ReturnTag\TagCore\Application\Account\OwnerTagAccessState;
use ReturnTag\TagCore\Application\Account\ReadOwnerConversations;
use ReturnTag\TagCore\Application\Account\ReadOwnerTag;
use ReturnTag\TagCore\Application\Account\ReadOwnerTags;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Pagination\TagCursor;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\PublicSite\TagEntryLinkBlock;
use RuntimeException;
use Throwable;
use WP;
use WP_Rewrite;

/**
 * Registers and serves the theme-independent Owner Account routes.
 */
final class AccountRouteController {
	public const ROUTE_QUERY_VAR = 'returntag_account_route';

	public const TAG_QUERY_VAR = 'returntag_account_tag';

	public const SIGN_IN_PATTERN = '^account/sign-in/?$';

	public const OVERVIEW_PATTERN = '^account/?$';

	public const TAG_PATTERN = '^account/tags/([23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6})/?$';

	public const CONVERSATIONS_PATTERN = '^account/conversations/?$';

	/**
	 * Create the Account route adapter.
	 *
	 * @param string                          $plugin_dir Absolute TagCore directory.
	 * @param FeatureFlagReader               $feature_flags Operational controls.
	 * @param AuthenticatedSession            $session Server-side WordPress session.
	 * @param ReadOwnerTags                   $list_tags Current-Owner list use case.
	 * @param ReadOwnerTag                    $read_tag Current-Owner detail use case.
	 * @param ReadOwnerConversations          $read_conversations Current-Owner Conversation reader.
	 * @param AccountSignInFormHandler        $sign_in Passwordless form boundary.
	 * @param AccountTagMutationFormHandler   $tag_mutations Owner Tag form boundary.
	 * @param AccountConversationFormHandler  $conversation_form Conversation continuation boundary.
	 * @param AccountSecureReplySessionCookie $session_cookie Secure Reply session cookie.
	 * @param AccountTemplateRenderer         $renderer Standalone renderer.
	 * @param AccountUrlProvider              $urls Same-site URLs.
	 * @param AccountResponsePolicy           $responses HTTP response policy.
	 */
	public function __construct(
		private readonly string $plugin_dir,
		private readonly FeatureFlagReader $feature_flags,
		private readonly AuthenticatedSession $session,
		private readonly ReadOwnerTags $list_tags,
		private readonly ReadOwnerTag $read_tag,
		private readonly ReadOwnerConversations $read_conversations,
		private readonly AccountSignInFormHandler $sign_in,
		private readonly AccountTagMutationFormHandler $tag_mutations,
		private readonly AccountConversationFormHandler $conversation_form,
		private readonly AccountSecureReplySessionCookie $session_cookie,
		private readonly AccountTemplateRenderer $renderer,
		private readonly AccountUrlProvider $urls,
		private readonly AccountResponsePolicy $responses
	) {
	}

	/** Register request-time WordPress hooks. */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_filter( 'redirect_canonical', array( $this, 'disable_canonical_redirect' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'prepare_response' ), 0 );
	}

	/** Register the four closed Account paths. */
	public function register_rewrite_rules(): void {
		add_rewrite_rule( self::SIGN_IN_PATTERN, 'index.php?' . self::ROUTE_QUERY_VAR . '=sign-in', 'top' );
		add_rewrite_rule( self::TAG_PATTERN, 'index.php?' . self::ROUTE_QUERY_VAR . '=tag&' . self::TAG_QUERY_VAR . '=$matches[1]', 'top' );
		add_rewrite_rule( self::CONVERSATIONS_PATTERN, 'index.php?' . self::ROUTE_QUERY_VAR . '=conversations', 'top' );
		add_rewrite_rule( self::OVERVIEW_PATTERN, 'index.php?' . self::ROUTE_QUERY_VAR . '=overview', 'top' );
	}

	/** Remove Account rules before a lifecycle flush. */
	public function unregister_rewrite_rules(): void {
		global $wp_rewrite;

		if ( ! $wp_rewrite instanceof WP_Rewrite ) {
			return;
		}

		foreach ( array( self::SIGN_IN_PATTERN, self::TAG_PATTERN, self::CONVERSATIONS_PATTERN, self::OVERVIEW_PATTERN ) as $pattern ) {
			unset( $wp_rewrite->extra_rules_top[ $pattern ] );
			unset( $wp_rewrite->extra_rules[ $pattern ] );
		}
	}

	/**
	 * Add internal Account route variables.
	 *
	 * @param array $query_vars Existing query variables.
	 * @phpstan-param list<string> $query_vars
	 * @return list<string>
	 */
	public function register_query_vars( array $query_vars ): array {
		$query_vars[] = self::ROUTE_QUERY_VAR;
		$query_vars[] = self::TAG_QUERY_VAR;

		return array_values( array_unique( $query_vars ) );
	}

	/**
	 * Keep WordPress canonicalization from competing with Account routes.
	 *
	 * @param string|false $redirect_url Proposed canonical URL.
	 * @param string       $requested_url Requested URL.
	 */
	public function disable_canonical_redirect( string|false $redirect_url, string $requested_url ): string|false {
		unset( $requested_url );

		return null !== $this->route() ? false : $redirect_url;
	}

	/** Validate, redirect, or render one Account response. */
	public function prepare_response(): void {
		$route = $this->route();

		if ( null === $route ) {
			return;
		}

		$method = $this->request_method();
		$this->send_headers( $method );

		if (
			! in_array( $method, array( 'GET', 'HEAD', 'POST' ), true )
			|| ( 'POST' === $method && ! in_array( $route, array( AccountRoute::SIGN_IN, AccountRoute::TAG, AccountRoute::CONVERSATIONS ), true ) )
		) {
			status_header( 405 );
			$this->finish_head_or_die( $method, __( 'This Account action is unavailable.', 'tagcore' ) );
		}

		if ( ! $this->feature_flags->is_enabled( FeatureFlag::OWNER_ACCOUNT ) ) {
			status_header( 503 );
			$this->render( $method, $route, new AccountFormResult( AccountFormState::UNAVAILABLE ) );
		}

		if ( AccountRoute::SIGN_IN === $route ) {
			if ( null !== $this->session->current_user_id() ) {
				wp_safe_redirect( $this->urls->overview(), 303, 'TagCore' );
				exit;
			}

			$form = 'POST' === $method ? $this->sign_in->submit() : new AccountFormResult( AccountFormState::READY );

			if ( AccountFormState::AUTHENTICATED === $form->state ) {
				wp_safe_redirect( $this->urls->overview(), 303, 'TagCore' );
				exit;
			}

			$this->render( $method, $route, $form );
		}

		if ( AccountRoute::OVERVIEW === $route ) {
			try {
				$collection = $this->list_tags->execute( $this->cursor() );
			} catch ( Throwable ) {
				status_header( 503 );
				$this->render( $method, $route, new AccountFormResult( AccountFormState::UNAVAILABLE ) );
			}

			if ( OwnerTagAccessState::AUTHENTICATION_REQUIRED === $collection->state ) {
				wp_safe_redirect( $this->urls->sign_in(), 303, 'TagCore' );
				exit;
			}

			if ( OwnerTagAccessState::READY !== $collection->state ) {
				status_header( 404 );
			}

			$this->render( $method, $route, new AccountFormResult( AccountFormState::READY ), $collection );
		}

		if ( AccountRoute::CONVERSATIONS === $route ) {
			$form_result = 'POST' === $method
				? $this->conversation_form->submit()
				: new AccountConversationFormResult();

			if ( null !== $form_result->session ) {
				if ( $this->session_cookie->set( $form_result->session ) ) {
					wp_safe_redirect( $this->urls->secure_reply(), 303, 'TagCore' );
					exit;
				}

				$form_result = new AccountConversationFormResult( AccountConversationFeedback::UNAVAILABLE );
			}

			try {
				$conversations = $this->read_conversations->execute();
			} catch ( Throwable ) {
				status_header( 503 );
				$this->render( $method, $route, new AccountFormResult( AccountFormState::UNAVAILABLE ) );
			}

			if ( OwnerConversationAccessState::AUTHENTICATION_REQUIRED === $conversations->state ) {
				wp_safe_redirect( $this->urls->sign_in(), 303, 'TagCore' );
				exit;
			}

			if ( OwnerConversationAccessState::READY !== $conversations->state ) {
				status_header( 404 );
			}

			$this->render(
				$method,
				$route,
				new AccountFormResult( AccountFormState::READY ),
				null,
				null,
				null,
				$conversations,
				$form_result->feedback
			);
		}

		$tag_id = $this->tag_id();

		if ( null === $tag_id ) {
			status_header( 404 );
			$this->render( $method, $route, new AccountFormResult( AccountFormState::UNAVAILABLE ) );
		}

		$tag_feedback = 'POST' === $method
			? $this->tag_mutations->submit( $tag_id )
			: new AccountTagMutationFeedback();

		try {
			$detail = $this->read_tag->execute( $tag_id );
		} catch ( Throwable ) {
			status_header( 503 );
			$this->render( $method, $route, new AccountFormResult( AccountFormState::UNAVAILABLE ) );
		}

		if ( OwnerTagAccessState::AUTHENTICATION_REQUIRED === $detail->state ) {
			wp_safe_redirect( $this->urls->sign_in(), 303, 'TagCore' );
			exit;
		}

		if ( OwnerTagAccessState::READY !== $detail->state ) {
			status_header( 404 );
		}

		$this->render( $method, $route, new AccountFormResult( AccountFormState::READY ), null, $detail, $tag_feedback );
	}

	/** Return the closed Account route for the current matched rule. */
	public function route(): ?AccountRoute {
		global $wp;

		if ( ! $wp instanceof WP || ! in_array( $wp->matched_rule, array( self::SIGN_IN_PATTERN, self::TAG_PATTERN, self::CONVERSATIONS_PATTERN, self::OVERVIEW_PATTERN ), true ) ) {
			return null;
		}

		$value = get_query_var( self::ROUTE_QUERY_VAR, null );

		return is_string( $value ) ? AccountRoute::tryFrom( $value ) : null;
	}

	/** Return the canonical detail Tag ID for the current route. */
	private function tag_id(): ?TagId {
		$value = get_query_var( self::TAG_QUERY_VAR, null );

		if ( ! is_string( $value ) ) {
			return null;
		}

		try {
			return TagId::from_canonical( $value );
		} catch ( \InvalidArgumentException ) {
			return null;
		}
	}

	/** Return one validated read-only pagination cursor. */
	private function cursor(): ?TagCursor {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only bounded pagination selector.
		$status = isset( $_GET['after_status'] ) && is_string( $_GET['after_status'] ) ? wp_unslash( $_GET['after_status'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only bounded pagination selector.
		$tag = isset( $_GET['after_tag'] ) && is_string( $_GET['after_tag'] ) ? wp_unslash( $_GET['after_tag'] ) : '';

		if ( '' === $status && '' === $tag ) {
			return null;
		}

		$tag_status = TagStatus::tryFrom( $status );

		if ( null === $tag_status ) {
			return null;
		}

		try {
			return new TagCursor( $tag_status, TagId::from_canonical( $tag )->value );
		} catch ( \InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * Enqueue local styles and render one Account response.
	 *
	 * @param string                                                         $method Bounded request method.
	 * @param AccountRoute                                                   $route Closed Account route.
	 * @param AccountFormResult                                              $form Safe form feedback.
	 * @param \ReturnTag\TagCore\Application\Account\OwnerTagCollection|null $collection Optional Owner Tag page.
	 * @param \ReturnTag\TagCore\Application\Account\OwnerTagDetail|null     $detail Optional Owner Tag detail.
	 * @param AccountTagMutationFeedback|null                                $tag_feedback Optional mutation feedback.
	 * @param OwnerConversationCollection|null                               $conversations Optional current-Owner summaries.
	 * @param AccountConversationFeedback                                    $conversation_feedback Safe Conversation feedback.
	 */
	private function render(
		string $method,
		AccountRoute $route,
		AccountFormResult $form,
		?\ReturnTag\TagCore\Application\Account\OwnerTagCollection $collection = null,
		?\ReturnTag\TagCore\Application\Account\OwnerTagDetail $detail = null,
		?AccountTagMutationFeedback $tag_feedback = null,
		?OwnerConversationCollection $conversations = null,
		AccountConversationFeedback $conversation_feedback = AccountConversationFeedback::NONE
	): never {
		if ( 'HEAD' === $method ) {
			exit;
		}

		TagEntryLinkBlock::register_assets( $this->plugin_dir );
		wp_enqueue_style( TagEntryLinkBlock::STYLE_HANDLE );

		try {
			$this->renderer->render( $route, $form, $collection, $detail, $tag_feedback, $conversations, $conversation_feedback );
		} catch ( RuntimeException ) {
			wp_die( esc_html__( 'ForgeTag Account is temporarily unavailable.', 'tagcore' ), esc_html__( 'Account unavailable', 'tagcore' ), array( 'response' => 500 ) );
		}

		exit;
	}

	/**
	 * Emit approved privacy-safe response headers.
	 *
	 * @param string $method Bounded request method.
	 */
	private function send_headers( string $method ): void {
		foreach ( $this->responses->headers( $method ) as $name => $value ) {
			header( $name . ': ' . $value, true );
		}
	}

	/**
	 * Finish one unsupported method without rendering unsafe context.
	 *
	 * @param string $method Bounded request method.
	 * @param string $message Translated generic message.
	 */
	private function finish_head_or_die( string $method, string $message ): never {
		if ( 'HEAD' === $method ) {
			exit;
		}

		wp_die( esc_html( $message ), esc_html__( 'Account unavailable', 'tagcore' ), array( 'response' => 405 ) );
	}

	/** Return one bounded uppercase request-method token. */
	private function request_method(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Server method is checked against a closed list.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( $_SERVER['REQUEST_METHOD'] )
			: 'GET';

		return 1 === preg_match( '/^[A-Z]+$/D', $method ) ? $method : 'GET';
	}
}
