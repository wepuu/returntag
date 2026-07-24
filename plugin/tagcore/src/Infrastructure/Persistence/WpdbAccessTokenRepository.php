<?php
/**
 * WordPress database Access Token Repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceConstraintViolationException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\Record\AccessTokenRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewAccessTokenRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\AccessTokenRepository;
use ReturnTag\TagCore\Application\Persistence\Value\AccessTokenDigest;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Persists hash-only secure-link and Conversation Access Tokens.
 */
final class WpdbAccessTokenRepository implements AccessTokenRepository {
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
	 * Insert one hash-only Access Token.
	 *
	 * @param NewAccessTokenRecord $record New Token data.
	 */
	public function insert( NewAccessTokenRecord $record ): AccessTokenRecord {
		$this->assert_conversation_exists( $record->conversation_id );
		$token_id = $this->gateway->insert(
			$this->tables->access_tokens(),
			array(
				'conversation_id' => $record->conversation_id,
				'purpose'         => $record->purpose,
				'actor_role'      => $record->actor_role->value,
				'token_hash'      => $record->token_hash->value,
				'expires_at'      => $this->dates->format( $record->expires_at ),
				'exchanged_at'    => $this->dates->format_nullable( $record->exchanged_at ),
				'revoked_at'      => $this->dates->format_nullable( $record->revoked_at ),
				'created_at'      => $this->dates->format( $record->created_at ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return new AccessTokenRecord( $token_id, $record );
	}

	/**
	 * Find one Access Token by digest.
	 *
	 * @param AccessTokenDigest $token_hash Canonical Token digest.
	 */
	public function find_by_hash( AccessTokenDigest $token_hash ): ?AccessTokenRecord {
		$row = $this->gateway->row(
			'SELECT * FROM %i WHERE token_hash = %s LIMIT 1',
			array( $this->tables->access_tokens(), $token_hash->value )
		);

		return null === $row ? null : $this->hydrate( $row );
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
	 * Map one strict stored Access Token row.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @throws PersistenceMappingException When stored data violates the contract.
	 */
	private function hydrate( array $row ): AccessTokenRecord {
		try {
			return new AccessTokenRecord(
				StoredRow::positive_int( $row, 'token_id' ),
				new NewAccessTokenRecord(
					StoredRow::positive_int( $row, 'conversation_id' ),
					StoredRow::string( $row, 'purpose' ),
					StoredRow::enum( $row, 'actor_role', MessageSenderRole::class ),
					AccessTokenDigest::from_storage( StoredRow::string( $row, 'token_hash' ) ),
					$this->dates->parse( StoredRow::string( $row, 'expires_at' ) ),
					$this->dates->parse_nullable( StoredRow::nullable_string( $row, 'exchanged_at' ) ),
					$this->dates->parse_nullable( StoredRow::nullable_string( $row, 'revoked_at' ) ),
					$this->dates->parse( StoredRow::string( $row, 'created_at' ) )
				)
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Access Token record is invalid.' );
		}
	}
}
