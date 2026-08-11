<?php
/**
 * WordPress database current-Owner Conversation projection.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Account\OwnerConversationReader;
use ReturnTag\TagCore\Application\Account\OwnerConversationSummary;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Domain\Conversation\ConversationStatus;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/** Selects only status and bounded activity data for the current Owner. */
final readonly class WpdbOwnerConversationReader implements OwnerConversationReader {
	private const PAGE_SIZE = 20;

	/**
	 * Create the projection adapter.
	 *
	 * @param WpdbGateway           $gateway Safe database gateway.
	 * @param TableNames            $tables Prefix-aware table names.
	 * @param DatabaseDateTimeCodec $dates UTC database codec.
	 */
	public function __construct(
		private WpdbGateway $gateway,
		private TableNames $tables,
		private DatabaseDateTimeCodec $dates
	) {
	}

	/**
	 * Return at most twenty privacy-minimized current-Owner summaries.
	 *
	 * @param int               $owner_id Current WordPress Owner identifier.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @return list<OwnerConversationSummary>
	 */
	public function list_for_owner( int $owner_id, DateTimeImmutable $now ): array {
		RecordValidator::positive_id( $owner_id, 'owner_id' );
		$rows = $this->gateway->rows(
			'SELECT c.conversation_id, c.conversation_status, c.last_activity_at, c.created_at, c.expires_at, c.finder_verified_at, t.tag_status, r.report_status, r.evidence_status FROM %i c JOIN %i t ON t.tag_id = c.tag_id JOIN %i r ON r.conversation_id = c.conversation_id WHERE c.owner_id_snapshot = %d AND t.owner_id = %d ORDER BY c.last_activity_at DESC, c.conversation_id DESC LIMIT %d',
			array(
				$this->tables->conversations(),
				$this->tables->tags(),
				$this->tables->finder_reports(),
				$owner_id,
				$owner_id,
				self::PAGE_SIZE,
			)
		);

		return array_map(
			function ( array $row ) use ( $now ): OwnerConversationSummary {
				$status = StoredRow::enum( $row, 'conversation_status', ConversationStatus::class );

				return new OwnerConversationSummary(
					StoredRow::positive_int( $row, 'conversation_id' ),
					$status,
					$this->dates->parse( StoredRow::string( $row, 'last_activity_at' ) ),
					$this->dates->parse( StoredRow::string( $row, 'created_at' ) ),
					ConversationStatus::OPEN === $status
						&& TagStatus::ACTIVE->value === StoredRow::string( $row, 'tag_status' )
						&& 'notified' === StoredRow::string( $row, 'report_status' )
						&& 'ready' === StoredRow::string( $row, 'evidence_status' )
						&& null !== StoredRow::nullable_string( $row, 'finder_verified_at' )
						&& $this->dates->parse( StoredRow::string( $row, 'expires_at' ) ) > $now
				);
			},
			$rows
		);
	}
}
