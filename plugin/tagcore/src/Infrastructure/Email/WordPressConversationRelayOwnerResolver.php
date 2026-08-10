<?php
/**
 * WordPress Owner email resolver.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\Conversation\ConversationRelayOwnerResolver;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
/** Resolves only the current internal WordPress user email. */
final class WordPressConversationRelayOwnerResolver implements ConversationRelayOwnerResolver {
	/**
	 * Resolve one Owner email.
	 *
	 * @param int $owner_id Owner identifier.
	 */
	public function resolve( int $owner_id ): ?EmailAddress {
		$user = get_user_by( 'id', $owner_id );
		if ( false === $user ) {
			return null;
		} try {
			return new EmailAddress( (string) $user->user_email );
		} catch ( \Throwable ) {
			return null;} }
}
