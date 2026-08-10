<?php
/**
 * RT-315 Finder Reports table migration.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/**
 * Creates and verifies schema version 0009.
 */
final class CreateFinderReportsTableMigration implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param wpdb                       $database Database adapter.
	 * @param TableNames                 $table_names Trusted table-name mapping.
	 * @param WordPressSchemaInspector   $inspector Schema verifier.
	 * @param CreateEventsTableMigration $prerequisite Required version 0008 schema.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly TableNames $table_names,
		private readonly WordPressSchemaInspector $inspector,
		private readonly CreateEventsTableMigration $prerequisite
	) {
	}

	/**
	 * Return schema version nine.
	 */
	public function version(): int {
		return 9;
	}

	/**
	 * Return a stable non-sensitive migration name.
	 */
	public function name(): string {
		return 'create_returntag_finder_reports_table';
	}

	/**
	 * Create or safely complete the Finder Reports table.
	 *
	 * @throws MigrationException When the predecessor or existing schema is incompatible.
	 */
	public function up(): void {
		if ( ! $this->prerequisite->verify() ) {
			throw new MigrationException( 'The required previous schema is unavailable.' );
		}

		$state = $this->inspect();

		if ( SchemaTableState::INCOMPATIBLE === $state && ! $this->has_approved_conversation_extension() ) {
			throw new MigrationException( 'The existing schema is incompatible with this migration.' );
		}

		if ( SchemaTableState::EXACT === $state || $this->has_approved_conversation_extension() ) {
			return;
		}

		$table_name      = $this->table_names->finder_reports();
		$charset_collate = $this->database->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
		finder_report_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		tag_id char(6) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		owner_id_at_submission bigint(20) unsigned NOT NULL,
		message_ciphertext longblob DEFAULT NULL,
		report_status varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		evidence_status varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		owner_notification_status varchar(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
		owner_notified_at datetime DEFAULT NULL,
		expires_at datetime NOT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (finder_report_id),
		KEY tag_status_created_at (tag_id, report_status, created_at),
		KEY owner_status_created_at (owner_id_at_submission, report_status, created_at),
		KEY report_status_expires_at (report_status, expires_at),
		KEY notification_status_updated_at (owner_notification_status, updated_at)
	) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Verify the complete version 0009 contract and predecessor.
	 */
	public function verify(): bool {
		return $this->prerequisite->verify()
			&& ( SchemaTableState::EXACT === $this->inspect() || $this->has_approved_conversation_extension() );
	}

	/**
	 * Recognize only the approved additive Schema 11 column and index.
	 */
	private function has_approved_conversation_extension(): bool {
		$table   = $this->table_names->finder_reports();
		$columns = $this->database->get_col(
			$this->database->prepare(
				'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s ORDER BY ORDINAL_POSITION',
				$table
			)
		);
		$indexes = $this->database->get_col(
			$this->database->prepare(
				'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s ORDER BY INDEX_NAME',
				$table
			)
		);

		return array(
			'finder_report_id',
			'conversation_id',
			'tag_id',
			'owner_id_at_submission',
			'message_ciphertext',
			'report_status',
			'evidence_status',
			'owner_notification_status',
			'owner_notified_at',
			'expires_at',
			'created_at',
			'updated_at',
		) === $columns
			&& array(
				'conversation_id_unique',
				'notification_status_updated_at',
				'owner_status_created_at',
				'PRIMARY',
				'report_status_expires_at',
				'tag_status_created_at',
			) === $indexes;
	}

	/**
	 * Inspect the complete table contract before mutation.
	 */
	private function inspect(): SchemaTableState {
		return $this->inspector->inspect_table(
			$this->table_names->finder_reports(),
			'InnoDB',
			strtolower( (string) $this->database->collate ),
			array(
				'finder_report_id'          => $this->integer_requirement( 'bigint', false, true ),
				'tag_id'                    => $this->ascii_requirement( 'char', 6 ),
				'owner_id_at_submission'    => $this->integer_requirement( 'bigint' ),
				'message_ciphertext'        => $this->plain_requirement( 'longblob', true ),
				'report_status'             => $this->ascii_requirement( 'varchar', 32 ),
				'evidence_status'           => $this->ascii_requirement( 'varchar', 32 ),
				'owner_notification_status' => $this->ascii_requirement( 'varchar', 32, true ),
				'owner_notified_at'         => $this->datetime_requirement( true ),
				'expires_at'                => $this->datetime_requirement(),
				'created_at'                => $this->datetime_requirement(),
				'updated_at'                => $this->datetime_requirement(),
			),
			array(
				'PRIMARY'                        => array(
					'unique'  => true,
					'columns' => array( 'finder_report_id' ),
				),
				'notification_status_updated_at' => array(
					'unique'  => false,
					'columns' => array( 'owner_notification_status', 'updated_at' ),
				),
				'owner_status_created_at'        => array(
					'unique'  => false,
					'columns' => array( 'owner_id_at_submission', 'report_status', 'created_at' ),
				),
				'report_status_expires_at'       => array(
					'unique'  => false,
					'columns' => array( 'report_status', 'expires_at' ),
				),
				'tag_status_created_at'          => array(
					'unique'  => false,
					'columns' => array( 'tag_id', 'report_status', 'created_at' ),
				),
			)
		);
	}

	/**
	 * Build an unsigned integer requirement.
	 *
	 * @param string $type SQL integer type.
	 * @param bool   $nullable Whether NULL is accepted.
	 * @param bool   $auto_increment Whether the column auto-increments.
	 * @return array{data_type: string, unsigned: true, nullable: bool, default: null, auto_increment: bool}
	 */
	private function integer_requirement( string $type, bool $nullable = false, bool $auto_increment = false ): array {
		return array(
			'data_type'      => $type,
			'unsigned'       => true,
			'nullable'       => $nullable,
			'default'        => null,
			'auto_increment' => $auto_increment,
		);
	}

	/**
	 * Build an ASCII binary string requirement.
	 *
	 * @param string $type SQL string type.
	 * @param int    $length Exact character length.
	 * @param bool   $nullable Whether NULL is accepted.
	 * @return array{data_type: string, unsigned: false, nullable: bool, default: null, maximum_length: int, character_set: string, collation: string}
	 */
	private function ascii_requirement( string $type, int $length, bool $nullable = false ): array {
		return array(
			'data_type'      => $type,
			'unsigned'       => false,
			'nullable'       => $nullable,
			'default'        => null,
			'maximum_length' => $length,
			'character_set'  => 'ascii',
			'collation'      => 'ascii_bin',
		);
	}

	/**
	 * Build a non-text scalar requirement.
	 *
	 * @param string $type SQL data type.
	 * @param bool   $nullable Whether NULL is accepted.
	 * @return array{data_type: string, unsigned: false, nullable: bool, default: null}
	 */
	private function plain_requirement( string $type, bool $nullable = false ): array {
		return array(
			'data_type' => $type,
			'unsigned'  => false,
			'nullable'  => $nullable,
			'default'   => null,
		);
	}

	/**
	 * Build a UTC datetime requirement.
	 *
	 * @param bool $nullable Whether NULL is accepted.
	 * @return array{data_type: 'datetime', unsigned: false, nullable: bool, default: null}
	 */
	private function datetime_requirement( bool $nullable = false ): array {
		return array(
			'data_type' => 'datetime',
			'unsigned'  => false,
			'nullable'  => $nullable,
			'default'   => null,
		);
	}
}
