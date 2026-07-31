<?php
/**
 * Passwordless WordPress account provisioning boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/**
 * Finds or creates exactly one least-privilege WordPress account.
 */
interface PasswordlessAccountProvisioner {
	/**
	 * Return the reused or newly created WordPress User ID.
	 *
	 * @param EmailAddress      $email Canonical verified email.
	 * @param LookupDigest      $email_lookup Keyed email scope for locking.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function provision(
		EmailAddress $email,
		LookupDigest $email_lookup,
		DateTimeImmutable $now
	): int;
}
