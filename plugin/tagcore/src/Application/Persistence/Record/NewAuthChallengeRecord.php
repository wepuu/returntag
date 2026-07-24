<?php
/**
 * New authentication challenge data.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;

/**
 * Immutable opaque challenge persistence data.
 */
final readonly class NewAuthChallengeRecord {
	/**
	 * Create challenge persistence data.
	 *
	 * @param string                 $purpose Challenge purpose.
	 * @param string                 $subject_type Subject type.
	 * @param string                 $subject_id Opaque subject identifier.
	 * @param EmailCiphertext        $email_ciphertext Opaque encrypted email.
	 * @param LookupDigest           $email_lookup Keyed lookup digest.
	 * @param OtpHash                $code_hash Secure challenge-code hash.
	 * @param int                    $attempt_count Attempt count.
	 * @param int                    $send_count Send count.
	 * @param LookupDigest|null      $ip_hash Optional keyed IP digest.
	 * @param DateTimeImmutable      $expires_at UTC expiry.
	 * @param DateTimeImmutable|null $verified_at UTC verification time.
	 * @param DateTimeImmutable|null $consumed_at UTC consumption time.
	 * @param DateTimeImmutable      $created_at UTC creation time.
	 */
	public function __construct(
		public string $purpose,
		public string $subject_type,
		public string $subject_id,
		public EmailCiphertext $email_ciphertext,
		public LookupDigest $email_lookup,
		public OtpHash $code_hash,
		public int $attempt_count,
		public int $send_count,
		public ?LookupDigest $ip_hash,
		public DateTimeImmutable $expires_at,
		public ?DateTimeImmutable $verified_at,
		public ?DateTimeImmutable $consumed_at,
		public DateTimeImmutable $created_at
	) {
		RecordValidator::ascii( $this->purpose, 32, 'purpose' );
		RecordValidator::ascii( $this->subject_type, 32, 'subject_type' );
		RecordValidator::ascii( $this->subject_id, 191, 'subject_id' );
		RecordValidator::unsigned_int( $this->attempt_count, 'attempt_count' );
		RecordValidator::unsigned_int( $this->send_count, 'send_count' );

		RecordValidator::utc( $this->expires_at, 'expires_at' );
		RecordValidator::nullable_utc( $this->verified_at, 'verified_at' );
		RecordValidator::nullable_utc( $this->consumed_at, 'consumed_at' );
		RecordValidator::utc( $this->created_at, 'created_at' );
	}
}
