<?php
/**
 * Theme-independent fail-closed public Tag page.
 *
 * @package ReturnTag\TagCore
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
	<title><?php echo esc_html__( 'Tag service is temporarily unavailable — ReturnTag', 'tagcore' ); ?></title>
	<?php wp_print_styles( ReturnTag\TagCore\PublicSite\PublicTagRouteController::STYLE_HANDLE ); ?>
</head>
<body class="returntag-public returntag-public--unavailable">
	<header class="returntag-public__header">
		<span class="returntag-public__wordmark">ReturnTag</span>
	</header>

	<main class="returntag-public__main">
		<section class="returntag-public__status" aria-labelledby="returntag-public-title">
			<div class="returntag-public__status-title">
				<p class="returntag-public__eyebrow"><?php esc_html_e( 'Tag recovery', 'tagcore' ); ?></p>
				<h1 id="returntag-public-title"><?php esc_html_e( 'Tag service is temporarily unavailable', 'tagcore' ); ?></h1>
			</div>

			<p class="returntag-public__message">
				<?php esc_html_e( 'We can’t check this ReturnTag right now. Please try again in a moment.', 'tagcore' ); ?>
			</p>

			<a class="returntag-public__home-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php esc_html_e( 'Return to homepage', 'tagcore' ); ?>
			</a>
		</section>
	</main>
</body>
</html>
