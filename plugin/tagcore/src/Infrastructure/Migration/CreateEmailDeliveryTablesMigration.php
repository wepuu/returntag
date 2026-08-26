<?php
/**
 * RT-337 provider-neutral email delivery tables.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/** Creates and verifies the additive Schema 15 email projection contract. */
final class CreateEmailDeliveryTablesMigration implements Migration {
	/**
	 * Create the Schema 15 Migration.
	 *
	 * @param wpdb                           $database Active WordPress database adapter.
	 * @param TableNames                     $tables Trusted table names.
	 * @param WordPressSchemaInspector       $inspector Exact schema inspector.
	 * @param AddFinderEvidenceHoldMigration $prerequisite Required Schema 14 Migration.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly TableNames $tables,
		private readonly WordPressSchemaInspector $inspector,
		private readonly AddFinderEvidenceHoldMigration $prerequisite
	) {}

	/** Return Schema version fifteen. */
	public function version(): int {
		return 15;
	}

	/** Return the stable Migration name. */
	public function name(): string {
		return 'create_returntag_email_delivery_tables';
	}

	/**
	 * Install both additive metadata-only tables.
	 *
	 * @throws MigrationException When the predecessor or table contract is unavailable.
	 */
	public function up(): void {
		if ( ! $this->prerequisite->verify() ) {
			throw new MigrationException( 'The required previous schema is unavailable.' );
		}

		foreach ( array( $this->delivery_state(), $this->event_state() ) as $state ) {
			if ( SchemaTableState::INCOMPATIBLE === $state ) {
				throw new MigrationException( 'The existing Schema 15 email table is incompatible.' );
			}
		}

		if ( $this->verify_tables() ) {
			return;
		}

		$deliveries = $this->tables->email_deliveries();
		$events     = $this->tables->email_webhook_events();
		$collate    = $this->database->get_charset_collate();
		$sql        = "CREATE TABLE {$deliveries} (
		delivery_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		idempotency_key char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		purpose varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		provider varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		provider_message_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
		delivery_status varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'queued',
		provider_event_at datetime DEFAULT NULL,
		delivered_at datetime DEFAULT NULL,
		dispatch_attempt_count int(10) unsigned NOT NULL DEFAULT 1,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (delivery_id),
		UNIQUE KEY idempotency_key (idempotency_key),
		UNIQUE KEY provider_message (provider, provider_message_id),
		KEY status_updated (delivery_status, updated_at)
	) ENGINE=InnoDB {$collate};

	CREATE TABLE {$events} (
		webhook_event_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		provider varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		provider_event_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		provider_message_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		event_type varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		mapped_status varchar(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
		occurred_at datetime NOT NULL,
		received_at datetime NOT NULL,
		processed_at datetime DEFAULT NULL,
		processing_attempt_count int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (webhook_event_id),
		UNIQUE KEY provider_event (provider, provider_event_id),
		KEY pending_events (processed_at, webhook_event_id),
		KEY provider_message (provider, provider_message_id)
	) ENGINE=InnoDB {$collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		if ( ! $this->verify_tables() ) {
			throw new MigrationException( 'Email delivery schema could not be installed.' );
		}
	}

	/** Verify Schema 15 and its predecessor. */
	public function verify(): bool {
		return $this->prerequisite->verify() && $this->verify_tables();
	}

	/**
	 * Verify both metadata-only tables.
	 *
	 * @phpstan-impure
	 */
	private function verify_tables(): bool {
		return SchemaTableState::EXACT === $this->delivery_state() && SchemaTableState::EXACT === $this->event_state();
	}

	/** Inspect the delivery table. */
	private function delivery_state(): SchemaTableState {
		return $this->inspector->inspect_table(
			$this->tables->email_deliveries(),
			'InnoDB',
			strtolower( (string) $this->database->collate ),
			array(
				'delivery_id'            => $this->integer( 'bigint', false, true ),
				'idempotency_key'        => $this->ascii( 'char', 64 ),
				'purpose'                => $this->ascii( 'varchar', 64 ),
				'provider'               => $this->ascii( 'varchar', 32 ),
				'provider_message_id'    => $this->ascii( 'varchar', 191, true ),
				'delivery_status'        => $this->ascii( 'varchar', 32, false, 'queued' ),
				'provider_event_at'      => $this->datetime( true ),
				'delivered_at'           => $this->datetime( true ),
				'dispatch_attempt_count' => $this->integer( 'int', false, false, '1' ),
				'created_at'             => $this->datetime(),
				'updated_at'             => $this->datetime(),
			),
			array(
				'PRIMARY'          => array(
					'unique'  => true,
					'columns' => array( 'delivery_id' ),
				),
				'idempotency_key'  => array(
					'unique'  => true,
					'columns' => array( 'idempotency_key' ),
				),
				'provider_message' => array(
					'unique'  => true,
					'columns' => array( 'provider', 'provider_message_id' ),
				),
				'status_updated'   => array(
					'unique'  => false,
					'columns' => array( 'delivery_status', 'updated_at' ),
				),
			)
		);
	}

	/** Inspect the webhook event table. */
	private function event_state(): SchemaTableState {
		return $this->inspector->inspect_table(
			$this->tables->email_webhook_events(),
			'InnoDB',
			strtolower( (string) $this->database->collate ),
			array(
				'webhook_event_id'         => $this->integer( 'bigint', false, true ),
				'provider'                 => $this->ascii( 'varchar', 32 ),
				'provider_event_id'        => $this->ascii( 'varchar', 191 ),
				'provider_message_id'      => $this->ascii( 'varchar', 191 ),
				'event_type'               => $this->ascii( 'varchar', 64 ),
				'mapped_status'            => $this->ascii( 'varchar', 32, true ),
				'occurred_at'              => $this->datetime(),
				'received_at'              => $this->datetime(),
				'processed_at'             => $this->datetime( true ),
				'processing_attempt_count' => $this->integer( 'int', false, false, '0' ),
			),
			array(
				'PRIMARY'          => array(
					'unique'  => true,
					'columns' => array( 'webhook_event_id' ),
				),
				'pending_events'   => array(
					'unique'  => false,
					'columns' => array( 'processed_at', 'webhook_event_id' ),
				),
				'provider_event'   => array(
					'unique'  => true,
					'columns' => array( 'provider', 'provider_event_id' ),
				),
				'provider_message' => array(
					'unique'  => false,
					'columns' => array( 'provider', 'provider_message_id' ),
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
