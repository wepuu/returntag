<?php
/**
 * WordPress database Conversation relay store.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Account\OwnerConversationContinuationStore;
use ReturnTag\TagCore\Application\Conversation\ConversationDispatch;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayIdentity;
use ReturnTag\TagCore\Application\Conversation\ConversationRelayStore;
use ReturnTag\TagCore\Application\Conversation\ConversationSafetyAction;
use ReturnTag\TagCore\Application\Persistence\Record\AccessTokenRecord;
use ReturnTag\TagCore\Application\Persistence\Record\ConversationRecord;
use ReturnTag\TagCore\Application\Persistence\Record\MessageRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewAccessTokenRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewConversationRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewMessageRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Value\AccessTokenDigest;
use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\MessageCiphertext;
use ReturnTag\TagCore\Domain\Conversation\ConversationStatus;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/** Owns all row locks and eligibility checks for the private relay. */
final readonly class WpdbConversationRelayStore implements ConversationRelayStore, OwnerConversationContinuationStore {
	/**
	 * Create the store.
	 *
	 * @param WpdbGateway            $gateway Database gateway.
	 * @param TableNames             $tables Trusted table names.
	 * @param DatabaseDateTimeCodec  $dates UTC date codec.
	 * @param WpdbTransactionManager $transactions Transaction manager.
	 * @param EventRepository        $events Audit Event repository.
	 */
	public function __construct(
		private WpdbGateway $gateway,
		private TableNames $tables,
		private DatabaseDateTimeCodec $dates,
		private WpdbTransactionManager $transactions,
		private EventRepository $events
	) {}

	/**
	 * Resolve one notified report Conversation.
	 *
	 * @param int               $finder_report_id Report identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function conversation_id_for_notified_report( int $finder_report_id, DateTimeImmutable $now ): ?int {
		$row = $this->eligible_report( $finder_report_id, $now, false );
		return null === $row || ! $this->eligible_row( $row, $now ) ? null : StoredRow::positive_int( $row, 'conversation_id' );
	}

	/**
	 * Create the unique system access Message.
	 *
	 * @param int               $finder_report_id Report identifier.
	 * @param MessageCiphertext $ciphertext Encrypted body.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function ensure_access_message( int $finder_report_id, MessageCiphertext $ciphertext, DateTimeImmutable $now ): ?MessageRecord {
		return $this->transactions->transactional(
			function () use ( $finder_report_id, $ciphertext, $now ): ?MessageRecord {
				$row = $this->eligible_report( $finder_report_id, $now, true );
				if ( null === $row || ! $this->eligible_row( $row, $now ) ) {
					return null; }
				$conversation_id = StoredRow::positive_int( $row, 'conversation_id' );
				$existing        = $this->gateway->row( 'SELECT message_id FROM %i WHERE conversation_id = %d AND sender_role = %s LIMIT 1', array( $this->tables->messages(), $conversation_id, MessageSenderRole::SYSTEM->value ) );
				if ( null !== $existing ) {
					return null; }
				return $this->insert_message( $conversation_id, MessageSenderRole::SYSTEM, $ciphertext, $now );
			}
		);
	}

	/**
	 * Persist one role-bound link digest.
	 *
	 * @param int               $conversation_id Conversation identifier.
	 * @param string            $purpose Link purpose.
	 * @param MessageSenderRole $role Recipient role.
	 * @param AccessTokenDigest $digest Token digest.
	 * @param DateTimeImmutable $expires_at Expiry.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @throws \InvalidArgumentException For an invalid purpose or role.
	 */
	public function issue_link( int $conversation_id, string $purpose, MessageSenderRole $role, AccessTokenDigest $digest, DateTimeImmutable $expires_at, DateTimeImmutable $now ): AccessTokenRecord {
		if ( ! in_array( $purpose, array( 'owner_secure_reply', 'finder_continue_conversation' ), true ) || MessageSenderRole::SYSTEM === $role ) {
			throw new \InvalidArgumentException( 'Invalid relay link.' ); }
		return $this->transactions->transactional(
			function () use ( $conversation_id, $purpose, $role, $digest, $expires_at, $now ): AccessTokenRecord {
				if ( null === $this->eligible_conversation( $conversation_id, $role, $now, true ) ) {
					throw new \RuntimeException( 'Conversation access is unavailable.' );
				}
				$record = new NewAccessTokenRecord( $conversation_id, $purpose, $role, $digest, $expires_at, null, null, $now );
				$id     = $this->gateway->insert(
					$this->tables->access_tokens(),
					array(
						'conversation_id' => $conversation_id,
						'purpose'         => $purpose,
						'actor_role'      => $role->value,
						'token_hash'      => $digest->value,
						'expires_at'      => $this->dates->format( $expires_at ),
						'exchanged_at'    => null,
						'revoked_at'      => null,
						'created_at'      => $this->dates->format( $now ),
					),
					array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
				);
				return new AccessTokenRecord( $id, $record );
			}
		);
	}

	/**
	 * Exchange one link for a rotated session.
	 *
	 * @param AccessTokenDigest $link Link digest.
	 * @param AccessTokenDigest $session Session digest.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param DateTimeImmutable $session_expires_at Session expiry.
	 */
	public function exchange_link( AccessTokenDigest $link, AccessTokenDigest $session, DateTimeImmutable $now, DateTimeImmutable $session_expires_at ): ?ConversationRelayIdentity {
		return $this->transactions->transactional(
			function () use ( $link, $session, $now, $session_expires_at ): ?ConversationRelayIdentity {
				$row = $this->gateway->row( 'SELECT * FROM %i WHERE token_hash = %s FOR UPDATE', array( $this->tables->access_tokens(), $link->value ) );
				if ( null === $row || null !== ( $row['exchanged_at'] ?? null ) || null !== ( $row['revoked_at'] ?? null ) || StoredRow::string( $row, 'expires_at' ) <= $this->dates->format( $now ) ) {
					return null; }
				$purpose = StoredRow::string( $row, 'purpose' );
				$role    = StoredRow::enum( $row, 'actor_role', MessageSenderRole::class );
				if ( ! ( ( 'owner_secure_reply' === $purpose && MessageSenderRole::OWNER === $role ) || ( 'finder_continue_conversation' === $purpose && MessageSenderRole::FINDER === $role ) ) ) {
					return null; }
				$conversation_id = StoredRow::positive_int( $row, 'conversation_id' );
				if ( null === $this->eligible_conversation( $conversation_id, $role, $now, true ) ) {
					return null; }
				if ( 1 !== $this->gateway->execute( 'UPDATE %i SET exchanged_at = %s WHERE token_id = %d AND exchanged_at IS NULL AND revoked_at IS NULL', array( $this->tables->access_tokens(), $this->dates->format( $now ), StoredRow::positive_int( $row, 'token_id' ) ) ) ) {
					return null; }
				$this->gateway->execute( 'UPDATE %i SET revoked_at = %s WHERE conversation_id = %d AND purpose = %s AND actor_role = %s AND revoked_at IS NULL', array( $this->tables->access_tokens(), $this->dates->format( $now ), $conversation_id, 'conversation_session', $role->value ) );
				$this->issue_session( $conversation_id, $role, $session, $session_expires_at, $now );
				return new ConversationRelayIdentity( $conversation_id, $role );
			}
		);
	}

	/**
	 * Issue one Account-authorized Owner session after complete revalidation.
	 *
	 * @param int               $conversation_id Conversation candidate.
	 * @param int               $owner_id Current WordPress Owner identifier.
	 * @param AccessTokenDigest $session New session digest.
	 * @param DateTimeImmutable $expires_at Session expiry.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function issue_owner_session( int $conversation_id, int $owner_id, AccessTokenDigest $session, DateTimeImmutable $expires_at, DateTimeImmutable $now ): bool {
		RecordValidator::positive_id( $conversation_id, 'conversation_id' );
		RecordValidator::positive_id( $owner_id, 'owner_id' );

		return $this->transactions->transactional(
			function () use ( $conversation_id, $owner_id, $session, $expires_at, $now ): bool {
				$row = $this->eligible_conversation( $conversation_id, MessageSenderRole::OWNER, $now, true );

				if (
					null === $row
					|| StoredRow::positive_int( $row, 'owner_id_snapshot' ) !== $owner_id
					|| StoredRow::positive_int( $row, 'current_owner_id' ) !== $owner_id
				) {
					return false;
				}

				$this->gateway->execute(
					'UPDATE %i SET revoked_at = %s WHERE conversation_id = %d AND purpose = %s AND actor_role = %s AND revoked_at IS NULL',
					array(
						$this->tables->access_tokens(),
						$this->dates->format( $now ),
						$conversation_id,
						'conversation_session',
						MessageSenderRole::OWNER->value,
					)
				);
				$this->issue_session( $conversation_id, MessageSenderRole::OWNER, $session, $expires_at, $now );

				return true;
			}
		);
	}

	/**
	 * Resolve one active session.
	 *
	 * @param AccessTokenDigest $session Session digest.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function resolve_session( AccessTokenDigest $session, DateTimeImmutable $now ): ?ConversationRelayIdentity {
		$row = $this->gateway->row( 'SELECT conversation_id, actor_role FROM %i WHERE token_hash = %s AND purpose = %s AND exchanged_at IS NULL AND revoked_at IS NULL AND expires_at > %s LIMIT 1', array( $this->tables->access_tokens(), $session->value, 'conversation_session', $this->dates->format( $now ) ) );
		if ( null === $row ) {
			return null; }
		$role = StoredRow::enum( $row, 'actor_role', MessageSenderRole::class );
		if ( MessageSenderRole::SYSTEM === $role ) {
			return null; }
		$conversation_id = StoredRow::positive_int( $row, 'conversation_id' );
		return null === $this->eligible_conversation( $conversation_id, $role, $now, false ) ? null : new ConversationRelayIdentity( $conversation_id, $role );
	}

	/**
	 * Append one bounded human Message.
	 *
	 * @param ConversationRelayIdentity $identity Authorized actor.
	 * @param MessageCiphertext         $ciphertext Encrypted body.
	 * @param DateTimeImmutable         $now Current UTC time.
	 */
	public function append_human_message( ConversationRelayIdentity $identity, MessageCiphertext $ciphertext, DateTimeImmutable $now ): ?MessageRecord {
		return $this->transactions->transactional(
			function () use ( $identity, $ciphertext, $now ): ?MessageRecord {
				if ( null === $this->eligible_conversation( $identity->conversation_id, $identity->role, $now, true ) ) {
					return null; }
				$counts = $this->gateway->row( 'SELECT SUM(sender_role <> %s) AS total_count, SUM(sender_role = %s) AS role_count FROM %i WHERE conversation_id = %d', array( MessageSenderRole::SYSTEM->value, $identity->role->value, $this->tables->messages(), $identity->conversation_id ) );
				if ( null === $counts || (int) ( $counts['total_count'] ?? 0 ) >= 20 || (int) ( $counts['role_count'] ?? 0 ) >= 10 ) {
					return null; }
				$message = $this->insert_message( $identity->conversation_id, $identity->role, $ciphertext, $now );
				$this->gateway->execute( 'UPDATE %i SET last_activity_at = %s WHERE conversation_id = %d', array( $this->tables->conversations(), $this->dates->format( $now ), $identity->conversation_id ) );
				if ( MessageSenderRole::FINDER === $identity->role ) {
					$this->events->append( new NewEventRecord( 'finder_message_submitted', 'system', null, 'conversation', (string) $identity->conversation_id, 'submitted', null, EventMetadata::none(), $now ) );
				}
				return $message;
			}
		);
	}

	/**
	 * List authorized human Messages.
	 *
	 * @param ConversationRelayIdentity $identity Authorized actor.
	 * @param DateTimeImmutable         $now Current UTC time.
	 * @return list<MessageRecord>
	 */
	public function list_human_messages( ConversationRelayIdentity $identity, DateTimeImmutable $now ): array {
		if ( null === $this->eligible_conversation( $identity->conversation_id, $identity->role, $now, false ) ) {
			return array(); }
		$rows = $this->gateway->rows( 'SELECT * FROM %i WHERE conversation_id = %d AND sender_role <> %s ORDER BY message_id ASC LIMIT 20', array( $this->tables->messages(), $identity->conversation_id, MessageSenderRole::SYSTEM->value ) );
		return array_map( fn( array $row ): MessageRecord => $this->hydrate_message( $row ), $rows );
	}

	/**
	 * Apply one atomic role-specific terminal action.
	 *
	 * @param ConversationRelayIdentity $identity Authorized actor.
	 * @param ConversationSafetyAction  $action Terminal action.
	 * @param DateTimeImmutable         $now Current UTC time.
	 */
	public function apply_safety_action( ConversationRelayIdentity $identity, ConversationSafetyAction $action, DateTimeImmutable $now ): bool {
		return $this->transactions->transactional(
			function () use ( $identity, $action, $now ): bool {
				if ( $identity->role !== $action->role() || null === $this->eligible_conversation( $identity->conversation_id, $identity->role, $now, true ) ) {
					return false;
				}
				if ( 1 !== $this->gateway->execute( 'UPDATE %i SET conversation_status=%s,last_activity_at=%s WHERE conversation_id=%d AND conversation_status=%s', array( $this->tables->conversations(), $action->status()->value, $this->dates->format( $now ), $identity->conversation_id, ConversationStatus::OPEN->value ) ) ) {
					return false;
				}
				$this->gateway->execute( 'UPDATE %i SET revoked_at=%s WHERE conversation_id=%d AND revoked_at IS NULL', array( $this->tables->access_tokens(), $this->dates->format( $now ), $identity->conversation_id ) );
				$this->gateway->execute( 'UPDATE %i SET delivery_status=%s WHERE conversation_id=%d AND delivery_status=%s', array( $this->tables->messages(), DeliveryStatus::FAILED->value, $identity->conversation_id, DeliveryStatus::QUEUED->value ) );
				$this->events->append( new NewEventRecord( $action->event_type(), 'system', null, 'conversation', (string) $identity->conversation_id, $action->status()->value, null, EventMetadata::none(), $now ) );
				return true;
			}
		);
	}

	/**
	 * Claim one queued Message.
	 *
	 * @param int               $message_id Message identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function claim_dispatch( int $message_id, DateTimeImmutable $now ): ?ConversationDispatch {
		return $this->transactions->transactional(
			function () use ( $message_id, $now ): ?ConversationDispatch {
				$row = $this->gateway->row( 'SELECT m.message_id,m.conversation_id,m.sender_role,m.body_ciphertext,m.delivery_status,m.provider_message_id,m.delivered_at,m.created_at AS message_created_at,m.dispatch_claimed_at,m.dispatch_attempt_count,c.*,r.finder_report_id,r.report_status,r.evidence_status,t.owner_id AS current_owner_id,t.tag_status FROM %i m JOIN %i c ON c.conversation_id=m.conversation_id JOIN %i r ON r.conversation_id=c.conversation_id JOIN %i t ON t.tag_id=c.tag_id WHERE m.message_id=%d FOR UPDATE', array( $this->tables->messages(), $this->tables->conversations(), $this->tables->finder_reports(), $this->tables->tags(), $message_id ) );
				if ( null === $row || DeliveryStatus::QUEUED->value !== ( $row['delivery_status'] ?? null ) || null !== ( $row['dispatch_claimed_at'] ?? null ) || 0 !== (int) ( $row['dispatch_attempt_count'] ?? -1 ) || ! $this->eligible_row( $row, $now ) ) {
					return null; }
				$this->gateway->execute( 'UPDATE %i SET dispatch_claimed_at=%s, dispatch_attempt_count=1 WHERE message_id=%d', array( $this->tables->messages(), $this->dates->format( $now ), $message_id ) );
				return new ConversationDispatch( $this->hydrate_message( $row ), $this->hydrate_conversation( $row ), StoredRow::positive_int( $row, 'finder_report_id' ), StoredRow::positive_int( $row, 'current_owner_id' ) );
			}
		);
	}

	/**
	 * Recheck one claimed dispatch and its exact continuation Token.
	 *
	 * @param int               $message_id Message identifier.
	 * @param int               $token_id Token identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function dispatch_is_active( int $message_id, int $token_id, DateTimeImmutable $now ): bool {
		$row = $this->gateway->row( 'SELECT m.delivery_status,m.dispatch_claimed_at,m.dispatch_attempt_count,c.*,r.report_status,r.evidence_status,t.owner_id AS current_owner_id,t.tag_status,a.revoked_at AS token_revoked_at,a.expires_at AS token_expires_at FROM %i m JOIN %i c ON c.conversation_id=m.conversation_id JOIN %i r ON r.conversation_id=c.conversation_id JOIN %i t ON t.tag_id=c.tag_id JOIN %i a ON a.conversation_id=c.conversation_id WHERE m.message_id=%d AND a.token_id=%d LIMIT 1', array( $this->tables->messages(), $this->tables->conversations(), $this->tables->finder_reports(), $this->tables->tags(), $this->tables->access_tokens(), $message_id, $token_id ) );
		return null !== $row
			&& DeliveryStatus::QUEUED->value === ( $row['delivery_status'] ?? null )
			&& null !== ( $row['dispatch_claimed_at'] ?? null )
			&& 1 === (int) ( $row['dispatch_attempt_count'] ?? 0 )
			&& null === ( $row['token_revoked_at'] ?? null )
			&& StoredRow::string( $row, 'token_expires_at' ) > $this->dates->format( $now )
			&& $this->eligible_row( $row, $now );
	}

	/**
	 * Mark provider acceptance.
	 *
	 * @param int               $message_id Message identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_sent( int $message_id, DateTimeImmutable $now ): bool {
		return $this->transactions->transactional(
			function () use ( $message_id, $now ): bool {
				$row = $this->gateway->row( 'SELECT conversation_id, sender_role FROM %i WHERE message_id=%d AND delivery_status=%s AND dispatch_attempt_count=1 AND dispatch_claimed_at IS NOT NULL FOR UPDATE', array( $this->tables->messages(), $message_id, DeliveryStatus::QUEUED->value ) );
				if ( null === $row || 1 !== $this->gateway->execute( 'UPDATE %i SET delivery_status=%s WHERE message_id=%d AND delivery_status=%s', array( $this->tables->messages(), DeliveryStatus::SENT->value, $message_id, DeliveryStatus::QUEUED->value ) ) ) {
					return false;
				}
				if ( MessageSenderRole::OWNER === StoredRow::enum( $row, 'sender_role', MessageSenderRole::class ) ) {
					$conversation_id = StoredRow::positive_int( $row, 'conversation_id' );
					$this->events->append( new NewEventRecord( 'owner_reply_sent', 'system', null, 'conversation', (string) $conversation_id, 'sent', null, EventMetadata::none(), $now ) );
				}
				return true;
			}
		);
	}

	/**
	 * Mark terminal failure.
	 *
	 * @param int               $message_id Message identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function mark_failed( int $message_id, DateTimeImmutable $now ): bool {
		return 1 === $this->gateway->execute( 'UPDATE %i SET delivery_status=%s WHERE message_id=%d AND delivery_status=%s AND dispatch_attempt_count=1 AND dispatch_claimed_at IS NOT NULL', array( $this->tables->messages(), DeliveryStatus::FAILED->value, $message_id, DeliveryStatus::QUEUED->value ) ); }

	/**
	 * Revoke one undelivered link.
	 *
	 * @param int               $token_id Token identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function revoke_token( int $token_id, DateTimeImmutable $now ): void {
		$this->gateway->execute( 'UPDATE %i SET revoked_at=%s WHERE token_id=%d AND revoked_at IS NULL', array( $this->tables->access_tokens(), $this->dates->format( $now ), $token_id ) ); }
	/**
	 * Fail bounded stale claims.
	 *
	 * @param DateTimeImmutable $before Stale boundary.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param int               $limit Bound.
	 */
	public function fail_stale_claims( DateTimeImmutable $before, DateTimeImmutable $now, int $limit ): int {
		$ids   = $this->gateway->rows( 'SELECT message_id FROM %i WHERE delivery_status=%s AND dispatch_attempt_count=1 AND dispatch_claimed_at < %s ORDER BY message_id ASC LIMIT %d', array( $this->tables->messages(), DeliveryStatus::QUEUED->value, $this->dates->format( $before ), max( 1, min( 100, $limit ) ) ) );
		$count = 0;
		foreach ( $ids as $row ) {
			if ( $this->mark_failed( StoredRow::positive_int( $row, 'message_id' ), $now ) ) {
				++$count;
			}
		} return $count;
	}
	/**
	 * List bounded unclaimed work.
	 *
	 * @param int $limit Bound.
	 * @return list<int>
	 */
	public function pending_message_ids( int $limit ): array {
		return array_map( static fn( array $row ): int => StoredRow::positive_int( $row, 'message_id' ), $this->gateway->rows( 'SELECT message_id FROM %i WHERE delivery_status=%s AND dispatch_attempt_count=0 AND dispatch_claimed_at IS NULL ORDER BY message_id ASC LIMIT %d', array( $this->tables->messages(), DeliveryStatus::QUEUED->value, max( 1, min( 100, $limit ) ) ) ) ); }

	/**
	 * Return one report projection.
	 *
	 * @param int               $report_id Report identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param bool              $lock Whether to lock.
	 * @return array<string,mixed>|null
	 */
	private function eligible_report( int $report_id, DateTimeImmutable $now, bool $lock ): ?array {
		$suffix = $lock ? ' FOR UPDATE' : '';
		return $this->gateway->row( 'SELECT r.conversation_id, r.report_status, r.evidence_status, c.*, t.owner_id AS current_owner_id, t.tag_status FROM %i r JOIN %i c ON c.conversation_id=r.conversation_id JOIN %i t ON t.tag_id=c.tag_id WHERE r.finder_report_id=%d' . $suffix, array( $this->tables->finder_reports(), $this->tables->conversations(), $this->tables->tags(), $report_id ) );
	}
	/**
	 * Return one authorized Conversation projection.
	 *
	 * @param int               $id Conversation identifier.
	 * @param MessageSenderRole $role Actor role.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param bool              $lock Whether to lock.
	 * @return array<string,mixed>|null
	 */
	private function eligible_conversation( int $id, MessageSenderRole $role, DateTimeImmutable $now, bool $lock ): ?array {
		$row = $this->gateway->row( 'SELECT c.*, r.finder_report_id, r.report_status, r.evidence_status, t.owner_id AS current_owner_id, t.tag_status FROM %i c JOIN %i r ON r.conversation_id=c.conversation_id JOIN %i t ON t.tag_id=c.tag_id WHERE c.conversation_id=%d' . ( $lock ? ' FOR UPDATE' : '' ), array( $this->tables->conversations(), $this->tables->finder_reports(), $this->tables->tags(), $id ) );
		return null !== $row && $this->eligible_row( $row, $now ) && ( MessageSenderRole::OWNER !== $role || StoredRow::positive_int( $row, 'owner_id_snapshot' ) === StoredRow::positive_int( $row, 'current_owner_id' ) ) ? $row : null;
	}
	/**
	 * Check the shared runtime projection.
	 *
	 * @param array<string,mixed> $row Stored projection.
	 * @param DateTimeImmutable   $now Current UTC time.
	 */
	private function eligible_row( array $row, DateTimeImmutable $now ): bool {
		return ConversationStatus::OPEN->value === ( $row['conversation_status'] ?? null ) && 'notified' === ( $row['report_status'] ?? null ) && 'ready' === ( $row['evidence_status'] ?? null ) && 'active' === ( $row['tag_status'] ?? null ) && null !== ( $row['finder_verified_at'] ?? null ) && StoredRow::string( $row, 'expires_at' ) > $this->dates->format( $now ) && StoredRow::positive_int( $row, 'owner_id_snapshot' ) === StoredRow::positive_int( $row, 'current_owner_id' );
	}
	/**
	 * Persist one role-bound session digest.
	 *
	 * @param int               $id Conversation identifier.
	 * @param MessageSenderRole $role Actor role.
	 * @param AccessTokenDigest $digest Session digest.
	 * @param DateTimeImmutable $expires Expiry.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	private function issue_session( int $id, MessageSenderRole $role, AccessTokenDigest $digest, DateTimeImmutable $expires, DateTimeImmutable $now ): void {
		$this->gateway->insert(
			$this->tables->access_tokens(),
			array(
				'conversation_id' => $id,
				'purpose'         => 'conversation_session',
				'actor_role'      => $role->value,
				'token_hash'      => $digest->value,
				'expires_at'      => $this->dates->format( $expires ),
				'exchanged_at'    => null,
				'revoked_at'      => null,
				'created_at'      => $this->dates->format( $now ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
	/**
	 * Persist one encrypted queued Message.
	 *
	 * @param int               $id Conversation identifier.
	 * @param MessageSenderRole $role Sender role.
	 * @param MessageCiphertext $cipher Encrypted body.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	private function insert_message( int $id, MessageSenderRole $role, MessageCiphertext $cipher, DateTimeImmutable $now ): MessageRecord {
		$data       = new NewMessageRecord( $id, $role, $cipher, DeliveryStatus::QUEUED, null, null, $now );
		$message_id = $this->gateway->insert(
			$this->tables->messages(),
			array(
				'conversation_id'     => $id,
				'sender_role'         => $role->value,
				'body_ciphertext'     => $cipher->value,
				'delivery_status'     => DeliveryStatus::QUEUED->value,
				'provider_message_id' => null,
				'delivered_at'        => null,
				'created_at'          => $this->dates->format( $now ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return new MessageRecord( $message_id, $data );
	}
	/**
	 * Hydrate one encrypted Message.
	 *
	 * @param array<string,mixed> $row Stored row.
	 */
	private function hydrate_message( array $row ): MessageRecord {
		$created = array_key_exists( 'message_created_at', $row ) ? 'message_created_at' : 'created_at';
		return new MessageRecord( StoredRow::positive_int( $row, 'message_id' ), new NewMessageRecord( StoredRow::positive_int( $row, 'conversation_id' ), StoredRow::enum( $row, 'sender_role', MessageSenderRole::class ), MessageCiphertext::from_storage( StoredRow::string( $row, 'body_ciphertext' ) ), StoredRow::enum( $row, 'delivery_status', DeliveryStatus::class ), StoredRow::nullable_string( $row, 'provider_message_id' ), $this->dates->parse_nullable( StoredRow::nullable_string( $row, 'delivered_at' ) ), $this->dates->parse( StoredRow::string( $row, $created ) ) ) ); }
	/**
	 * Hydrate one Conversation.
	 *
	 * @param array<string,mixed> $row Stored row.
	 */
	private function hydrate_conversation( array $row ): ConversationRecord {
		return new ConversationRecord( StoredRow::positive_int( $row, 'conversation_id' ), new NewConversationRecord( StoredRow::string( $row, 'tag_id' ), StoredRow::positive_int( $row, 'owner_id_snapshot' ), EmailCiphertext::from_storage( StoredRow::string( $row, 'finder_email_ciphertext' ) ), LookupDigest::from_storage( StoredRow::string( $row, 'finder_email_lookup' ) ), $this->dates->parse_nullable( StoredRow::nullable_string( $row, 'finder_verified_at' ) ), StoredRow::enum( $row, 'conversation_status', ConversationStatus::class ), $this->dates->parse( StoredRow::string( $row, 'expires_at' ) ), $this->dates->parse( StoredRow::string( $row, 'last_activity_at' ) ), $this->dates->parse( StoredRow::string( $row, 'created_at' ) ) ) ); }
}
