<?php
/**
 * Owner Account OTP protection tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Infrastructure\Security\ActivationOtpSecrets;
use ReturnTag\TagCore\Infrastructure\Security\SodiumAccountOtpProtector;
use ReturnTag\TagCore\Infrastructure\Security\SodiumActivationOtpProtector;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Verifies Account cryptographic domains cannot be confused with activation.
 */
final class SodiumAccountOtpProtectorTest extends TestCase {
	/** Account ciphertext and code hashes remain purpose-separated. */
	public function test_account_domain_does_not_accept_activation_context(): void {
		$secrets    = ActivationOtpSecrets::from_keys(
			str_repeat( 'e', 32 ),
			str_repeat( 'l', 32 ),
			str_repeat( 'p', 32 )
		);
		$account    = new SodiumAccountOtpProtector( $secrets );
		$activation = new SodiumActivationOtpProtector( $secrets );
		$email      = new EmailAddress( 'owner@example.test' );
		$subject    = $account->email_lookup( $email );
		$ciphertext = $account->encrypt_email( $email, $subject );

		self::assertSame( $email->value, $account->decrypt_email( $ciphertext, $subject )->value );
		self::assertNotSame( $subject->value, $activation->email_lookup( $email )->value );
		self::assertFalse( $activation->verify_code( '123456', $account->hash_code( '123456' ) ) );

		$this->expectException( \RuntimeException::class );
		$activation->decrypt_email( $ciphertext, TagId::from_canonical( 'A7R2W9' ) );
	}
}
