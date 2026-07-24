<?php
/**
 * RT-108 business audit events table migration.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/**
 * Creates and verifies schema version 0008.
 */
final class CreateEventsTableMigration implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param wpdb                             $database     WordPress database adapter.
	 * @param TableNames                       $table_names Trusted table-name mapping.
	 * @param WordPressSchemaInspector         $inspector   Schema postcondition verifier.
	 * @param CreateAccessTokensTableMigration $prerequisite Required version 0007 schema.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly TableNames $table_names,
		private readonly WordPressSchemaInspector $inspector,
		private readonly CreateAccessTokensTableMigration $prerequisite
	) {
	}

	/**
	 * Return schema version eight.
	 */
	public function version(): int {
		return 8;
	}

	/**
	 * Return a stable non-sensitive migration name.
	 */
	public function name(): string {
		return 'create_returntag_events_table';
	}

	/**
	 * Create or safely complete the events table with dbDelta().
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

		$table_name      = $this->table_names->events();
		$charset_collate = $this->database->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
		event_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_type varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		actor_type varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		actor_id bigint(20) unsigned DEFAULT NULL,
		target_type varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		target_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		event_result varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		correlation_id varchar(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
		metadata_json longtext DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (event_id),
		KEY event_type_created_at (event_type, created_at),
		KEY target_type_target_id_created_at (target_type, target_id, created_at),
		KEY actor_type_actor_id_created_at (actor_type, actor_id, created_at),
		KEY correlation_id (correlation_id),
		KEY created_at_event_id (created_at, event_id)
	) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Verify the complete RT-108 events contract and its predecessors.
	 */
	public function verify(): bool {
		if ( ! $this->prerequisite->verify() ) {
			return false;
		}

		return SchemaTableState::EXACT === $this->inspect();
	}

	/**
	 * Inspect the complete events table contract before mutation.
	 */
	private function inspect(): SchemaTableState {
		$wordpress_charset   = strtolower( (string) $this->database->charset );
		$wordpress_collation = strtolower( (string) $this->database->collate );

		return $this->inspector->inspect_table(
			$this->table_names->events(),
			'InnoDB',
			$wordpress_collation,
			array(
				'event_id'       => $this->integer_requirement( 'bigint', false, true ),
				'event_type'     => $this->ascii_requirement( 'varchar', 64 ),
				'actor_type'     => $this->ascii_requirement( 'varchar', 32 ),
				'actor_id'       => $this->integer_requirement( 'bigint', true ),
				'target_type'    => $this->ascii_requirement( 'varchar', 32 ),
				'target_id'      => $this->ascii_requirement( 'varchar', 191 ),
				'event_result'   => $this->ascii_requirement( 'varchar', 32 ),
				'correlation_id' => $this->ascii_requirement( 'varchar', 64, true ),
				'metadata_json'  => $this->text_requirement( $wordpress_charset, $wordpress_collation ),
				'created_at'     => $this->datetime_requirement(),
			),
			array(
				'PRIMARY'                          => array(
					'unique'  => true,
					'columns' => array( 'event_id' ),
				),
				'actor_type_actor_id_created_at'   => array(
					'unique'  => false,
					'columns' => array( 'actor_type', 'actor_id', 'created_at' ),
				),
				'correlation_id'                   => array(
					'unique'  => false,
					'columns' => array( 'correlation_id' ),
				),
				'created_at_event_id'              => array(
					'unique'  => false,
					'columns' => array( 'created_at', 'event_id' ),
				),
				'event_type_created_at'            => array(
					'unique'  => false,
					'columns' => array( 'event_type', 'created_at' ),
				),
				'target_type_target_id_created_at' => array(
					'unique'  => false,
					'columns' => array( 'target_type', 'target_id', 'created_at' ),
				),
			)
		);
	}

	/**
	 * Build an unsigned integer requirement.
	 *
	 * @param string $data_type      Integer data type.
	 * @param bool   $nullable       Whether NULL is accepted.
	 * @param bool   $auto_increment Whether the column auto-increments.
	 * @return array{data_type: string, unsigned: true, nullable: bool, default: null, auto_increment: bool}
	 */
	private function integer_requirement( string $data_type, bool $nullable = false, bool $auto_increment = false ): array {
		return array(
			'data_type'      => $data_type,
			'unsigned'       => true,
			'nullable'       => $nullable,
			'default'        => null,
			'auto_increment' => $auto_increment,
		);
	}

	/**
	 * Build an ASCII binary string requirement.
	 *
	 * @param string $data_type SQL string type.
	 * @param int    $length    Exact character length.
	 * @param bool   $nullable  Whether NULL is accepted.
	 * @return array{data_type: string, unsigned: false, nullable: bool, default: null, maximum_length: int, character_set: string, collation: string}
	 */
	private function ascii_requirement( string $data_type, int $length, bool $nullable = false ): array {
		return array(
			'data_type'      => $data_type,
			'unsigned'       => false,
			'nullable'       => $nullable,
			'default'        => null,
			'maximum_length' => $length,
			'character_set'  => 'ascii',
			'collation'      => 'ascii_bin',
		);
	}

	/**
	 * Build a nullable JSON-text storage requirement.
	 *
	 * @param string $character_set WordPress table character set.
	 * @param string $collation     WordPress table collation.
	 * @return array{data_type: 'longtext', unsigned: false, nullable: true, default: null, character_set: string, collation: string}
	 */
	private function text_requirement( string $character_set, string $collation ): array {
		return array(
			'data_type'     => 'longtext',
			'unsigned'      => false,
			'nullable'      => true,
			'default'       => null,
			'character_set' => $character_set,
			'collation'     => $collation,
		);
	}

	/**
	 * Build a UTC datetime storage requirement.
	 *
	 * @return array{data_type: 'datetime', unsigned: false, nullable: false, default: null}
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
