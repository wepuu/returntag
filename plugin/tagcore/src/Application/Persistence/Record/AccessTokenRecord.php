<?php
/**
 * Stored hash-only Access Token record.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Record;

use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * One persisted hash-only Access Token row.
 */
final readonly class AccessTokenRecord {
	/**
	 * Create a stored Access Token record.
	 *
	 * @param int                  $token_id Token identifier.
	 * @param NewAccessTokenRecord $data Stored Token data.
	 */
	public function __construct(
		public int $token_id,
		public NewAccessTokenRecord $data
	) {
		RecordValidator::positive_id( $this->token_id, 'token_id' );
	}
}
