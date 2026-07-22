<?php
/**
 * WordPress integration tests for administrative Migration lifecycle behavior.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use ReturnTag\TagCore\Infrastructure\Migration\MigrationLifecycle;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRegistry;
use ReturnTag\TagCore\Infrastructure\Migration\MigrationRunner;
use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;
use ReturnTag\TagCore\Tests\Unit\Infrastructure\Migration\Fixture\RunnerLockFake;
use ReturnTag\TagCore\Tests\Unit\Infrastructure\Migration\Fixture\RunnerMigrationFake;
use ReturnTag\TagCore\Tests\Unit\Infrastructure\Migration\Fixture\RunnerVersionStoreFake;
use WP_UnitTestCase;

/**
 * Verifies capability gating and privacy-safe failure output.
 */
final class MigrationLifecycleTest extends WP_UnitTestCase {
	/**
	 * Restore the anonymous WordPress user after each test.
	 */
	protected function tearDown(): void {
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * An unauthorized admin request cannot trigger pending schema work.
	 */
	public function test_admin_compensation_requires_activate_plugins_capability(): void {
		$migration = new RunnerMigrationFake( 1 );
		$lifecycle = $this->create_lifecycle( $migration );
		$user_id   = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $user_id );
		$lifecycle->maybe_migrate_in_admin();

		self::assertSame( 0, $migration->up_count );
	}

	/**
	 * A failure notice must not reveal raw exception or SQL detail.
	 */
	public function test_admin_failure_notice_is_generic(): void {
		$migration           = new RunnerMigrationFake( 1 );
		$migration->up_error = true;
		$lifecycle           = $this->create_lifecycle( $migration );
		$user_id             = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $user_id );
		$lifecycle->maybe_migrate_in_admin();

		ob_start();
		$lifecycle->render_admin_notice();
		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( 'TagCore database preparation did not complete', $output );
		self::assertStringNotContainsString( 'Fixture failure detail', $output );
		self::assertStringNotContainsString( 'SELECT', $output );
	}

	/**
	 * A completed single-plugin upgrade runs pending schema work.
	 */
	public function test_single_plugin_upgrade_context_runs_pending_migration(): void {
		$migration = new RunnerMigrationFake( 1 );
		$lifecycle = $this->create_lifecycle( $migration );

		$lifecycle->after_plugin_upgrade(
			null,
			array(
				'action' => 'update',
				'type'   => 'plugin',
				'plugin' => plugin_basename( RETURNTAG_TAGCORE_FILE ),
			)
		);

		self::assertSame( 1, $migration->up_count );
	}

	/**
	 * An unrelated plugin upgrade cannot run TagCore schema work.
	 */
	public function test_unrelated_plugin_upgrade_is_ignored(): void {
		$migration = new RunnerMigrationFake( 1 );
		$lifecycle = $this->create_lifecycle( $migration );

		$lifecycle->after_plugin_upgrade(
			null,
			array(
				'action'  => 'update',
				'type'    => 'plugin',
				'plugins' => array( 'another-plugin/another-plugin.php' ),
			)
		);

		self::assertSame( 0, $migration->up_count );
	}

	/**
	 * Build one pending Migration lifecycle around in-memory collaborators.
	 *
	 * @param RunnerMigrationFake $migration Pending migration fixture.
	 */
	private function create_lifecycle( RunnerMigrationFake $migration ): MigrationLifecycle {
		$registry = new MigrationRegistry( array( $migration ) );
		$store    = new RunnerVersionStoreFake( 0 );
		$runner   = new MigrationRunner( $registry, $store, new RunnerLockFake() );

		return new MigrationLifecycle(
			RETURNTAG_TAGCORE_FILE,
			$runner,
			new SchemaState( $store, $registry )
		);
	}
}
