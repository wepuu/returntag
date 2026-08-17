<?php
/**
 * RT-328 additive evidence-hold migration.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/** Adds and verifies the complete Schema 14 Hold tuple. */
final class AddFinderEvidenceHoldMigration implements Migration {
	/**
	 * Create the Migration.
	 *
	 * @param wpdb                             $database WordPress database adapter.
	 * @param TableNames                       $tables Trusted table names.
	 * @param CreateTagTransfersTableMigration $prerequisite Required Schema 13 Migration.
	 */
	public function __construct( private wpdb $database, private TableNames $tables, private CreateTagTransfersTableMigration $prerequisite ) {
	}

	/** Return Schema version fourteen. */
	public function version(): int {
		return 14; }
	/** Return the stable Migration name. */
	public function name(): string {
		return 'add_returntag_finder_evidence_hold'; }
	/**
	 * Install the additive Hold contract.
	 *
	 * @throws MigrationException When Schema 13 or the additive contract is unavailable.
	 */
	public function up(): void {
		if ( ! $this->prerequisite->verify() ) {
			throw new MigrationException( 'The required previous schema is unavailable.' ); }
		if ( $this->verify_hold() ) {
			return; }
		$table = $this->tables->finder_report_media();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
		$result = $this->database->query( "ALTER TABLE {$table} ADD COLUMN hold_until datetime DEFAULT NULL AFTER retention_until, ADD COLUMN hold_placed_at datetime DEFAULT NULL AFTER hold_until, ADD COLUMN hold_placed_by bigint(20) unsigned DEFAULT NULL AFTER hold_placed_at, ADD KEY media_retention_hold (media_status, retention_until, hold_until)" );
		if ( false === $result || ! $this->verify_hold() ) {
			throw new MigrationException( 'Finder evidence hold schema could not be installed.' ); }
	}
	/** Verify Schema 14 and its predecessor. */
	public function verify(): bool {
		return $this->prerequisite->verify() && $this->verify_hold(); }
	/**
	 * Verify the three nullable columns and cleanup index.
	 *
	 * @phpstan-impure
	 */
	private function verify_hold(): bool {
		$table   = $this->tables->finder_report_media();
		$columns = $this->database->get_results( $this->database->prepare( 'SELECT COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME IN (%s,%s,%s) ORDER BY ORDINAL_POSITION', $table, 'hold_until', 'hold_placed_at', 'hold_placed_by' ), ARRAY_A );
		$index   = $this->database->get_results( $this->database->prepare( 'SELECT NON_UNIQUE,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND INDEX_NAME=%s ORDER BY SEQ_IN_INDEX', $table, 'media_retention_hold' ), ARRAY_A );
		return is_array( $columns ) && 3 === count( $columns )
			&& array( 'hold_until', 'hold_placed_at', 'hold_placed_by' ) === array_column( $columns, 'COLUMN_NAME' )
			&& 'datetime' === strtolower( (string) $columns[0]['DATA_TYPE'] ) && 'datetime' === strtolower( (string) $columns[1]['DATA_TYPE'] )
			&& 'bigint' === strtolower( (string) $columns[2]['DATA_TYPE'] ) && str_contains( strtolower( (string) $columns[2]['COLUMN_TYPE'] ), 'unsigned' )
			&& 0 === count( array_filter( $columns, static fn( array $column ): bool => 'YES' !== $column['IS_NULLABLE'] || ( null !== $column['COLUMN_DEFAULT'] && 'NULL' !== strtoupper( (string) $column['COLUMN_DEFAULT'] ) ) ) )
			&& is_array( $index ) && array( 'media_status', 'retention_until', 'hold_until' ) === array_column( $index, 'COLUMN_NAME' )
			&& 0 === count( array_filter( $index, static fn( array $row ): bool => '1' !== (string) $row['NON_UNIQUE'] ) );
	}
}
