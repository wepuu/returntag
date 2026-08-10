<?php
/**
 * RT-315 Finder Report to Conversation linkage migration.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/** Adds one nullable, unique Conversation link to each Finder Report. */
final class LinkFinderReportsToConversationsMigration implements Migration {
	/**
	 * Create Migration 0011.
	 *
	 * @param wpdb                                  $database Database adapter.
	 * @param TableNames                            $table_names Trusted table names.
	 * @param CreateFinderReportMediaTableMigration $prerequisite Required Schema 10 migration.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly TableNames $table_names,
		private readonly CreateFinderReportMediaTableMigration $prerequisite
	) {
	}

	/** Return Schema version eleven. */
	public function version(): int {
		return 11;
	}

	/** Return the stable migration name. */
	public function name(): string {
		return 'link_returntag_finder_reports_to_conversations';
	}

	/**
	 * Add the nullable unique link after verifying Schema 10.
	 *
	 * @throws MigrationException When the predecessor or linkage cannot be verified.
	 */
	public function up(): void {
		if ( ! $this->prerequisite->verify() ) {
			throw new MigrationException( 'The required previous schema is unavailable.' );
		}

		if ( $this->verify_link() ) {
			return;
		}

		$table_name = $this->table_names->finder_reports();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier owned by TableNames.
		$result = $this->database->query( "ALTER TABLE {$table_name} ADD COLUMN conversation_id bigint(20) unsigned DEFAULT NULL AFTER finder_report_id, ADD UNIQUE KEY conversation_id_unique (conversation_id)" );

		if ( false === $result || ! $this->verify_link() ) {
			throw new MigrationException( 'Finder Report Conversation linkage could not be installed.' );
		}
	}

	/** Verify the complete Schema 11 link contract. */
	public function verify(): bool {
		return $this->verify_link();
	}

	/**
	 * Verify the nullable unsigned column and unique index.
	 *
	 * @phpstan-impure
	 */
	private function verify_link(): bool {
		$table  = $this->table_names->finder_reports();
		$column = $this->database->get_row(
			$this->database->prepare(
				'SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				$table,
				'conversation_id'
			),
			ARRAY_A
		);
		$index  = $this->database->get_results(
			$this->database->prepare(
				'SELECT NON_UNIQUE, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s ORDER BY SEQ_IN_INDEX',
				$table,
				'conversation_id_unique'
			),
			ARRAY_A
		);

		$index_rows = $index ?? array();
		$index_row  = 1 === count( $index_rows ) ? current( $index_rows ) : null;
		$default    = is_array( $column ) ? ( $column['COLUMN_DEFAULT'] ?? null ) : null;

		return is_array( $column )
			&& 'bigint' === strtolower( (string) ( $column['DATA_TYPE'] ?? '' ) )
			&& str_contains( strtolower( (string) ( $column['COLUMN_TYPE'] ?? '' ) ), 'unsigned' )
			&& 'YES' === ( $column['IS_NULLABLE'] ?? null )
			&& ( null === $default || 'NULL' === strtoupper( (string) $default ) )
			&& is_array( $index_row )
			&& '0' === (string) ( $index_row['NON_UNIQUE'] ?? '' )
			&& 'conversation_id' === ( $index_row['COLUMN_NAME'] ?? null );
	}
}
