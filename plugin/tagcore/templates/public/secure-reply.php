<?php
/**
 * Secure Reply standalone presentation.
 *
 * @package ReturnTag\TagCore
 * @var array{status:string,thread:array|null,nonce:string,action:string} $view
 */

declare(strict_types=1);

use ReturnTag\TagCore\PublicSite\PublicTagRouteController;

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
	<title><?php esc_html_e( 'Secure Reply - ForgeTag', 'tagcore' ); ?></title>
	<?php wp_print_styles( PublicTagRouteController::STYLE_HANDLE ); ?>
</head>
<body class="returntag-public returntag-public--secure-reply">
	<header class="returntag-public__header"><span class="returntag-public__wordmark">ForgeTag</span></header>
	<main class="returntag-public__main">
		<section class="returntag-public__status" aria-labelledby="returntag-reply-title">
			<div class="returntag-public__status-title">
				<p class="returntag-public__eyebrow"><?php esc_html_e( 'Private recovery conversation', 'tagcore' ); ?></p>
				<h1 id="returntag-reply-title"><?php esc_html_e( 'Secure Reply', 'tagcore' ); ?></h1>
			</div>
			<?php if ( 'continue' === $view['status'] ) : ?>
				<p class="returntag-public__message"><?php esc_html_e( 'Continue to a private 30-minute session. The email link will be used once.', 'tagcore' ); ?></p>
				<form class="returntag-public__form" method="post" action="<?php echo esc_url( $view['action'] ); ?>">
					<input type="hidden" name="returntag_action" value="exchange">
					<input type="hidden" name="_returntag_nonce" value="<?php echo esc_attr( $view['nonce'] ); ?>">
					<button class="returntag-public__submit" type="submit"><?php esc_html_e( 'Continue securely', 'tagcore' ); ?></button>
				</form>
			<?php elseif ( 'thread' === $view['status'] && is_array( $view['thread'] ) ) : ?>
				<?php $identity = $view['thread']['identity']; ?>
				<p class="returntag-public__message"><?php esc_html_e( 'Email addresses stay private. Send plain text only; do not include payment details or sensitive personal information.', 'tagcore' ); ?></p>
				<ol class="returntag-public__conversation">
					<?php foreach ( $view['thread']['messages'] as $message ) : ?>
						<li>
							<strong><?php echo esc_html( $message->sender_role === $identity->role ? __( 'You', 'tagcore' ) : ( 'owner' === $identity->role->value ? __( 'Finder', 'tagcore' ) : __( 'Owner', 'tagcore' ) ) ); ?></strong>
							<p><?php echo nl2br( esc_html( $message->body ) ); ?></p>
						</li>
					<?php endforeach; ?>
				</ol>
				<form class="returntag-public__form" method="post" action="<?php echo esc_url( $view['action'] ); ?>">
					<input type="hidden" name="returntag_action" value="message">
					<input type="hidden" name="_returntag_nonce" value="<?php echo esc_attr( $view['nonce'] ); ?>">
					<label for="returntag-reply-message"><?php esc_html_e( 'Message', 'tagcore' ); ?></label>
					<textarea id="returntag-reply-message" name="message" minlength="10" maxlength="500" required></textarea>
					<p class="returntag-public__hint"><?php esc_html_e( '10–500 characters. Attachments and HTML are not supported.', 'tagcore' ); ?></p>
					<button class="returntag-public__submit" type="submit"><?php esc_html_e( 'Send private message', 'tagcore' ); ?></button>
				</form>
				<aside class="returntag-public__safety" aria-labelledby="returntag-safety-title">
					<h2 id="returntag-safety-title"><?php esc_html_e( 'Conversation safety', 'tagcore' ); ?></h2>
					<p>
						<?php
						echo esc_html(
							'owner' === $identity->role->value
								? __( 'Report and block this conversation if the messages are unwanted or unsafe. This action cannot be undone here.', 'tagcore' )
								: __( 'End this conversation when you no longer need to contact the owner. This action cannot be undone here.', 'tagcore' )
						);
						?>
					</p>
					<form class="returntag-public__form returntag-public__form--terminal" method="post" action="<?php echo esc_url( $view['action'] ); ?>">
						<input type="hidden" name="returntag_action" value="<?php echo esc_attr( 'owner' === $identity->role->value ? 'owner_report_block' : 'finder_close' ); ?>">
						<input type="hidden" name="_returntag_nonce" value="<?php echo esc_attr( $view['nonce'] ); ?>">
						<label class="returntag-public__confirmation">
							<input type="checkbox" name="confirm_terminal_action" value="1" required>
							<span><?php esc_html_e( 'I understand this will end access to the conversation.', 'tagcore' ); ?></span>
						</label>
						<button class="returntag-public__submit returntag-public__submit--danger" type="submit">
							<?php echo esc_html( 'owner' === $identity->role->value ? __( 'Report and block', 'tagcore' ) : __( 'End conversation', 'tagcore' ) ); ?>
						</button>
					</form>
				</aside>
			<?php elseif ( 'terminal' === $view['status'] ) : ?>
				<p class="returntag-public__message" role="status"><?php esc_html_e( 'This private conversation has ended and can no longer be accessed from this session.', 'tagcore' ); ?></p>
			<?php else : ?>
				<p class="returntag-public__message"><?php esc_html_e( 'This secure reply is unavailable or has expired. Request a new link from the latest email.', 'tagcore' ); ?></p>
			<?php endif; ?>
		</section>
	</main>
</body>
</html>
