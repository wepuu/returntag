<?php
/**
 * Wpdb authentication challenge retention adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Auth\AuthChallengeRetentionStore;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/** Deletes only bounded, already-expired or consumed challenge rows. */
final readonly class WpdbAuthChallengeRetentionStore implements AuthChallengeRetentionStore {
	/**
	 * Create the retention adapter.
	 *
	 * @param WpdbGateway           $gateway Prepared database gateway.
	 * @param TableNames            $tables Trusted TagCore table names.
	 * @param DatabaseDateTimeCodec $dates UTC database date codec.
	 */
	public function __construct( private WpdbGateway $gateway, private TableNames $tables, private DatabaseDateTimeCodec $dates ) {}

	/**
	 * Delete one bounded set without selecting or decrypting private fields.
	 *
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param int               $limit Maximum rows removed.
	 */
	public function cleanup_eligible( DateTimeImmutable $now, int $limit ): int {
		return $this->gateway->execute(
			'DELETE FROM %i WHERE expires_at <= %s OR (consumed_at IS NOT NULL AND consumed_at <= %s) ORDER BY expires_at ASC, challenge_id ASC LIMIT %d',
			array(
				$this->tables->auth_challenges(),
				$this->dates->format( $now ),
				$this->dates->format( $now ),
				max( 1, min( 500, $limit ) ),
			)
		);
	}
}
