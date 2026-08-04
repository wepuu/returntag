<?php
/**
 * RT-315 Finder Report Repository integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceConstraintViolationException;
use ReturnTag\TagCore\Application\Persistence\Record\NewFinderReportMediaRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewFinderReportRecord;
use ReturnTag\TagCore\Application\Persistence\Value\FinderReportMessageCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDigest;
use ReturnTag\TagCore\Application\Persistence\Value\PrivateMediaReferenceCiphertext;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceMime;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbFinderReportMediaRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbFinderReportRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use WP_UnitTestCase;
use wpdb;

/**
 * Verifies typed persistence without opening a public workflow.
 */
final class FinderReportRepositoryTest extends WP_UnitTestCase {
	/**
	 * Trusted table-name mapping.
	 *
	 * @var TableNames
	 */
	private TableNames $tables;

	/** Build a clean Schema-10 fixture. */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->tables = new TableNames( $wpdb->prefix );
		$this->clear_schema( $wpdb );
		$this->migrate( $wpdb );
		$this->insert_owned_tag( $wpdb );
	}

	/** Remove the isolated Schema-10 fixture. */
	protected function tearDown(): void {
		global $wpdb;

		$this->clear_schema( $wpdb );
		parent::tearDown();
	}

	/** Optional encrypted message and required evidence metadata round-trip. */
	public function test_report_and_media_round_trip_without_plaintext_or_public_url(): void {
		global $wpdb;

		$repositories = $this->repositories( $wpdb );
		$report       = $repositories['reports']->insert( $this->new_report( 42 ) );
		$media        = $repositories['media']->insert( $this->new_media( $report->finder_report_id ) );

		$stored_report = $repositories['reports']->find_by_id( $report->finder_report_id );
		$stored_media  = $repositories['media']->find_by_report_id( $report->finder_report_id );

		self::assertNotNull( $stored_report );
		self::assertNotNull( $stored_media );
		self::assertSame( "encrypted-message\0bytes", $stored_report->data->message_ciphertext?->value );
		self::assertSame( FinderReportStatus::RECEIVED, $stored_report->data->report_status );
		self::assertSame( FinderEvidenceStatus::QUARANTINED, $stored_media->data->media_status );
		self::assertSame( "encrypted-object-reference\0bytes", $stored_media->data->object_reference_ciphertext->value );
		self::assertNull( $stored_media->data->review_derivative );
		self::assertNull( $stored_media->data->email_derivative );
		self::assertSame( $media->finder_report_media_id, $stored_media->finder_report_media_id );
	}

	/** A browser-supplied Owner ID cannot authorize a Finder Report. */
	public function test_report_rejects_inconsistent_owner_snapshot(): void {
		global $wpdb;

		$this->expectException( PersistenceConstraintViolationException::class );
		$this->repositories( $wpdb )['reports']->insert( $this->new_report( 99 ) );
	}

	/** Media cannot be persisted without its separate Finder Report. */
	public function test_media_rejects_missing_report(): void {
		global $wpdb;

		$this->expectException( PersistenceConstraintViolationException::class );
		$this->repositories( $wpdb )['media']->insert( $this->new_media( 999 ) );
	}

	/**
	 * Build the two RT-315 repositories.
	 *
	 * @param wpdb $database Database adapter.
	 * @return array{reports: WpdbFinderReportRepository, media: WpdbFinderReportMediaRepository}
	 */
	private function repositories( wpdb $database ): array {
		$gateway = new WpdbGateway( $database );
		$dates   = new DatabaseDateTimeCodec();

		return array(
			'reports' => new WpdbFinderReportRepository( $gateway, $this->tables, $dates ),
			'media'   => new WpdbFinderReportMediaRepository( $gateway, $this->tables, $dates ),
		);
	}

	/**
	 * Build one new Finder Report fixture.
	 *
	 * @param int $owner_id Owner snapshot identifier.
	 */
	private function new_report( int $owner_id ): NewFinderReportRecord {
		return new NewFinderReportRecord(
			'N7R2W9',
			$owner_id,
			FinderReportMessageCiphertext::from_encrypted_bytes( "encrypted-message\0bytes" ),
			FinderReportStatus::RECEIVED,
			FinderEvidenceStatus::QUARANTINED,
			null,
			null,
			$this->utc( '2026-08-05 00:00:00' ),
			$this->utc( '2026-08-04 00:00:00' ),
			$this->utc( '2026-08-04 00:00:00' )
		);
	}

	/**
	 * Build one quarantined media fixture.
	 *
	 * @param int $report_id Parent Finder Report identifier.
	 */
	private function new_media( int $report_id ): NewFinderReportMediaRecord {
		return new NewFinderReportMediaRecord(
			$report_id,
			PrivateMediaReferenceCiphertext::from_encrypted_bytes( "encrypted-object-reference\0bytes" ),
			'rt315-test-key',
			MediaDigest::from_digest( str_repeat( 'a', 64 ) ),
			FinderEvidenceMime::JPEG,
			1024,
			640,
			480,
			null,
			null,
			FinderEvidenceStatus::QUARANTINED,
			$this->utc( '2026-08-05 00:00:00' ),
			$this->utc( '2026-08-04 00:00:00' ),
			$this->utc( '2026-08-04 00:00:00' )
		);
	}

	/**
	 * Apply the complete production Migration chain.
	 *
	 * @param wpdb $database Database adapter.
	 */
	private function migrate( wpdb $database ): void {
		$registry = ( new MigrationRegistryFactory( $database ) )->create();
		$runner   = new MigrationRunner(
			$registry,
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
		);

		self::assertSame( 10, $runner->migrate()->ending_version );
	}

	/**
	 * Insert one synthetic owned Tag fixture.
	 *
	 * @param wpdb $database Database adapter.
	 */
	private function insert_owned_tag( wpdb $database ): void {
		self::assertSame(
			1,
			$database->insert(
				$this->tables->batches(),
				array(
					'batch_code'         => 'RT315-REPO',
					'tag_type'           => 'classic_tag',
					'model_code'         => 'RT315',
					'smart_network'      => 'none',
					'manufacturer'       => 'Synthetic',
					'sales_channel'      => 'direct',
					'requested_quantity' => 1,
					'generated_quantity' => 1,
					'batch_status'       => 'released',
					'activation_enabled' => 1,
					'notes'              => null,
					'created_by'         => 1,
					'created_at'         => '2026-08-04 00:00:00',
					'updated_at'         => '2026-08-04 00:00:00',
				)
			)
		);
		$batch_id = (int) $database->insert_id;
		self::assertSame(
			1,
			$database->insert(
				$this->tables->tags(),
				array(
					'tag_id'       => 'N7R2W9',
					'batch_id'     => $batch_id,
					'owner_id'     => 42,
					'tag_type'     => 'classic_tag',
					'model_code'   => 'RT315',
					'public_label' => 'Synthetic',
					'tag_status'   => 'active',
					'lost_mode'    => 0,
					'activated_at' => '2026-08-04 00:00:00',
					'created_at'   => '2026-08-04 00:00:00',
					'updated_at'   => '2026-08-04 00:00:00',
				)
			)
		);
	}

	/**
	 * Build one strict UTC timestamp.
	 *
	 * @param string $value Database timestamp.
	 */
	private function utc( string $value ): DateTimeImmutable {
		return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Remove trusted ReturnTag tables and Schema state.
	 *
	 * @param wpdb $database Database adapter.
	 */
	private function clear_schema( wpdb $database ): void {
		$names = new TableNames( $database->prefix );
		foreach ( array( $names->finder_report_media(), $names->finder_reports(), $names->events(), $names->access_tokens(), $names->messages(), $names->conversations(), $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated trusted test cleanup.
			$database->query( "DROP TABLE IF EXISTS {$table}" );
		}
		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}
}
