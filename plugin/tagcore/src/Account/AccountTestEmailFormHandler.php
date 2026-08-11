<?php
/**
 * Owner Test Email form boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

use ReturnTag\TagCore\Application\Account\OwnerTestEmailResult;
use ReturnTag\TagCore\Application\Account\RequestOwnerTestEmail;
use Throwable;

/** Validates and submits one same-site Test Email request. */
final readonly class AccountTestEmailFormHandler {
	public const ACTION_FIELD = 'returntag_account_action';
	public const ACTION       = 'send_test_email';
	public const NONCE_FIELD  = 'returntag_account_test_email_nonce';
	public const NONCE_ACTION = 'returntag_account_test_email';
	/**
	 * Create the form boundary.
	 *
	 * @param RequestOwnerTestEmail   $request Test Email use case.
	 * @param AccountFormRequestGuard $guard Same-site request guard.
	 */
	public function __construct( private RequestOwnerTestEmail $request, private AccountFormRequestGuard $guard ) {}

	/** Submit one closed Test Email action. */
	public function submit(): OwnerTestEmailResult {
		if ( self::ACTION !== $this->guard->post_string( self::ACTION_FIELD, 64 ) ) {
			return OwnerTestEmailResult::UNAVAILABLE;
		}
		if ( ! $this->guard->is_same_site() || ! $this->guard->valid_nonce( self::NONCE_FIELD, self::NONCE_ACTION ) ) {
			return OwnerTestEmailResult::UNAVAILABLE;
		}
		try {
			return $this->request->execute( $this->guard->direct_peer_ip() );
		} catch ( Throwable ) {
			return OwnerTestEmailResult::UNAVAILABLE;
		}
	}
}
