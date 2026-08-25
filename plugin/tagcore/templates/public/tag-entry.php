<?php
/**
 * Theme-independent ForgeTag manual entry page.
 *
 * @package ReturnTag\TagCore
 *
 * @var ReturnTag\TagCore\PublicSite\ManualTagEntryView $view Render-ready view.
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
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="referrer" content="no-referrer">
	<meta name="robots" content="noindex,nofollow,noarchive">
	<?php /* translators: %s: Current ForgeTag entry title. */ ?>
	<title><?php echo esc_html( sprintf( __( '%s - ForgeTag', 'tagcore' ), $view->title ) ); ?></title>
	<?php wp_print_styles( ReturnTag\TagCore\PublicSite\TagEntryLinkBlock::STYLE_HANDLE ); ?>
</head>
<body class="returntag-entry-page">
	<header class="returntag-entry-page__header">
		<a class="returntag-entry-page__wordmark" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'ForgeTag home', 'tagcore' ); ?>">
			<?php esc_html_e( 'ForgeTag', 'tagcore' ); ?>
		</a>
		<span class="returntag-entry-page__header-label"><?php esc_html_e( 'Tag recovery', 'tagcore' ); ?></span>
	</header>

	<main class="returntag-entry-page__main">
		<section class="returntag-entry-page__panel" aria-labelledby="returntag-entry-page-title">
			<div class="returntag-entry-page__content">
				<p class="returntag-entry__eyebrow">
					<?php esc_html_e( 'Tag recovery', 'tagcore' ); ?>
				</p>
				<h1 id="returntag-entry-page-title"><?php echo esc_html( $view->title ); ?></h1>
				<p class="returntag-entry__introduction"><?php echo esc_html( $view->introduction ); ?></p>

				<?php require __DIR__ . '/tag-entry-form.php'; ?>
			</div>
			<aside class="returntag-entry-page__guide" aria-label="<?php esc_attr_e( 'Tag ID guidance', 'tagcore' ); ?>">
				<p class="returntag-entry-page__guide-label"><?php esc_html_e( 'One ID. Six characters.', 'tagcore' ); ?></p>
				<h2><?php esc_html_e( 'Find it beside the QR code', 'tagcore' ); ?></h2>
				<p><?php esc_html_e( 'Enter all six characters. Spaces and hyphens are okay.', 'tagcore' ); ?></p>
				<div class="returntag-entry__orientation">
					<strong><?php esc_html_e( 'What happens next', 'tagcore' ); ?></strong>
					<p class="returntag-entry__orientation-text"><?php echo esc_html( $view->context ); ?></p>
				</div>
			</aside>
		</section>
	</main>
	<?php wp_script_modules()->print_enqueued_script_modules(); ?>
</body>
</html>
