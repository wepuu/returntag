<?php
/**
 * RT-327 administrator Tag lifecycle unit coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Admin;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Admin\AdminTagLifecycleAction;
use ReturnTag\TagCore\Application\Admin\AdminTagLifecycleEventMetadataPolicy;
use ReturnTag\TagCore\Application\Admin\AdminTagLifecyclePolicy;
use ReturnTag\TagCore\Application\Admin\AdminTagLifecycleResult;
use ReturnTag\TagCore\Application\Admin\AdminTagLifecycleState;
use ReturnTag\TagCore\Application\Admin\AdminTagLifecycleStore;
use ReturnTag\TagCore\Application\Admin\ManageAdminTagLifecycle;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;

/** Verifies the frozen state matrix, confirmation, flag, and metadata. */
final class AdminTagLifecycleTest extends TestCase {
	/**
	 * Provide the frozen transition matrix.
	 *
	 * @return iterable<string, array{AdminTagLifecycleAction, TagStatus, int|null, int|null, TagStatus|null, int|null}>
	 */
	public static function transitions(): iterable {
		yield 'suspend unregistered' => array( AdminTagLifecycleAction::SUSPEND, TagStatus::UNREGISTERED, null, null, TagStatus::SUSPENDED, null );
		yield 'suspend active owner' => array( AdminTagLifecycleAction::SUSPEND, TagStatus::ACTIVE, 11, null, TagStatus::SUSPENDED, 11 );
		yield 'cannot suspend suspended' => array( AdminTagLifecycleAction::SUSPEND, TagStatus::SUSPENDED, 11, null, null, null );
		yield 'retire unregistered' => array( AdminTagLifecycleAction::RETIRE, TagStatus::UNREGISTERED, null, null, TagStatus::RETIRED, null );
		yield 'retire suspended preserves owner' => array( AdminTagLifecycleAction::RETIRE, TagStatus::SUSPENDED, 11, null, TagStatus::RETIRED, 11 );
		yield 'retired is terminal' => array( AdminTagLifecycleAction::RETIRE, TagStatus::RETIRED, 11, null, null, null );
		yield 'remove active owner' => array( AdminTagLifecycleAction::REMOVE_OWNER, TagStatus::ACTIVE, 11, null, TagStatus::SUSPENDED, null );
		yield 'remove suspended owner' => array( AdminTagLifecycleAction::REMOVE_OWNER, TagStatus::SUSPENDED, 11, null, TagStatus::SUSPENDED, null );
		yield 'cannot remove missing owner' => array( AdminTagLifecycleAction::REMOVE_OWNER, TagStatus::ACTIVE, null, null, null, null );
		yield 'transfer active owner' => array( AdminTagLifecycleAction::TRANSFER_OWNER, TagStatus::ACTIVE, 11, 22, TagStatus::ACTIVE, 22 );
		yield 'transfer suspended stays suspended' => array( AdminTagLifecycleAction::TRANSFER_OWNER, TagStatus::SUSPENDED, 11, 22, TagStatus::SUSPENDED, 22 );
		yield 'cannot transfer to current owner' => array( AdminTagLifecycleAction::TRANSFER_OWNER, TagStatus::ACTIVE, 11, 11, null, null );
	}

	/**
	 * Verify one frozen state transition.
	 *
	 * @param AdminTagLifecycleAction $action Administrator action.
	 * @param TagStatus               $status Current Tag status.
	 * @param int|null                $owner_id Current Owner User ID.
	 * @param int|null                $target_user_id Optional target User ID.
	 * @param TagStatus|null          $expected_status Expected committed status.
	 * @param int|null                $expected_owner_id Expected committed Owner.
	 * @dataProvider transitions
	 */
	public function test_state_matrix(
		AdminTagLifecycleAction $action,
		TagStatus $status,
		?int $owner_id,
		?int $target_user_id,
		?TagStatus $expected_status,
		?int $expected_owner_id
	): void {
		$result = ( new AdminTagLifecyclePolicy() )->decide( $action, new AdminTagLifecycleState( $status, $owner_id ), $target_user_id );
		if ( null === $expected_status ) {
			self::assertNull( $result );
			return;
		}
		self::assertNotNull( $result );
		self::assertSame( $expected_status, $result->status );
		self::assertSame( $expected_owner_id, $result->owner_id );
	}

	/** Exact confirmation and the default-off flag fail before persistence. */
	public function test_service_fails_closed_before_store(): void {
		$store = $this->createMock( AdminTagLifecycleStore::class );
		$store->expects( self::never() )->method( 'change' );
		$service = new ManageAdminTagLifecycle( $store, $this->flags( false ), $this->clock() );
		$result  = $service->execute(
			TagId::from_canonical( '234567' ),
			AdminTagLifecycleAction::SUSPEND,
			'234567',
			new AdminTagLifecycleState( TagStatus::ACTIVE, 11 ),
			null,
			9
		);
		self::assertFalse( $result->changed );
	}

	/** Enabled, exactly confirmed actions carry only internal state to the store. */
	public function test_service_delegates_an_enabled_exact_action(): void {
		$tag      = TagId::from_canonical( '234567' );
		$expected = new AdminTagLifecycleState( TagStatus::ACTIVE, 11 );
		$changed  = new AdminTagLifecycleState( TagStatus::SUSPENDED, 11 );
		$store    = $this->createMock( AdminTagLifecycleStore::class );
		$store->expects( self::once() )->method( 'change' )->with( $tag, AdminTagLifecycleAction::SUSPEND, $expected, null, 9, $this->clock()->now() )->willReturn( AdminTagLifecycleResult::changed( $changed ) );
		$result = ( new ManageAdminTagLifecycle( $store, $this->flags( true ), $this->clock() ) )->execute( $tag, AdminTagLifecycleAction::SUSPEND, '234567', $expected, null, 9 );
		self::assertTrue( $result->changed );
		self::assertSame( $changed, $result->state );
	}

	/** Event metadata allows only before/after state and internal Owner IDs. */
	public function test_event_metadata_is_bounded_to_state_and_owner_ids(): void {
		$policy   = new AdminTagLifecycleEventMetadataPolicy();
		$metadata = EventMetadata::from_values(
			'tag_transferred',
			array(
				'before_status'   => 'active',
				'after_status'    => 'active',
				'before_owner_id' => 11,
				'after_owner_id'  => 22,
			),
			$policy
		);
		self::assertSame(
			array(
				'after_owner_id'  => 22,
				'after_status'    => 'active',
				'before_owner_id' => 11,
				'before_status'   => 'active',
			),
			$metadata->values()
		);
	}

	/** Return a deterministic test clock. */
	private function clock(): Clock {
		return new class() implements Clock {
			/** Return the fixed UTC fixture time. */
			public function now(): DateTimeImmutable {
				return new DateTimeImmutable( '2026-08-17 10:00:00 UTC' );
			}
		};
	}

	/**
	 * Return a lifecycle-only feature flag reader.
	 *
	 * @param bool $enabled Lifecycle flag state.
	 */
	private function flags( bool $enabled ): FeatureFlagReader {
		return new class( $enabled ) implements FeatureFlagReader {
			/**
			 * Create the flag reader.
			 *
			 * @param bool $enabled Lifecycle flag state.
			 */
			public function __construct( private readonly bool $enabled ) {
			}

			/**
			 * Return only the configured lifecycle flag state.
			 *
			 * @param FeatureFlag $feature_flag Requested feature flag.
			 */
			public function is_enabled( FeatureFlag $feature_flag ): bool {
				return FeatureFlag::ADMIN_TAG_LIFECYCLE === $feature_flag && $this->enabled;
			}
		};
	}
}
