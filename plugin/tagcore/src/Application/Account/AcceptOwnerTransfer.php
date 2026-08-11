<?php
/**
 * Owner Transfer acceptance use case.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use ReturnTag\TagCore\Application\Auth\AccountOtpProtector;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Auth\AuthenticatedUserEmailReader;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;

/** Revalidates the invited authenticated email before atomic acceptance. */
final readonly class AcceptOwnerTransfer {
	/**
	 * Create the acceptance service.
	 *
	 * @param AuthenticatedSession         $session Current WordPress session.
	 * @param AuthenticatedUserEmailReader $emails Server-side email reader.
	 * @param AccountOtpProtector          $protector Email lookup protection.
	 * @param FeatureFlagReader            $flags Operational controls.
	 * @param OwnerLifecycleStore          $store Atomic lifecycle persistence.
	 * @param Clock                        $clock UTC clock.
	 */
	public function __construct( private AuthenticatedSession $session, private AuthenticatedUserEmailReader $emails, private AccountOtpProtector $protector, private FeatureFlagReader $flags, private OwnerLifecycleStore $store, private Clock $clock ) {}
	/**
	 * Accept one structurally valid invitation Token.
	 *
	 * @param string $token Raw one-time invitation Token.
	 */
	public function execute( string $token ): OwnerLifecycleResult {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{43}$/D', $token ) || ! $this->flags->is_enabled( FeatureFlag::OWNER_ACCOUNT ) || ! $this->flags->is_enabled( FeatureFlag::OWNER_LIFECYCLE ) ) {
			return OwnerLifecycleResult::UNAVAILABLE; }
		$user_id = $this->session->current_user_id();
		if ( null === $user_id ) {
			return OwnerLifecycleResult::AUTHENTICATION_REQUIRED; }
		$email = $this->emails->find( $user_id );
		if ( null === $email ) {
			return OwnerLifecycleResult::UNAVAILABLE; }
		return $this->store->accept_transfer( hash( 'sha256', $token ), $this->protector->email_lookup( $email ), $user_id, $this->clock->now() );
	}
}
