<?php
/**
 * RT-107 access tokens table migration.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/**
 * Creates and verifies schema version 0007.
 */
final class CreateAccessTokensTableMigration implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param wpdb                         $database     WordPress database adapter.
	 * @param TableNames                   $table_names Trusted table-name mapping.
	 * @param WordPressSchemaInspector     $inspector   Schema postcondition verifier.
	 * @param CreateMessagesTableMigration $prerequisite Required version 0006 schema.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly TableNames $table_names,
		private readonly WordPressSchemaInspector $inspector,
		private readonly CreateMessagesTableMigration $prerequisite
	) {
	}

	/**
	 * Return schema version seven.
	 */
	public function version(): int {
		return 7;
	}

	/**
	 * Return a stable non-sensitive migration name.
	 */
	public function name(): string {
		return 'create_returntag_access_tokens_table';
	}

	/**
	 * Create or safely complete the access tokens table with dbDelta().
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

		$table_name      = $this->table_names->access_tokens();
		$charset_collate = $this->database->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
		token_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		conversation_id bigint(20) unsigned NOT NULL,
		purpose varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		actor_role varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		token_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		expires_at datetime NOT NULL,
		exchanged_at datetime DEFAULT NULL,
		revoked_at datetime DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (token_id),
		UNIQUE KEY token_hash_unique (token_hash),
		KEY conversation_purpose_actor (conversation_id, purpose, actor_role),
		KEY expires_revoked_at (expires_at, revoked_at)
	) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Verify the complete RT-107 access token contract and its predecessors.
	 */
	public function verify(): bool {
		if ( ! $this->prerequisite->verify() ) {
			return false;
		}

		return SchemaTableState::EXACT === $this->inspect();
	}

	/**
	 * Inspect the complete access tokens table contract before mutation.
	 */
	private function inspect(): SchemaTableState {
		$wordpress_collation = strtolower( (string) $this->database->collate );

		return $this->inspector->inspect_table(
			$this->table_names->access_tokens(),
			'InnoDB',
			$wordpress_collation,
			array(
				'token_id'        => $this->integer_requirement( 'bigint', true ),
				'conversation_id' => $this->integer_requirement( 'bigint' ),
				'purpose'         => $this->ascii_requirement( 'varchar', 32 ),
				'actor_role'      => $this->ascii_requirement( 'varchar', 32 ),
				'token_hash'      => $this->ascii_requirement( 'char', 64 ),
				'expires_at'      => $this->datetime_requirement(),
				'exchanged_at'    => $this->datetime_requirement( true ),
				'revoked_at'      => $this->datetime_requirement( true ),
				'created_at'      => $this->datetime_requirement(),
			),
			array(
				'PRIMARY'                    => array(
					'unique'  => true,
					'columns' => array( 'token_id' ),
				),
				'conversation_purpose_actor' => array(
					'unique'  => false,
					'columns' => array( 'conversation_id', 'purpose', 'actor_role' ),
				),
				'expires_revoked_at'         => array(
					'unique'  => false,
					'columns' => array( 'expires_at', 'revoked_at' ),
				),
				'token_hash_unique'          => array(
					'unique'  => true,
					'columns' => array( 'token_hash' ),
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
