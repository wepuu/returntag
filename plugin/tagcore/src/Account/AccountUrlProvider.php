<?php
/**
 * Same-site Owner Account URLs.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

use ReturnTag\TagCore\Application\Persistence\Pagination\TagCursor;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Generates Account URLs without assuming the WordPress installation path.
 */
final class AccountUrlProvider {
	/** Return the canonical Account sign-in URL. */
	public function sign_in(): string {
		return home_url( '/account/sign-in/' );
	}

	/**
	 * Return the Account overview URL with an optional stable cursor.
	 *
	 * @param TagCursor|null $cursor Optional stable cursor.
	 */
	public function overview( ?TagCursor $cursor = null ): string {
		$url = home_url( '/account/' );

		return null === $cursor
			? $url
			: add_query_arg(
				array(
					'after_status' => $cursor->tag_status->value,
					'after_tag'    => $cursor->tag_id,
				),
				$url
			);
	}

	/**
	 * Return one canonical Account Tag detail URL.
	 *
	 * @param TagId $tag_id Canonical public Tag ID.
	 */
	public function tag( TagId $tag_id ): string {
		return home_url( '/account/tags/' . rawurlencode( $tag_id->value ) . '/' );
	}

	/** Return the current-Owner Conversation summary URL. */
	public function conversations(): string {
		return home_url( '/account/conversations/' );
	}

	/** Return the canonical Secure Reply URL without a bearer query. */
	public function secure_reply(): string {
		return home_url( '/secure-reply/' );
	}
}
