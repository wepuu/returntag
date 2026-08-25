<?php
/**
 * Tests for the RT-336 staging-only correlation probe.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Spike;

use PHPUnit\Framework\TestCase;
use ReturnTag\Spike\Rt336\ResendCorrelationProbe;
use stdClass;

require_once dirname( __DIR__, 5 ) . '/scripts/spikes/rt-336/class-resendcorrelationprobe.php';

/**
 * Verifies the staging probe's decision and privacy behavior.
 */
final class ResendCorrelationProbeTest extends TestCase {

	/** Verifies that a single valid provider ID produces hashed evidence. */
	public function test_reports_a_privacy_safe_correlation(): void {
		$probe = new ResendCorrelationProbe();
		$probe->capture( $this->mailer( 'resend' ), $this->mailcatcher( array( array( 'X-Msg-ID', 'provider-123' ) ) ) );

		$result = $probe->result( true );

		self::assertSame( 'correlated', $result['status'] );
		self::assertSame( 12, $result['provider_id_length'] );
		self::assertSame( hash( 'sha256', 'provider-123' ), $result['provider_id_sha256'] );
		self::assertFalse( in_array( 'provider-123', array_values( $result ), true ) );
	}

	/**
	 * Verifies fail-closed results for non-correlation paths.
	 *
	 * @dataProvider failure_case_provider
	 *
	 * @param bool                           $accepted    Expected wp_mail() acceptance.
	 * @param object|null                    $mailer      Mailer fixture.
	 * @param object|null                    $mailcatcher Mailcatcher fixture.
	 * @param string                         $expected    Expected status.
	 * @param array<array{0:string,1:mixed}> $headers Custom header fixture.
	 */
	public function test_reports_non_correlation_without_identifier_disclosure(
		bool $accepted,
		?object $mailer,
		?object $mailcatcher,
		string $expected,
		array $headers = array()
	): void {
		$probe = new ResendCorrelationProbe();

		if ( null !== $mailer && null !== $mailcatcher ) {
			$probe->capture( $mailer, $mailcatcher );
		} elseif ( null !== $mailer ) {
			$probe->capture( $mailer, $this->mailcatcher( $headers ) );
		}

		$result = $probe->result( $accepted );

		self::assertSame( $expected, $result['status'] );
		self::assertNull( $result['provider_id_sha256'] );
		self::assertNull( $result['provider_id_length'] );
	}

	/**
	 * Supplies non-correlation cases.
	 *
	 * @return array<string, array{bool, object|null, object|null, string, array<array{0:string,1:mixed}>}>
	 */
	public function failure_case_provider(): array {
		return array(
			'send failed'  => array( false, null, null, 'send_failed', array() ),
			'hook absent'  => array( true, null, null, 'hook_not_observed', array() ),
			'wrong mailer' => array( true, $this->mailer( 'smtp' ), null, 'unexpected_mailer', array() ),
			'missing id'   => array( true, $this->mailer( 'resend' ), null, 'provider_id_missing', array() ),
			'invalid id'   => array( true, $this->mailer( 'resend' ), null, 'invalid_provider_id', array( array( 'X-Msg-ID', "bad\nvalue" ) ) ),
		);
	}

	/** Verifies that multiple IDs cannot be treated as a stable correlation. */
	public function test_rejects_ambiguous_provider_identifiers(): void {
		$probe = new ResendCorrelationProbe();
		$probe->capture(
			$this->mailer( 'resend' ),
			$this->mailcatcher(
				array(
					array( 'X-Msg-ID', 'provider-123' ),
					array( 'x-msg-id', 'provider-456' ),
				)
			)
		);

		$result = $probe->result( true );

		self::assertSame( 'provider_id_ambiguous', $result['status'] );
		self::assertNull( $result['provider_id_sha256'] );
	}

	/**
	 * Build a mailer fixture.
	 *
	 * @param string $name Provider name.
	 */
	private function mailer( string $name ): object {
		$mailer = $this->getMockBuilder( stdClass::class )
			->addMethods( array( 'get_mailer_name' ) )
			->getMock();
		$mailer->method( 'get_mailer_name' )->willReturn( $name );

		return $mailer;
	}

	/**
	 * Build a mailcatcher fixture.
	 *
	 * @param array<array{0:string,1:mixed}> $headers Custom headers.
	 */
	private function mailcatcher( array $headers ): object {
		$mailcatcher = $this->getMockBuilder( stdClass::class )
			->addMethods( array( 'getCustomHeaders' ) ) // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Upstream PHPMailer API.
			->getMock();
		$mailcatcher->method( 'getCustomHeaders' )->willReturn( $headers );

		return $mailcatcher;
	}
}
