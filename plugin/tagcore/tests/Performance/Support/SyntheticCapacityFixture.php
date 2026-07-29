<?php
/**
 * Synthetic RT-210 database fixture.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Performance\Support;

use RuntimeException;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use wpdb;

/**
 * Creates deterministic, non-PII rows only inside the isolated test database.
 *
 * The deterministic Tag IDs are fixtures, not production generation logic.
 */
final readonly class SyntheticCapacityFixture {
	private const ALPHABET          = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
	private const INSERT_CHUNK_SIZE = 500;

	/**
	 * Create the fixture writer.
	 *
	 * @param wpdb       $database Active isolated test database.
	 * @param TableNames $tables Trusted table names.
	 */
	public function __construct(
		private wpdb $database,
		private TableNames $tables
	) {
	}

	/**
	 * Create equal complete Batches whose Tag IDs are interleaved globally.
	 *
	 * @param int $batch_count Number of Batches.
	 * @param int $tags_per_batch Tags assigned to each Batch.
	 * @return list<int> Inserted Batch IDs in Batch Code order.
	 * @throws RuntimeException When dimensions or a fixture write are invalid.
	 */
	public function create_dataset( int $batch_count, int $tags_per_batch ): array {
		if ( $batch_count < 1 || $tags_per_batch < 1 ) {
			throw new RuntimeException( 'Capacity fixture dimensions are invalid.' );
		}

		$total = $batch_count * $tags_per_batch;

		if ( $total > strlen( self::ALPHABET ) ** 6 ) {
			throw new RuntimeException( 'Capacity fixture exceeds the Tag ID space.' );
		}

		$batch_ids = array();
		$time      = '2026-07-29 10:00:00';

		for ( $batch = 0; $batch < $batch_count; ++$batch ) {
			$inserted = $this->database->insert(
				$this->tables->batches(),
				array(
					'batch_code'         => sprintf( 'RT-210-CAPACITY-%02d', $batch + 1 ),
					'tag_type'           => 'classic_tag',
					'model_code'         => 'RT210-CAPACITY',
					'smart_network'      => 'none',
					'requested_quantity' => $tags_per_batch,
					'generated_quantity' => $tags_per_batch,
					'batch_status'       => 'generated',
					'activation_enabled' => 0,
					'created_by'         => 1,
					'created_at'         => $time,
					'updated_at'         => $time,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s' )
			);

			if ( 1 !== $inserted || $this->database->insert_id < 1 ) {
				throw new RuntimeException( 'Capacity fixture could not create a Batch.' );
			}

			$batch_ids[] = (int) $this->database->insert_id;
		}

		for ( $offset = 0; $offset < $total; $offset += self::INSERT_CHUNK_SIZE ) {
			$values = array();
			$end    = min( $offset + self::INSERT_CHUNK_SIZE, $total );

			for ( $index = $offset; $index < $end; ++$index ) {
				$values[] = $this->database->prepare(
					'(%s,%d,%s,%s,%s,%d,%s,%s)',
					$this->tag_id( $index ),
					$batch_ids[ $index % $batch_count ],
					'classic_tag',
					'RT210-CAPACITY',
					'unregistered',
					0,
					$time,
					$time
				);
			}

			$query = sprintf(
				'INSERT INTO %s (tag_id,batch_id,tag_type,model_code,tag_status,lost_mode,created_at,updated_at) VALUES %s',
				$this->tables->tags(),
				implode( ',', $values )
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Synthetic values are prepared above and the table is a trusted isolated fixture.
			$inserted = $this->database->query( $query );

			if ( count( $values ) !== $inserted ) {
				throw new RuntimeException( 'Capacity fixture could not create Tag rows.' );
			}
		}

		$this->append_generation_events( $batch_ids[0], $time );

		return $batch_ids;
	}

	/**
	 * Return one deterministic valid Tag ID for a fixture row.
	 *
	 * @param int $index Zero-based global fixture index.
	 * @throws RuntimeException When the fixture index is invalid.
	 */
	public function tag_id( int $index ): string {
		if ( $index < 0 ) {
			throw new RuntimeException( 'Capacity fixture index is invalid.' );
		}

		$value = '';
		$base  = strlen( self::ALPHABET );

		for ( $position = 0; $position < 6; ++$position ) {
			$value = self::ALPHABET[ $index % $base ] . $value;
			$index = intdiv( $index, $base );
		}

		return $value;
	}

	/**
	 * Add the two aggregate generation Events required by the progress projection.
	 *
	 * @param int    $batch_id Target Batch ID.
	 * @param string $time Fixture UTC time.
	 * @throws RuntimeException When an Event cannot be written.
	 */
	private function append_generation_events( int $batch_id, string $time ): void {
		foreach ( array( 'batch_generation_started', 'batch_generation_completed' ) as $event_type ) {
			$inserted = $this->database->insert(
				$this->tables->events(),
				array(
					'event_type'   => $event_type,
					'actor_type'   => 'system',
					'actor_id'     => null,
					'target_type'  => 'batch',
					'target_id'    => (string) $batch_id,
					'event_result' => 'success',
					'created_at'   => $time,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( 1 !== $inserted ) {
				throw new RuntimeException( 'Capacity fixture could not create an Event.' );
			}
		}
	}
}
