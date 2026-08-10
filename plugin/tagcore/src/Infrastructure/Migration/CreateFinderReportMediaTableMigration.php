<?php
/**
 * RT-315 Finder Report private-media table migration.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/**
 * Creates and verifies schema version 0010.
 */
final class CreateFinderReportMediaTableMigration implements Migration {
	/**
	 * Create the migration.
	 *
	 * @param wpdb                              $database Database adapter.
	 * @param TableNames                        $table_names Trusted table-name mapping.
	 * @param WordPressSchemaInspector          $inspector Schema verifier.
	 * @param CreateFinderReportsTableMigration $prerequisite Required version 0009 schema.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly TableNames $table_names,
		private readonly WordPressSchemaInspector $inspector,
		private readonly CreateFinderReportsTableMigration $prerequisite
	) {
	}

	/** Return schema version ten. */
	public function version(): int {
		return 10;
	}

	/** Return a stable non-sensitive migration name. */
	public function name(): string {
		return 'create_returntag_finder_report_media_table';
	}

	/**
	 * Create or safely complete the private-media table.
	 *
	 * @throws MigrationException When the predecessor or existing schema is incompatible.
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

		$table_name      = $this->table_names->finder_report_media();
		$charset_collate = $this->database->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
		finder_report_media_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		finder_report_id bigint(20) unsigned NOT NULL,
		object_reference_ciphertext longblob NOT NULL,
		encryption_key_id varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		content_sha256 char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		source_mime varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		source_byte_count bigint(20) unsigned NOT NULL,
		source_width int(10) unsigned NOT NULL,
		source_height int(10) unsigned NOT NULL,
		review_reference_ciphertext longblob DEFAULT NULL,
		review_sha256 char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
		review_byte_count bigint(20) unsigned DEFAULT NULL,
		review_width int(10) unsigned DEFAULT NULL,
		review_height int(10) unsigned DEFAULT NULL,
		email_reference_ciphertext longblob DEFAULT NULL,
		email_sha256 char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
		email_byte_count bigint(20) unsigned DEFAULT NULL,
		email_width int(10) unsigned DEFAULT NULL,
		email_height int(10) unsigned DEFAULT NULL,
		media_status varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		retention_until datetime NOT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (finder_report_media_id),
		UNIQUE KEY finder_report_id (finder_report_id),
		KEY media_status_retention_until (media_status, retention_until)
	) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/** Verify the complete version 0010 contract and predecessor. */
	public function verify(): bool {
		return $this->prerequisite->verify() && SchemaTableState::EXACT === $this->inspect();
	}

	/** Inspect the complete table contract before mutation. */
	private function inspect(): SchemaTableState {
		return $this->inspector->inspect_table(
			$this->table_names->finder_report_media(),
			'InnoDB',
			strtolower( (string) $this->database->collate ),
			array(
				'finder_report_media_id'      => $this->integer_requirement( 'bigint', false, true ),
				'finder_report_id'            => $this->integer_requirement( 'bigint' ),
				'object_reference_ciphertext' => $this->plain_requirement( 'longblob' ),
				'encryption_key_id'           => $this->ascii_requirement( 'varchar', 64 ),
				'content_sha256'              => $this->ascii_requirement( 'char', 64 ),
				'source_mime'                 => $this->ascii_requirement( 'varchar', 32 ),
				'source_byte_count'           => $this->integer_requirement( 'bigint' ),
				'source_width'                => $this->integer_requirement( 'int' ),
				'source_height'               => $this->integer_requirement( 'int' ),
				'review_reference_ciphertext' => $this->plain_requirement( 'longblob', true ),
				'review_sha256'               => $this->ascii_requirement( 'char', 64, true ),
				'review_byte_count'           => $this->integer_requirement( 'bigint', true ),
				'review_width'                => $this->integer_requirement( 'int', true ),
				'review_height'               => $this->integer_requirement( 'int', true ),
				'email_reference_ciphertext'  => $this->plain_requirement( 'longblob', true ),
				'email_sha256'                => $this->ascii_requirement( 'char', 64, true ),
				'email_byte_count'            => $this->integer_requirement( 'bigint', true ),
				'email_width'                 => $this->integer_requirement( 'int', true ),
				'email_height'                => $this->integer_requirement( 'int', true ),
				'media_status'                => $this->ascii_requirement( 'varchar', 32 ),
				'retention_until'             => $this->datetime_requirement(),
				'created_at'                  => $this->datetime_requirement(),
				'updated_at'                  => $this->datetime_requirement(),
			),
			array(
				'PRIMARY'                      => array(
					'unique'  => true,
					'columns' => array( 'finder_report_media_id' ),
				),
				'finder_report_id'             => array(
					'unique'  => true,
					'columns' => array( 'finder_report_id' ),
				),
				'media_status_retention_until' => array(
					'unique'  => false,
					'columns' => array( 'media_status', 'retention_until' ),
				),
			)
		);
	}

	/**
	 * Build an unsigned integer requirement.
	 *
	 * @param string $type SQL integer type.
	 * @param bool   $nullable Whether NULL is accepted.
	 * @param bool   $auto_increment Whether the column auto-increments.
	 * @return array{data_type: string, unsigned: true, nullable: bool, default: null, auto_increment: bool}
	 */
	private function integer_requirement( string $type, bool $nullable = false, bool $auto_increment = false ): array {
		return array(
			'data_type'      => $type,
			'unsigned'       => true,
			'nullable'       => $nullable,
			'default'        => null,
			'auto_increment' => $auto_increment,
		);
	}

	/**
	 * Build an ASCII binary string requirement.
	 *
	 * @param string $type SQL string type.
	 * @param int    $length Exact character length.
	 * @param bool   $nullable Whether NULL is accepted.
	 * @return array{data_type: string, unsigned: false, nullable: bool, default: null, maximum_length: int, character_set: string, collation: string}
	 */
	private function ascii_requirement( string $type, int $length, bool $nullable = false ): array {
		return array(
			'data_type'      => $type,
			'unsigned'       => false,
			'nullable'       => $nullable,
			'default'        => null,
			'maximum_length' => $length,
			'character_set'  => 'ascii',
			'collation'      => 'ascii_bin',
		);
	}

	/**
	 * Build a non-text scalar requirement.
	 *
	 * @param string $type SQL data type.
	 * @param bool   $nullable Whether NULL is accepted.
	 * @return array{data_type: string, unsigned: false, nullable: bool, default: null}
	 */
	private function plain_requirement( string $type, bool $nullable = false ): array {
		return array(
			'data_type' => $type,
			'unsigned'  => false,
			'nullable'  => $nullable,
			'default'   => null,
		);
	}

	/**
	 * Build a required UTC datetime requirement.
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
