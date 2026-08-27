<?php
/**
 * RT-329 fixed retention task coverage.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Account\AccountBootstrap;
use ReturnTag\TagCore\Admin\RetentionTaskCatalog;
use ReturnTag\TagCore\Infrastructure\Queue\AuthChallengeRetentionBootstrap;
use ReturnTag\TagCore\Infrastructure\Queue\ActivationOtpBootstrap;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerFinderReportProcessingScheduler;
use ReturnTag\TagCore\Infrastructure\Queue\FinderReportActionHandler;
use ReturnTag\TagCore\Infrastructure\Queue\FinderReportBootstrap;

/** Verifies immutable policy-to-existing-hook mappings. */
final class RetentionTaskCatalogTest extends TestCase {
	/** The console exposes the unified challenge task and four bounded maintenance hooks. */
	public function test_maps_fixed_tasks_to_existing_cleanup_hooks(): void {
		$tasks = ( new RetentionTaskCatalog() )->tasks();

		self::assertSame( array( 'auth-challenges', 'activation-otp', 'account-otp', 'finder-email', 'finder-evidence' ), array_keys( $tasks ) );
		self::assertSame( AuthChallengeRetentionBootstrap::CLEANUP_HOOK, $tasks['auth-challenges']['hook'] );
		self::assertSame( AuthChallengeRetentionBootstrap::CLEANUP_GROUP, $tasks['auth-challenges']['group'] );
		self::assertSame( ActivationOtpBootstrap::CLEANUP_HOOK, $tasks['activation-otp']['hook'] );
		self::assertSame( AccountBootstrap::CLEANUP_HOOK, $tasks['account-otp']['hook'] );
		self::assertSame( FinderReportBootstrap::EMAIL_RATE_CLEANUP_HOOK, $tasks['finder-email']['hook'] );
		self::assertSame( FinderReportActionHandler::CLEANUP_HOOK, $tasks['finder-evidence']['hook'] );
		self::assertSame( ActionSchedulerFinderReportProcessingScheduler::GROUP, $tasks['finder-evidence']['group'] );
	}
}
