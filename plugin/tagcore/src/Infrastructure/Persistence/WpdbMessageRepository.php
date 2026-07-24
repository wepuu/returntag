<?php
/**
 * WordPress database Message Repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceConstraintViolationException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\Pagination\MessageCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\MessagePage;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Record\MessageRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewMessageRecord;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Repository\MessageRepository;
use ReturnTag\TagCore\Application\Persistence\Value\MessageCiphertext;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Appends and reads opaque encrypted Message records.
 */
final class WpdbMessageRepository implements MessageRepository {
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
	 * Append one encrypted Message.
	 *
	 * @param NewMessageRecord $record New Message data.
	 */
	public function append( NewMessageRecord $record ): MessageRecord {
		$this->assert_conversation_exists( $record->conversation_id );
		$message_id = $this->gateway->insert(
			$this->tables->messages(),
			array(
				'conversation_id'     => $record->conversation_id,
				'sender_role'         => $record->sender_role->value,
				'body_ciphertext'     => $record->body_ciphertext->value,
				'delivery_status'     => $record->delivery_status->value,
				'provider_message_id' => $record->provider_message_id,
				'delivered_at'        => $this->dates->format_nullable( $record->delivered_at ),
				'created_at'          => $this->dates->format( $record->created_at ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return new MessageRecord( $message_id, $record );
	}

	/**
	 * Return one bounded chronological Message page.
	 *
	 * @param int                $conversation_id Conversation identifier.
	 * @param MessageCursor|null $cursor Previous cursor.
	 * @param PageSize           $page_size Bounded page size.
	 */
	public function list_by_conversation( int $conversation_id, ?MessageCursor $cursor, PageSize $page_size ): MessagePage {
		RecordValidator::positive_id( $conversation_id, 'conversation_id' );
		$limit = $page_size->value + 1;

		if ( null === $cursor ) {
			$rows = $this->gateway->rows(
				'SELECT * FROM %i WHERE conversation_id = %d ORDER BY message_id ASC LIMIT %d',
				array( $this->tables->messages(), $conversation_id, $limit )
			);
		} else {
			$rows = $this->gateway->rows(
				'SELECT * FROM %i WHERE conversation_id = %d AND message_id > %d ORDER BY message_id ASC LIMIT %d',
				array( $this->tables->messages(), $conversation_id, $cursor->message_id, $limit )
			);
		}

		$has_more = count( $rows ) > $page_size->value;

		if ( $has_more ) {
			array_pop( $rows );
		}

		$items = array_map( fn( array $row ): MessageRecord => $this->hydrate( $row ), $rows );
		$last  = $has_more ? end( $items ) : false;
		$next  = $last instanceof MessageRecord
			? new MessageCursor( $last->message_id )
			: null;

		return new MessagePage( $items, $next );
	}

	/**
	 * Verify the referenced Conversation.
	 *
	 * @param int $conversation_id Conversation identifier.
	 * @throws PersistenceConstraintViolationException When the Conversation is absent.
	 */
	private function assert_conversation_exists( int $conversation_id ): void {
		$row = $this->gateway->row(
			'SELECT conversation_id FROM %i WHERE conversation_id = %d LIMIT 1',
			array( $this->tables->conversations(), $conversation_id )
		);

		if ( null === $row ) {
			throw new PersistenceConstraintViolationException( 'Referenced record is unavailable or inconsistent.' );
		}
	}

	/**
	 * Map one strict stored Message row.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @throws PersistenceMappingException When stored data violates the contract.
	 */
	private function hydrate( array $row ): MessageRecord {
		try {
			return new MessageRecord(
				StoredRow::positive_int( $row, 'message_id' ),
				new NewMessageRecord(
					StoredRow::positive_int( $row, 'conversation_id' ),
					StoredRow::enum( $row, 'sender_role', MessageSenderRole::class ),
					MessageCiphertext::from_storage( StoredRow::string( $row, 'body_ciphertext' ) ),
					StoredRow::enum( $row, 'delivery_status', DeliveryStatus::class ),
					StoredRow::nullable_string( $row, 'provider_message_id' ),
					$this->dates->parse_nullable( StoredRow::nullable_string( $row, 'delivered_at' ) ),
					$this->dates->parse( StoredRow::string( $row, 'created_at' ) )
				)
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Message record is invalid.' );
		}
	}
}
