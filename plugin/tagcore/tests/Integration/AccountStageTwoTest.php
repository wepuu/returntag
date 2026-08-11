<?php
/**
 * RT-317 Stage 2 Account mutation boundary tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use ReturnTag\TagCore\Account\AccountFormRequestGuard;
use ReturnTag\TagCore\Account\AccountFormResult;
use ReturnTag\TagCore\Account\AccountFormState;
use ReturnTag\TagCore\Account\AccountRoute;
use ReturnTag\TagCore\Account\AccountTagMutationFormHandler;
use ReturnTag\TagCore\Account\AccountTagMutationState;
use ReturnTag\TagCore\Account\AccountTemplateRenderer;
use ReturnTag\TagCore\Account\AccountUrlProvider;
use ReturnTag\TagCore\Application\Account\MutateOwnerTag;
use ReturnTag\TagCore\Application\Account\OwnerTagAccessState;
use ReturnTag\TagCore\Application\Account\OwnerTagDetail;
use ReturnTag\TagCore\Application\Account\OwnerTagMetadata;
use ReturnTag\TagCore\Application\Account\OwnerTagMutationRateLimiter;
use ReturnTag\TagCore\Application\Account\OwnerTagMutationResult;
use ReturnTag\TagCore\Application\Account\OwnerTagMutationStore;
use ReturnTag\TagCore\Application\Auth\AuthenticatedSession;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Record\NewTagRecord;
use ReturnTag\TagCore\Application\Persistence\Record\TagRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\TransactionManager;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use WP_UnitTestCase;

/** Verifies editable/read-only rendering and the nonce-bound browser boundary. */
final class AccountStageTwoTest extends WP_UnitTestCase {
	/** Active Tags expose only the approved bounded controls. */
	public function test_active_tag_renders_bounded_stage_two_forms(): void {
		$html = $this->render_tag( $this->tag() );

		self::assertStringContainsString( 'value="update_metadata"', $html );
		self::assertStringContainsString( 'value="update_lost_state"', $html );
		self::assertStringContainsString( 'name="returntag_item_name"', $html );
		self::assertStringContainsString( 'name="returntag_public_label"', $html );
		self::assertStringContainsString( 'name="returntag_lost_message"', $html );
		self::assertStringContainsString( 'maxlength="191"', $html );
		self::assertStringContainsString( 'maxlength="500"', $html );
		self::assertStringNotContainsString( 'name="owner_id"', $html );
		self::assertStringNotContainsString( 'value="acknowledge_smart_setup"', $html );
	}

	/** Suspended Tags remain visible but cannot submit a Stage 2 action. */
	public function test_suspended_tag_is_read_only(): void {
		$html = $this->render_tag( $this->tag( TagType::CLASSIC_TAG, TagStatus::SUSPENDED ) );

		self::assertStringContainsString( 'read-only in its current status', $html );
		self::assertStringNotContainsString( 'returntag_account_tag_action', $html );
	}

	/** Smart Tags expose acknowledgement without claiming pairing verification. */
	public function test_smart_tag_renders_one_acknowledgement_control(): void {
		$html = $this->render_tag( $this->tag( TagType::SMART_TAG ) );

		self::assertSame( 1, substr_count( $html, 'value="acknowledge_smart_setup"' ) );
		self::assertStringContainsString( 'does not verify pairing', $html );
	}

	/** A valid same-site nonce reaches the service without browser-supplied authority. */
	public function test_valid_nonce_allows_closed_metadata_action(): void {
		$store = $this->createMock( OwnerTagMutationStore::class );
		$store->expects( self::once() )
			->method( 'update_metadata' )
			->with(
				self::callback( static fn( TagId $tag_id ): bool => 'A7R2W9' === $tag_id->value ),
				42,
				self::callback(
					static fn( OwnerTagMetadata $metadata ): bool => 'Work laptop' === $metadata->item_name
						&& 'Silver laptop' === $metadata->public_label
				),
				self::isInstanceOf( DateTimeImmutable::class )
			)
			->willReturn( OwnerTagMutationResult::UNCHANGED );

		$_SERVER['HTTP_ORIGIN'] = home_url( '/' );
		$_POST                  = array(
			AccountTagMutationFormHandler::NONCE_FIELD     => wp_create_nonce( AccountTagMutationFormHandler::NONCE_PREFIX . 'A7R2W9' ),
			AccountTagMutationFormHandler::ACTION_FIELD    => AccountTagMutationFormHandler::UPDATE_METADATA,
			AccountTagMutationFormHandler::ITEM_NAME_FIELD => 'Work laptop',
			AccountTagMutationFormHandler::PUBLIC_LABEL_FIELD => 'Silver laptop',
		);

		$result = ( new AccountTagMutationFormHandler( $this->service( $store ), new AccountFormRequestGuard() ) )
			->submit( TagId::from_canonical( 'A7R2W9' ) );

		self::assertSame( AccountTagMutationState::UNCHANGED, $result->state );
	}

	/** Invalid nonce and injected authority fields fail before persistence. */
	public function test_invalid_boundary_evidence_is_rejected_before_persistence(): void {
		$store = $this->createMock( OwnerTagMutationStore::class );
		$store->expects( self::never() )->method( 'update_metadata' );
		$handler = new AccountTagMutationFormHandler( $this->service( $store ), new AccountFormRequestGuard() );

		$_SERVER['HTTP_ORIGIN'] = home_url( '/' );
		$_POST                  = array(
			AccountTagMutationFormHandler::NONCE_FIELD  => 'invalid',
			AccountTagMutationFormHandler::ACTION_FIELD => AccountTagMutationFormHandler::UPDATE_METADATA,
		);
		self::assertSame( AccountTagMutationState::UNAVAILABLE, $handler->submit( TagId::from_canonical( 'A7R2W9' ) )->state );

		$_POST = array(
			AccountTagMutationFormHandler::NONCE_FIELD  => wp_create_nonce( AccountTagMutationFormHandler::NONCE_PREFIX . 'A7R2W9' ),
			AccountTagMutationFormHandler::ACTION_FIELD => AccountTagMutationFormHandler::UPDATE_METADATA,
			'owner_id'                                  => '43',
		);
		self::assertSame( AccountTagMutationState::UNAVAILABLE, $handler->submit( TagId::from_canonical( 'A7R2W9' ) )->state );
	}

	/** Restore request globals changed by browser-boundary tests. */
	protected function tearDown(): void {
		$_POST = array();
		unset( $_SERVER['HTTP_ORIGIN'] );
		parent::tearDown();
	}

	/**
	 * Render one synthetic Owner Tag detail.
	 *
	 * @param TagRecord $tag Synthetic current-Owner Tag.
	 */
	private function render_tag( TagRecord $tag ): string {
		return ( new AccountTemplateRenderer( RETURNTAG_TAGCORE_DIR, new AccountUrlProvider() ) )->render_to_string(
			AccountRoute::TAG,
			new AccountFormResult( AccountFormState::READY ),
			null,
			new OwnerTagDetail( OwnerTagAccessState::READY, $tag )
		);
	}

	/**
	 * Build one synthetic current-Owner Tag without real PII.
	 *
	 * @param TagType   $type Canonical product type.
	 * @param TagStatus $status Canonical Tag status.
	 */
	private function tag( TagType $type = TagType::CLASSIC_TAG, TagStatus $status = TagStatus::ACTIVE ): TagRecord {
		$time = new DateTimeImmutable( '2026-08-10 00:00:00', new DateTimeZone( 'UTC' ) );

		return new TagRecord(
			new NewTagRecord(
				'A7R2W9',
				1,
				42,
				$type,
				null,
				'Weekend carry-on',
				'Black suitcase',
				$status,
				true,
				'Please contact me through ForgeTag.',
				null,
				$time,
				$time,
				null,
				$time,
				$time
			)
		);
	}

	/**
	 * Build the use case with a fixed authenticated Owner and no side effects.
	 *
	 * @param OwnerTagMutationStore $store Test mutation store.
	 */
	private function service( OwnerTagMutationStore $store ): MutateOwnerTag {
		$session      = new class() implements AuthenticatedSession {
			/** Return the synthetic Owner. */
			public function current_user_id(): ?int {
				return 42;
			}

			/**
			 * Authentication is outside this test.
			 *
			 * @param int $user_id Ignored User identifier.
			 */
			public function authenticate( int $user_id ): void {
				unset( $user_id );
			}
		};
		$flags        = new class() implements FeatureFlagReader {
			/**
			 * Enable only the Owner Account control.
			 *
			 * @param FeatureFlag $feature_flag Requested control.
			 */
			public function is_enabled( FeatureFlag $feature_flag ): bool {
				return FeatureFlag::OWNER_ACCOUNT === $feature_flag;
			}
		};
		$limiter      = new class() implements OwnerTagMutationRateLimiter {
			/**
			 * Allow the bounded synthetic request.
			 *
			 * @param int               $owner_id Synthetic Owner identifier.
			 * @param TagId             $tag_id Synthetic Tag identifier.
			 * @param DateTimeImmutable $now Fixed current time.
			 */
			public function reserve( int $owner_id, TagId $tag_id, DateTimeImmutable $now ): bool {
				unset( $owner_id, $tag_id, $now );

				return true;
			}
		};
		$transactions = new class() implements TransactionManager {
			/**
			 * Execute the synthetic transaction inline.
			 *
			 * @param callable $operation Test operation.
			 */
			public function transactional( callable $operation ): mixed {
				return $operation();
			}
		};
		$clock        = new class() implements Clock {
			/** Return a stable UTC timestamp. */
			public function now(): DateTimeImmutable {
				return new DateTimeImmutable( '2026-08-10 00:00:00', new DateTimeZone( 'UTC' ) );
			}
		};

		return new MutateOwnerTag(
			$session,
			$flags,
			$store,
			$limiter,
			$this->createMock( EventRepository::class ),
			$transactions,
			$clock
		);
	}
}
