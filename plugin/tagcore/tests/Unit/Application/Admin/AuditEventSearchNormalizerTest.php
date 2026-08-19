<?php
/**
 * RT-329 Audit Log normalization coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Admin;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Admin\AuditEventSearchNormalizer;

/** Verifies default window, exact filters, and 31-day boundary. */
final class AuditEventSearchNormalizerTest extends TestCase {
	/** Return one stable UTC clock fixture. */
	private function now(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-08-18 12:00:00', new DateTimeZone( 'UTC' ) );
	}

	/** Empty filters default to the most recent 24 hours. */
	public function test_defaults_to_recent_twenty_four_hours(): void {
		$criteria = ( new AuditEventSearchNormalizer() )->normalize( array(), $this->now() );
		self::assertSame( '2026-08-17 12:00:00', $criteria['from'] );
		self::assertSame( '2026-08-18 12:00:00', $criteria['to'] );
	}

	/** Exact allowlisted filters normalize without broad matching. */
	public function test_accepts_exact_allowlisted_filter_combination(): void {
		$criteria = ( new AuditEventSearchNormalizer() )->normalize(
			array(
				'actor_user_id' => '17',
				'target_type'   => 'tag',
				'target_id'     => '234567',
				'event_type'    => 'tag_suspended',
				'result'        => 'success',
			),
			$this->now()
		);
		self::assertSame( 17, $criteria['actor_user_id'] );
		self::assertSame( 'tag', $criteria['target_type'] );
		self::assertSame( '234567', $criteria['target_id'] );
	}

	/** Search windows cannot exceed 31 days. */
	public function test_rejects_window_longer_than_thirty_one_days(): void {
		$this->expectException( InvalidArgumentException::class );
		( new AuditEventSearchNormalizer() )->normalize(
			array(
				'from' => '2026-07-01T00:00:00Z',
				'to'   => '2026-08-18T00:00:00Z',
			),
			$this->now()
		);
	}

	/** A Target ID cannot be supplied without an allowlisted Target type. */
	public function test_rejects_target_identifier_without_allowlisted_target(): void {
		$this->expectException( InvalidArgumentException::class );
		( new AuditEventSearchNormalizer() )->normalize( array( 'target_id' => '17' ), $this->now() );
	}

	/** Numeric targets reject broad or malformed identifiers. */
	public function test_rejects_non_numeric_identifier_for_numeric_target(): void {
		$this->expectException( InvalidArgumentException::class );
		( new AuditEventSearchNormalizer() )->normalize(
			array(
				'target_type' => 'user',
				'target_id'   => 'all-users',
			),
			$this->now()
		);
	}

	/** Tag targets accept only the canonical six-character alphabet. */
	public function test_rejects_noncanonical_tag_identifier(): void {
		$this->expectException( InvalidArgumentException::class );
		( new AuditEventSearchNormalizer() )->normalize(
			array(
				'target_type' => 'tag',
				'target_id'   => 'A1BCDE',
			),
			$this->now()
		);
	}
}
