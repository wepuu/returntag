<?php
/**
 * Administrator Tag lifecycle state.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

use ReturnTag\TagCore\Domain\Tag\TagStatus;

/** One privacy-safe before or committed Tag state. */
final readonly class AdminTagLifecycleState {
	/**
	 * Create one state snapshot.
	 *
	 * @param TagStatus $status Canonical Tag status.
	 * @param int|null  $owner_id Nullable WordPress Owner User ID.
	 */
	public function __construct( public TagStatus $status, public ?int $owner_id ) {
	}
}
