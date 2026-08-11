<?php
/**
 * Owner lifecycle persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Account;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Domain\Tag\TagId;

interface OwnerLifecycleStore {
	/**
	 * Persist one pending transfer after current-Owner reauthentication.
	 *
	 * @param TagId             $tag_id Current Tag.
	 * @param int               $owner_id Current Owner identifier.
	 * @param EmailCiphertext   $email Encrypted target email.
	 * @param LookupDigest      $lookup Keyed target email lookup.
	 * @param DateTimeImmutable $expires_at Invitation expiry.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function create_transfer( TagId $tag_id, int $owner_id, EmailCiphertext $email, LookupDigest $lookup, DateTimeImmutable $expires_at, DateTimeImmutable $now ): ?int;

	/**
	 * Claim one invitation before issuing its plaintext Token.
	 *
	 * @param int               $transfer_id Internal Transfer identifier.
	 * @param string            $token_hash SHA-256 Token digest.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @return array{email: EmailCiphertext, lookup: LookupDigest, tag_id: string}|null
	 */
	public function claim_invitation( int $transfer_id, string $token_hash, DateTimeImmutable $now ): ?array;

	/**
	 * Record WordPress mailer acceptance separately from delivery.
	 *
	 * @param int               $transfer_id Internal Transfer identifier.
	 * @param bool              $accepted Whether wp_mail accepted the message.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function finish_invitation( int $transfer_id, bool $accepted, DateTimeImmutable $now ): void;

	/**
	 * Atomically accept a matching pending transfer and revoke prior access.
	 *
	 * @param string            $token_hash SHA-256 Token digest.
	 * @param LookupDigest      $authenticated_email Current user email lookup.
	 * @param int               $new_owner_id Authenticated target Owner.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function accept_transfer( string $token_hash, LookupDigest $authenticated_email, int $new_owner_id, DateTimeImmutable $now ): OwnerLifecycleResult;

	/**
	 * Cancel current pending transfers for one active Owner Tag.
	 *
	 * @param TagId             $tag_id Current Tag.
	 * @param int               $owner_id Current Owner identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function cancel_transfer( TagId $tag_id, int $owner_id, DateTimeImmutable $now ): OwnerLifecycleResult;

	/**
	 * Permanently retire one active current-Owner Tag.
	 *
	 * @param TagId             $tag_id Current Tag.
	 * @param int               $owner_id Current Owner identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function retire( TagId $tag_id, int $owner_id, DateTimeImmutable $now ): OwnerLifecycleResult;
}
