<?php
/**
 * Owner Test Email Worker use case.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use ReturnTag\TagCore\Application\Auth\AuthenticatedUserEmailReader;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;

/** Claims and submits one current-Owner test message at most once. */
final readonly class DispatchOwnerTestEmail {
	/**
	 * Create the Worker service.
	 *
	 * @param FeatureFlagReader                $flags Operational controls.
	 * @param AuthenticatedUserEmailReader     $emails Server-side email reader.
	 * @param OwnerTestEmailDispatchClaimStore $claims Durable at-most-once claims.
	 * @param OwnerTestEmailSender             $sender WordPress mail boundary.
	 * @param EventRepository                  $events Privacy-safe Event store.
	 * @param Clock                            $clock UTC clock.
	 */
	public function __construct( private FeatureFlagReader $flags, private AuthenticatedUserEmailReader $emails, private OwnerTestEmailDispatchClaimStore $claims, private OwnerTestEmailSender $sender, private EventRepository $events, private Clock $clock ) {}
	/**
	 * Dispatch one internal Event and Owner pair.
	 *
	 * @param int $event_id Request Event identifier.
	 * @param int $owner_id Server-derived Owner identifier.
	 */
	public function execute( int $event_id, int $owner_id ): void {
		if ( $event_id < 1 || $owner_id < 1 || ! $this->flags->is_enabled( FeatureFlag::OWNER_ACCOUNT ) || ! $this->flags->is_enabled( FeatureFlag::EMAIL_DISPATCH ) ) {
			return; }
		$now = $this->clock->now();
		if ( ! $this->claims->claim( $event_id, $now ) ) {
			return; }
		$recipient = $this->emails->find( $owner_id );
		$accepted  = null !== $recipient && $this->sender->send( $recipient, hash( 'sha256', "returntag:owner-test-email:v1\0" . $event_id ) );
		$this->events->append( new NewEventRecord( $accepted ? 'owner_test_email_accepted' : 'owner_test_email_failed', 'user', $owner_id, 'user', (string) $owner_id, $accepted ? 'accepted_by_mailer' : 'failed', (string) $event_id, EventMetadata::none(), $now ) );
	}
}
