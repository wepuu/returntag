<?php
/**
 * WordPress database Conversation Repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceConstraintViolationException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\Record\ConversationRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewConversationRecord;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Repository\ConversationRepository;
use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Domain\Conversation\ConversationStatus;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Persists privacy-preserving Finder/Owner Conversations.
 */
final class WpdbConversationRepository implements ConversationRepository {
	/**
	 * Create the Repository.
	 *
	 * @param WpdbGateway           $gateway Database gateway.
	 * @param TableNames            $tables Trusted table names.
	 * @param DatabaseDateTimeCodec $dates UTC datetime codec.
	 */
	public function __construct(
		private readonly WpdbGateway $gateway,
		private readonly TableNames $tables,
		private readonly DatabaseDateTimeCodec $dates
	) {
	}

	/**
	 * Insert one privacy-preserving Conversation.
	 *
	 * @param NewConversationRecord $record New Conversation data.
	 */
	public function insert( NewConversationRecord $record ): ConversationRecord {
		$this->assert_owner_snapshot( $record );
		$conversation_id = $this->gateway->insert(
			$this->tables->conversations(),
			array(
				'tag_id'                  => $record->tag_id,
				'owner_id_snapshot'       => $record->owner_id_snapshot,
				'finder_email_ciphertext' => $record->finder_email_ciphertext->value,
				'finder_email_lookup'     => $record->finder_email_lookup->value,
				'finder_verified_at'      => $this->dates->format_nullable( $record->finder_verified_at ),
				'conversation_status'     => $record->conversation_status->value,
				'expires_at'              => $this->dates->format( $record->expires_at ),
				'last_activity_at'        => $this->dates->format( $record->last_activity_at ),
				'created_at'              => $this->dates->format( $record->created_at ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return new ConversationRecord( $conversation_id, $record );
	}

	/**
	 * Find one Conversation by identifier.
	 *
	 * @param int $conversation_id Conversation identifier.
	 */
	public function find_by_id( int $conversation_id ): ?ConversationRecord {
		RecordValidator::positive_id( $conversation_id, 'conversation_id' );
		$row = $this->gateway->row(
			'SELECT * FROM %i WHERE conversation_id = %d LIMIT 1',
			array( $this->tables->conversations(), $conversation_id )
		);

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Verify the referenced Tag and current owner snapshot.
	 *
	 * @param NewConversationRecord $record New Conversation data.
	 * @throws PersistenceConstraintViolationException When the snapshot is absent or inconsistent.
	 */
	private function assert_owner_snapshot( NewConversationRecord $record ): void {
		$row = $this->gateway->row(
			'SELECT owner_id FROM %i WHERE tag_id = %s LIMIT 1',
			array( $this->tables->tags(), $record->tag_id )
		);

		if ( null === $row || StoredRow::nullable_positive_int( $row, 'owner_id' ) !== $record->owner_id_snapshot ) {
			throw new PersistenceConstraintViolationException( 'Referenced record is unavailable or inconsistent.' );
		}
	}

	/**
	 * Map one strict stored Conversation row.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @throws PersistenceMappingException When stored data violates the contract.
	 */
	private function hydrate( array $row ): ConversationRecord {
		try {
			return new ConversationRecord(
				StoredRow::positive_int( $row, 'conversation_id' ),
				new NewConversationRecord(
					StoredRow::string( $row, 'tag_id' ),
					StoredRow::positive_int( $row, 'owner_id_snapshot' ),
					EmailCiphertext::from_storage( StoredRow::string( $row, 'finder_email_ciphertext' ) ),
					LookupDigest::from_storage( StoredRow::string( $row, 'finder_email_lookup' ) ),
					$this->dates->parse_nullable( StoredRow::nullable_string( $row, 'finder_verified_at' ) ),
					StoredRow::enum( $row, 'conversation_status', ConversationStatus::class ),
					$this->dates->parse( StoredRow::string( $row, 'expires_at' ) ),
					$this->dates->parse( StoredRow::string( $row, 'last_activity_at' ) ),
					$this->dates->parse( StoredRow::string( $row, 'created_at' ) )
				)
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Conversation record is invalid.' );
		}
	}
}
