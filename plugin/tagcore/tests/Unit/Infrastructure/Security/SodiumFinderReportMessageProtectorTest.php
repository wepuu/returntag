<?php
/**
 * Finder Report message encryption tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Infrastructure\Security\FinderReportMessageSecrets;
use ReturnTag\TagCore\Infrastructure\Security\SodiumFinderReportMessageProtector;
use RuntimeException;

/** Verifies authenticated encryption is bound to the intended Tag. */
final class SodiumFinderReportMessageProtectorTest extends TestCase {
	/** Round-trip keeps plaintext out of the stored envelope. */
	public function test_encrypts_and_decrypts_for_the_same_tag(): void {
		$protector  = $this->protector();
		$tag_id     = TagId::from_canonical( '234567' );
		$message    = 'The item is waiting at airport security.';
		$ciphertext = $protector->encrypt( $message, $tag_id );

		self::assertStringNotContainsString( $message, $ciphertext->value );
		self::assertSame( $message, $protector->decrypt( $ciphertext, $tag_id ) );
	}

	/** Associated data prevents a message being moved to another Tag. */
	public function test_rejects_a_different_tag(): void {
		$protector = $this->protector();
		$encrypted = $protector->encrypt(
			'The item is waiting at airport security.',
			TagId::from_canonical( '234567' )
		);

		$this->expectException( RuntimeException::class );
		$protector->decrypt( $encrypted, TagId::from_canonical( '234568' ) );
	}

	/** Build deterministic-size test key material without persisting a secret. */
	private function protector(): SodiumFinderReportMessageProtector {
		return new SodiumFinderReportMessageProtector(
			FinderReportMessageSecrets::from_key( str_repeat( "\x2a", SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES ) )
		);
	}
}
