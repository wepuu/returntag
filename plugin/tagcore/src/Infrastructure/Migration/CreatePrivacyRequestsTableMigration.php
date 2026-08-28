<?php
/**
 * RT-340 Stage 2 metadata-only privacy request ledger.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/** Creates and verifies additive Schema 16 privacy request persistence. */
final class CreatePrivacyRequestsTableMigration implements Migration {
	/**
	 * Create the Schema 16 Migration.
	 *
	 * @param wpdb                               $database Active WordPress database adapter.
	 * @param TableNames                         $tables Trusted table names.
	 * @param WordPressSchemaInspector           $inspector Exact schema inspector.
	 * @param CreateEmailDeliveryTablesMigration $prerequisite Required Schema 15 Migration.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly TableNames $tables,
		private readonly WordPressSchemaInspector $inspector,
		private readonly CreateEmailDeliveryTablesMigration $prerequisite
	) {}

	/** Return Schema version sixteen. */
	public function version(): int {
		return 16;
	}

	/** Return the stable Migration name. */
	public function name(): string {
		return 'create_returntag_privacy_requests_table';
	}

	/**
	 * Install the additive metadata-only request ledger.
	 *
	 * @throws MigrationException When the predecessor or table contract is unavailable.
	 */
	public function up(): void {
		if ( ! $this->prerequisite->verify() ) {
			throw new MigrationException( 'The required previous schema is unavailable.' );
		}

		$state = $this->table_state();
		if ( SchemaTableState::INCOMPATIBLE === $state ) {
			throw new MigrationException( 'The existing Schema 16 privacy request table is incompatible.' );
		}
		if ( SchemaTableState::EXACT === $state ) {
			return;
		}

		$table   = $this->tables->privacy_requests();
		$collate = $this->database->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
		request_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		requester_type varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		requester_user_id bigint(20) unsigned DEFAULT NULL,
		requester_key char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		request_type varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		request_state varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'queued',
		policy_version varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		idempotency_key char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		active_request_key char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
		checkpoint_code varchar(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
		attempt_count int(10) unsigned NOT NULL DEFAULT 0,
		row_version int(10) unsigned NOT NULL DEFAULT 1,
		reason_code varchar(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
		error_code varchar(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		processing_started_at datetime DEFAULT NULL,
		action_required_at datetime DEFAULT NULL,
		completed_at datetime DEFAULT NULL,
		failed_at datetime DEFAULT NULL,
		PRIMARY KEY  (request_id),
		UNIQUE KEY idempotency_key (idempotency_key),
		UNIQUE KEY active_request_key (active_request_key),
		KEY requester_history (requester_key, request_type, request_id),
		KEY state_updated (request_state, updated_at, request_id)
	) ENGINE=InnoDB {$collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		if ( ! $this->verify_table() ) {
			throw new MigrationException( 'Privacy request schema could not be installed.' );
		}
	}

	/** Verify Schema 16 and its predecessor. */
	public function verify(): bool {
		return $this->prerequisite->verify() && $this->verify_table();
	}

	/** Verify the exact privacy request table contract. */
	private function verify_table(): bool {
		return SchemaTableState::EXACT === $this->table_state();
	}

	/** Inspect the privacy request table. */
	private function table_state(): SchemaTableState {
		return $this->inspector->inspect_table(
			$this->tables->privacy_requests(),
			'InnoDB',
			strtolower( (string) $this->database->collate ),
			array(
				'request_id'            => $this->integer( 'bigint', false, true ),
				'requester_type'        => $this->ascii( 'varchar', 16 ),
				'requester_user_id'     => $this->integer( 'bigint', true ),
				'requester_key'         => $this->ascii( 'char', 64 ),
				'request_type'          => $this->ascii( 'varchar', 16 ),
				'request_state'         => $this->ascii( 'varchar', 32, false, 'queued' ),
				'policy_version'        => $this->ascii( 'varchar', 64 ),
				'idempotency_key'       => $this->ascii( 'char', 64 ),
				'active_request_key'    => $this->ascii( 'char', 64, true ),
				'checkpoint_code'       => $this->ascii( 'varchar', 64, true ),
				'attempt_count'         => $this->integer( 'int', false, false, '0' ),
				'row_version'           => $this->integer( 'int', false, false, '1' ),
				'reason_code'           => $this->ascii( 'varchar', 32, true ),
				'error_code'            => $this->ascii( 'varchar', 64, true ),
				'created_at'            => $this->datetime(),
				'updated_at'            => $this->datetime(),
				'processing_started_at' => $this->datetime( true ),
				'action_required_at'    => $this->datetime( true ),
				'completed_at'          => $this->datetime( true ),
				'failed_at'             => $this->datetime( true ),
			),
			array(
				'PRIMARY'            => array(
					'unique'  => true,
					'columns' => array( 'request_id' ),
				),
				'active_request_key' => array(
					'unique'  => true,
					'columns' => array( 'active_request_key' ),
				),
				'idempotency_key'    => array(
					'unique'  => true,
					'columns' => array( 'idempotency_key' ),
				),
				'requester_history'  => array(
					'unique'  => false,
					'columns' => array( 'requester_key', 'request_type', 'request_id' ),
				),
				'state_updated'      => array(
					'unique'  => false,
					'columns' => array( 'request_state', 'updated_at', 'request_id' ),
				),
			)
		);
	}

	/**
	 * Build an ASCII column requirement.
	 *
	 * @param string          $type SQL data type.
	 * @param int             $length Maximum character length.
	 * @param bool            $nullable Whether NULL is allowed.
	 * @param int|string|null $default_value Optional database default.
	 * @return array{data_type:string,unsigned:bool,nullable:bool,default:int|string|null,maximum_length:int,character_set:string,collation:string}
	 */
	private function ascii( string $type, int $length, bool $nullable = false, int|string|null $default_value = null ): array {
		return array(
			'data_type'      => $type,
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
	 * @param string          $type SQL integer type.
	 * @param bool            $nullable Whether NULL is allowed.
	 * @param bool            $auto Whether auto-increment is required.
	 * @param int|string|null $default_value Optional database default.
	 * @return array{data_type:string,unsigned:bool,nullable:bool,default:int|string|null,auto_increment:bool}
	 */
	private function integer( string $type, bool $nullable = false, bool $auto = false, int|string|null $default_value = null ): array {
		return array(
			'data_type'      => $type,
			'unsigned'       => true,
			'nullable'       => $nullable,
			'default'        => $default_value,
			'auto_increment' => $auto,
		);
	}

	/**
	 * Build a datetime requirement.
	 *
	 * @param bool $nullable Whether NULL is allowed.
	 * @return array{data_type:string,unsigned:bool,nullable:bool,default:null}
	 */
	private function datetime( bool $nullable = false ): array {
		return array(
			'data_type' => 'datetime',
			'unsigned'  => false,
			'nullable'  => $nullable,
			'default'   => null,
		);
	}
}
