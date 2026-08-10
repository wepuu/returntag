<?php
/**
 * Conversation Message limiter adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Infrastructure\RateLimit;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Conversation\ConversationMessageRateLimiter;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
/** Reuses the atomic site-scoped fixed-window engine with relay-specific keyed inputs. */
final readonly class WordPressOptionConversationMessageRateLimiter implements ConversationMessageRateLimiter {
	/**
	 * Create the relay limiter.
	 *
	 * @param WordPressOptionFinderEmailRateLimiter $limiter Atomic limiter.
	 */
	public function __construct( private WordPressOptionFinderEmailRateLimiter $limiter ) {}
	/**
	 * Reserve keyed request budgets.
	 *
	 * @param LookupDigest      $session Session lookup.
	 * @param LookupDigest      $peer Peer lookup.
	 * @param int               $conversation_id Conversation identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function reserve( LookupDigest $session, LookupDigest $peer, int $conversation_id, DateTimeImmutable $now ): bool {
		return $this->limiter->reserve_conversation_message( $session, $peer, $conversation_id, $now );}
}
