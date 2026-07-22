<?php
/**
 * Unit tests for the migration registry.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Migration;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistry;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistryException;
use ReturnTag\TagCore\Tests\Unit\Infrastructure\Migration\Fixture\RegistryMigrationStub;

/**
 * Verifies strict migration numbering before any schema work can run.
 */
final class MigrationRegistryTest extends TestCase {
	/**
	 * Empty RT-101 registry must represent schema version zero.
	 */
	public function test_empty_registry_targets_version_zero(): void {
		$registry = new MigrationRegistry( array() );

		self::assertSame( array(), $registry->all() );
		self::assertSame( 0, $registry->target_version() );
	}

	/**
	 * Ordered contiguous migrations are accepted.
	 */
	public function test_ordered_contiguous_registry_is_accepted(): void {
		$first    = new RegistryMigrationStub( 1, 'create batches' );
		$second   = new RegistryMigrationStub( 2, 'create tags' );
		$registry = new MigrationRegistry( array( $first, $second ) );

		self::assertSame( array( $first, $second ), $registry->all() );
		self::assertSame( 2, $registry->target_version() );
	}

	/**
	 * Duplicate versions are ambiguous and must fail fast.
	 */
	public function test_duplicate_version_is_rejected(): void {
		$this->expectException( MigrationRegistryException::class );

		new MigrationRegistry(
			array(
				new RegistryMigrationStub( 1, 'first' ),
				new RegistryMigrationStub( 1, 'duplicate' ),
			)
		);
	}

	/**
	 * Out-of-order input is rejected instead of silently reordered.
	 */
	public function test_out_of_order_version_is_rejected(): void {
		$this->expectException( MigrationRegistryException::class );

		new MigrationRegistry(
			array(
				new RegistryMigrationStub( 2, 'second' ),
				new RegistryMigrationStub( 1, 'first' ),
			)
		);
	}

	/**
	 * Missing versions prevent the registry from being built.
	 */
	public function test_version_gap_is_rejected(): void {
		$this->expectException( MigrationRegistryException::class );

		new MigrationRegistry(
			array(
				new RegistryMigrationStub( 1, 'first' ),
				new RegistryMigrationStub( 3, 'third' ),
			)
		);
	}

	/**
	 * Operational migration names cannot be blank.
	 */
	public function test_empty_name_is_rejected(): void {
		$this->expectException( MigrationRegistryException::class );

		new MigrationRegistry( array( new RegistryMigrationStub( 1, ' ' ) ) );
	}
}
