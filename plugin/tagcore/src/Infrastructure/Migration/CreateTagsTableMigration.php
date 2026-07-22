<?php
/**
 * RT-103 tags table migration.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/**
 * Creates and verifies schema version 0002.
 */
final class CreateTagsTableMigration implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param wpdb                        $database     WordPress database adapter.
	 * @param TableNames                  $table_names Trusted table-name mapping.
	 * @param WordPressSchemaInspector    $inspector   Schema postcondition verifier.
	 * @param CreateBatchesTableMigration $prerequisite Required version 0001 schema.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly TableNames $table_names,
		private readonly WordPressSchemaInspector $inspector,
		private readonly CreateBatchesTableMigration $prerequisite
	) {
	}

	/**
	 * Return schema version two.
	 */
	public function version(): int {
		return 2;
	}

	/**
	 * Return a stable non-sensitive migration name.
	 */
	public function name(): string {
		return 'create_returntag_tags_table';
	}

	/**
	 * Create or safely complete the tags table with dbDelta().
	 *
	 * @throws MigrationException When the recorded predecessor schema has drifted.
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

		$table_name      = $this->table_names->tags();
		$charset_collate = $this->database->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
		tag_id char(6) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		batch_id bigint(20) unsigned NOT NULL,
		owner_id bigint(20) unsigned DEFAULT NULL,
		tag_type varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		model_code varchar(191) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
		item_name varchar(191) DEFAULT NULL,
		public_label varchar(191) DEFAULT NULL,
		tag_status varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'unregistered',
		lost_mode tinyint(1) unsigned NOT NULL DEFAULT 0,
		lost_message text NULL,
		owner_pairing_ack_at datetime DEFAULT NULL,
		activated_at datetime DEFAULT NULL,
		owner_changed_at datetime DEFAULT NULL,
		last_scanned_at datetime DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (tag_id),
		KEY batch_id_status (batch_id, tag_status),
		KEY owner_id_status (owner_id, tag_status),
		KEY tag_status_updated_at (tag_status, updated_at)
	) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Verify the complete RT-103 schema contract and its predecessor.
	 */
	public function verify(): bool {
		if ( ! $this->prerequisite->verify() ) {
			return false;
		}

		return SchemaTableState::EXACT === $this->inspect();
	}

	/**
	 * Inspect the complete RT-103 table contract before mutation.
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
			$this->table_names->tags(),
			'InnoDB',
			$wordpress_collation,
			array(
				'tag_id'               => $this->string_requirement( 'char', 6, false ),
				'batch_id'             => $this->integer_requirement( 'bigint', false ),
				'owner_id'             => $this->integer_requirement( 'bigint', true ),
				'tag_type'             => $this->string_requirement( 'varchar', 32, false ),
				'model_code'           => $this->string_requirement( 'varchar', 191, true ),
				'item_name'            => $this->unicode_string_requirement( 191, $unicode_column ),
				'public_label'         => $this->unicode_string_requirement( 191, $unicode_column ),
				'tag_status'           => $this->string_requirement( 'varchar', 32, false, 'unregistered' ),
				'lost_mode'            => $this->integer_requirement( 'tinyint', false, 0 ),
				'lost_message'         => array_merge(
					array(
						'data_type' => 'text',
						'unsigned'  => false,
						'nullable'  => true,
						'default'   => null,
					),
					$unicode_column
				),
				'owner_pairing_ack_at' => $this->datetime_requirement( true ),
				'activated_at'         => $this->datetime_requirement( true ),
				'owner_changed_at'     => $this->datetime_requirement( true ),
				'last_scanned_at'      => $this->datetime_requirement( true ),
				'created_at'           => $this->datetime_requirement( false ),
				'updated_at'           => $this->datetime_requirement( false ),
			),
			array(
				'PRIMARY'               => array(
					'unique'  => true,
					'columns' => array( 'tag_id' ),
				),
				'batch_id_status'       => array(
					'unique'  => false,
					'columns' => array( 'batch_id', 'tag_status' ),
				),
				'owner_id_status'       => array(
					'unique'  => false,
					'columns' => array( 'owner_id', 'tag_status' ),
				),
				'tag_status_updated_at' => array(
					'unique'  => false,
					'columns' => array( 'tag_status', 'updated_at' ),
				),
			)
		);
	}

	/**
	 * Build an ASCII binary string requirement.
	 *
	 * @param string          $data_type     SQL string type.
	 * @param int             $length        Maximum field length.
	 * @param bool            $nullable      Whether NULL is allowed.
	 * @param int|string|null $default_value Database default.
	 * @return array{data_type: string, unsigned: false, nullable: bool, default: int|string|null, maximum_length: int, character_set: string, collation: string}
	 */
	private function string_requirement( string $data_type, int $length, bool $nullable, int|string|null $default_value = null ): array {
		return array(
			'data_type'      => $data_type,
			'unsigned'       => false,
			'nullable'       => $nullable,
			'default'        => $default_value,
			'maximum_length' => $length,
			'character_set'  => 'ascii',
			'collation'      => 'ascii_bin',
		);
	}

	/**
	 * Build a nullable WordPress-charset string requirement.
	 *
	 * @param int   $length         Maximum field length.
	 * @param array $unicode_column Active WordPress charset and optional collation.
	 * @phpstan-param array{character_set: string, collation?: string} $unicode_column
	 * @return array{data_type: string, unsigned: false, nullable: true, default: null, maximum_length: int, character_set: string, collation?: string}
	 */
	private function unicode_string_requirement( int $length, array $unicode_column ): array {
		$requirement = array(
			'data_type'      => 'varchar',
			'unsigned'       => false,
			'nullable'       => true,
			'default'        => null,
			'maximum_length' => $length,
			'character_set'  => $unicode_column['character_set'],
		);

		if ( isset( $unicode_column['collation'] ) ) {
			$requirement['collation'] = $unicode_column['collation'];
		}

		return $requirement;
	}

	/**
	 * Build an unsigned integer requirement.
	 *
	 * @param string          $data_type     Integer data type.
	 * @param bool            $nullable      Whether NULL is allowed.
	 * @param int|string|null $default_value Database default.
	 * @return array{data_type: string, unsigned: true, nullable: bool, default: int|string|null}
	 */
	private function integer_requirement( string $data_type, bool $nullable, int|string|null $default_value = null ): array {
		return array(
			'data_type' => $data_type,
			'unsigned'  => true,
			'nullable'  => $nullable,
			'default'   => $default_value,
		);
	}

	/**
	 * Build a UTC datetime storage requirement.
	 *
	 * @param bool $nullable Whether the timestamp is optional.
	 * @return array{data_type: string, unsigned: false, nullable: bool, default: null}
	 */
	private function datetime_requirement( bool $nullable ): array {
		return array(
			'data_type' => 'datetime',
			'unsigned'  => false,
			'nullable'  => $nullable,
			'default'   => null,
		);
	}
}
