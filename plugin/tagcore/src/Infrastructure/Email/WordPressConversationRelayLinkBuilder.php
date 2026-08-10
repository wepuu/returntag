<?php
/**
 * Same-site secure reply URL builder.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\Conversation\ConversationRelayLinkBuilder;
/** Builds the same-site URL for a raw in-memory Token. */
final class WordPressConversationRelayLinkBuilder implements ConversationRelayLinkBuilder {
	/**
	 * Build one same-site URL.
	 *
	 * @param string $token Raw Token.
	 */
	public function build( string $token ): string {
		return add_query_arg( 'token', rawurlencode( $token ), home_url( '/secure-reply/' ) ); }
}
