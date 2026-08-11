<?php
/**
 * WordPress database Owner lifecycle adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Account\OwnerLifecycleResult;
use ReturnTag\TagCore\Application\Account\OwnerLifecycleStore;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/** Performs transfer acceptance and retirement with access revocation atomically. */
final readonly class WpdbOwnerLifecycleStore implements OwnerLifecycleStore {
	/**
	 * Create the atomic lifecycle adapter.
	 *
	 * @param WpdbGateway           $db Prepared database gateway.
	 * @param TableNames            $tables Trusted table names.
	 * @param DatabaseDateTimeCodec $dates UTC date codec.
	 * @param TransactionManager    $transactions Transaction boundary.
	 * @param EventRepository       $events Privacy-safe Event store.
	 */
	public function __construct( private WpdbGateway $db, private TableNames $tables, private DatabaseDateTimeCodec $dates, private TransactionManager $transactions, private EventRepository $events ) {}

	/**
	 * Persist one pending Transfer.
	 *
	 * @param TagId             $tag_id Current Tag.
	 * @param int               $owner_id Current Owner identifier.
	 * @param EmailCiphertext   $email Encrypted target email.
	 * @param LookupDigest      $lookup Keyed target email lookup.
	 * @param DateTimeImmutable $expires_at Invitation expiry.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function create_transfer( TagId $tag_id, int $owner_id, EmailCiphertext $email, LookupDigest $lookup, DateTimeImmutable $expires_at, DateTimeImmutable $now ): ?int {
		return $this->transactions->transactional(
			function () use ( $tag_id, $owner_id, $email, $lookup, $expires_at, $now ): ?int {
				$tag = $this->db->row( 'SELECT owner_id FROM %i WHERE tag_id=%s AND owner_id=%d AND tag_status=%s FOR UPDATE', array( $this->tables->tags(), $tag_id->value, $owner_id, TagStatus::ACTIVE->value ) );
				if ( null === $tag ) {
					return null; }
				$this->db->execute( 'UPDATE %i SET transfer_status=%s,cancelled_at=%s,updated_at=%s WHERE tag_id=%s AND transfer_status=%s', array( $this->tables->tag_transfers(), 'cancelled', $this->dates->format( $now ), $this->dates->format( $now ), $tag_id->value, 'pending' ) );
				return $this->db->insert(
					$this->tables->tag_transfers(),
					array(
						'tag_id'                   => $tag_id->value,
						'from_owner_id'            => $owner_id,
						'target_email_ciphertext'  => $email->value,
						'target_email_lookup'      => $lookup->value,
						'transfer_status'          => 'pending',
						'invitation_status'        => 'pending',
						'invitation_attempt_count' => 0,
						'expires_at'               => $this->dates->format( $expires_at ),
						'created_at'               => $this->dates->format( $now ),
						'updated_at'               => $this->dates->format( $now ),
					),
					array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
				);
			}
		);
	}

	/**
	 * Claim one invitation before issuing its Token.
	 *
	 * @param int               $transfer_id Internal Transfer identifier.
	 * @param string            $token_hash SHA-256 Token digest.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @return array{email: EmailCiphertext, lookup: LookupDigest, tag_id: string}|null
	 */
	public function claim_invitation( int $transfer_id, string $token_hash, DateTimeImmutable $now ): ?array {
		return $this->transactions->transactional(
			function () use ( $transfer_id, $token_hash, $now ): ?array {
				$row = $this->db->row( 'SELECT * FROM %i WHERE transfer_id=%d FOR UPDATE', array( $this->tables->tag_transfers(), $transfer_id ) );
				if ( null === $row || 'pending' !== ( $row['transfer_status'] ?? null ) || 'pending' !== ( $row['invitation_status'] ?? null ) || (string) $row['expires_at'] <= $this->dates->format( $now ) ) {
					return null; }
				if ( 1 !== $this->db->execute( 'UPDATE %i SET token_hash=%s,invitation_status=%s,invitation_claimed_at=%s,invitation_attempt_count=1,updated_at=%s WHERE transfer_id=%d AND invitation_status=%s', array( $this->tables->tag_transfers(), $token_hash, 'in_flight', $this->dates->format( $now ), $this->dates->format( $now ), $transfer_id, 'pending' ) ) ) {
					return null; }
				return array(
					'email'  => EmailCiphertext::from_storage( (string) $row['target_email_ciphertext'] ),
					'lookup' => LookupDigest::from_storage( (string) $row['target_email_lookup'] ),
					'tag_id' => (string) $row['tag_id'],
				);
			}
		);
	}

	/**
	 * Record mailer acceptance separately from delivery.
	 *
	 * @param int               $transfer_id Internal Transfer identifier.
	 * @param bool              $accepted Whether wp_mail accepted the message.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function finish_invitation( int $transfer_id, bool $accepted, DateTimeImmutable $now ): void {
		$this->db->execute( 'UPDATE %i SET invitation_status=%s,updated_at=%s WHERE transfer_id=%d AND invitation_status=%s', array( $this->tables->tag_transfers(), $accepted ? 'accepted_by_mailer' : 'mailer_rejected', $this->dates->format( $now ), $transfer_id, 'in_flight' ) );
	}

	/**
	 * Atomically accept a matching pending Transfer.
	 *
	 * @param string            $token_hash SHA-256 Token digest.
	 * @param LookupDigest      $authenticated_email Current user email lookup.
	 * @param int               $new_owner_id Authenticated target Owner.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function accept_transfer( string $token_hash, LookupDigest $authenticated_email, int $new_owner_id, DateTimeImmutable $now ): OwnerLifecycleResult {
		return $this->transactions->transactional(
			function () use ( $token_hash, $authenticated_email, $new_owner_id, $now ): OwnerLifecycleResult {
				$row = $this->db->row( 'SELECT * FROM %i WHERE token_hash=%s FOR UPDATE', array( $this->tables->tag_transfers(), $token_hash ) );
				if ( null === $row || 'pending' !== ( $row['transfer_status'] ?? null ) || 'accepted_by_mailer' !== ( $row['invitation_status'] ?? null ) || ! hash_equals( (string) $row['target_email_lookup'], $authenticated_email->value ) || (string) $row['expires_at'] <= $this->dates->format( $now ) ) {
					return OwnerLifecycleResult::UNAVAILABLE; }
				$tag_id = (string) $row['tag_id'];
				$old    = (int) $row['from_owner_id'];
				if ( 1 !== $this->db->execute( 'UPDATE %i SET owner_id=%d,owner_changed_at=%s,updated_at=%s WHERE tag_id=%s AND owner_id=%d AND tag_status=%s', array( $this->tables->tags(), $new_owner_id, $this->dates->format( $now ), $this->dates->format( $now ), $tag_id, $old, TagStatus::ACTIVE->value ) ) ) {
					return OwnerLifecycleResult::UNAVAILABLE; }
				$this->revoke_tag_access( $tag_id, $now );
				$this->db->execute( 'UPDATE %i SET transfer_status=%s,accepted_at=%s,updated_at=%s WHERE transfer_id=%d AND transfer_status=%s', array( $this->tables->tag_transfers(), 'accepted', $this->dates->format( $now ), $this->dates->format( $now ), (int) $row['transfer_id'], 'pending' ) );
				$this->events->append( new NewEventRecord( 'tag_transferred', 'user', $new_owner_id, 'tag', $tag_id, 'success', (string) $row['transfer_id'], EventMetadata::none(), $now ) );
				return OwnerLifecycleResult::ACCEPTED;
			}
		);
	}

	/**
	 * Cancel current pending Transfers for one Owner Tag.
	 *
	 * @param TagId             $tag_id Current Tag.
	 * @param int               $owner_id Current Owner identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function cancel_transfer( TagId $tag_id, int $owner_id, DateTimeImmutable $now ): OwnerLifecycleResult {
		return $this->transactions->transactional(
			function () use ( $tag_id, $owner_id, $now ): OwnerLifecycleResult {
				$tag = $this->db->row( 'SELECT owner_id FROM %i WHERE tag_id=%s AND owner_id=%d AND tag_status=%s FOR UPDATE', array( $this->tables->tags(), $tag_id->value, $owner_id, TagStatus::ACTIVE->value ) );
				if ( null === $tag ) {
					return OwnerLifecycleResult::UNAVAILABLE;
				}
				$count = $this->db->execute( 'UPDATE %i SET transfer_status=%s,cancelled_at=%s,updated_at=%s WHERE tag_id=%s AND from_owner_id=%d AND transfer_status=%s', array( $this->tables->tag_transfers(), 'cancelled', $this->dates->format( $now ), $this->dates->format( $now ), $tag_id->value, $owner_id, 'pending' ) );
				return $count > 0 ? OwnerLifecycleResult::CANCELLED : OwnerLifecycleResult::UNCHANGED;
			}
		);
	}

	/**
	 * Permanently retire one active current-Owner Tag.
	 *
	 * @param TagId             $tag_id Current Tag.
	 * @param int               $owner_id Current Owner identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function retire( TagId $tag_id, int $owner_id, DateTimeImmutable $now ): OwnerLifecycleResult {
		return $this->transactions->transactional(
			function () use ( $tag_id, $owner_id, $now ): OwnerLifecycleResult {
				if ( 1 !== $this->db->execute( 'UPDATE %i SET tag_status=%s,updated_at=%s WHERE tag_id=%s AND owner_id=%d AND tag_status=%s', array( $this->tables->tags(), TagStatus::RETIRED->value, $this->dates->format( $now ), $tag_id->value, $owner_id, TagStatus::ACTIVE->value ) ) ) {
					return OwnerLifecycleResult::UNAVAILABLE; }
				$this->revoke_tag_access( $tag_id->value, $now );
				$this->db->execute( 'UPDATE %i SET transfer_status=%s,cancelled_at=%s,updated_at=%s WHERE tag_id=%s AND transfer_status=%s', array( $this->tables->tag_transfers(), 'cancelled', $this->dates->format( $now ), $this->dates->format( $now ), $tag_id->value, 'pending' ) );
				$this->events->append( new NewEventRecord( 'tag_retired', 'user', $owner_id, 'tag', $tag_id->value, 'success', null, EventMetadata::none(), $now ) );
				return OwnerLifecycleResult::RETIRED;
			}
		);
	}

	/**
	 * Revoke every Conversation access path for one Tag.
	 *
	 * @param string            $tag_id Canonical Tag ID.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	private function revoke_tag_access( string $tag_id, DateTimeImmutable $now ): void {
		$this->db->execute( 'UPDATE %i m JOIN %i c ON c.conversation_id=m.conversation_id SET m.delivery_status=%s WHERE c.tag_id=%s AND m.delivery_status IN (%s,%s)', array( $this->tables->messages(), $this->tables->conversations(), 'failed', $tag_id, 'queued', 'in_flight' ) );
		$this->db->execute( 'UPDATE %i SET conversation_status=%s,last_activity_at=%s WHERE tag_id=%s AND conversation_status IN (%s,%s)', array( $this->tables->conversations(), 'closed', $this->dates->format( $now ), $tag_id, 'pending_verification', 'open' ) );
		$this->db->execute( 'UPDATE %i a JOIN %i c ON c.conversation_id=a.conversation_id SET a.revoked_at=%s WHERE c.tag_id=%s AND a.revoked_at IS NULL', array( $this->tables->access_tokens(), $this->tables->conversations(), $this->dates->format( $now ), $tag_id ) );
	}
}
