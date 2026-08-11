<?php
/**
 * RT-317 Stage 2 Owner Tag mutation tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Account;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Account\MutateOwnerTag;
use ReturnTag\TagCore\Application\Account\OwnerTagLostState;
use ReturnTag\TagCore\Application\Account\OwnerTagMetadata;
use ReturnTag\TagCore\Application\Account\OwnerTagMutationEventIdentityPolicy;
use ReturnTag\TagCore\Application\Account\OwnerTagMutationRateLimiter;
use ReturnTag\TagCore\Application\Account\OwnerTagMutationResult;
use ReturnTag\TagCore\Application\Account\OwnerTagMutationStore;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\ImmediateTransactionManager;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryEventRepository;

/** Verifies validation, session identity, atomic Events, and safe outcomes. */
final class OwnerTagMutationTest extends TestCase {
	/** Metadata is trimmed, optional, bounded, and plain text. */
	public function test_metadata_value_normalizes_and_rejects_markup(): void {
		$metadata = new OwnerTagMetadata( '  Work laptop  ', '  Silver laptop  ' );

		self::assertSame( 'Work laptop', $metadata->item_name );
		self::assertSame( 'Silver laptop', $metadata->public_label );
		self::assertNull( ( new OwnerTagMetadata( ' ', '' ) )->item_name );

		$this->expectException( InvalidArgumentException::class );
		new OwnerTagMetadata( '<strong>Laptop</strong>', 'Laptop' );
	}

	/**
	 * Lost Message rejects each frozen high-risk class.
	 *
	 * @param string $message Unsafe Finder-visible content.
	 * @dataProvider unsafe_lost_message_provider
	 */
	public function test_lost_message_rejects_high_risk_content( string $message ): void {
		$this->expectException( InvalidArgumentException::class );
		new OwnerTagLostState( true, $message );
	}

	/**
	 * Return one case for each prohibited content class.
	 *
	 * @return iterable<string, array{string}>
	 */
	public function unsafe_lost_message_provider(): iterable {
		yield 'HTML' => array( '<a href="tel:1">Call</a>' );
		yield 'password' => array( 'The password is travel123.' );
		yield 'verification code' => array( 'Use verification code 123456.' );
		yield 'bank account' => array( 'Send it after checking bank account 1234.' );
		yield 'identity document' => array( 'My passport number is available here.' );
		yield 'home address' => array( 'Bring it to 123 Main Street.' );
	}

	/** A successful write uses the session Owner and appends one metadata-free Event. */
	public function test_metadata_update_is_transactional_and_audited(): void {
		$tag_id       = TagId::from_canonical( 'A7R2W9' );
		$store        = $this->createMock( OwnerTagMutationStore::class );
		$events       = new InMemoryEventRepository();
		$transactions = new ImmediateTransactionManager();
		$now          = new DateTimeImmutable( '2026-08-10 12:00:00', new DateTimeZone( 'UTC' ) );
		$metadata     = new OwnerTagMetadata( 'Work laptop', 'Silver laptop' );
		$store->expects( self::once() )
			->method( 'update_metadata' )
			->with( $tag_id, 42, $metadata, $now )
			->willReturn( OwnerTagMutationResult::UPDATED );

		$result = $this->service( $store, $events, $transactions, $now )->update_metadata( $tag_id, $metadata );

		self::assertSame( OwnerTagMutationResult::UPDATED, $result );
		self::assertSame( 1, $transactions->calls );
		self::assertCount( 1, $events->records );
		$event = $events->records[0]->data;
		self::assertSame( OwnerTagMutationEventIdentityPolicy::METADATA_UPDATED, $event->event_type );
		self::assertSame( 42, $event->actor_id );
		self::assertSame( 'A7R2W9', $event->target_id );
		self::assertNull( $event->metadata->json() );
	}

	/** Idempotent writes do not create duplicate Events. */
	public function test_unchanged_write_has_no_event(): void {
		$store = $this->createMock( OwnerTagMutationStore::class );
		$store->method( 'update_lost_state' )->willReturn( OwnerTagMutationResult::UNCHANGED );
		$events = new InMemoryEventRepository();

		$result = $this->service(
			$store,
			$events,
			new ImmediateTransactionManager(),
			new DateTimeImmutable( '2026-08-10 12:00:00', new DateTimeZone( 'UTC' ) )
		)->update_lost_state( TagId::from_canonical( 'A7R2W9' ), new OwnerTagLostState( false, '' ) );

		self::assertSame( OwnerTagMutationResult::UNCHANGED, $result );
		self::assertSame( array(), $events->records );
	}

	/** Disabled Account and unauthenticated requests do not reach persistence. */
	public function test_missing_authority_fails_before_write(): void {
		$session = $this->createMock( AuthenticatedSession::class );
		$session->method( 'current_user_id' )->willReturn( null );
		$store = $this->createMock( OwnerTagMutationStore::class );
		$store->expects( self::never() )->method( 'acknowledge_smart_setup' );
		$service = new MutateOwnerTag(
			$session,
			$this->flags( true ),
			$store,
			$this->limiter( true ),
			new InMemoryEventRepository(),
			new ImmediateTransactionManager(),
			new FixedClock( new DateTimeImmutable( '2026-08-10 12:00:00', new DateTimeZone( 'UTC' ) ) )
		);

		self::assertSame(
			OwnerTagMutationResult::AUTHENTICATION_REQUIRED,
			$service->acknowledge_smart_setup( TagId::from_canonical( 'A7R2W9' ) )
		);
	}

	/**
	 * Build one enabled, authenticated mutation service.
	 *
	 * @param OwnerTagMutationStore       $store Mutation store.
	 * @param InMemoryEventRepository     $events In-memory Event store.
	 * @param ImmediateTransactionManager $transactions Test transaction boundary.
	 * @param DateTimeImmutable           $now Fixed current time.
	 */
	private function service(
		OwnerTagMutationStore $store,
		InMemoryEventRepository $events,
		ImmediateTransactionManager $transactions,
		DateTimeImmutable $now
	): MutateOwnerTag {
		$session = $this->createMock( AuthenticatedSession::class );
		$session->method( 'current_user_id' )->willReturn( 42 );

		return new MutateOwnerTag(
			$session,
			$this->flags( true ),
			$store,
			$this->limiter( true ),
			$events,
			$transactions,
			new FixedClock( $now )
		);
	}

	/**
	 * Return one fixed Account flag reader.
	 *
	 * @param bool $enabled Whether the control is enabled.
	 */
	private function flags( bool $enabled ): FeatureFlagReader {
		return new class( $enabled ) implements FeatureFlagReader {
			/**
			 * Create the fixed reader.
			 *
			 * @param bool $enabled Whether the control is enabled.
			 */
			public function __construct( private readonly bool $enabled ) {
			}

			/**
			 * Read the synthetic control.
			 *
			 * @param FeatureFlag $feature_flag Requested control.
			 */
			public function is_enabled( FeatureFlag $feature_flag ): bool {
				return FeatureFlag::OWNER_ACCOUNT === $feature_flag && $this->enabled;
			}
		};
	}

	/**
	 * Return one fixed mutation limiter.
	 *
	 * @param bool $allowed Whether the request is allowed.
	 */
	private function limiter( bool $allowed ): OwnerTagMutationRateLimiter {
		return new class( $allowed ) implements OwnerTagMutationRateLimiter {
			/**
			 * Create the fixed limiter.
			 *
			 * @param bool $allowed Whether the request is allowed.
			 */
			public function __construct( private readonly bool $allowed ) {
			}

			/**
			 * Return the fixed result.
			 *
			 * @param int               $owner_id Synthetic Owner identifier.
			 * @param TagId             $tag_id Synthetic Tag identifier.
			 * @param DateTimeImmutable $now Fixed current time.
			 */
			public function reserve( int $owner_id, TagId $tag_id, DateTimeImmutable $now ): bool {
				unset( $owner_id, $tag_id, $now );

				return $this->allowed;
			}
		};
	}
}
