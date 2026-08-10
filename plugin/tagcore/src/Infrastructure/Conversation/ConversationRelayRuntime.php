<?php
/**
 * Composed Conversation relay runtime.
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

/** Holds one complete relay composition. */
final readonly class ConversationRelayRuntime {
	/**
	 * Create the runtime container.
	 *
	 * @param EnsureConversationAccess      $ensure_access Access service.
	 * @param ExchangeConversationLink      $exchange_link Exchange service.
	 * @param ReadConversationThread        $read_thread Reader.
	 * @param SubmitConversationMessage     $submit_message Submission service.
	 * @param DispatchConversationMessage   $dispatch Dispatch service.
	 * @param ConvergeConversationDispatch  $converge Recovery service.
	 * @param ApplyConversationSafetyAction $safety_action Participant safety service.
	 */
	public function __construct(
		public EnsureConversationAccess $ensure_access,
		public ExchangeConversationLink $exchange_link,
		public ReadConversationThread $read_thread,
		public SubmitConversationMessage $submit_message,
		public DispatchConversationMessage $dispatch,
		public ConvergeConversationDispatch $converge,
		public ApplyConversationSafetyAction $safety_action
	) {}
}
