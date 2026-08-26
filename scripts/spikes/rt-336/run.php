<?php
/**
 * RT-336 staging-only Resend provider-ID correlation probe.
 *
 * Run with:
 * RETURNTAG_RT336_TEST_RECIPIENT='<synthetic inbox>' wp eval-file scripts/spikes/rt-336/run.php
 *
 * @package ReturnTag\Spike\Rt336
 */

declare(strict_types=1);

use ReturnTag\Spike\Rt336\ResendCorrelationProbe;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'RT-336 probe requires WP-CLI.' );
}

require_once __DIR__ . '/class-resendcorrelationprobe.php';

$recipient = getenv( 'RETURNTAG_RT336_TEST_RECIPIENT' );
if ( ! is_string( $recipient ) || ! is_email( $recipient ) ) {
	WP_CLI::error( 'Set RETURNTAG_RT336_TEST_RECIPIENT to an approved synthetic staging inbox.' );
}

$probe    = new ResendCorrelationProbe();
$capture  = array( $probe, 'capture' );
$probe_id = bin2hex( random_bytes( 8 ) );

add_action( 'wp_mail_smtp_mailcatcher_send_after', $capture, 10, 2 );

try {
	$accepted = wp_mail(
		$recipient,
		'[RT-336] Resend correlation probe',
		'Synthetic staging probe. Correlation nonce: ' . $probe_id,
		array( 'Content-Type: text/plain; charset=UTF-8' )
	);
} finally {
	remove_action( 'wp_mail_smtp_mailcatcher_send_after', $capture, 10 );
}

$result = $probe->result( $accepted );
WP_CLI::line( (string) wp_json_encode( $result, JSON_UNESCAPED_SLASHES ) );

if ( 'correlated' !== $result['status'] ) {
	WP_CLI::halt( 1 );
}
