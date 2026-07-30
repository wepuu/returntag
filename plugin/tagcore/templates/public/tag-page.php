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
