<?php
/**
 * Durable anonymous Finder Report idempotency ledger.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use ReturnTag\TagCore\Domain\Tag\TagId;

/** Issues and atomically claims signed, short-lived form tokens. */
interface FinderReportSubmissionLedger {
	/**
	 * Issue a token bound to one public Tag.
	 *
	 * @param TagId $tag_id Server-resolved Tag.
	 */
	public function issue( TagId $tag_id ): string;

	/**
	 * Claim a token once and distinguish a completed replay.
	 *
	 * @param TagId  $tag_id Server-resolved Tag.
	 * @param string $token Signed token.
	 */
	public function claim( TagId $tag_id, string $token ): FinderReportSubmissionClaim;

	/**
	 * Mark a successfully persisted claim complete.
	 *
	 * @param TagId    $tag_id Server-resolved Tag.
	 * @param string   $token Signed token.
	 * @param int|null $finder_report_id Internal persisted report identifier.
	 */
	public function complete( TagId $tag_id, string $token, ?int $finder_report_id = null ): void;

	/**
	 * Resolve the internal report only from a completed, unexpired claim.
	 *
	 * @param TagId  $tag_id Server-resolved Tag.
	 * @param string $token Signed token.
	 */
	public function resolve_report_id( TagId $tag_id, string $token ): ?int;

	/**
	 * Release a failed claim so a corrected submission can be retried.
	 *
	 * @param TagId  $tag_id Server-resolved Tag.
	 * @param string $token Signed token.
	 */
	public function release( TagId $tag_id, string $token ): void;
}
