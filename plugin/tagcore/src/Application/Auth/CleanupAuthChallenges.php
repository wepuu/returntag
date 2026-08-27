<?php
/**
 * Bounded authentication challenge retention use case.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Auth;

use ReturnTag\TagCore\Application\Clock;

/** Removes expired or consumed challenges without reading their private values. */
final readonly class CleanupAuthChallenges {
	public const CHUNK_SIZE = 500;
	public const MAX_CHUNKS = 10;

	/**
	 * Create the bounded cleanup use case.
	 *
	 * @param AuthChallengeRetentionStore $store Challenge retention persistence.
	 * @param Clock                       $clock Current UTC clock.
	 */
	public function __construct( private AuthChallengeRetentionStore $store, private Clock $clock ) {}

	/** Return the number of records removed during this bounded run. */
	public function execute(): int {
		$removed = 0;
		$now     = $this->clock->now();

		for ( $chunk = 0; $chunk < self::MAX_CHUNKS; ++$chunk ) {
			$count    = $this->store->cleanup_eligible( $now, self::CHUNK_SIZE );
			$removed += $count;

			if ( self::CHUNK_SIZE !== $count ) {
				break;
			}
		}

		return $removed;
	}
}
