<?php
/**
 * Transfer acceptance form boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

use ReturnTag\TagCore\Application\Account\AcceptOwnerTransfer;
use ReturnTag\TagCore\Application\Account\OwnerLifecycleResult;
use Throwable;

/** Requires an explicit same-site POST before acceptance. */
final readonly class AccountTransferFormHandler {
	public const NONCE_FIELD  = 'returntag_transfer_nonce';
	public const NONCE_ACTION = 'returntag_accept_transfer';
	/**
	 * Create the acceptance boundary.
	 *
	 * @param AcceptOwnerTransfer|null   $accept Configured acceptance service.
	 * @param AccountTransferTokenCookie $cookie HttpOnly Token cookie.
	 * @param AccountFormRequestGuard    $guard Same-site request guard.
	 */
	public function __construct( private ?AcceptOwnerTransfer $accept, private AccountTransferTokenCookie $cookie, private AccountFormRequestGuard $guard ) {}

	/** Submit one explicit transfer acceptance. */
	public function submit(): OwnerLifecycleResult {
		if ( null === $this->accept || ! $this->guard->is_same_site() || ! $this->guard->valid_nonce( self::NONCE_FIELD, self::NONCE_ACTION ) ) {
			return OwnerLifecycleResult::UNAVAILABLE;
		}

		$token = $this->cookie->read();
		if ( null === $token ) {
			return OwnerLifecycleResult::UNAVAILABLE;
		}

		try {
			$result = $this->accept->execute( $token );
			if ( OwnerLifecycleResult::ACCEPTED === $result ) {
				$this->cookie->clear();
			}

			return $result;
		} catch ( Throwable ) {
			return OwnerLifecycleResult::UNAVAILABLE;
		}
	}
}
