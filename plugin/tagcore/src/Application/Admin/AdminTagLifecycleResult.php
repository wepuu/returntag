<?php
/**
 * Administrator Tag lifecycle outcomes.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

/** Privacy-safe use-case result. */
final readonly class AdminTagLifecycleResult {
	/**
	 * Create one result.
	 *
	 * @param bool                        $changed Whether the transaction committed.
	 * @param AdminTagLifecycleState|null $state Committed state when changed.
	 */
	private function __construct( public bool $changed, public ?AdminTagLifecycleState $state = null ) {
	}

	/**
	 * Create a committed result.
	 *
	 * @param AdminTagLifecycleState $state Committed Tag state.
	 */
	public static function changed( AdminTagLifecycleState $state ): self {
		return new self( true, $state );
	}

	/** Create a privacy-safe unavailable result. */
	public static function unavailable(): self {
		return new self( false );
	}
}
