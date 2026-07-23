<?php
/**
 * RT-106 messages table migration.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/**
 * Creates and verifies schema version 0006.
 */
final class CreateMessagesTableMigration implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param wpdb                              $database     WordPress database adapter.
	 * @param TableNames                        $table_names Trusted table-name mapping.
	 * @param WordPressSchemaInspector          $inspector   Schema postcondition verifier.
	 * @param CreateConversationsTableMigration $prerequisite Required version 0005 schema.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly TableNames $table_names,
		private readonly WordPressSchemaInspector $inspector,
		private readonly CreateConversationsTableMigration $prerequisite
	) {
	}

	/**
	 * Return schema version six.
	 */
	public function version(): int {
		return 6;
	}

	/**
	 * Return a stable non-sensitive migration name.
	 */
	public function name(): string {
		return 'create_returntag_messages_table';
	}

	/**
	 * Create or safely complete the messages table with dbDelta().
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

		$table_name      = $this->table_names->messages();
		$charset_collate = $this->database->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
		message_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		conversation_id bigint(20) unsigned NOT NULL,
		sender_role varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		body_ciphertext longblob NOT NULL,
		delivery_status varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'queued',
		provider_message_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
		delivered_at datetime DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (message_id),
		KEY conversation_message (conversation_id, message_id),
		KEY delivery_status_created_at (delivery_status, created_at),
		KEY provider_message_id (provider_message_id)
	) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Verify the complete RT-106 messages contract and its predecessors.
	 */
	public function verify(): bool {
		if ( ! $this->prerequisite->verify() ) {
			return false;
		}

		return SchemaTableState::EXACT === $this->inspect();
	}

	/**
	 * Inspect the complete messages table contract before mutation.
	 */
	private function inspect(): SchemaTableState {
		$wordpress_collation = strtolower( (string) $this->database->collate );

		return $this->inspector->inspect_table(
			$this->table_names->messages(),
			'InnoDB',
			$wordpress_collation,
			array(
				'message_id'          => $this->integer_requirement( 'bigint', true ),
				'conversation_id'     => $this->integer_requirement( 'bigint' ),
				'sender_role'         => $this->ascii_requirement( 'varchar', 32 ),
				'body_ciphertext'     => $this->plain_requirement( 'longblob' ),
				'delivery_status'     => $this->ascii_requirement( 'varchar', 32, false, 'queued' ),
				'provider_message_id' => $this->ascii_requirement( 'varchar', 191, true ),
				'delivered_at'        => $this->datetime_requirement( true ),
				'created_at'          => $this->datetime_requirement(),
			),
			array(
				'PRIMARY'                    => array(
					'unique'  => true,
					'columns' => array( 'message_id' ),
				),
				'conversation_message'       => array(
					'unique'  => false,
					'columns' => array( 'conversation_id', 'message_id' ),
				),
				'delivery_status_created_at' => array(
					'unique'  => false,
					'columns' => array( 'delivery_status', 'created_at' ),
				),
				'provider_message_id'        => array(
					'unique'  => false,
					'columns' => array( 'provider_message_id' ),
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
	 * @param string      $data_type    SQL string type.
	 * @param int         $length       Exact character length.
	 * @param bool        $nullable     Whether NULL is accepted.
	 * @param string|null $default_value Exact string default.
	 * @return array{data_type: string, unsigned: false, nullable: bool, default: string|null, maximum_length: int, character_set: string, collation: string}
	 */
	private function ascii_requirement( string $data_type, int $length, bool $nullable = false, ?string $default_value = null ): array {
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
