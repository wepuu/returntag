<?php
/**
 * Administrator Finder Report decision outcome.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

/** Privacy-safe committed or unavailable result. */
final readonly class AdminFinderReportDecisionResult {
	/**
	 * Create one result.
	 *
	 * @param bool                        $changed Whether the transaction committed.
	 * @param AdminFinderReportState|null $state Committed state when changed.
	 */
	private function __construct( public bool $changed, public ?AdminFinderReportState $state = null ) {
	}

	/**
	 * Create a committed result.
	 *
	 * @param AdminFinderReportState $state Committed state.
	 */
	public static function changed( AdminFinderReportState $state ): self {
		return new self( true, $state );
	}

	/** Create a fail-closed unavailable result. */
	public static function unavailable(): self {
		return new self( false );
	}
}
