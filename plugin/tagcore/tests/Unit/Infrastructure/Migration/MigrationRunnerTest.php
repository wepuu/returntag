<?php
/**
 * Unit tests for retry-safe migration execution.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Migration;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationException;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistry;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Tests\Unit\Infrastructure\Migration\Fixture\RunnerLockFake;
use ReturnTag\TagCore\Tests\Unit\Infrastructure\Migration\Fixture\RunnerMigrationFake;
use ReturnTag\TagCore\Tests\Unit\Infrastructure\Migration\Fixture\RunnerVersionStoreFake;

/**
 * Verifies version progress, locking, verification, and safe retries.
 */
final class MigrationRunnerTest extends TestCase {
	/**
	 * Current schema returns immediately without database locking.
	 */
	public function test_no_pending_migrations_returns_without_locking(): void {
		$store  = new RunnerVersionStoreFake( 0 );
		$lock   = new RunnerLockFake();
		$runner = new MigrationRunner( new MigrationRegistry( array() ), $store, $lock );

		$report = $runner->migrate();

		self::assertSame( 0, $report->starting_version );
		self::assertSame( 0, $report->ending_version );
		self::assertSame( array(), $report->applied_versions );
		self::assertSame( 0, $lock->acquire_count );
	}

	/**
	 * Pending versions advance only after successful verification.
	 */
	public function test_applies_pending_migrations_and_advances_after_verification(): void {
		$first  = new RunnerMigrationFake( 1 );
		$second = new RunnerMigrationFake( 2 );
		$store  = new RunnerVersionStoreFake( 0 );
		$lock   = new RunnerLockFake();
		$runner = new MigrationRunner( new MigrationRegistry( array( $first, $second ) ), $store, $lock );

		$report = $runner->migrate();

		self::assertSame( array( 1, 2 ), $report->applied_versions );
		self::assertSame( 2, $report->ending_version );
		self::assertSame( array( 1, 2 ), $store->marked_versions );
		self::assertSame( 1, $first->up_count );
		self::assertSame( 1, $second->up_count );
		self::assertSame( 1, $lock->release_count );
	}

	/**
	 * The generic runner can advance the complete Milestone 1 version sequence.
	 */
	public function test_advances_from_zero_through_eight_in_order(): void {
		$migrations = array();

		for ( $version = 1; $version <= 8; ++$version ) {
			$migrations[] = new RunnerMigrationFake( $version );
		}

		$store  = new RunnerVersionStoreFake( 0 );
		$runner = new MigrationRunner(
			new MigrationRegistry( $migrations ),
			$store,
			new RunnerLockFake()
		);

		$report = $runner->migrate();

		self::assertSame( range( 1, 8 ), $report->applied_versions );
		self::assertSame( 8, $report->ending_version );
		self::assertSame( range( 1, 8 ), $store->marked_versions );
	}

	/**
	 * Already completed versions are never executed again.
	 */
	public function test_completed_versions_are_skipped(): void {
		$first  = new RunnerMigrationFake( 1 );
		$second = new RunnerMigrationFake( 2 );
		$store  = new RunnerVersionStoreFake( 1 );
		$runner = new MigrationRunner(
			new MigrationRegistry( array( $first, $second ) ),
			$store,
			new RunnerLockFake()
		);

		$report = $runner->migrate();

		self::assertSame( 0, $first->up_count );
		self::assertSame( 1, $second->up_count );
		self::assertSame( array( 2 ), $report->applied_versions );
	}

	/**
	 * Failed postconditions remain retryable at the same version.
	 */
	public function test_verification_failure_does_not_advance_and_can_be_retried(): void {
		$migration           = new RunnerMigrationFake( 1 );
		$migration->verified = false;
		$store               = new RunnerVersionStoreFake( 0 );
		$lock                = new RunnerLockFake();
		$runner              = new MigrationRunner( new MigrationRegistry( array( $migration ) ), $store, $lock );

		try {
			$runner->migrate();
			self::fail( 'Expected migration verification to fail.' );
		} catch ( MigrationException ) {
			self::assertSame( 0, $store->current_version() );
			self::assertSame( array(), $store->marked_versions );
			self::assertSame( 1, $lock->release_count );
		}

		$migration->verified = true;
		$report              = $runner->migrate();

		self::assertSame( array( 1 ), $report->applied_versions );
		self::assertSame( 2, $migration->up_count );
	}

	/**
	 * Execution failures always release the lock and preserve version state.
	 */
	public function test_migration_exception_releases_lock_without_advancing_version(): void {
		$migration           = new RunnerMigrationFake( 1 );
		$migration->up_error = true;
		$store               = new RunnerVersionStoreFake( 0 );
		$lock                = new RunnerLockFake();
		$runner              = new MigrationRunner( new MigrationRegistry( array( $migration ) ), $store, $lock );

		$this->expectException( MigrationException::class );

		try {
			$runner->migrate();
		} finally {
			self::assertSame( 0, $store->current_version() );
			self::assertSame( 1, $lock->release_count );
		}
	}

	/**
	 * Lock contention prevents all migration execution.
	 */
	public function test_unavailable_lock_prevents_execution(): void {
		$migration       = new RunnerMigrationFake( 1 );
		$lock            = new RunnerLockFake();
		$lock->available = false;
		$runner          = new MigrationRunner(
			new MigrationRegistry( array( $migration ) ),
			new RunnerVersionStoreFake( 0 ),
			$lock
		);

		$this->expectException( MigrationException::class );

		try {
			$runner->migrate();
		} finally {
			self::assertSame( 0, $migration->up_count );
			self::assertSame( 0, $lock->release_count );
		}
	}

	/**
	 * Older code fails closed when it sees a newer database schema.
	 */
	public function test_newer_stored_schema_fails_closed_without_locking(): void {
		$lock   = new RunnerLockFake();
		$runner = new MigrationRunner(
			new MigrationRegistry( array( new RunnerMigrationFake( 1 ) ) ),
			new RunnerVersionStoreFake( 2 ),
			$lock
		);

		$this->expectException( MigrationException::class );

		try {
			$runner->migrate();
		} finally {
			self::assertSame( 0, $lock->acquire_count );
		}
	}
}
