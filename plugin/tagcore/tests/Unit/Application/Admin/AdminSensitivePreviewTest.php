<?php
/**
 * RT-326 sensitive preview policy tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application\Admin;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\Admin\AdminSensitivePreview;
use ReturnTag\TagCore\Application\Admin\SensitivePreviewAudit;
use ReturnTag\TagCore\Application\Clock;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\FinderReport\FinderReportMessageProtector;
use ReturnTag\TagCore\Application\FinderReport\PrivateMediaStorage;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportMediaRepository;
use ReturnTag\TagCore\Application\Persistence\Repository\FinderReportRepository;
use ReturnTag\TagCore\Application\Persistence\Record\FinderReportRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewFinderReportRecord;
use ReturnTag\TagCore\Application\Persistence\Value\FinderReportMessageCiphertext;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceStatus;
use ReturnTag\TagCore\Domain\FinderReport\FinderReportStatus;
use RuntimeException;

/** Verifies the independent operational kill switch fails closed. */
final class AdminSensitivePreviewTest extends TestCase {
	/** Disabled preview must stop before any persistence access. */
	public function test_disabled_preview_reads_nothing_and_writes_no_audit_event(): void {
		$flags = $this->createMock( FeatureFlagReader::class );
		$flags->expects( self::once() )->method( 'is_enabled' )->with( FeatureFlag::ADMIN_SENSITIVE_PREVIEW )->willReturn( false );
		$reports = $this->createMock( FinderReportRepository::class );
		$reports->expects( self::never() )->method( 'find_by_id' );
		$audit = $this->createMock( SensitivePreviewAudit::class );
		$audit->expects( self::never() )->method( 'record' );

		$preview = new AdminSensitivePreview(
			$flags,
			$reports,
			$this->createMock( FinderReportMediaRepository::class ),
			$this->createMock( FinderReportMessageProtector::class ),
			$this->createMock( PrivateMediaStorage::class ),
			$audit,
			$this->createMock( Clock::class )
		);

		$this->expectException( RuntimeException::class );
		$preview->reveal_message( 1, 2 );
	}

	/** A blocked report cannot reveal content or create a successful-view event. */
	public function test_blocked_report_fails_before_decryption_and_audit(): void {
		$now   = new DateTimeImmutable( '2026-08-13 08:00:00', new DateTimeZone( 'UTC' ) );
		$flags = $this->createMock( FeatureFlagReader::class );
		$flags->method( 'is_enabled' )->willReturn( true );
		$reports = $this->createMock( FinderReportRepository::class );
		$reports->method( 'find_by_id' )->willReturn(
			new FinderReportRecord(
				1,
				new NewFinderReportRecord(
					'234567',
					3,
					FinderReportMessageCiphertext::from_encrypted_bytes( 'ciphertext' ),
					FinderReportStatus::BLOCKED,
					FinderEvidenceStatus::READY,
					null,
					null,
					$now->modify( '+1 day' ),
					$now->modify( '-1 day' ),
					$now
				)
			)
		);
		$messages = $this->createMock( FinderReportMessageProtector::class );
		$messages->expects( self::never() )->method( 'decrypt' );
		$audit = $this->createMock( SensitivePreviewAudit::class );
		$audit->expects( self::never() )->method( 'record' );
		$clock = $this->createMock( Clock::class );
		$clock->method( 'now' )->willReturn( $now );

		$preview = new AdminSensitivePreview(
			$flags,
			$reports,
			$this->createMock( FinderReportMediaRepository::class ),
			$messages,
			$this->createMock( PrivateMediaStorage::class ),
			$audit,
			$clock
		);

		$this->expectException( RuntimeException::class );
		$preview->reveal_message( 1, 2 );
	}
}
