<?php
/**
 * Provider-neutral transactional email outcome.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Email;

/** Distinguishes provider acceptance from confirmed delivery. */
final readonly class TransactionalEmailResult {
	/**
	 * Create a bounded outcome.
	 *
	 * @param bool        $accepted Whether the provider accepted the request.
	 * @param string|null $provider_message_id Optional provider identifier.
	 */
	private function __construct( public bool $accepted, public ?string $provider_message_id ) {}

	/**
	 * Return a provider-accepted result.
	 *
	 * @param string $provider_message_id Provider response identifier.
	 */
	public static function accepted( string $provider_message_id ): self {
		return new self( true, $provider_message_id );
	}

	/** Return a fail-closed result. */
	public static function failed(): self {
		return new self( false, null );
	}
}
