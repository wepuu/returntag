<?php
/**
 * RT-102 batches table migration.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/**
 * Creates and verifies schema version 0001.
 */
final class CreateBatchesTableMigration implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param wpdb                     $database    WordPress database adapter.
	 * @param TableNames               $table_names Trusted table-name mapping.
	 * @param WordPressSchemaInspector $inspector  Schema postcondition verifier.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly TableNames $table_names,
		private readonly WordPressSchemaInspector $inspector
	) {
	}

	/**
	 * Return schema version one.
	 */
	public function version(): int {
		return 1;
	}

	/**
	 * Return a stable non-sensitive migration name.
	 */
	public function name(): string {
		return 'create_returntag_batches_table';
	}

	/**
	 * Create or safely complete the batches table with dbDelta().
	 *
	 * @throws MigrationException When an existing table has unsafe schema drift.
	 */
	public function up(): void {
		$state = $this->inspect();

		if ( SchemaTableState::INCOMPATIBLE === $state ) {
			throw new MigrationException( 'The existing schema is incompatible with this migration.' );
		}

		if ( SchemaTableState::EXACT === $state ) {
			return;
		}

		$table_name      = $this->table_names->batches();
		$charset_collate = $this->database->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
		batch_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		batch_code varchar(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		tag_type varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		model_code varchar(191) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
		smart_network varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'none',
		manufacturer varchar(191) DEFAULT NULL,
		sales_channel varchar(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
		requested_quantity int(10) unsigned NOT NULL,
		generated_quantity int(10) unsigned NOT NULL DEFAULT 0,
		batch_status varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
		activation_enabled tinyint(1) unsigned NOT NULL DEFAULT 0,
		notes text NULL,
		created_by bigint(20) unsigned NOT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (batch_id),
		UNIQUE KEY batch_code_unique (batch_code),
		KEY batch_status_created_at (batch_status, created_at),
		KEY tag_type_model_code (tag_type, model_code),
		KEY activation_enabled_status (activation_enabled, batch_status)
	) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Verify the exact RT-102 schema contract.
	 */
	public function verify(): bool {
		return SchemaTableState::EXACT === $this->inspect();
	}

	/**
	 * Inspect the complete RT-102 schema before mutation.
	 */
	private function inspect(): SchemaTableState {
		$wordpress_charset   = strtolower( (string) $this->database->charset );
		$wordpress_collation = strtolower( (string) $this->database->collate );
		$unicode_column      = array(
			'character_set' => $wordpress_charset,
			'collation'     => $wordpress_collation,
		);

		if ( '' === $wordpress_collation ) {
			unset( $unicode_column['collation'] );
		}

		return $this->inspector->inspect_table(
			$this->table_names->batches(),
			'InnoDB',
			$wordpress_collation,
			array(
				'batch_id'           => array(
					'data_type'      => 'bigint',
					'unsigned'       => true,
					'nullable'       => false,
					'default'        => null,
					'auto_increment' => true,
				),
				'batch_code'         => $this->string_requirement( 191, false ),
				'tag_type'           => $this->string_requirement( 32, false ),
				'model_code'         => $this->string_requirement( 191, true ),
				'smart_network'      => $this->string_requirement( 32, false, 'none' ),
				'manufacturer'       => array_merge(
					array(
						'data_type'      => 'varchar',
						'unsigned'       => false,
						'nullable'       => true,
						'default'        => null,
						'maximum_length' => 191,
					),
					$unicode_column
				),
				'sales_channel'      => $this->string_requirement( 64, true ),
				'requested_quantity' => $this->integer_requirement( 'int' ),
				'generated_quantity' => $this->integer_requirement( 'int', 0 ),
				'batch_status'       => $this->string_requirement( 32, false, 'draft' ),
				'activation_enabled' => $this->integer_requirement( 'tinyint', 0 ),
				'notes'              => array_merge(
					array(
						'data_type' => 'text',
						'unsigned'  => false,
						'nullable'  => true,
						'default'   => null,
					),
					$unicode_column
				),
				'created_by'         => $this->integer_requirement( 'bigint' ),
				'created_at'         => $this->datetime_requirement(),
				'updated_at'         => $this->datetime_requirement(),
			),
			array(
				'PRIMARY'                   => array(
					'unique'  => true,
					'columns' => array( 'batch_id' ),
				),
				'activation_enabled_status' => array(
					'unique'  => false,
					'columns' => array( 'activation_enabled', 'batch_status' ),
				),
				'batch_code_unique'         => array(
					'unique'  => true,
					'columns' => array( 'batch_code' ),
				),
				'batch_status_created_at'   => array(
					'unique'  => false,
					'columns' => array( 'batch_status', 'created_at' ),
				),
				'tag_type_model_code'       => array(
					'unique'  => false,
					'columns' => array( 'tag_type', 'model_code' ),
				),
			)
		);
	}

	/**
	 * Build an ASCII binary string requirement.
	 *
	 * @param int             $length        Maximum field length.
	 * @param bool            $nullable      Whether NULL is allowed.
	 * @param int|string|null $default_value Database default.
	 * @return array{data_type: string, unsigned: false, nullable: bool, default: int|string|null, maximum_length: int, character_set: string, collation: string}
	 */
	private function string_requirement( int $length, bool $nullable, int|string|null $default_value = null ): array {
		return array(
			'data_type'      => 'varchar',
			'unsigned'       => false,
			'nullable'       => $nullable,
			'default'        => $default_value,
			'maximum_length' => $length,
			'character_set'  => 'ascii',
			'collation'      => 'ascii_bin',
		);
	}

	/**
	 * Build an unsigned integer requirement.
	 *
	 * @param string          $data_type     Integer data type.
	 * @param int|string|null $default_value Database default.
	 * @return array{data_type: string, unsigned: true, nullable: false, default: int|string|null}
	 */
	private function integer_requirement( string $data_type, int|string|null $default_value = null ): array {
		return array(
			'data_type' => $data_type,
			'unsigned'  => true,
			'nullable'  => false,
			'default'   => $default_value,
		);
	}

	/**
	 * Build a required UTC datetime storage requirement.
	 *
	 * @return array{data_type: string, unsigned: false, nullable: false, default: null}
	 */
	private function datetime_requirement(): array {
		return array(
			'data_type' => 'datetime',
			'unsigned'  => false,
			'nullable'  => false,
			'default'   => null,
		);
	}
}
