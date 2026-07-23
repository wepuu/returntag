<?php
/**
 * RT-106 conversations table migration.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/**
 * Creates and verifies schema version 0005.
 */
final class CreateConversationsTableMigration implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param wpdb                               $database     WordPress database adapter.
	 * @param TableNames                         $table_names Trusted table-name mapping.
	 * @param WordPressSchemaInspector           $inspector   Schema postcondition verifier.
	 * @param CreateAuthChallengesTableMigration $prerequisite Required version 0004 schema.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly TableNames $table_names,
		private readonly WordPressSchemaInspector $inspector,
		private readonly CreateAuthChallengesTableMigration $prerequisite
	) {
	}

	/**
	 * Return schema version five.
	 */
	public function version(): int {
		return 5;
	}

	/**
	 * Return a stable non-sensitive migration name.
	 */
	public function name(): string {
		return 'create_returntag_conversations_table';
	}

	/**
	 * Create or safely complete the conversations table with dbDelta().
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

		$table_name      = $this->table_names->conversations();
		$charset_collate = $this->database->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
		conversation_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		tag_id char(6) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		owner_id_snapshot bigint(20) unsigned NOT NULL,
		finder_email_ciphertext longblob NOT NULL,
		finder_email_lookup char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		finder_verified_at datetime DEFAULT NULL,
		conversation_status varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		expires_at datetime NOT NULL,
		last_activity_at datetime NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (conversation_id),
		KEY tag_id_status_activity (tag_id, conversation_status, last_activity_at),
		KEY owner_status_activity (owner_id_snapshot, conversation_status, last_activity_at),
		KEY finder_lookup_created_at (finder_email_lookup, created_at),
		KEY status_expires_at (conversation_status, expires_at)
	) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Verify the complete RT-106 conversations contract and its predecessors.
	 */
	public function verify(): bool {
		if ( ! $this->prerequisite->verify() ) {
			return false;
		}

		return SchemaTableState::EXACT === $this->inspect();
	}

	/**
	 * Inspect the complete conversations table contract before mutation.
	 */
	private function inspect(): SchemaTableState {
		$wordpress_collation = strtolower( (string) $this->database->collate );

		return $this->inspector->inspect_table(
			$this->table_names->conversations(),
			'InnoDB',
			$wordpress_collation,
			array(
				'conversation_id'         => $this->integer_requirement( 'bigint', true ),
				'tag_id'                  => $this->ascii_requirement( 'char', 6 ),
				'owner_id_snapshot'       => $this->integer_requirement( 'bigint' ),
				'finder_email_ciphertext' => $this->plain_requirement( 'longblob' ),
				'finder_email_lookup'     => $this->ascii_requirement( 'char', 64 ),
				'finder_verified_at'      => $this->datetime_requirement( true ),
				'conversation_status'     => $this->ascii_requirement( 'varchar', 32 ),
				'expires_at'              => $this->datetime_requirement(),
				'last_activity_at'        => $this->datetime_requirement(),
				'created_at'              => $this->datetime_requirement(),
			),
			array(
				'PRIMARY'                  => array(
					'unique'  => true,
					'columns' => array( 'conversation_id' ),
				),
				'finder_lookup_created_at' => array(
					'unique'  => false,
					'columns' => array( 'finder_email_lookup', 'created_at' ),
				),
				'owner_status_activity'    => array(
					'unique'  => false,
					'columns' => array( 'owner_id_snapshot', 'conversation_status', 'last_activity_at' ),
				),
				'status_expires_at'        => array(
					'unique'  => false,
					'columns' => array( 'conversation_status', 'expires_at' ),
				),
				'tag_id_status_activity'   => array(
					'unique'  => false,
					'columns' => array( 'tag_id', 'conversation_status', 'last_activity_at' ),
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
	 * Build an ASCII binary string requirement.
	 *
	 * @param string $data_type SQL string type.
	 * @param int    $length    Exact character length.
	 * @return array{data_type: string, unsigned: false, nullable: false, default: null, maximum_length: int, character_set: string, collation: string}
	 */
	private function ascii_requirement( string $data_type, int $length ): array {
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

	/**
	 * Build a non-text scalar requirement without collation.
	 *
	 * @param string $data_type SQL data type.
	 * @return array{data_type: string, unsigned: false, nullable: false, default: null}
	 */
	private function plain_requirement( string $data_type ): array {
		return array(
			'data_type' => $data_type,
			'unsigned'  => false,
			'nullable'  => false,
			'default'   => null,
		);
	}

	/**
	 * Build a UTC datetime storage requirement.
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
