<?php
/**
 * Administrator Tag lifecycle persistence port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

use DateTimeImmutable;
use ReturnTag\TagCore\Domain\Tag\TagId;

interface AdminTagLifecycleStore {
	/**
	 * Execute one conditional, fully audited lifecycle transaction.
	 *
	 * @param TagId                   $tag_id Canonical Tag ID.
	 * @param AdminTagLifecycleAction $action Administrator action.
	 * @param AdminTagLifecycleState  $expected Submitted current-state snapshot.
	 * @param int|null                $target_user_id Optional target User ID.
	 * @param int                     $operator_id Operator WordPress User ID.
	 * @param DateTimeImmutable       $now Current UTC time.
	 */
	public function change(
		TagId $tag_id,
		AdminTagLifecycleAction $action,
		AdminTagLifecycleState $expected,
		?int $target_user_id,
		int $operator_id,
		DateTimeImmutable $now
	): AdminTagLifecycleResult;
}
