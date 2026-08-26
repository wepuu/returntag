<?php
/**
 * Owner Transfer invitation Worker use case.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use ReturnTag\TagCore\Application\Auth\AccountOtpProtector;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;

/** Issues plaintext Token material only in Worker memory. */
final readonly class DispatchOwnerTransferInvitation {
	/**
	 * Create the invitation Worker.
	 *
	 * @param FeatureFlagReader        $flags Operational controls.
	 * @param OwnerLifecycleStore      $store Transfer persistence.
	 * @param AccountOtpProtector      $protector Target email protection.
	 * @param OwnerTransferEmailSender $email WordPress mail boundary.
	 * @param Clock                    $clock UTC clock.
	 */
	public function __construct( private FeatureFlagReader $flags, private OwnerLifecycleStore $store, private AccountOtpProtector $protector, private OwnerTransferEmailSender $email, private Clock $clock ) {}
	/**
	 * Dispatch one internal Transfer identifier at most once.
	 *
	 * @param int $transfer_id Internal Transfer identifier.
	 */
	public function execute( int $transfer_id ): void {
		if ( $transfer_id < 1 || ! $this->flags->is_enabled( FeatureFlag::OWNER_LIFECYCLE ) || ! $this->flags->is_enabled( FeatureFlag::EMAIL_DISPATCH ) ) {
			return; }
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes random Token bytes as URL-safe text; it does not obfuscate executable code.
		$token = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
		$now   = $this->clock->now();
		$claim = $this->store->claim_invitation( $transfer_id, hash( 'sha256', $token ), $now );
		if ( null === $claim ) {
			return; }
		$recipient = $this->protector->decrypt_email( $claim['email'], $claim['lookup'] );
		$url       = add_query_arg( 'transfer_token', rawurlencode( $token ), home_url( '/account/transfer/' ) );
		$this->store->finish_invitation( $transfer_id, $this->email->send( $recipient, $url, hash( 'sha256', "returntag:owner-transfer:v1\0" . $transfer_id ) ), $now );
		if ( function_exists( 'sodium_memzero' ) ) {
			sodium_memzero( $token ); }
	}
}
