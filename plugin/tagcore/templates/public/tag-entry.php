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
		<a class="returntag-entry-page__wordmark" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php esc_html_e( 'ForgeTag', 'tagcore' ); ?>
		</a>
	</header>

	<main class="returntag-entry-page__main">
		<section class="returntag-entry-page__panel" aria-labelledby="returntag-entry-page-title">
			<p class="returntag-entry__eyebrow">
				<?php esc_html_e( 'Tag recovery', 'tagcore' ); ?>
			</p>
			<h1 id="returntag-entry-page-title"><?php echo esc_html( $view->title ); ?></h1>
			<p class="returntag-entry__introduction"><?php echo esc_html( $view->introduction ); ?></p>

			<?php require __DIR__ . '/tag-entry-form.php'; ?>
		</section>
	</main>
	<?php wp_script_modules()->print_enqueued_script_modules(); ?>
</body>
</html>
