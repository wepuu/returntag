<?php
/**
 * Current-Owner Account read tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Account;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Account\OwnerTagAccessState;
use ReturnTag\TagCore\Application\Account\OwnerTagReader;
use ReturnTag\TagCore\Application\Account\ReadOwnerTag;
use ReturnTag\TagCore\Application\Account\ReadOwnerTags;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Pagination\TagCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\TagPage;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Verifies fail-closed flags, session-derived identity, and generic detail denial.
 */
final class OwnerTagReadTest extends TestCase {
	/** Account reads fail before persistence when the containment flag is off. */
	public function test_disabled_account_does_not_query_owner_tags(): void {
		$session = $this->createMock( AuthenticatedSession::class );
		$reader  = $this->createMock( OwnerTagReader::class );
		$reader->expects( self::never() )->method( 'list_for_owner' );

		$result = ( new ReadOwnerTags( $session, $this->flags( false ), $reader ) )->execute();

		self::assertSame( OwnerTagAccessState::UNAVAILABLE, $result->state );
		self::assertNull( $result->page );
	}

	/** Account reads derive one positive Owner ID from the session. */
	public function test_list_uses_only_the_authenticated_owner_id(): void {
		$session = $this->createMock( AuthenticatedSession::class );
		$session->method( 'current_user_id' )->willReturn( 42 );
		$reader = $this->createMock( OwnerTagReader::class );
		$reader->expects( self::once() )
			->method( 'list_for_owner' )
			->with( 42, null, self::isInstanceOf( PageSize::class ) )
			->willReturn( new TagPage( array(), null ) );

		$result = ( new ReadOwnerTags( $session, $this->flags( true ), $reader ) )->execute();

		self::assertSame( OwnerTagAccessState::READY, $result->state );
		self::assertSame( array(), $result->page?->items );
	}

	/** Unknown and transferred detail candidates converge on one safe state. */
	public function test_missing_owned_detail_is_generic_unavailable(): void {
		$session = $this->createMock( AuthenticatedSession::class );
		$session->method( 'current_user_id' )->willReturn( 42 );
		$tag_id = TagId::from_canonical( 'A7R2W9' );
		$reader = $this->createMock( OwnerTagReader::class );
		$reader->expects( self::once() )->method( 'find_for_owner' )->with( 42, $tag_id )->willReturn( null );

		$result = ( new ReadOwnerTag( $session, $this->flags( true ), $reader ) )->execute( $tag_id );

		self::assertSame( OwnerTagAccessState::UNAVAILABLE, $result->state );
		self::assertNull( $result->tag );
	}

	/**
	 * Build one fixed feature flag reader.
	 *
	 * @param bool $enabled Fixed Owner Account state.
	 */
	private function flags( bool $enabled ): FeatureFlagReader {
		return new class( $enabled ) implements FeatureFlagReader {
			/**
			 * Create the fixed reader.
			 *
			 * @param bool $enabled Fixed Owner Account state.
			 */
			public function __construct( private readonly bool $enabled ) {
			}

			/**
			 * Return the configured result only for the Owner Account control.
			 *
			 * @param FeatureFlag $feature_flag Requested operational control.
			 */
			public function is_enabled( FeatureFlag $feature_flag ): bool {
				return FeatureFlag::OWNER_ACCOUNT === $feature_flag && $this->enabled;
			}
		};
	}
}
