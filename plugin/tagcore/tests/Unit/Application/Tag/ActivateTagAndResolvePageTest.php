<?php
/**
 * RT-308 activation state-convergence tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Tag;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPagePolicy;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPageState;
use ReturnTag\TagCore\Application\PublicTag\PublicTagStateReader;
use ReturnTag\TagCore\Application\PublicTag\PublicTagStateRecord;
use ReturnTag\TagCore\Application\PublicTag\ResolvePublicTagPage;
use ReturnTag\TagCore\Application\Tag\ActivateTag;
use ReturnTag\TagCore\Application\Tag\ActivateTagAndResolvePage;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Application\Tag\TagActivationRepository;
use ReturnTag\TagCore\Application\Tag\TagActivationResult;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\FixedClock;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\ImmediateTransactionManager;
use ReturnTag\TagCore\Tests\Unit\Application\Batch\Fixture\InMemoryEventRepository;

/**
 * Verifies that persistence outcomes never become a fourth public page state.
 */
final class ActivateTagAndResolvePageTest extends TestCase {
	/**
	 * Success and same-Owner retry both converge to the Owner page.
	 *
	 * @param TagActivationResult $activation_result Persistence outcome.
	 * @dataProvider owner_outcome_provider
	 */
	public function test_owner_outcomes_resolve_to_owner_page( TagActivationResult $activation_result ): void {
		$page = $this->service(
			$activation_result,
			$this->active_record( 42 ),
			true,
			true
		)->execute( TagId::from_canonical( 'A7R2W9' ), 42 );

		self::assertSame( PublicTagPageState::OWNER_ENTRY, $page->state );
	}

	/**
	 * Provide successful and idempotent Owner outcomes.
	 *
	 * @return iterable<string, array{TagActivationResult}>
	 */
	public function owner_outcome_provider(): iterable {
		yield 'first activation' => array( TagActivationResult::ACTIVATED );
		yield 'same owner retry' => array( TagActivationResult::ALREADY_OWNED );
	}

	/**
	 * A concurrent winner owned by someone else converges to Finder.
	 */
	public function test_changed_active_owner_resolves_to_finder_page(): void {
		$page = $this->service(
			TagActivationResult::STATE_CHANGED,
			$this->active_record( 24 ),
			true,
			true
		)->execute( TagId::from_canonical( 'A7R2W9' ), 42 );

		self::assertSame( PublicTagPageState::FINDER_ENTRY, $page->state );
		self::assertSame( 'Travel bag', $page->public_label );
		self::assertTrue( $page->lost_mode );
		self::assertSame( 'Please help this item get home.', $page->lost_message );
	}

	/**
	 * A missing committed Tag converges to the generic invalid page.
	 */
	public function test_changed_missing_tag_resolves_to_invalid_page(): void {
		$page = $this->service(
			TagActivationResult::STATE_CHANGED,
			null,
			true,
			true
		)->execute( TagId::from_canonical( 'A7R2W9' ), 42 );

		self::assertSame( PublicTagPageState::INVALID, $page->state );
		self::assertNull( $page->tag_type );
	}

	/**
	 * Operational and Tag state controls retain their existing explanation page.
	 *
	 * @param PublicTagStateRecord $record Stored committed state.
	 * @param PublicTagPageState   $expected Expected existing page state.
	 * @param bool                 $finder_enabled Finder incident control.
	 * @dataProvider blocked_state_provider
	 */
	public function test_changed_blocked_state_uses_existing_page(
		PublicTagStateRecord $record,
		PublicTagPageState $expected,
		bool $finder_enabled
	): void {
		$page = $this->service(
			TagActivationResult::STATE_CHANGED,
			$record,
			true,
			$finder_enabled
		)->execute( TagId::from_canonical( 'A7R2W9' ), 42 );

		self::assertSame( $expected, $page->state );
	}

	/**
	 * Provide existing blocked and unavailable route states.
	 *
	 * @return iterable<string, array{PublicTagStateRecord, PublicTagPageState, bool}>
	 */
	public function blocked_state_provider(): iterable {
		yield 'suspended Tag' => array(
			$this->record( TagStatus::SUSPENDED, 24, new DateTimeImmutable( '2026-07-31 12:00:00', new DateTimeZone( 'UTC' ) ) ),
			PublicTagPageState::SUSPENDED,
			true,
		);
		yield 'retired Tag' => array(
			$this->record( TagStatus::RETIRED, 24, new DateTimeImmutable( '2026-07-31 12:00:00', new DateTimeZone( 'UTC' ) ) ),
			PublicTagPageState::RETIRED,
			true,
		);
		yield 'Finder incident control' => array(
			$this->active_record( 24 ),
			PublicTagPageState::FINDER_UNAVAILABLE,
			false,
		);
	}

	/**
	 * A disabled activation control resolves to the existing unavailable page.
	 */
	public function test_unavailable_activation_resolves_to_existing_page(): void {
		$page = $this->service(
			TagActivationResult::UNAVAILABLE,
			$this->record( TagStatus::UNREGISTERED, null, null ),
			false,
			true
		)->execute( TagId::from_canonical( 'A7R2W9' ), 42 );

		self::assertSame( PublicTagPageState::ACTIVATION_UNAVAILABLE, $page->state );
	}

	/**
	 * Build the state-convergence service with committed synthetic state.
	 *
	 * @param TagActivationResult       $activation_result Persistence outcome.
	 * @param PublicTagStateRecord|null $record Committed public projection.
	 * @param bool                      $global_enabled Global activation control.
	 * @param bool                      $finder_enabled Finder contact control.
	 */
	private function service(
		TagActivationResult $activation_result,
		?PublicTagStateRecord $record,
		bool $global_enabled,
		bool $finder_enabled
	): ActivateTagAndResolvePage {
		$flags  = new class( $global_enabled, $finder_enabled ) implements FeatureFlagReader {
			/**
			 * Create fixed feature controls.
			 *
			 * @param bool $global_enabled Global activation control.
			 * @param bool $finder_enabled Finder contact control.
			 */
			public function __construct(
				private readonly bool $global_enabled,
				private readonly bool $finder_enabled
			) {
			}

			/**
			 * Return the configured control value.
			 *
			 * @param FeatureFlag $feature_flag Requested feature flag.
			 */
			public function is_enabled( FeatureFlag $feature_flag ): bool {
				return match ( $feature_flag ) {
					FeatureFlag::GLOBAL_ACTIVATION => $this->global_enabled,
					FeatureFlag::FINDER_CONTACT => $this->finder_enabled,
					default => true,
				};
			}
		};
		$tags   = new class( $activation_result ) implements TagActivationRepository {
			/**
			 * Create the fixed activation Repository.
			 *
			 * @param TagActivationResult $result Persistence outcome.
			 */
			public function __construct( private readonly TagActivationResult $result ) {
			}

			/**
			 * Return the fixed persistence outcome.
			 *
			 * @param TagId             $tag_id Canonical Tag ID.
			 * @param int               $owner_id Current User ID.
			 * @param DateTimeImmutable $now Current UTC time.
			 */
			public function activate(
				TagId $tag_id,
				int $owner_id,
				DateTimeImmutable $now
			): TagActivationResult {
				unset( $tag_id, $owner_id, $now );

				return $this->result;
			}
		};
		$states = new class( $record ) implements PublicTagStateReader {
			/**
			 * Create the fixed state reader.
			 *
			 * @param PublicTagStateRecord|null $record Committed projection.
			 */
			public function __construct( private readonly ?PublicTagStateRecord $record ) {
			}

			/**
			 * Return the committed projection.
			 *
			 * @param TagId $tag_id Canonical Tag ID.
			 */
			public function find( TagId $tag_id ): ?PublicTagStateRecord {
				unset( $tag_id );

				return $this->record;
			}
		};

		return new ActivateTagAndResolvePage(
			new ActivateTag(
				$tags,
				new InMemoryEventRepository(),
				new ImmediateTransactionManager(),
				$flags,
				new FixedClock( new DateTimeImmutable( '2026-07-31 12:00:00', new DateTimeZone( 'UTC' ) ) )
			),
			new ResolvePublicTagPage(
				$states,
				$flags,
				new PublicTagPagePolicy( new TagActivationAvailabilityPolicy() )
			)
		);
	}

	/**
	 * Build a valid active projection.
	 *
	 * @param int $owner_id Committed Owner ID.
	 */
	private function active_record( int $owner_id ): PublicTagStateRecord {
		return $this->record(
			TagStatus::ACTIVE,
			$owner_id,
			new DateTimeImmutable( '2026-07-31 12:00:00', new DateTimeZone( 'UTC' ) )
		);
	}

	/**
	 * Build one valid public projection.
	 *
	 * @param TagStatus              $status Canonical Tag state.
	 * @param int|null               $owner_id Optional committed Owner.
	 * @param DateTimeImmutable|null $activated_at Optional activation time.
	 */
	private function record(
		TagStatus $status,
		?int $owner_id,
		?DateTimeImmutable $activated_at
	): PublicTagStateRecord {
		return new PublicTagStateRecord(
			$owner_id,
			TagType::CLASSIC_TAG,
			'Travel bag',
			$status,
			true,
			'Please help this item get home.',
			$activated_at,
			BatchStatus::RELEASED,
			true
		);
	}
}
