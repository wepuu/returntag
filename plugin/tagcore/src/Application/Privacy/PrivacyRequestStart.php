<?php
/**
 * Idempotent privacy request start result.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Privacy;

/** Distinguishes one new request from an existing unfinished request. */
final readonly class PrivacyRequestStart {
	/**
	 * Create one idempotent start result.
	 *
	 * @param PrivacyRequestRecord $request Current persisted request.
	 * @param bool                 $created Whether this call created the request.
	 */
	public function __construct( public PrivacyRequestRecord $request, public bool $created ) {}
}
