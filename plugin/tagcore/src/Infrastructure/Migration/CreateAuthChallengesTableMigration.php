<?php
/**
 * RT-105 authentication challenges table migration.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/**
 * Creates and verifies schema version 0004.
 */
final class CreateAuthChallengesTableMigration implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param wpdb                             $database     WordPress database adapter.
	 * @param TableNames                       $table_names Trusted table-name mapping.
	 * @param WordPressSchemaInspector         $inspector   Schema postcondition verifier.
	 * @param CreateBatchExportsTableMigration $prerequisite Required version 0003 schema.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly TableNames $table_names,
		private readonly WordPressSchemaInspector $inspector,
		private readonly CreateBatchExportsTableMigration $prerequisite
	) {
	}

	/**
	 * Return schema version four.
	 */
	public function version(): int {
		return 4;
	}

	/**
	 * Return a stable non-sensitive migration name.
	 */
	public function name(): string {
		return 'create_returntag_auth_challenges_table';
	}

	/**
	 * Create or safely complete the challenges table with dbDelta().
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

		$table_name      = $this->table_names->auth_challenges();
		$charset_collate = $this->database->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
		challenge_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		purpose varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		subject_type varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		subject_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		email_ciphertext longblob NOT NULL,
		email_lookup char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		code_hash varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		attempt_count int(10) unsigned NOT NULL DEFAULT 0,
		send_count int(10) unsigned NOT NULL DEFAULT 0,
		ip_hash char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
		expires_at datetime NOT NULL,
		verified_at datetime DEFAULT NULL,
		consumed_at datetime DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (challenge_id),
		KEY purpose_email_created_at (purpose, email_lookup, created_at),
		KEY subject_created_at (subject_type, subject_id, created_at),
		KEY expires_consumed_at (expires_at, consumed_at)
	) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Verify the complete RT-105 schema contract and its predecessors.
	 */
	public function verify(): bool {
		if ( ! $this->prerequisite->verify() ) {
			return false;
		}

		return SchemaTableState::EXACT === $this->inspect();
	}

	/**
	 * Inspect the complete RT-105 table contract before mutation.
	 */
	private function inspect(): SchemaTableState {
		$wordpress_collation = strtolower( (string) $this->database->collate );

		return $this->inspector->inspect_table(
			$this->table_names->auth_challenges(),
			'InnoDB',
			$wordpress_collation,
			array(
				'challenge_id'     => $this->integer_requirement( 'bigint', null, true ),
				'purpose'          => $this->ascii_requirement( 'varchar', 32 ),
				'subject_type'     => $this->ascii_requirement( 'varchar', 32 ),
				'subject_id'       => $this->ascii_requirement( 'varchar', 191 ),
				'email_ciphertext' => $this->plain_requirement( 'longblob' ),
				'email_lookup'     => $this->ascii_requirement( 'char', 64 ),
				'code_hash'        => $this->ascii_requirement( 'varchar', 255 ),
				'attempt_count'    => $this->integer_requirement( 'int', 0 ),
				'send_count'       => $this->integer_requirement( 'int', 0 ),
				'ip_hash'          => $this->ascii_requirement( 'char', 64, true ),
				'expires_at'       => $this->datetime_requirement(),
				'verified_at'      => $this->datetime_requirement( true ),
				'consumed_at'      => $this->datetime_requirement( true ),
				'created_at'       => $this->datetime_requirement(),
			),
			array(
				'PRIMARY'                  => array(
					'unique'  => true,
					'columns' => array( 'challenge_id' ),
				),
				'expires_consumed_at'      => array(
					'unique'  => false,
					'columns' => array( 'expires_at', 'consumed_at' ),
				),
				'purpose_email_created_at' => array(
					'unique'  => false,
					'columns' => array( 'purpose', 'email_lookup', 'created_at' ),
				),
				'subject_created_at'       => array(
					'unique'  => false,
					'columns' => array( 'subject_type', 'subject_id', 'created_at' ),
				),
			)
		);
	}

	/**
	 * Build an unsigned integer requirement.
	 *
	 * @param string   $data_type      Integer data type.
	 * @param int|null $default_value  Exact default value.
	 * @param bool     $auto_increment Whether the column auto-increments.
	 * @return array{data_type: string, unsigned: true, nullable: false, default: int|null, auto_increment: bool}
	 */
	private function integer_requirement( string $data_type, ?int $default_value = null, bool $auto_increment = false ): array {
		return array(
			'data_type'      => $data_type,
			'unsigned'       => true,
			'nullable'       => false,
			'default'        => $default_value,
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
