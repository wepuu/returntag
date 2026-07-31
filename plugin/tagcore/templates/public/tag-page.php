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

			<?php if ( null !== $view->activation_form ) : ?>
				<div class="returntag-public__activation">
					<?php if ( ReturnTag\TagCore\PublicSite\ActivationOtpFormState::AUTHENTICATED === $view->activation_form->state ) : ?>
						<div class="returntag-public__notice returntag-public__notice--success" role="status">
							<h2><?php esc_html_e( 'You are signed in', 'tagcore' ); ?></h2>
							<p><?php esc_html_e( 'Your identity is confirmed. Activating this ReturnTag is the next step.', 'tagcore' ); ?></p>
						</div>
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
</body>
</html>
