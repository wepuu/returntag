<?php
/**
 * WordPress database public Tag state reader.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\PublicTag\PublicTagStateReader;
use ReturnTag\TagCore\Application\PublicTag\PublicTagStateRecord;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Loads one exact projection without selecting owner-private item data.
 */
final readonly class WpdbPublicTagStateReader implements PublicTagStateReader {
	/**
	 * Create the reader.
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
	 * Find one Tag and its Batch using the Tag primary key.
	 *
	 * A left join preserves a Tag whose Batch row is missing so the Application
	 * can fail closed as a data-integrity error rather than report a false 404.
	 *
	 * @param TagId $tag_id Canonical public Tag ID.
	 * @throws PersistenceMappingException When the stored projection is invalid.
	 */
	public function find( TagId $tag_id ): ?PublicTagStateRecord {
		$row = $this->gateway->row(
			'SELECT t.owner_id, t.tag_type, t.public_label, t.tag_status, t.lost_mode, t.lost_message, t.activated_at, b.batch_status, b.activation_enabled AS batch_activation_enabled FROM %i AS t LEFT JOIN %i AS b ON b.batch_id = t.batch_id WHERE t.tag_id = %s LIMIT 1',
			array( $this->tables->tags(), $this->tables->batches(), $tag_id->value )
		);

		if ( null === $row ) {
			return null;
		}

		$batch_status_value = StoredRow::nullable_string( $row, 'batch_status' );
		$batch_status       = null;
		$batch_enabled      = null;

		if ( null !== $batch_status_value ) {
			$batch_status = BatchStatus::tryFrom( $batch_status_value );

			if ( null === $batch_status ) {
				throw new PersistenceMappingException( 'Stored public Tag state has an unknown Batch status.' );
			}

			$batch_enabled = StoredRow::boolean( $row, 'batch_activation_enabled' );
		} elseif ( array_key_exists( 'batch_activation_enabled', $row ) && null !== $row['batch_activation_enabled'] ) {
			throw new PersistenceMappingException( 'Stored public Tag state has an inconsistent Batch projection.' );
		}

		return new PublicTagStateRecord(
			StoredRow::nullable_positive_int( $row, 'owner_id' ),
			StoredRow::enum( $row, 'tag_type', TagType::class ),
			StoredRow::nullable_string( $row, 'public_label' ),
			StoredRow::enum( $row, 'tag_status', TagStatus::class ),
			StoredRow::boolean( $row, 'lost_mode' ),
			StoredRow::nullable_string( $row, 'lost_message' ),
			$this->dates->parse_nullable( StoredRow::nullable_string( $row, 'activated_at' ) ),
			$batch_status,
			$batch_enabled
		);
	}
}
