<?php
/**
 * WordPress database Tag activation Repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Tag\TagActivationRepository;
use ReturnTag\TagCore\Application\Tag\TagActivationResult;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Uses one conditional joined update as the first-owner authority.
 */
final readonly class WpdbTagActivationRepository implements TagActivationRepository {
	/**
	 * Create the Repository.
	 *
	 * @param WpdbGateway           $gateway Database gateway.
	 * @param TableNames            $tables Trusted table names.
	 * @param DatabaseDateTimeCodec $dates UTC datetime codec.
	 */
	public function __construct(
		private WpdbGateway $gateway,
		private TableNames $tables,
		private DatabaseDateTimeCodec $dates
	) {
	}

	/**
	 * Atomically claim one eligible Tag or classify its committed state.
	 *
	 * @param TagId             $tag_id Canonical public Tag ID.
	 * @param int               $owner_id Server-derived WordPress User ID.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @throws PersistenceMappingException When the write affects an invalid number of records.
	 */
	public function activate( TagId $tag_id, int $owner_id, DateTimeImmutable $now ): TagActivationResult {
		RecordValidator::positive_id( $owner_id, 'owner_id' );
		RecordValidator::utc( $now, 'now' );
		$timestamp = $this->dates->format( $now );

		$affected = $this->gateway->execute(
			'UPDATE %i AS t INNER JOIN %i AS b ON b.batch_id = t.batch_id
			SET t.owner_id = %d, t.tag_status = %s, t.activated_at = %s, t.updated_at = %s
			WHERE t.tag_id = %s
			  AND t.owner_id IS NULL
			  AND t.tag_status = %s
			  AND t.activated_at IS NULL
			  AND b.batch_status = %s
			  AND b.activation_enabled = 1',
			array(
				$this->tables->tags(),
				$this->tables->batches(),
				$owner_id,
				TagStatus::ACTIVE->value,
				$timestamp,
				$timestamp,
				$tag_id->value,
				TagStatus::UNREGISTERED->value,
				BatchStatus::RELEASED->value,
			)
		);

		if ( 1 === $affected ) {
			return TagActivationResult::ACTIVATED;
		}

		if ( 0 !== $affected ) {
			throw new PersistenceMappingException( 'Activation changed an unexpected number of records.' );
		}

		$row = $this->gateway->row(
			'SELECT t.owner_id, t.tag_status, t.activated_at, b.batch_status, b.activation_enabled
			FROM %i AS t
			LEFT JOIN %i AS b ON b.batch_id = t.batch_id
			WHERE t.tag_id = %s
			LIMIT 1
			FOR UPDATE',
			array( $this->tables->tags(), $this->tables->batches(), $tag_id->value )
		);

		if (
			null !== $row
			&& StoredRow::nullable_positive_int( $row, 'owner_id' ) === $owner_id
			&& TagStatus::ACTIVE->value === StoredRow::string( $row, 'tag_status' )
			&& null !== StoredRow::nullable_string( $row, 'activated_at' )
		) {
			return TagActivationResult::ALREADY_OWNED;
		}

		return TagActivationResult::STATE_CHANGED;
	}
}
