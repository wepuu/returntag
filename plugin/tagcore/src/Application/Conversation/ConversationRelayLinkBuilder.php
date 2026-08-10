<?php
/**
 * Conversation continuation link port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Application\Conversation;

/** Builds one same-site Secure Reply URL. */
interface ConversationRelayLinkBuilder {
	/**
	 * Build one URL containing only the raw single-use Token.
	 *
	 * @param string $token Raw Token.
	 */
	public function build( string $token ): string;
}
