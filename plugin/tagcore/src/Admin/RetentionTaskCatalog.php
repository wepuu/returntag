<?php
/**
 * Fixed retention policies exposed to operators.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use ReturnTag\TagCore\Account\AccountBootstrap;
use ReturnTag\TagCore\Infrastructure\Queue\ActivationOtpBootstrap;
use ReturnTag\TagCore\Infrastructure\Queue\FinderReportActionHandler;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerFinderReportProcessingScheduler;
use ReturnTag\TagCore\Infrastructure\Queue\FinderReportBootstrap;

/** Provides immutable task identifiers, hooks, and policy copy. */
final class RetentionTaskCatalog {
	/**
	 * Return the immutable retention task catalog.
	 *
	 * @return array<string, array{name: string, description: string, policy: string, hook: non-empty-string, group: string}>
	 */
	public function tasks(): array {
		return array(
			'activation-otp'  => array(
				'name'        => 'Activation security cleanup',
				'description' => 'Activation OTP, verification, and related rate-limit cleanup.',
				'policy'      => 'Expired challenges are removed in bounded batches after the fixed security window.',
				'hook'        => ActivationOtpBootstrap::CLEANUP_HOOK,
				'group'       => ActivationOtpBootstrap::CLEANUP_GROUP,
			),
			'account-otp'     => array(
				'name'        => 'Account security cleanup',
				'description' => 'Account OTP, Owner action limits, and test-email claim cleanup.',
				'policy'      => 'Expired account security records are removed in bounded batches after the fixed security window.',
				'hook'        => AccountBootstrap::CLEANUP_HOOK,
				'group'       => AccountBootstrap::CLEANUP_GROUP,
			),
			'finder-email'    => array(
				'name'        => 'Finder email cleanup',
				'description' => 'Finder email verification and rate-limit cleanup.',
				'policy'      => 'Expired verification and limiter records follow the fixed Finder privacy window.',
				'hook'        => FinderReportBootstrap::EMAIL_RATE_CLEANUP_HOOK,
				'group'       => FinderReportBootstrap::EMAIL_RATE_CLEANUP_GROUP,
			),
			'finder-evidence' => array(
				'name'        => 'Finder evidence cleanup',
				'description' => 'Finder evidence processing, Hold-aware retention, and deletion.',
				'policy'      => 'Only expired derivatives without an Active Hold are eligible; original business records remain.',
				'hook'        => FinderReportActionHandler::CLEANUP_HOOK,
				'group'       => ActionSchedulerFinderReportProcessingScheduler::GROUP,
			),
		);
	}
}
