<?php
/**
 * RT-203 collision retry integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceConstraintViolationException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceDuplicateKeyException;
use ReturnTag\TagCore\Application\Persistence\Record\BatchRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewBatchRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewTagRecord;
use ReturnTag\TagCore\Application\Tag\Exception\TagIdCollisionRetryExhausted;
use ReturnTag\TagCore\Application\Tag\GeneratedTagInput;
use ReturnTag\TagCore\Application\Tag\InsertGeneratedTag;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\SmartNetwork;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryFactory;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressAdvisoryMigrationLock;
use ReturnTag\TagCore\Infrastructure\Migration\WordPressSchemaVersionStore;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbBatchRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTagRepository;
use ReturnTag\TagCore\Tests\Fixture\SequenceTagIdGenerator;
use WP_UnitTestCase;
use wpdb;

/**
 * Verifies duplicate classification and retry against MariaDB/MySQL storage.
 */
final class TagCollisionRetryTest extends WP_UnitTestCase {
	/**
	 * Build an isolated current Schema before every test.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$this->clear_schema( $wpdb );
		$this->migrate( $wpdb );
	}

	/**
	 * Remove isolated fixtures after every test.
	 */
	protected function tearDown(): void {
		global $wpdb;

		$this->clear_schema( $wpdb );
		parent::tearDown();
	}

	/**
	 * A database 1062 collision retries and leaves the existing row unchanged.
	 */
	public function test_duplicate_tag_id_retries_with_a_fresh_candidate(): void {
		global $wpdb;

		$repositories = $this->repositories( $wpdb );
		$batch        = $this->insert_batch( $repositories['batches'] );
		$existing     = $this->insert_tag( $repositories['tags'], $batch, 'N7R2W8' );
		$generator    = new SequenceTagIdGenerator( array( 'N7R2W8', 'N7R2W9' ) );
		$result       = ( new InsertGeneratedTag( $generator, $repositories['tags'] ) )->execute(
			$this->input( $batch )
		);

		self::assertSame( 'N7R2W9', $result->tag->data->tag_id );
		self::assertSame( 1, $result->collision_count );
		self::assertSame( 2, $generator->calls );
		self::assertEquals( $existing, $repositories['tags']->find_by_tag_id( 'N7R2W8' ) );
		self::assertSame( 13, ( new WordPressSchemaVersionStore() )->current_version() );
	}

	/**
	 * The gateway exposes only an explicit duplicate-key exception for error 1062.
	 */
	public function test_duplicate_key_error_is_classified_without_database_message(): void {
		global $wpdb;

		$repositories = $this->repositories( $wpdb );
		$batch        = $this->insert_batch( $repositories['batches'] );
		$this->insert_tag( $repositories['tags'], $batch, 'N7R2W8' );

		try {
			$this->insert_tag( $repositories['tags'], $batch, 'N7R2W8' );
			self::fail( 'Expected the duplicate primary key to fail.' );
		} catch ( PersistenceDuplicateKeyException $exception ) {
			self::assertSame(
				'Persistence operation failed because a unique key already exists.',
				$exception->getMessage()
			);
			self::assertStringNotContainsString( 'N7R2W8', $exception->getMessage() );
			self::assertStringNotContainsString( 'Duplicate entry', $exception->getMessage() );
		}
	}

	/**
	 * Exhaustion preserves every existing Tag and inserts no replacement.
	 */
	public function test_ten_collisions_fail_closed_without_deleting_or_reusing_ids(): void {
		global $wpdb;

		$repositories = $this->repositories( $wpdb );
		$batch        = $this->insert_batch( $repositories['batches'] );
		$ids          = $this->ten_ids();

		foreach ( $ids as $tag_id ) {
			$this->insert_tag( $repositories['tags'], $batch, $tag_id );
		}

		$generator = new SequenceTagIdGenerator( $ids );
		$service   = new InsertGeneratedTag( $generator, $repositories['tags'] );

		try {
			$service->execute( $this->input( $batch ) );
			self::fail( 'Expected collision retry exhaustion.' );
		} catch ( TagIdCollisionRetryExhausted ) {
			self::assertSame( InsertGeneratedTag::MAXIMUM_ATTEMPTS, $generator->calls );
			self::assertSame( count( $ids ), $this->tag_count( $wpdb ) );

			foreach ( $ids as $tag_id ) {
				self::assertSame(
					TagStatus::UNREGISTERED,
					$repositories['tags']->find_by_tag_id( $tag_id )?->data->tag_status
				);
			}
		}
	}

	/**
	 * A missing Batch fails before collision retry and consumes one candidate.
	 */
	public function test_batch_snapshot_failure_is_not_retried(): void {
		global $wpdb;

		$repositories = $this->repositories( $wpdb );
		$generator    = new SequenceTagIdGenerator( array( 'N7R2W8', 'N7R2W9' ) );
		$service      = new InsertGeneratedTag( $generator, $repositories['tags'] );
		$input        = new GeneratedTagInput(
			999999,
			TagType::CLASSIC_TAG,
			'RT203-MODEL',
			$this->utc()
		);

		try {
			$service->execute( $input );
			self::fail( 'Expected the missing Batch snapshot to fail.' );
		} catch ( PersistenceConstraintViolationException ) {
			self::assertSame( 1, $generator->calls );
			self::assertSame( 0, $this->tag_count( $wpdb ) );
		}
	}

	/**
	 * Build concrete repositories.
	 *
	 * @param wpdb $database Active database adapter.
	 * @return array{batches: WpdbBatchRepository, tags: WpdbTagRepository}
	 */
	private function repositories( wpdb $database ): array {
		$gateway = new WpdbGateway( $database );
		$tables  = new TableNames( $database->prefix );
		$dates   = new DatabaseDateTimeCodec();

		return array(
			'batches' => new WpdbBatchRepository( $gateway, $tables, $dates ),
			'tags'    => new WpdbTagRepository( $gateway, $tables, $dates ),
		);
	}

	/**
	 * Insert one synthetic Batch.
	 *
	 * @param WpdbBatchRepository $repository Batch Repository.
	 */
	private function insert_batch( WpdbBatchRepository $repository ): BatchRecord {
		return $repository->insert(
			new NewBatchRecord(
				'RT203-COLLISION',
				TagType::CLASSIC_TAG,
				'RT203-MODEL',
				SmartNetwork::NONE,
				'Synthetic Manufacturer',
				'direct',
				20,
				0,
				BatchStatus::DRAFT,
				false,
				null,
				7,
				$this->utc(),
				$this->utc()
			)
		);
	}

	/**
	 * Insert one synthetic unregistered Tag.
	 *
	 * @param WpdbTagRepository $repository Tag Repository.
	 * @param BatchRecord       $batch Batch fixture.
	 * @param string            $tag_id Public Tag ID fixture.
	 */
	private function insert_tag( WpdbTagRepository $repository, BatchRecord $batch, string $tag_id ): object {
		return $repository->insert(
			new NewTagRecord(
				$tag_id,
				$batch->batch_id,
				null,
				$batch->data->tag_type,
				$batch->data->model_code,
				null,
				null,
				TagStatus::UNREGISTERED,
				false,
				null,
				null,
				null,
				null,
				null,
				$this->utc(),
				$this->utc()
			)
		);
	}

	/**
	 * Build generated Tag input from a Batch snapshot.
	 *
	 * @param BatchRecord $batch Batch fixture.
	 */
	private function input( BatchRecord $batch ): GeneratedTagInput {
		return new GeneratedTagInput(
			$batch->batch_id,
			$batch->data->tag_type,
			$batch->data->model_code,
			$this->utc()
		);
	}

	/**
	 * Count Tags in the isolated test table.
	 *
	 * @param wpdb $database Active database adapter.
	 */
	private function tag_count( wpdb $database ): int {
		$table = ( new TableNames( $database->prefix ) )->tags();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated assertion with a trusted table identifier.
		return (int) $database->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Apply all production Migrations.
	 *
	 * @param wpdb $database Active database adapter.
	 */
	private function migrate( wpdb $database ): void {
		$runner = new MigrationRunner(
			( new MigrationRegistryFactory( $database ) )->create(),
			new WordPressSchemaVersionStore(),
			new WordPressAdvisoryMigrationLock( $database, get_current_blog_id(), 0 )
		);

		self::assertSame( 13, $runner->migrate()->ending_version );
	}

	/**
	 * Remove only trusted ReturnTag tables from the isolated database.
	 *
	 * @param wpdb $database Active database adapter.
	 */
	private function clear_schema( wpdb $database ): void {
		$names = new TableNames( $database->prefix );

		foreach ( array( $names->events(), $names->access_tokens(), $names->messages(), $names->conversations(), $names->auth_challenges(), $names->batch_exports(), $names->tags(), $names->batches() ) as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Isolated test cleanup with trusted identifiers.
			$database->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		delete_option( WordPressSchemaVersionStore::OPTION_NAME );
	}

	/**
	 * Return a fixed UTC timestamp.
	 */
	private function utc(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-07-27 01:02:03', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Return ten distinct canonical IDs.
	 *
	 * @return list<string>
	 */
	private function ten_ids(): array {
		return array(
			'N7R2W2',
			'N7R2W3',
			'N7R2W4',
			'N7R2W5',
			'N7R2W6',
			'N7R2W7',
			'N7R2W8',
			'N7R2W9',
			'N7R2WA',
			'N7R2WB',
		);
	}
}
