<?php
/**
 * Fixed retention policies exposed to operators.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use ReturnTag\TagCore\Account\AccountBootstrap;
use ReturnTag\TagCore\Infrastructure\Queue\AuthChallengeRetentionBootstrap;
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
			'auth-challenges' => array(
				'name'        => 'Authentication challenge cleanup',
				'description' => 'Purpose-independent cleanup for expired or consumed Activation, Account, and Finder email challenges.',
				'policy'      => 'Expired or consumed challenges are immediately eligible and removed in bounded hourly batches.',
				'hook'        => AuthChallengeRetentionBootstrap::CLEANUP_HOOK,
				'group'       => AuthChallengeRetentionBootstrap::CLEANUP_GROUP,
			),
			'activation-otp'  => array(
				'name'        => 'Activation temporary-state cleanup',
				'description' => 'Activation request, verification, mutation, and manual-entry rate-limit cleanup.',
				'policy'      => 'Expired temporary security state is removed by bounded hourly maintenance.',
				'hook'        => ActivationOtpBootstrap::CLEANUP_HOOK,
				'group'       => ActivationOtpBootstrap::CLEANUP_GROUP,
			),
			'account-otp'     => array(
				'name'        => 'Account temporary-state cleanup',
				'description' => 'Account request limits, Owner action limits, and test-email claim cleanup.',
				'policy'      => 'Expired temporary security state is removed by bounded hourly maintenance.',
				'hook'        => AccountBootstrap::CLEANUP_HOOK,
				'group'       => AccountBootstrap::CLEANUP_GROUP,
			),
			'finder-email'    => array(
				'name'        => 'Finder temporary-state cleanup',
				'description' => 'Finder email rate-limit cleanup; verification challenges use the unified challenge task.',
				'policy'      => 'Expired temporary security state is removed by bounded hourly maintenance.',
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
