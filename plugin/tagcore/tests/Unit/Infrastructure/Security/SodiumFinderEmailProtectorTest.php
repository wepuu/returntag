<?php
/**
 * Finder email verification cryptography tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Infrastructure\Security\FinderEmailVerificationSecrets;
use ReturnTag\TagCore\Infrastructure\Security\SodiumFinderEmailProtector;
use RuntimeException;

/** Verifies report binding and domain-separated Finder identity protection. */
final class SodiumFinderEmailProtectorTest extends TestCase {
	/** Email ciphertext never exposes plaintext and is report-bound. */
	public function test_encrypts_email_and_binds_it_to_the_report(): void {
		$protector = $this->protector();
		$email     = new EmailAddress( 'finder@example.test' );
		$encrypted = $protector->encrypt_email( $email, 41 );

		self::assertStringNotContainsString( $email->value, $encrypted->value );
		self::assertSame( $email->value, $protector->decrypt_email( $encrypted, 41 )->value );

		$this->expectException( RuntimeException::class );
		$protector->decrypt_email( $encrypted, 42 );
	}

	/** OTP and lookup domains remain separated and codes are one-way. */
	public function test_separates_lookup_and_otp_domains(): void {
		$protector = $this->protector();
		$email     = new EmailAddress( 'finder@example.test' );
		$hash      = $protector->hash_code( '123456' );

		self::assertNotSame( $protector->email_lookup( $email )->value, $protector->ip_lookup( '192.0.2.8' )->value );
		self::assertStringNotContainsString( '123456', $hash->value );
		self::assertTrue( $protector->verify_code( '123456', $hash ) );
		self::assertFalse( $protector->verify_code( '654321', $hash ) );
	}

	/** Build a deterministic test-only protector. */
	private function protector(): SodiumFinderEmailProtector {
		return new SodiumFinderEmailProtector(
			FinderEmailVerificationSecrets::from_keys(
				str_repeat( 'f', 32 ),
				str_repeat( 'l', 32 ),
				str_repeat( 'p', 32 )
			)
		);
	}
}
