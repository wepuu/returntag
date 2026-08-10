<?php
/**
 * RT-315 Stage 6 Message dispatch claim migration.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/** Adds one-attempt dispatch convergence fields to encrypted Messages. */
final class AddMessageDispatchClaimsMigration implements Migration {
	/**
	 * Create Migration 0012.
	 *
	 * @param wpdb                                      $database Database adapter.
	 * @param TableNames                                $table_names Trusted table names.
	 * @param LinkFinderReportsToConversationsMigration $prerequisite Required Schema 11 migration.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly TableNames $table_names,
		private readonly LinkFinderReportsToConversationsMigration $prerequisite
	) {
	}

	/** Return Schema version twelve. */
	public function version(): int {
		return 12;
	}

	/** Return the stable migration name. */
	public function name(): string {
		return 'add_returntag_message_dispatch_claims';
	}

	/**
	 * Add dispatch fields after verifying Schema 11.
	 *
	 * @throws MigrationException When the predecessor or postcondition is unavailable.
	 */
	public function up(): void {
		if ( ! $this->prerequisite->verify() ) {
			throw new MigrationException( 'The required previous schema is unavailable.' );
		}
		if ( $this->verify_dispatch_contract() ) {
			return;
		}

		$table = $this->table_names->messages();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier owned by TableNames.
		$result = $this->database->query( "ALTER TABLE {$table} ADD COLUMN dispatch_claimed_at datetime DEFAULT NULL AFTER delivered_at, ADD COLUMN dispatch_attempt_count int(10) unsigned NOT NULL DEFAULT 0 AFTER dispatch_claimed_at, ADD KEY message_dispatch (delivery_status, dispatch_claimed_at, message_id)" );

		if ( false === $result || ! $this->verify_dispatch_contract() ) {
			throw new MigrationException( 'Message dispatch claims could not be installed.' );
		}
	}

	/** Verify Schema 11 and the additive dispatch contract. */
	public function verify(): bool {
		return $this->prerequisite->verify() && $this->verify_dispatch_contract();
	}

	/**
	 * Verify both fields and the ordered recovery index.
	 *
	 * @phpstan-impure
	 */
	private function verify_dispatch_contract(): bool {
		$table   = $this->table_names->messages();
		$columns = $this->database->get_results(
			$this->database->prepare(
				'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME IN (%s, %s) ORDER BY ORDINAL_POSITION',
				$table,
				'dispatch_claimed_at',
				'dispatch_attempt_count'
			),
			ARRAY_A
		) ?? array();
		$indexes = $this->database->get_results(
			$this->database->prepare(
				'SELECT NON_UNIQUE, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s ORDER BY SEQ_IN_INDEX',
				$table,
				'message_dispatch'
			),
			ARRAY_A
		) ?? array();

		return 2 === count( $columns )
			&& 'dispatch_claimed_at' === ( $columns[0]['COLUMN_NAME'] ?? null )
			&& 'datetime' === strtolower( (string) ( $columns[0]['DATA_TYPE'] ?? '' ) )
			&& 'YES' === ( $columns[0]['IS_NULLABLE'] ?? null )
			&& in_array( $columns[0]['COLUMN_DEFAULT'] ?? null, array( null, 'NULL' ), true )
			&& 'dispatch_attempt_count' === ( $columns[1]['COLUMN_NAME'] ?? null )
			&& 'int' === strtolower( (string) ( $columns[1]['DATA_TYPE'] ?? '' ) )
			&& str_contains( strtolower( (string) ( $columns[1]['COLUMN_TYPE'] ?? '' ) ), 'unsigned' )
			&& 'NO' === ( $columns[1]['IS_NULLABLE'] ?? null )
			&& '0' === (string) ( $columns[1]['COLUMN_DEFAULT'] ?? '' )
			&& array( 'delivery_status', 'dispatch_claimed_at', 'message_id' ) === array_map( static fn( array $row ): mixed => $row['COLUMN_NAME'] ?? null, $indexes )
			&& array_reduce( $indexes, static fn( bool $valid, array $row ): bool => $valid && '1' === (string) ( $row['NON_UNIQUE'] ?? '' ), true );
	}
}
