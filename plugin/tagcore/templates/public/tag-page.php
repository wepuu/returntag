<?php
/**
 * Theme-independent public Tag state page.
 *
 * @package ReturnTag\TagCore
 *
 * @var ReturnTag\TagCore\PublicSite\PublicTagPageView $view Render-ready view.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="referrer" content="no-referrer">
	<meta name="robots" content="noindex,nofollow,noarchive">
	<?php /* translators: %s: Current ReturnTag page title. */ ?>
	<title><?php echo esc_html( sprintf( __( '%s - ReturnTag', 'tagcore' ), $view->title ) ); ?></title>
	<?php wp_print_styles( ReturnTag\TagCore\PublicSite\PublicTagRouteController::STYLE_HANDLE ); ?>
</head>
<body class="<?php echo esc_attr( 'returntag-public ' . $view->body_class ); ?>">
	<header class="returntag-public__header">
		<span class="returntag-public__wordmark">ReturnTag</span>
	</header>

	<main class="returntag-public__main">
		<section class="returntag-public__status" aria-labelledby="returntag-public-title">
			<div class="returntag-public__status-title">
				<p class="returntag-public__eyebrow"><?php echo esc_html( $view->eyebrow ); ?></p>
				<h1 id="returntag-public-title"><?php echo esc_html( $view->title ); ?></h1>
			</div>

			<p class="returntag-public__message">
				<?php echo esc_html( $view->message ); ?>
			</p>

			<?php if ( null !== $view->smart_tag_guide ) : ?>
				<section class="returntag-public__smart-guide" aria-labelledby="returntag-smart-guide-title">
					<h2 id="returntag-smart-guide-title">
						<?php echo esc_html( $view->smart_tag_guide->title ); ?>
					</h2>
					<p class="returntag-public__smart-guide-summary">
						<?php echo esc_html( $view->smart_tag_guide->summary ); ?>
					</p>
					<dl class="returntag-public__smart-guide-systems">
						<div>
							<dt><?php echo esc_html( $view->smart_tag_guide->network_label ); ?></dt>
							<dd><?php echo esc_html( $view->smart_tag_guide->network_message ); ?></dd>
						</div>
						<div>
							<dt><?php echo esc_html( $view->smart_tag_guide->qr_label ); ?></dt>
							<dd><?php echo esc_html( $view->smart_tag_guide->qr_message ); ?></dd>
						</div>
					</dl>
					<p class="returntag-public__smart-guide-privacy">
						<?php echo esc_html( $view->smart_tag_guide->privacy_message ); ?>
					</p>
				</section>
			<?php endif; ?>

			<?php if ( null !== $view->activation_form ) : ?>
				<div class="returntag-public__activation">
					<?php if ( in_array( $view->activation_form->state, array( ReturnTag\TagCore\PublicSite\ActivationOtpFormState::AUTHENTICATED, ReturnTag\TagCore\PublicSite\ActivationOtpFormState::ACTIVATION_ERROR ), true ) ) : ?>
						<div class="returntag-public__notice returntag-public__notice--success" role="status">
							<h2><?php esc_html_e( 'You are signed in', 'tagcore' ); ?></h2>
							<p><?php esc_html_e( 'Your identity is confirmed. You can now activate this ReturnTag.', 'tagcore' ); ?></p>
						</div>
						<?php if ( ReturnTag\TagCore\PublicSite\ActivationOtpFormState::ACTIVATION_ERROR === $view->activation_form->state ) : ?>
							<div class="returntag-public__notice returntag-public__notice--error" role="alert">
								<p><?php esc_html_e( 'We could not activate this Tag right now. Please wait and try again.', 'tagcore' ); ?></p>
							</div>
						<?php endif; ?>
						<form class="returntag-public__form" method="post" action="<?php echo esc_url( $view->activation_form->action_url ); ?>">
							<input
								type="hidden"
								name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\ActivationOtpFormHandler::ACTION_FIELD ); ?>"
								value="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\ActivationOtpFormHandler::ACTIVATE_ACTION ); ?>"
							>
							<input
								type="hidden"
								name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\ActivationOtpFormHandler::NONCE_FIELD ); ?>"
								value="<?php echo esc_attr( $view->activation_form->nonce ); ?>"
							>
							<button class="returntag-public__submit" type="submit">
								<?php esc_html_e( 'Activate my tag', 'tagcore' ); ?>
							</button>
						</form>
					<?php else : ?>
						<?php if ( ReturnTag\TagCore\PublicSite\ActivationOtpFormState::REQUEST_ACCEPTED === $view->activation_form->state ) : ?>
							<div class="returntag-public__notice returntag-public__notice--success" role="status">
								<h2><?php esc_html_e( 'Check your email', 'tagcore' ); ?></h2>
								<p><?php esc_html_e( 'If this request is eligible, a six-digit code is on its way. It will expire in 10 minutes.', 'tagcore' ); ?></p>
							</div>
						<?php elseif ( ReturnTag\TagCore\PublicSite\ActivationOtpFormState::REQUEST_ERROR === $view->activation_form->state ) : ?>
							<div class="returntag-public__notice returntag-public__notice--error" role="alert">
								<p><?php esc_html_e( 'We could not send a code right now. Please wait and try again.', 'tagcore' ); ?></p>
							</div>
						<?php endif; ?>

						<?php if ( in_array( $view->activation_form->state, array( ReturnTag\TagCore\PublicSite\ActivationOtpFormState::READY, ReturnTag\TagCore\PublicSite\ActivationOtpFormState::REQUEST_INVALID_EMAIL, ReturnTag\TagCore\PublicSite\ActivationOtpFormState::REQUEST_ERROR ), true ) ) : ?>
							<form class="returntag-public__form" method="post" action="<?php echo esc_url( $view->activation_form->action_url ); ?>">
								<div class="returntag-public__field">
									<label for="returntag-activation-email"><?php esc_html_e( 'Email address', 'tagcore' ); ?></label>
									<p id="returntag-activation-email-help" class="returntag-public__field-help">
										<?php esc_html_e( 'We will send a six-digit verification code. Your email is not shown publicly.', 'tagcore' ); ?>
									</p>
									<input
										id="returntag-activation-email"
										name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\ActivationOtpFormHandler::EMAIL_FIELD ); ?>"
										type="email"
										inputmode="email"
										autocomplete="email"
										aria-describedby="returntag-activation-email-help<?php echo ReturnTag\TagCore\PublicSite\ActivationOtpFormState::REQUEST_INVALID_EMAIL === $view->activation_form->state ? ' returntag-activation-email-error' : ''; ?>"
										aria-invalid="<?php echo ReturnTag\TagCore\PublicSite\ActivationOtpFormState::REQUEST_INVALID_EMAIL === $view->activation_form->state ? 'true' : 'false'; ?>"
										maxlength="254"
										required
									>
									<?php if ( ReturnTag\TagCore\PublicSite\ActivationOtpFormState::REQUEST_INVALID_EMAIL === $view->activation_form->state ) : ?>
										<p id="returntag-activation-email-error" class="returntag-public__field-error" role="alert">
											<?php esc_html_e( 'Enter a valid email address.', 'tagcore' ); ?>
										</p>
									<?php endif; ?>
								</div>

								<input
									type="hidden"
									name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\ActivationOtpFormHandler::ACTION_FIELD ); ?>"
									value="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\ActivationOtpFormHandler::REQUEST_ACTION ); ?>"
								>
								<input
									type="hidden"
									name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\ActivationOtpFormHandler::NONCE_FIELD ); ?>"
									value="<?php echo esc_attr( $view->activation_form->nonce ); ?>"
								>

								<button class="returntag-public__submit" type="submit">
									<?php esc_html_e( 'Email me a code', 'tagcore' ); ?>
								</button>
							</form>
						<?php endif; ?>

						<?php if ( in_array( $view->activation_form->state, array( ReturnTag\TagCore\PublicSite\ActivationOtpFormState::READY, ReturnTag\TagCore\PublicSite\ActivationOtpFormState::REQUEST_ACCEPTED, ReturnTag\TagCore\PublicSite\ActivationOtpFormState::VERIFICATION_INVALID ), true ) ) : ?>
							<section class="returntag-public__verification" aria-labelledby="returntag-verification-title">
								<h2 id="returntag-verification-title">
									<?php
									echo esc_html(
										ReturnTag\TagCore\PublicSite\ActivationOtpFormState::REQUEST_ACCEPTED === $view->activation_form->state
											? __( 'Enter your code', 'tagcore' )
											: __( 'Already have a code?', 'tagcore' )
									);
									?>
								</h2>
								<p class="returntag-public__verification-intro">
									<?php esc_html_e( 'Enter the same email address and the six-digit code from your message.', 'tagcore' ); ?>
								</p>

								<?php if ( ReturnTag\TagCore\PublicSite\ActivationOtpFormState::VERIFICATION_INVALID === $view->activation_form->state ) : ?>
									<div id="returntag-verification-error" class="returntag-public__notice returntag-public__notice--error" role="alert">
										<p><?php esc_html_e( 'We could not verify that code. Request a new code and try again.', 'tagcore' ); ?></p>
									</div>
								<?php endif; ?>

								<form class="returntag-public__form" method="post" action="<?php echo esc_url( $view->activation_form->action_url ); ?>">
									<div class="returntag-public__field">
										<label for="returntag-verification-email"><?php esc_html_e( 'Email address', 'tagcore' ); ?></label>
										<input
											id="returntag-verification-email"
											name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\ActivationOtpFormHandler::EMAIL_FIELD ); ?>"
											type="email"
											inputmode="email"
											autocomplete="email"
											maxlength="254"
											required
										>
									</div>

									<div class="returntag-public__field">
										<label for="returntag-activation-code"><?php esc_html_e( 'Six-digit code', 'tagcore' ); ?></label>
										<p id="returntag-activation-code-help" class="returntag-public__field-help">
											<?php esc_html_e( 'Codes expire after 10 minutes and can be used only once.', 'tagcore' ); ?>
										</p>
										<input
											id="returntag-activation-code"
											class="returntag-public__code-input"
											name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\ActivationOtpFormHandler::CODE_FIELD ); ?>"
											type="text"
											inputmode="numeric"
											autocomplete="one-time-code"
											pattern="[0-9]{6}"
											minlength="6"
											maxlength="6"
											aria-describedby="returntag-activation-code-help<?php echo ReturnTag\TagCore\PublicSite\ActivationOtpFormState::VERIFICATION_INVALID === $view->activation_form->state ? ' returntag-verification-error' : ''; ?>"
											required
										>
									</div>

									<input
										type="hidden"
										name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\ActivationOtpFormHandler::ACTION_FIELD ); ?>"
										value="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\ActivationOtpFormHandler::VERIFY_ACTION ); ?>"
									>
									<input
										type="hidden"
										name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\ActivationOtpFormHandler::NONCE_FIELD ); ?>"
										value="<?php echo esc_attr( $view->activation_form->nonce ); ?>"
									>

									<button class="returntag-public__submit" type="submit">
										<?php esc_html_e( 'Verify code', 'tagcore' ); ?>
									</button>
								</form>
							</section>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( null !== $view->finder_report_form ) : ?>
				<section class="returntag-public__finder" aria-labelledby="returntag-finder-title">
					<?php if ( ReturnTag\TagCore\PublicSite\FinderReportFormState::ACCEPTED === $view->finder_report_form->state ) : ?>
						<div class="returntag-public__finder-success" role="status">
							<span class="returntag-public__finder-success-mark" aria-hidden="true">✓</span>
							<p class="returntag-public__eyebrow"><?php esc_html_e( 'Report received', 'tagcore' ); ?></p>
							<h2 id="returntag-finder-title"><?php esc_html_e( 'Thank you for helping', 'tagcore' ); ?></h2>
							<p class="returntag-public__finder-copy"><?php esc_html_e( 'Your report was received and is being checked. If the evidence is approved, the current owner can be notified securely.', 'tagcore' ); ?></p>
						</div>
						<?php if ( null !== $view->finder_report_form->email_form ) : ?>
							<section class="returntag-public__verification returntag-public__finder-email" aria-labelledby="returntag-finder-email-title">
								<?php if ( ReturnTag\TagCore\PublicSite\FinderEmailFormState::VERIFIED === $view->finder_report_form->email_form->state ) : ?>
									<div class="returntag-public__notice returntag-public__notice--success" role="status">
										<h2 id="returntag-finder-email-title"><?php esc_html_e( 'Private contact is ready', 'tagcore' ); ?></h2>
										<p><?php esc_html_e( 'Your email is verified and linked to this recovery report. It is never shown to the owner.', 'tagcore' ); ?></p>
									</div>
								<?php else : ?>
									<h2 id="returntag-finder-email-title"><?php esc_html_e( 'Continue privately', 'tagcore' ); ?></h2>
									<p class="returntag-public__verification-intro"><?php esc_html_e( 'Optional. Verify your email so the owner can reply later without either address being shared.', 'tagcore' ); ?></p>
									<?php if ( ReturnTag\TagCore\PublicSite\FinderEmailFormState::CODE_SENT === $view->finder_report_form->email_form->state ) : ?>
										<div class="returntag-public__notice returntag-public__notice--success" role="status"><p><?php esc_html_e( 'If the request is eligible, a six-digit code is on its way.', 'tagcore' ); ?></p></div>
									<?php elseif ( in_array( $view->finder_report_form->email_form->state, array( ReturnTag\TagCore\PublicSite\FinderEmailFormState::INVALID, ReturnTag\TagCore\PublicSite\FinderEmailFormState::ERROR ), true ) ) : ?>
										<div class="returntag-public__notice returntag-public__notice--error" role="alert"><p><?php esc_html_e( 'We could not verify that request. Check the email and code, then try again.', 'tagcore' ); ?></p></div>
									<?php endif; ?>
									<form class="returntag-public__form" method="post" action="<?php echo esc_url( $view->finder_report_form->email_form->action_url ); ?>">
										<div class="returntag-public__field">
											<label for="returntag-finder-email"><?php esc_html_e( 'Email address', 'tagcore' ); ?></label>
											<input id="returntag-finder-email" name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderEmailFormHandler::EMAIL_FIELD ); ?>" type="email" inputmode="email" autocomplete="email" maxlength="254" required>
										</div>
										<input type="hidden" name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderEmailFormHandler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderEmailFormHandler::REQUEST_ACTION ); ?>">
										<input type="hidden" name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderEmailFormHandler::NONCE_FIELD ); ?>" value="<?php echo esc_attr( $view->finder_report_form->email_form->nonce ); ?>">
										<input type="hidden" name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderReportFormHandler::TOKEN_FIELD ); ?>" value="<?php echo esc_attr( $view->finder_report_form->email_form->continuation_token ); ?>">
										<button class="returntag-public__secondary" type="submit"><?php esc_html_e( 'Email me a code', 'tagcore' ); ?></button>
									</form>
									<form class="returntag-public__form" method="post" action="<?php echo esc_url( $view->finder_report_form->email_form->action_url ); ?>">
										<div class="returntag-public__field">
											<label for="returntag-finder-verify-email"><?php esc_html_e( 'Email address', 'tagcore' ); ?></label>
											<input id="returntag-finder-verify-email" name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderEmailFormHandler::EMAIL_FIELD ); ?>" type="email" inputmode="email" autocomplete="email" maxlength="254" required>
										</div>
										<div class="returntag-public__field">
											<label for="returntag-finder-email-code"><?php esc_html_e( 'Six-digit code', 'tagcore' ); ?></label>
											<input id="returntag-finder-email-code" class="returntag-public__code-input" name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderEmailFormHandler::CODE_FIELD ); ?>" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required>
										</div>
										<input type="hidden" name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderEmailFormHandler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderEmailFormHandler::VERIFY_ACTION ); ?>">
										<input type="hidden" name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderEmailFormHandler::NONCE_FIELD ); ?>" value="<?php echo esc_attr( $view->finder_report_form->email_form->nonce ); ?>">
										<input type="hidden" name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderReportFormHandler::TOKEN_FIELD ); ?>" value="<?php echo esc_attr( $view->finder_report_form->email_form->continuation_token ); ?>">
										<button class="returntag-public__submit" type="submit"><?php esc_html_e( 'Verify and continue', 'tagcore' ); ?></button>
									</form>
								<?php endif; ?>
							</section>
						<?php endif; ?>
					<?php else : ?>
						<div class="returntag-public__finder-intro">
							<p class="returntag-public__eyebrow"><?php esc_html_e( 'Secure item return', 'tagcore' ); ?></p>
							<h2 id="returntag-finder-title"><?php esc_html_e( 'Send a private recovery report', 'tagcore' ); ?></h2>
							<p class="returntag-public__finder-copy"><?php esc_html_e( 'A clear photo helps confirm that you found the item. You do not need an account, and your contact details are not requested.', 'tagcore' ); ?></p>
						</div>

						<?php if ( ReturnTag\TagCore\PublicSite\FinderReportFormState::ERROR === $view->finder_report_form->state ) : ?>
							<div class="returntag-public__notice returntag-public__notice--error" role="alert">
								<p><?php esc_html_e( 'We could not accept this report right now. Refresh the page and try again.', 'tagcore' ); ?></p>
							</div>
						<?php endif; ?>

						<ol class="returntag-public__finder-progress" aria-label="<?php esc_attr_e( 'Report progress', 'tagcore' ); ?>">
							<li aria-current="step"><span>1</span><?php esc_html_e( 'Report details', 'tagcore' ); ?></li>
							<li><span>2</span><?php esc_html_e( 'Review and send', 'tagcore' ); ?></li>
						</ol>

						<form class="returntag-public__finder-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( $view->finder_report_form->action_url ); ?>" data-returntag-finder-form data-message-error="<?php esc_attr_e( 'Leave the message blank or enter 10–500 characters.', 'tagcore' ); ?>">
							<fieldset class="returntag-public__finder-step" data-returntag-finder-step="1">
								<legend tabindex="-1"><?php esc_html_e( 'Tell the owner what you found', 'tagcore' ); ?></legend>
								<div class="returntag-public__field">
									<label for="returntag-finder-message"><?php esc_html_e( 'Message for the owner', 'tagcore' ); ?> <span><?php esc_html_e( '— optional', 'tagcore' ); ?></span></label>
									<p id="returntag-finder-message-help" class="returntag-public__field-help"><?php esc_html_e( 'If included, use 10–500 characters. Do not include sensitive personal information.', 'tagcore' ); ?></p>
									<textarea id="returntag-finder-message" name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderReportFormHandler::MESSAGE_FIELD ); ?>" rows="5" maxlength="500" aria-describedby="returntag-finder-message-help<?php echo ReturnTag\TagCore\PublicSite\FinderReportFormState::INVALID_MESSAGE === $view->finder_report_form->state ? ' returntag-finder-message-error' : ''; ?>" aria-invalid="<?php echo ReturnTag\TagCore\PublicSite\FinderReportFormState::INVALID_MESSAGE === $view->finder_report_form->state ? 'true' : 'false'; ?>"></textarea>
									<?php if ( ReturnTag\TagCore\PublicSite\FinderReportFormState::INVALID_MESSAGE === $view->finder_report_form->state ) : ?>
										<p id="returntag-finder-message-error" class="returntag-public__field-error" role="alert"><?php esc_html_e( 'Leave the message blank or enter 10–500 plain-text characters.', 'tagcore' ); ?></p>
									<?php endif; ?>
								</div>

								<div class="returntag-public__field returntag-public__photo-field">
									<label for="returntag-finder-photo"><?php esc_html_e( 'Item photo', 'tagcore' ); ?></label>
									<p id="returntag-finder-photo-help" class="returntag-public__field-help"><?php esc_html_e( 'Required. Add one clear JPEG, PNG, or WebP image up to 8 MB. The file is kept private and checked before it can be shown to the owner.', 'tagcore' ); ?></p>
									<input id="returntag-finder-photo" class="returntag-public__finder-file" name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderReportFormHandler::PHOTO_FIELD ); ?>" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" aria-describedby="returntag-finder-photo-help<?php echo ReturnTag\TagCore\PublicSite\FinderReportFormState::INVALID_PHOTO === $view->finder_report_form->state ? ' returntag-finder-photo-error' : ''; ?>" aria-invalid="<?php echo ReturnTag\TagCore\PublicSite\FinderReportFormState::INVALID_PHOTO === $view->finder_report_form->state ? 'true' : 'false'; ?>" required>
									<?php if ( ReturnTag\TagCore\PublicSite\FinderReportFormState::INVALID_PHOTO === $view->finder_report_form->state ) : ?>
										<p id="returntag-finder-photo-error" class="returntag-public__field-error" role="alert"><?php esc_html_e( 'Choose one valid JPEG, PNG, or WebP image up to 8 MB.', 'tagcore' ); ?></p>
									<?php endif; ?>
								</div>

								<button class="returntag-public__secondary" type="button" data-returntag-finder-next><?php esc_html_e( 'Review report', 'tagcore' ); ?></button>
							</fieldset>

							<fieldset class="returntag-public__finder-step" data-returntag-finder-step="2">
								<legend tabindex="-1"><?php esc_html_e( 'Review and send', 'tagcore' ); ?></legend>
								<div class="returntag-public__review-card">
									<p class="returntag-public__review-row"><strong><?php esc_html_e( 'Message', 'tagcore' ); ?></strong><span data-returntag-finder-message-review><?php esc_html_e( 'No message added', 'tagcore' ); ?></span></p>
									<p class="returntag-public__review-row"><strong><?php esc_html_e( 'Photo', 'tagcore' ); ?></strong><span data-returntag-finder-photo-review><?php esc_html_e( 'One required image', 'tagcore' ); ?></span></p>
								</div>
								<p class="returntag-public__finder-disclosure"><?php esc_html_e( 'Submitting does not guarantee owner notification. The photo must pass processing and safety review first. This report does not open a conversation.', 'tagcore' ); ?></p>
								<div class="returntag-public__finder-actions">
									<button class="returntag-public__secondary" type="button" data-returntag-finder-back><?php esc_html_e( 'Back', 'tagcore' ); ?></button>
									<button class="returntag-public__submit" type="submit"><?php esc_html_e( 'Send report for review', 'tagcore' ); ?></button>
								</div>
							</fieldset>

							<input type="hidden" name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderReportFormHandler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderReportFormHandler::SUBMIT_ACTION ); ?>">
							<input type="hidden" name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderReportFormHandler::NONCE_FIELD ); ?>" value="<?php echo esc_attr( $view->finder_report_form->nonce ); ?>">
							<input type="hidden" name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\FinderReportFormHandler::TOKEN_FIELD ); ?>" value="<?php echo esc_attr( $view->finder_report_form->submission_token ); ?>">
						</form>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php if ( null !== $view->product_type_label || null !== $view->public_label ) : ?>
				<dl class="returntag-public__details">
					<?php if ( null !== $view->product_type_label ) : ?>
						<div class="returntag-public__detail">
							<dt><?php esc_html_e( 'Tag type', 'tagcore' ); ?></dt>
							<dd><?php echo esc_html( $view->product_type_label ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( null !== $view->public_label && '' !== $view->public_label ) : ?>
						<div class="returntag-public__detail">
							<dt><?php esc_html_e( 'Item label', 'tagcore' ); ?></dt>
							<dd><?php echo esc_html( $view->public_label ); ?></dd>
						</div>
					<?php endif; ?>
				</dl>
			<?php endif; ?>

			<?php if ( $view->lost_mode ) : ?>
				<aside class="returntag-public__lost" aria-labelledby="returntag-public-lost-title">
					<h2 id="returntag-public-lost-title"><?php esc_html_e( 'Marked as lost', 'tagcore' ); ?></h2>
					<?php if ( null !== $view->lost_message && '' !== $view->lost_message ) : ?>
						<p><?php echo esc_html( $view->lost_message ); ?></p>
					<?php endif; ?>
				</aside>
			<?php endif; ?>

			<a class="returntag-public__home-link" href="<?php echo esc_url( $view->action_url ); ?>">
				<?php echo esc_html( $view->action_label ); ?>
			</a>
		</section>
	</main>
	<?php wp_print_footer_scripts(); ?>
</body>
</html>
