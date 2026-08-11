<?php
/**
 * Transfer invitation cookie boundary.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Account;

/** Moves a bearer off the URL without consuming it. */
final class AccountTransferTokenCookie {
	public const NAME = 'returntag_transfer_token';

	/**
	 * Capture one structurally valid invitation Token.
	 *
	 * @param string $token Raw invitation Token.
	 */
	public function capture( string $token ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9_-]{43}$/D', $token ) && setcookie(
			self::NAME,
			$token,
			array(
				'expires'  => time() + DAY_IN_SECONDS,
				'path'     => $this->path(),
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Strict',
			)
		);
	}

	/** Read one structurally valid captured Token. */
	public function read(): ?string {
		$value = $_COOKIE[ self::NAME ] ?? null;
		return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_-]{43}$/D', $value ) ? $value : null;
	}

	/** Expire the captured Token after acceptance. */
	public function clear(): void {
		setcookie(
			self::NAME,
			'',
			array(
				'expires'  => time() - 3600,
				'path'     => $this->path(),
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Strict',
			)
		);
	}

	/** Resolve the cookie path for root and subdirectory WordPress installs. */
	private function path(): string {
		$path = wp_parse_url( home_url( '/account/' ), PHP_URL_PATH );
		return is_string( $path ) && '' !== $path ? $path : '/';
	}
}
