<?php
/**
 * Owner Test Email request use case.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use Throwable;

/** Persists an audit Event before scheduling delivery. */
final readonly class RequestOwnerTestEmail {
	/**
	 * Create the request service.
	 *
	 * @param AuthenticatedSession      $session Current WordPress session.
	 * @param FeatureFlagReader         $flags Operational controls.
	 * @param OwnerTestEmailRateLimiter $limiter Durable request budget.
	 * @param EventRepository           $events Privacy-safe Event store.
	 * @param OwnerTestEmailScheduler   $scheduler Identifier-only queue.
	 * @param Clock                     $clock UTC clock.
	 */
	public function __construct( private AuthenticatedSession $session, private FeatureFlagReader $flags, private OwnerTestEmailRateLimiter $limiter, private EventRepository $events, private OwnerTestEmailScheduler $scheduler, private Clock $clock ) {}
	/**
	 * Request one rate-limited current-Owner Test Email.
	 *
	 * @param string $ip_address Direct-peer IP address.
	 */
	public function execute( string $ip_address ): OwnerTestEmailResult {
		$owner_id = $this->session->current_user_id();
		if ( null === $owner_id || ! $this->flags->is_enabled( FeatureFlag::OWNER_ACCOUNT ) || ! $this->flags->is_enabled( FeatureFlag::EMAIL_DISPATCH ) ) {
			return OwnerTestEmailResult::UNAVAILABLE;
		}
		$now = $this->clock->now();
		if ( ! $this->limiter->reserve( $owner_id, $ip_address, $now ) ) {
			return OwnerTestEmailResult::THROTTLED;
		}
		$event = $this->events->append( new NewEventRecord( 'owner_test_email_requested', 'user', $owner_id, 'user', (string) $owner_id, 'queued', null, EventMetadata::none(), $now ) );
		try {
			$this->scheduler->schedule( $event->event_id, $owner_id );
		} catch ( Throwable ) {
			return OwnerTestEmailResult::UNAVAILABLE;
		}
		return OwnerTestEmailResult::ACCEPTED;
	}
}
