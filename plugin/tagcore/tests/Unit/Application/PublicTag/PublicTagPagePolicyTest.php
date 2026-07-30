<?php
/**
 * RT-303 public Tag page policy tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\PublicTag;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPagePolicy;
use ReturnTag\TagCore\Application\PublicTag\PublicTagPageState;
use ReturnTag\TagCore\Application\PublicTag\PublicTagStateRecord;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;

/**
 * Verifies the complete server-derived public state matrix.
 */
final class PublicTagPagePolicyTest extends TestCase {
	/**
	 * Unregistered Tags expose activation only when every approved gate is open.
	 *
	 * @dataProvider unregistered_provider
	 *
	 * @param BatchStatus|null   $batch_status Stored Batch status.
	 * @param bool|null          $batch_enabled Stored Batch activation control.
	 * @param bool               $global_enabled Global activation control.
	 * @param PublicTagPageState $expected Derived page.
	 */
	public function test_unregistered_activation_matrix(
		?BatchStatus $batch_status,
		?bool $batch_enabled,
		bool $global_enabled,
		PublicTagPageState $expected
	): void {
		$page = $this->policy()->decide(
			$this->record(
				TagStatus::UNREGISTERED,
				null,
				null,
				$batch_status,
				$batch_enabled
			),
			null,
			$global_enabled,
			true
		);

		self::assertSame( $expected, $page->state );
		self::assertNull( $page->public_label );
		self::assertNull( $page->lost_message );
	}

	/**
	 * Provide representative activation gates.
	 *
	 * @return iterable<string, array{BatchStatus|null, bool|null, bool, PublicTagPageState}>
	 */
	public function unregistered_provider(): iterable {
		yield 'eligible' => array( BatchStatus::RELEASED, true, true, PublicTagPageState::ACTIVATION_ENTRY );
		yield 'global pause' => array( BatchStatus::RELEASED, true, false, PublicTagPageState::ACTIVATION_UNAVAILABLE );
		yield 'batch pause' => array( BatchStatus::RELEASED, false, true, PublicTagPageState::ACTIVATION_UNAVAILABLE );
		yield 'awaiting release' => array( BatchStatus::EXPORTED, false, true, PublicTagPageState::ACTIVATION_UNAVAILABLE );
		yield 'suspended batch' => array( BatchStatus::SUSPENDED, false, true, PublicTagPageState::ACTIVATION_UNAVAILABLE );
		yield 'voided batch' => array( BatchStatus::VOIDED, false, true, PublicTagPageState::ACTIVATION_UNAVAILABLE );
		yield 'missing batch' => array( null, null, true, PublicTagPageState::SERVICE_UNAVAILABLE );
	}

	/**
	 * Active owners are recognized before Batch incident state is considered.
	 */
	public function test_active_owner_is_retained_when_batch_is_voided(): void {
		$page = $this->policy()->decide(
			$this->record(
				TagStatus::ACTIVE,
				42,
				new DateTimeImmutable( '2026-07-30 00:00:00' ),
				BatchStatus::VOIDED,
				false
			),
			42,
			false,
			false
		);

		self::assertSame( PublicTagPageState::OWNER_ENTRY, $page->state );
		self::assertNull( $page->public_label );
		self::assertFalse( $page->lost_mode );
	}

	/**
	 * Finder fields are exposed only for a non-owner active Tag with contact enabled.
	 */
	public function test_finder_page_contains_only_approved_public_fields(): void {
		$record = $this->record(
			TagStatus::ACTIVE,
			42,
			new DateTimeImmutable( '2026-07-30 00:00:00' ),
			BatchStatus::SUSPENDED,
			false,
			true,
			'Blue backpack',
			'Please leave it with airport security.'
		);
		$page   = $this->policy()->decide( $record, 99, false, true );

		self::assertSame( PublicTagPageState::FINDER_ENTRY, $page->state );
		self::assertSame( TagType::CLASSIC_TAG, $page->tag_type );
		self::assertSame( 'Blue backpack', $page->public_label );
		self::assertTrue( $page->lost_mode );
		self::assertSame( 'Please leave it with airport security.', $page->lost_message );
	}

	/**
	 * Lost Message stays hidden when Lost Mode is off.
	 */
	public function test_lost_message_is_hidden_outside_lost_mode(): void {
		$record = $this->record(
			TagStatus::ACTIVE,
			42,
			new DateTimeImmutable( '2026-07-30 00:00:00' ),
			BatchStatus::RELEASED,
			true,
			false,
			'Water bottle',
			'Private until Lost Mode is enabled.'
		);
		$page   = $this->policy()->decide( $record, null, true, true );

		self::assertSame( PublicTagPageState::FINDER_ENTRY, $page->state );
		self::assertFalse( $page->lost_mode );
		self::assertNull( $page->lost_message );
	}

	/**
	 * Finder feature pause returns no public label or Lost Mode content.
	 */
	public function test_finder_pause_removes_optional_public_fields(): void {
		$record = $this->record(
			TagStatus::ACTIVE,
			42,
			new DateTimeImmutable( '2026-07-30 00:00:00' ),
			BatchStatus::RELEASED,
			true,
			true,
			'Camera',
			'Call venue security.'
		);
		$page   = $this->policy()->decide( $record, null, true, false );

		self::assertSame( PublicTagPageState::FINDER_UNAVAILABLE, $page->state );
		self::assertNull( $page->public_label );
		self::assertNull( $page->lost_message );
	}

	/**
	 * Tag-level terminal states override all entry experiences.
	 *
	 * @dataProvider terminal_state_provider
	 *
	 * @param TagStatus          $tag_status Stored Tag status.
	 * @param PublicTagPageState $expected Derived page.
	 */
	public function test_terminal_tag_states( TagStatus $tag_status, PublicTagPageState $expected ): void {
		$page = $this->policy()->decide(
			$this->record( $tag_status, 42, null, BatchStatus::RELEASED, true ),
			42,
			true,
			true
		);

		self::assertSame( $expected, $page->state );
		self::assertNull( $page->tag_type );
	}

	/**
	 * Provide Tag-level service states.
	 *
	 * @return iterable<string, array{TagStatus, PublicTagPageState}>
	 */
	public function terminal_state_provider(): iterable {
		yield 'suspended' => array( TagStatus::SUSPENDED, PublicTagPageState::SUSPENDED );
		yield 'retired' => array( TagStatus::RETIRED, PublicTagPageState::RETIRED );
	}

	/**
	 * Ownership and activation inconsistencies fail closed.
	 *
	 * @dataProvider inconsistent_record_provider
	 *
	 * @param TagStatus              $tag_status Stored Tag status.
	 * @param int|null               $owner_id Stored owner.
	 * @param DateTimeImmutable|null $activated_at Stored activation time.
	 */
	public function test_inconsistent_records_fail_closed(
		TagStatus $tag_status,
		?int $owner_id,
		?DateTimeImmutable $activated_at
	): void {
		$page = $this->policy()->decide(
			$this->record(
				$tag_status,
				$owner_id,
				$activated_at,
				BatchStatus::RELEASED,
				true
			),
			$owner_id,
			true,
			true
		);

		self::assertSame( PublicTagPageState::SERVICE_UNAVAILABLE, $page->state );
	}

	/**
	 * Provide unsafe stored combinations.
	 *
	 * @return iterable<string, array{TagStatus, int|null, DateTimeImmutable|null}>
	 */
	public function inconsistent_record_provider(): iterable {
		yield 'active without owner' => array(
			TagStatus::ACTIVE,
			null,
			new DateTimeImmutable( '2026-07-30 00:00:00' ),
		);
		yield 'active without activation time' => array( TagStatus::ACTIVE, 42, null );
		yield 'unregistered with owner' => array( TagStatus::UNREGISTERED, 42, null );
		yield 'unregistered with activation time' => array(
			TagStatus::UNREGISTERED,
			null,
			new DateTimeImmutable( '2026-07-30 00:00:00' ),
		);
	}

	/**
	 * Build the pure policy.
	 */
	private function policy(): PublicTagPagePolicy {
		return new PublicTagPagePolicy( new TagActivationAvailabilityPolicy() );
	}

	/**
	 * Build one synthetic, non-PII state record.
	 *
	 * @param TagStatus              $tag_status Tag status.
	 * @param int|null               $owner_id Owner ID.
	 * @param DateTimeImmutable|null $activated_at Activation time.
	 * @param BatchStatus|null       $batch_status Batch status.
	 * @param bool|null              $batch_enabled Batch activation control.
	 * @param bool                   $lost_mode Lost Mode state.
	 * @param string|null            $public_label Public label.
	 * @param string|null            $lost_message Lost message.
	 */
	private function record(
		TagStatus $tag_status,
		?int $owner_id,
		?DateTimeImmutable $activated_at,
		?BatchStatus $batch_status,
		?bool $batch_enabled,
		bool $lost_mode = false,
		?string $public_label = null,
		?string $lost_message = null
	): PublicTagStateRecord {
		return new PublicTagStateRecord(
			$owner_id,
			TagType::CLASSIC_TAG,
			$public_label,
			$tag_status,
			$lost_mode,
			$lost_message,
			$activated_at,
			$batch_status,
			$batch_enabled
		);
	}
}
