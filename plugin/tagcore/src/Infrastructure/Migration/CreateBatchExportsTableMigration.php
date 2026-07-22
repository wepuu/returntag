<?php
/**
 * RT-104 batch exports table migration.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/**
 * Creates and verifies schema version 0003.
 */
final class CreateBatchExportsTableMigration implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param wpdb                     $database     WordPress database adapter.
	 * @param TableNames               $table_names Trusted table-name mapping.
	 * @param WordPressSchemaInspector $inspector   Schema postcondition verifier.
	 * @param CreateTagsTableMigration $prerequisite Required version 0002 schema.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly TableNames $table_names,
		private readonly WordPressSchemaInspector $inspector,
		private readonly CreateTagsTableMigration $prerequisite
	) {
	}

	/**
	 * Return schema version three.
	 */
	public function version(): int {
		return 3;
	}

	/**
	 * Return a stable non-sensitive migration name.
	 */
	public function name(): string {
		return 'create_returntag_batch_exports_table';
	}

	/**
	 * Create or safely complete the batch exports table with dbDelta().
	 *
	 * @throws MigrationException When the predecessor or existing table is incompatible.
	 */
	public function up(): void {
		if ( ! $this->prerequisite->verify() ) {
			throw new MigrationException( 'The required previous schema is unavailable.' );
		}

		$state = $this->inspect();

		if ( SchemaTableState::INCOMPATIBLE === $state ) {
			throw new MigrationException( 'The existing schema is incompatible with this migration.' );
		}

		if ( SchemaTableState::EXACT === $state ) {
			return;
		}

		$table_name      = $this->table_names->batch_exports();
		$charset_collate = $this->database->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
		export_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		batch_id bigint(20) unsigned NOT NULL,
		export_version int(10) unsigned NOT NULL,
		row_count int(10) unsigned NOT NULL,
		file_format varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		file_checksum char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		created_by bigint(20) unsigned NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (export_id),
		UNIQUE KEY batch_export_version_unique (batch_id, export_version),
		KEY batch_file_checksum (batch_id, file_checksum)
	) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Verify the complete RT-104 schema contract and its predecessors.
	 */
	public function verify(): bool {
		if ( ! $this->prerequisite->verify() ) {
			return false;
		}

		return SchemaTableState::EXACT === $this->inspect();
	}

	/**
	 * Inspect the complete RT-104 table contract before mutation.
	 */
	private function inspect(): SchemaTableState {
		$wordpress_collation = strtolower( (string) $this->database->collate );

		return $this->inspector->inspect_table(
			$this->table_names->batch_exports(),
			'InnoDB',
			$wordpress_collation,
			array(
				'export_id'      => $this->integer_requirement( 'bigint', true ),
				'batch_id'       => $this->integer_requirement( 'bigint' ),
				'export_version' => $this->integer_requirement( 'int' ),
				'row_count'      => $this->integer_requirement( 'int' ),
				'file_format'    => $this->string_requirement( 'varchar', 32 ),
				'file_checksum'  => $this->string_requirement( 'char', 64 ),
				'created_by'     => $this->integer_requirement( 'bigint' ),
				'created_at'     => array(
					'data_type' => 'datetime',
					'unsigned'  => false,
					'nullable'  => false,
					'default'   => null,
				),
			),
			array(
				'PRIMARY'                     => array(
					'unique'  => true,
					'columns' => array( 'export_id' ),
				),
				'batch_export_version_unique' => array(
					'unique'  => true,
					'columns' => array( 'batch_id', 'export_version' ),
				),
				'batch_file_checksum'         => array(
					'unique'  => false,
					'columns' => array( 'batch_id', 'file_checksum' ),
				),
			)
		);
	}

	/**
	 * Build an unsigned integer requirement.
	 *
	 * @param string $data_type      Integer data type.
	 * @param bool   $auto_increment Whether the column auto-increments.
	 * @return array{data_type: string, unsigned: true, nullable: false, default: null, auto_increment: bool}
	 */
	private function integer_requirement( string $data_type, bool $auto_increment = false ): array {
		return array(
			'data_type'      => $data_type,
			'unsigned'       => true,
			'nullable'       => false,
			'default'        => null,
			'auto_increment' => $auto_increment,
		);
	}

	/**
	 * Build a required ASCII binary string requirement.
	 *
	 * @param string $data_type SQL string type.
	 * @param int    $length    Exact character length.
	 * @return array{data_type: string, unsigned: false, nullable: false, default: null, maximum_length: int, character_set: string, collation: string}
	 */
	private function string_requirement( string $data_type, int $length ): array {
		return array(
			'data_type'      => $data_type,
			'unsigned'       => false,
			'nullable'       => false,
			'default'        => null,
			'maximum_length' => $length,
			'character_set'  => 'ascii',
			'collation'      => 'ascii_bin',
		);
	}
}
