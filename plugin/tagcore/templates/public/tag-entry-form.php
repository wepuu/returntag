<?php
/**
 * Shared manual Tag entry form.
 *
 * @package ReturnTag\TagCore
 *
 * @var ReturnTag\TagCore\PublicSite\ManualTagEntryView $view Render-ready view.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$help_id  = $view->form_id . '-help';
$error_id = $view->form_id . '-error';
$has_error = ReturnTag\TagCore\PublicSite\ManualTagEntryFormState::READY !== $view->state;
?>
<form
	id="<?php echo esc_attr( $view->form_id ); ?>"
	class="returntag-entry__form"
	method="post"
	action="<?php echo esc_url( $view->action_url ); ?>"
	data-returntag-tag-entry-form
>
	<div class="returntag-entry__field">
		<label for="<?php echo esc_attr( $view->form_id . '-tag-id' ); ?>">
			<?php esc_html_e( 'Tag ID', 'tagcore' ); ?>
		</label>
		<p id="<?php echo esc_attr( $help_id ); ?>" class="returntag-entry__help">
			<?php esc_html_e( 'Enter the six-character ID printed on the tag. Example: A7R2W9.', 'tagcore' ); ?>
		</p>
		<input
			class="returntag-entry__input"
			id="<?php echo esc_attr( $view->form_id . '-tag-id' ); ?>"
			name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\ManualTagEntryFormHandler::TAG_ID_FIELD ); ?>"
			type="text"
			inputmode="text"
			autocomplete="off"
			autocapitalize="characters"
			spellcheck="false"
			maxlength="64"
			aria-describedby="<?php echo esc_attr( $help_id . ( $has_error ? ' ' . $error_id : '' ) ); ?>"
			aria-invalid="<?php echo $has_error ? 'true' : 'false'; ?>"
			required
		>
		<?php if ( $has_error ) : ?>
			<p id="<?php echo esc_attr( $error_id ); ?>" class="returntag-entry__error" role="alert">
				<?php
				echo esc_html(
					match ( $view->state ) {
						ReturnTag\TagCore\PublicSite\ManualTagEntryFormState::INVALID => __( 'Enter a valid six-character Tag ID.', 'tagcore' ),
						ReturnTag\TagCore\PublicSite\ManualTagEntryFormState::THROTTLED => __( 'Too many attempts. Wait a moment and try again.', 'tagcore' ),
						default => __( 'We could not continue right now. Refresh the page and try again.', 'tagcore' ),
					}
				);
				?>
			</p>
		<?php endif; ?>
	</div>

	<input
		type="hidden"
		name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\ManualTagEntryFormHandler::ACTION_FIELD ); ?>"
		value="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\ManualTagEntryFormHandler::SUBMIT_ACTION ); ?>"
	>
	<input
		type="hidden"
		name="<?php echo esc_attr( ReturnTag\TagCore\PublicSite\ManualTagEntryFormHandler::NONCE_FIELD ); ?>"
		value="<?php echo esc_attr( $view->nonce ); ?>"
	>

	<button class="returntag-entry__submit" type="submit">
		<?php esc_html_e( 'Continue', 'tagcore' ); ?>
	</button>
</form>
