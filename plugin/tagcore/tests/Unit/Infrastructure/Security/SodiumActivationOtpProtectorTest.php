<?php
/**
 * Activation OTP cryptography tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Infrastructure\Security\ActivationOtpSecrets;
use ReturnTag\TagCore\Infrastructure\Security\SodiumActivationOtpProtector;
use RuntimeException;

/**
 * Verifies encryption context, keyed lookup, and issued/unissued hash domains.
 */
final class SodiumActivationOtpProtectorTest extends TestCase {
	/**
	 * Sensitive values use encryption and separated keyed domains.
	 */
	public function test_encrypts_email_and_separates_sensitive_hash_domains(): void {
		$protector = $this->protector();
		$email     = new EmailAddress( 'owner@example.test' );
		$tag_id    = TagId::from_canonical( 'A7R2W9' );
		$encrypted = $protector->encrypt_email( $email, $tag_id );

		self::assertStringNotContainsString( $email->value, $encrypted->value );
		self::assertSame( $email->value, $protector->decrypt_email( $encrypted, $tag_id )->value );
		self::assertNotSame(
			$protector->email_lookup( $email )->value,
			$protector->ip_lookup( '192.0.2.4' )->value
		);
		self::assertNotSame(
			$protector->placeholder_hash()->value,
			$protector->hash_code( '123456' )->value
		);
		self::assertStringNotContainsString( '123456', $protector->hash_code( '123456' )->value );
	}

	/**
	 * Issued codes compare only against their keyed adaptive hash.
	 */
	public function test_verifies_only_the_matching_six_digit_code(): void {
		$protector = $this->protector();
		$hash      = $protector->hash_code( '123456' );

		self::assertTrue( $protector->verify_code( '123456', $hash ) );
		self::assertFalse( $protector->verify_code( '654321', $hash ) );
	}

	/**
	 * Associated data prevents cross-Tag envelope reuse.
	 */
	public function test_ciphertext_is_bound_to_the_tag_context(): void {
		$protector = $this->protector();
		$encrypted = $protector->encrypt_email(
			new EmailAddress( 'owner@example.test' ),
			TagId::from_canonical( 'A7R2W9' )
		);

		$this->expectException( RuntimeException::class );
		$protector->decrypt_email( $encrypted, TagId::from_canonical( 'A7R2W8' ) );
	}

	/**
	 * Build a deterministic test-only protector.
	 */
	private function protector(): SodiumActivationOtpProtector {
		return new SodiumActivationOtpProtector(
			ActivationOtpSecrets::from_keys(
				str_repeat( 'e', 32 ),
				str_repeat( 'l', 32 ),
				str_repeat( 'p', 32 )
			)
		);
	}
}
