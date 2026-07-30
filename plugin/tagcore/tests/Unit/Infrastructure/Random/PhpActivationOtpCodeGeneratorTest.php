<?php
/**
 * Activation OTP generator tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Random;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Infrastructure\Random\PhpActivationOtpCodeGenerator;

/**
 * Guards the exact PRD OTP shape.
 */
final class PhpActivationOtpCodeGeneratorTest extends TestCase {
	/**
	 * Generated codes always match the exact PRD format.
	 */
	public function test_generates_exactly_six_digits(): void {
		$generator = new PhpActivationOtpCodeGenerator();

		for ( $index = 0; $index < 100; ++$index ) {
			self::assertMatchesRegularExpression( '/^\d{6}$/D', $generator->generate() );
		}
	}
}
