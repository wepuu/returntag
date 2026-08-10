<?php
/**
 * WordPress Finder Report Owner email adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\FinderReport\FinderReportOwnerNotificationEmail;
use ReturnTag\TagCore\Application\FinderReport\FinderReportOwnerNotificationSender;
use Throwable;

/** Sends one HTML/text notification with a local inline CID JPEG. */
final class WordPressFinderReportOwnerNotificationSender implements FinderReportOwnerNotificationSender {
	private const EVIDENCE_CID = 'returntag-finder-evidence@returntag.invalid';

	/**
	 * Submit one privacy-minimized Owner alert through WordPress.
	 *
	 * @param FinderReportOwnerNotificationEmail $email Send-ready notification.
	 */
	public function send( FinderReportOwnerNotificationEmail $email ): bool {
		$subject    = __( 'A finder submitted a report about your ForgeTag', 'tagcore' );
		$html       = $this->html_body( $email->message );
		$text       = $this->text_body( $email->message );
		$configured = false;
		$embedder   = static function ( mixed $mailer ) use ( $email, $text, &$configured ): void {
			if (
				! $mailer instanceof \PHPMailer\PHPMailer\PHPMailer
			) {
				return;
			}

			$mailer->clearReplyTos();
			$mailer->clearCCs();
			$mailer->clearBCCs();
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer public API.
			$mailer->AltBody = $text;
			$configured      = (bool) $mailer->addStringEmbeddedImage(
				$email->evidence_jpeg,
				self::EVIDENCE_CID,
				'evidence.jpg',
				'base64',
				'image/jpeg',
				'inline'
			);
		};

		add_action( 'phpmailer_init', $embedder );

		try {
			$accepted = wp_mail(
				$email->recipient->value,
				$subject,
				$html,
				array( 'Content-Type: text/html; charset=UTF-8' )
			);
		} catch ( Throwable ) {
			$accepted = false;
		} finally {
			remove_action( 'phpmailer_init', $embedder );
		}

		return $configured && $accepted;
	}

	/**
	 * Build the ForgeTag-styled HTML body without links or private identifiers.
	 *
	 * @param string|null $message Optional Finder message.
	 */
	private function html_body( ?string $message ): string {
		$message_section = '';

		if ( null !== $message ) {
			$message_section = sprintf(
				'<div style="margin:24px 0;padding:18px 20px;background:#f4f5f7;border-radius:12px"><p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#111827">%s</p><p style="margin:0;color:#374151;line-height:1.6">&ldquo;%s&rdquo;</p></div>',
				esc_html__( 'Message for you', 'tagcore' ),
				nl2br( esc_html( $message ) )
			);
		}

		return sprintf(
			'<!doctype html><html><body style="margin:0;background:#f4f5f7;color:#111827;font-family:Arial,sans-serif"><div style="display:none;max-height:0;overflow:hidden">%1$s</div><table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="background:#f4f5f7"><tr><td align="center" style="padding:32px 16px"><table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #e5e7eb;border-radius:18px"><tr><td style="padding:32px"><p style="margin:0 0 28px;font-size:22px;font-weight:800;color:#111827">ForgeTag</p><p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:2px;color:#e30613;text-transform:uppercase">%2$s</p><h1 style="margin:0 0 14px;font-size:30px;line-height:1.2;color:#111827">%3$s</h1><p style="margin:0 0 24px;color:#4b5563;line-height:1.6">%4$s</p><img src="cid:%5$s" alt="%6$s" width="536" style="display:block;width:100%%;max-width:536px;height:auto;border-radius:14px"><div>%7$s</div><p style="margin:24px 0 0;color:#4b5563;line-height:1.6">%8$s</p><p style="margin:14px 0 0;font-size:13px;color:#6b7280;line-height:1.5">%9$s</p></td></tr></table></td></tr></table></body></html>',
			esc_html__( 'A finder submitted evidence through ForgeTag.', 'tagcore' ),
			esc_html__( 'Item recovery report', 'tagcore' ),
			esc_html__( 'A finder submitted evidence', 'tagcore' ),
			esc_html__( 'The processed photo below passed ForgeTag safety checks.', 'tagcore' ),
			self::EVIDENCE_CID,
			esc_attr__( 'Processed evidence photo', 'tagcore' ),
			$message_section,
			esc_html__( 'This is a one-way report. Reply is unavailable because the finder has not verified an email address.', 'tagcore' ),
			esc_html__( 'ForgeTag can delete its stored copy under the retention policy, but cannot recall a copy already received, cached, exported, or forwarded by an email client.', 'tagcore' )
		);
	}

	/**
	 * Build the plain-text alternative without a Secure Reply path.
	 *
	 * @param string|null $message Optional Finder message.
	 */
	private function text_body( ?string $message ): string {
		$lines = array(
			__( 'A finder submitted evidence through ForgeTag.', 'tagcore' ),
			__( 'The processed evidence photo is included inline in this email.', 'tagcore' ),
		);

		if ( null !== $message ) {
			$lines[] = __( 'Message for you:', 'tagcore' );
			$lines[] = $message;
		}

		$lines[] = __( 'This is a one-way report. Reply is unavailable because the finder has not verified an email address.', 'tagcore' );
		$lines[] = __( 'ForgeTag can delete its stored copy under the retention policy, but cannot recall a copy already received, cached, exported, or forwarded by an email client.', 'tagcore' );

		return implode( "\n\n", $lines );
	}
}
