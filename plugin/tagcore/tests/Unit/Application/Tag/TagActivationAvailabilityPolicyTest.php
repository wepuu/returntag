<?php
/**
 * RT-209 activation availability policy tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Tag;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailability;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagStatus;

/**
 * Verifies the read-only Tag and Batch state interpretation.
 */
final class TagActivationAvailabilityPolicyTest extends TestCase {
	/**
	 * The policy distinguishes activation history from current eligibility.
	 *
	 * @param TagStatus                 $tag_status Canonical Tag status.
	 * @param BatchStatus               $batch_status Canonical Batch status.
	 * @param bool                      $batch_enabled Batch activation control.
	 * @param bool                      $global_enabled Global activation control.
	 * @param DateTimeImmutable|null    $activated_at Optional activation time.
	 * @param TagActivationAvailability $expected Expected derived value.
	 * @dataProvider availability_provider
	 */
	public function test_derives_activation_availability(
		TagStatus $tag_status,
		BatchStatus $batch_status,
		bool $batch_enabled,
		bool $global_enabled,
		?DateTimeImmutable $activated_at,
		TagActivationAvailability $expected
	): void {
		self::assertSame(
			$expected,
			( new TagActivationAvailabilityPolicy() )->decide(
				$tag_status,
				$batch_status,
				$batch_enabled,
				$global_enabled,
				$activated_at
			)
		);
	}

	/**
	 * State combinations and expected presentation decisions.
	 *
	 * @return iterable<string, array{TagStatus, BatchStatus, bool, bool, DateTimeImmutable|null, TagActivationAvailability}>
	 */
	public static function availability_provider(): iterable {
		$activated = new DateTimeImmutable( '2026-07-29 08:00:00', new DateTimeZone( 'UTC' ) );

		yield 'released and enabled' => array(
			TagStatus::UNREGISTERED,
			BatchStatus::RELEASED,
			true,
			true,
			null,
			TagActivationAvailability::ELIGIBLE,
		);
		yield 'generated awaits release' => array(
			TagStatus::UNREGISTERED,
			BatchStatus::GENERATED,
			false,
			true,
			null,
			TagActivationAvailability::AWAITING_RELEASE,
		);
		yield 'exported awaits release' => array(
			TagStatus::UNREGISTERED,
			BatchStatus::EXPORTED,
			false,
			true,
			null,
			TagActivationAvailability::AWAITING_RELEASE,
		);
		yield 'global pause' => array(
			TagStatus::UNREGISTERED,
			BatchStatus::RELEASED,
			true,
			false,
			null,
			TagActivationAvailability::PAUSED_GLOBALLY,
		);
		yield 'released control disabled' => array(
			TagStatus::UNREGISTERED,
			BatchStatus::RELEASED,
			false,
			true,
			null,
			TagActivationAvailability::BLOCKED_BATCH_CONTROL,
		);
		yield 'batch suspended' => array(
			TagStatus::UNREGISTERED,
			BatchStatus::SUSPENDED,
			false,
			true,
			null,
			TagActivationAvailability::BLOCKED_BATCH_SUSPENDED,
		);
		yield 'batch voided' => array(
			TagStatus::UNREGISTERED,
			BatchStatus::VOIDED,
			false,
			true,
			null,
			TagActivationAvailability::BLOCKED_BATCH_VOIDED,
		);
		yield 'tag suspended wins' => array(
			TagStatus::SUSPENDED,
			BatchStatus::RELEASED,
			true,
			true,
			$activated,
			TagActivationAvailability::BLOCKED_TAG_SUSPENDED,
		);
		yield 'tag retired wins' => array(
			TagStatus::RETIRED,
			BatchStatus::RELEASED,
			true,
			true,
			$activated,
			TagActivationAvailability::BLOCKED_TAG_RETIRED,
		);
		yield 'active owner retained' => array(
			TagStatus::ACTIVE,
			BatchStatus::VOIDED,
			false,
			false,
			$activated,
			TagActivationAvailability::EXISTING_ACTIVATION_RETAINED,
		);
		yield 'active without timestamp is inconsistent' => array(
			TagStatus::ACTIVE,
			BatchStatus::RELEASED,
			true,
			true,
			null,
			TagActivationAvailability::DATA_INCONSISTENT,
		);
		yield 'unregistered with timestamp is inconsistent' => array(
			TagStatus::UNREGISTERED,
			BatchStatus::RELEASED,
			true,
			true,
			$activated,
			TagActivationAvailability::DATA_INCONSISTENT,
		);
	}
}
