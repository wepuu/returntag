<?php
/**
 * Access Token persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Persistence\Repository;

use ReturnTag\TagCore\Application\Persistence\Record\AccessTokenRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewAccessTokenRecord;
use ReturnTag\TagCore\Application\Persistence\Value\AccessTokenDigest;

/**
 * Narrow hash-only Access Token persistence contract.
 */
interface AccessTokenRepository {
	/**
	 * Insert one hash-only Access Token.
	 *
	 * @param NewAccessTokenRecord $record New Token data.
	 */
	public function insert( NewAccessTokenRecord $record ): AccessTokenRecord;

	/**
	 * Find one Access Token by digest.
	 *
	 * @param AccessTokenDigest $token_hash Canonical Token digest.
	 */
	public function find_by_hash( AccessTokenDigest $token_hash ): ?AccessTokenRecord;
}
