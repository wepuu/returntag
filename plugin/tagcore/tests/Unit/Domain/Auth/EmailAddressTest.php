<?php
/**
 * Email address value tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Domain\Auth;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;

/**
 * Guards the bounded canonical activation identity.
 */
final class EmailAddressTest extends TestCase {
	/**
	 * ASCII email input is trimmed and lowercased.
	 */
	public function test_normalizes_a_valid_ascii_address(): void {
		self::assertSame( 'owner@example.test', ( new EmailAddress( ' Owner@Example.Test ' ) )->value );
	}

	/**
	 * Invalid email input fails closed.
	 *
	 * @param string $value Invalid input.
	 * @dataProvider invalid_addresses
	 */
	public function test_rejects_invalid_or_unbounded_input( string $value ): void {
		$this->expectException( InvalidArgumentException::class );
		new EmailAddress( $value );
	}

	/**
	 * Supply invalid email cases.
	 *
	 * @return iterable<string, array{string}>
	 */
	public function invalid_addresses(): iterable {
		yield 'empty' => array( '' );
		yield 'no-domain' => array( 'owner' );
		yield 'unicode' => array( 'ownér@example.test' );
		yield 'too-long' => array( str_repeat( 'a', 245 ) . '@example.test' );
	}
}
