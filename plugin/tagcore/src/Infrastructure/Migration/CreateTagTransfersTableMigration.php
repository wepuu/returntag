<?php
/**
 * RT-318 pending Tag transfer table migration.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/** Creates and verifies the additive Schema 13 transfer contract. */
final class CreateTagTransfersTableMigration implements Migration {
	/**
	 * Create the Schema 13 Migration.
	 *
	 * @param wpdb                              $database WordPress database adapter.
	 * @param TableNames                        $tables Trusted table names.
	 * @param WordPressSchemaInspector          $inspector Schema verifier.
	 * @param AddMessageDispatchClaimsMigration $prerequisite Required Schema 12 Migration.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly TableNames $tables,
		private readonly WordPressSchemaInspector $inspector,
		private readonly AddMessageDispatchClaimsMigration $prerequisite
	) {
	}

	/** Return Schema version thirteen. */
	public function version(): int {
		return 13;
	}

	/** Return the stable Migration name. */
	public function name(): string {
		return 'create_returntag_tag_transfers_table';
	}

	/**
	 * Create or safely complete the Transfer table.
	 *
	 * @throws MigrationException When the predecessor or existing table is incompatible.
	 */
	public function up(): void {
		if ( ! $this->prerequisite->verify() ) {
			throw new MigrationException( 'The required previous schema is unavailable.' );
		}

		$state = $this->inspect();
		if ( SchemaTableState::INCOMPATIBLE === $state ) {
			throw new MigrationException( 'The existing Schema 13 transfer table is incompatible.' );
		}
		if ( SchemaTableState::EXACT === $state ) {
			return;
		}

		$table   = $this->tables->tag_transfers();
		$collate = $this->database->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
		transfer_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		tag_id char(6) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		from_owner_id bigint(20) unsigned NOT NULL,
		target_email_ciphertext longblob NOT NULL,
		target_email_lookup char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		token_hash char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
		transfer_status varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		invitation_status varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		invitation_claimed_at datetime DEFAULT NULL,
		invitation_attempt_count int(10) unsigned NOT NULL DEFAULT 0,
		expires_at datetime NOT NULL,
		accepted_at datetime DEFAULT NULL,
		cancelled_at datetime DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (transfer_id),
		UNIQUE KEY token_hash (token_hash),
		KEY tag_status_updated (tag_id, transfer_status, updated_at),
		KEY status_expiry (transfer_status, expires_at)
	) ENGINE=InnoDB {$collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/** Verify the complete Schema 13 contract and predecessor. */
	public function verify(): bool {
		return $this->prerequisite->verify() && SchemaTableState::EXACT === $this->inspect();
	}

	/** Inspect the complete Transfer table contract. */
	private function inspect(): SchemaTableState {
		$ascii    = static fn( string $type, int $length ): array => array(
			'data_type'      => $type,
			'unsigned'       => false,
			'nullable'       => false,
			'default'        => null,
			'maximum_length' => $length,
			'character_set'  => 'ascii',
			'collation'      => 'ascii_bin',
		);
		$integer  = static fn( string $type, bool $nullable = false, bool $auto = false, mixed $default_value = null ): array => array(
			'data_type'      => $type,
			'unsigned'       => true,
			'nullable'       => $nullable,
			'default'        => $default_value,
			'auto_increment' => $auto,
		);
		$datetime = static fn( bool $nullable = false ): array => array(
			'data_type' => 'datetime',
			'unsigned'  => false,
			'nullable'  => $nullable,
			'default'   => null,
		);
		return $this->inspector->inspect_table(
			$this->tables->tag_transfers(),
			'InnoDB',
			strtolower( (string) $this->database->collate ),
			array(
				'transfer_id'              => $integer( 'bigint', false, true ),
				'tag_id'                   => $ascii( 'char', 6 ),
				'from_owner_id'            => $integer( 'bigint' ),
				'target_email_ciphertext'  => array(
					'data_type' => 'longblob',
					'unsigned'  => false,
					'nullable'  => false,
					'default'   => null,
				),
				'target_email_lookup'      => $ascii( 'char', 64 ),
				'token_hash'               => array(
					'data_type'      => 'char',
					'unsigned'       => false,
					'nullable'       => true,
					'default'        => null,
					'maximum_length' => 64,
					'character_set'  => 'ascii',
					'collation'      => 'ascii_bin',
				),
				'transfer_status'          => $ascii( 'varchar', 32 ),
				'invitation_status'        => $ascii( 'varchar', 32 ),
				'invitation_claimed_at'    => $datetime( true ),
				'invitation_attempt_count' => $integer( 'int', false, false, '0' ),
				'expires_at'               => $datetime(),
				'accepted_at'              => $datetime( true ),
				'cancelled_at'             => $datetime( true ),
				'created_at'               => $datetime(),
				'updated_at'               => $datetime(),
			),
			array(
				'PRIMARY'            => array(
					'unique'  => true,
					'columns' => array( 'transfer_id' ),
				),
				'status_expiry'      => array(
					'unique'  => false,
					'columns' => array( 'transfer_status', 'expires_at' ),
				),
				'tag_status_updated' => array(
					'unique'  => false,
					'columns' => array( 'tag_id', 'transfer_status', 'updated_at' ),
				),
				'token_hash'         => array(
					'unique'  => true,
					'columns' => array( 'token_hash' ),
				),
			)
		);
	}
}
