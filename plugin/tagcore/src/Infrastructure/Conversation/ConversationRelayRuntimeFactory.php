<?php
/**
 * Conversation relay composition factory.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Infrastructure\Conversation;

use ReturnTag\TagCore\Application\Conversation\ApplyConversationSafetyAction;
use ReturnTag\TagCore\Application\Conversation\ConvergeConversationDispatch;
use ReturnTag\TagCore\Application\Conversation\DispatchConversationMessage;
use ReturnTag\TagCore\Application\Conversation\EnsureConversationAccess;
use ReturnTag\TagCore\Application\Conversation\ExchangeConversationLink;
use ReturnTag\TagCore\Application\Conversation\ReadConversationThread;
use ReturnTag\TagCore\Application\Conversation\SubmitConversationMessage;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayEventIdentityPolicy;
use ReturnTag\TagCore\Application\Persistence\DenyAllEventMetadataPolicy;
use ReturnTag\TagCore\Infrastructure\Email\WordPressConversationRelayEmailSender;
use ReturnTag\TagCore\Infrastructure\Email\WordPressConversationRelayLinkBuilder;
use ReturnTag\TagCore\Infrastructure\Email\WordPressConversationRelayOwnerResolver;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbConversationRelayStore;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbEventRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerConversationRelayScheduler;
use ReturnTag\TagCore\Infrastructure\Security\ConversationRelaySecrets;
use ReturnTag\TagCore\Infrastructure\Security\FinderEmailVerificationSecrets;
use ReturnTag\TagCore\Infrastructure\Security\SodiumConversationRelayProtector;
use ReturnTag\TagCore\Infrastructure\Security\SodiumFinderEmailProtector;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionConversationMessageRateLimiter;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionFinderEmailRateLimiter;
use ReturnTag\TagCore\Infrastructure\SystemClock;
use ReturnTag\TagCore\Infrastructure\WordPressOptionFeatureFlagReader;
use Throwable;
use wpdb;

/** Returns null unless both independent relay keys and Finder email keys load. */
final class ConversationRelayRuntimeFactory {
	/**
	 * Compose the relay or fail closed.
	 *
	 * @param wpdb $database Database adapter.
	 */
	public static function create( wpdb $database ): ?ConversationRelayRuntime {
		try {
			$gateway   = new WpdbGateway( $database );
			$tables    = new TableNames( $database->prefix );
			$dates     = new DatabaseDateTimeCodec();
			$events    = new WpdbEventRepository( $gateway, $tables, $dates, new DenyAllEventMetadataPolicy(), new ConversationRelayEventIdentityPolicy() );
			$store     = new WpdbConversationRelayStore( $gateway, $tables, $dates, new WpdbTransactionManager( $database ), $events );
			$protector = new SodiumConversationRelayProtector( ConversationRelaySecrets::load() );
			$finder    = new SodiumFinderEmailProtector( FinderEmailVerificationSecrets::load() );
			$scheduler = new ActionSchedulerConversationRelayScheduler();
			$clock     = new SystemClock();
			$flags     = new WordPressOptionFeatureFlagReader();
			return new ConversationRelayRuntime(
				new EnsureConversationAccess( $store, $protector, $scheduler, $clock ),
				new ExchangeConversationLink( $store, $protector, $clock ),
				new ReadConversationThread( $store, $protector, $clock ),
				new SubmitConversationMessage( $flags, $store, $protector, new WordPressOptionConversationMessageRateLimiter( new WordPressOptionFinderEmailRateLimiter( $database, get_current_blog_id() ) ), $scheduler, $clock ),
				new DispatchConversationMessage( $flags, $store, $protector, $finder, new WordPressConversationRelayOwnerResolver(), new WordPressConversationRelayEmailSender(), new WordPressConversationRelayLinkBuilder(), $clock ),
				new ConvergeConversationDispatch( $store, $scheduler, $clock ),
				new ApplyConversationSafetyAction( $store, $protector, $clock )
			);
		} catch ( Throwable ) {
			return null; }
	}
}
