<?php
/**
 * Activation OTP code tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Domain\Auth;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Domain\Auth\ActivationOtpCode;

/**
 * Verifies the exact public OTP input contract.
 */
final class ActivationOtpCodeTest extends TestCase {
	/**
	 * Exactly six ASCII digits are accepted.
	 */
	public function test_accepts_six_ascii_digits(): void {
		self::assertSame( '012345', ( new ActivationOtpCode( '012345' ) )->value );
	}

	/**
	 * Reject one invalid fixture.
	 *
	 * @dataProvider invalid_codes
	 *
	 * @param string $value Invalid code.
	 */
	public function test_rejects_every_other_shape( string $value ): void {
		$this->expectException( InvalidArgumentException::class );
		new ActivationOtpCode( $value );
	}

	/**
	 * Invalid code fixtures.
	 *
	 * @return array<string, array{string}>
	 */
	public static function invalid_codes(): array {
		return array(
			'too short'      => array( '12345' ),
			'too long'       => array( '1234567' ),
			'letters'        => array( '12A456' ),
			'whitespace'     => array( ' 123456' ),
			'unicode digits' => array( '１２３４５６' ),
			'empty'          => array( '' ),
		);
	}
}
