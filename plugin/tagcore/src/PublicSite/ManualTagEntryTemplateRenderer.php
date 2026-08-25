<?php
/**
 * Manual Tag entry template renderer.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\PublicSite;

use RuntimeException;

/**
 * Maps a closed intent and form state to escaped ForgeTag presentation.
 */
final readonly class ManualTagEntryTemplateRenderer {
	/**
	 * Create the renderer.
	 *
	 * @param string $plugin_dir Absolute TagCore plugin directory.
	 */
	public function __construct( private string $plugin_dir ) {
	}

	/**
	 * Render one standalone entry page.
	 *
	 * @param TagEntryIntent          $intent Closed presentation intent.
	 * @param string                  $action_url Same-site form action.
	 * @param ManualTagEntryFormState $state Safe form state.
	 */
	public function render( TagEntryIntent $intent, string $action_url, ManualTagEntryFormState $state ): void {
		echo $this->render_to_string( $intent, $action_url, $state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Templates escape by output context.
	}

	/**
	 * Render one entry page to a testable string.
	 *
	 * @param TagEntryIntent          $intent Closed presentation intent.
	 * @param string                  $action_url Same-site form action.
	 * @param ManualTagEntryFormState $state Safe form state.
	 * @param bool                    $standalone Whether the view is standalone.
	 * @param string|null             $form_id Optional unique form identifier.
	 * @throws RuntimeException When the packaged template is unavailable.
	 */
	public function render_to_string(
		TagEntryIntent $intent,
		string $action_url,
		ManualTagEntryFormState $state,
		bool $standalone = true,
		?string $form_id = null
	): string {
		$template = $this->plugin_dir . '/templates/public/tag-entry.php';

		if ( ! is_readable( $template ) ) {
			throw new RuntimeException( 'The manual Tag entry template is unavailable.' );
		}

		$view = $this->view( $intent, $action_url, $state, $standalone, $form_id );

		ob_start();
		require $template;
		$output = ob_get_clean();

		return $output;
	}

	/**
	 * Render only the shared manual-entry form.
	 *
	 * @param TagEntryIntent          $intent Closed presentation intent.
	 * @param string                  $action_url Same-site form action.
	 * @param ManualTagEntryFormState $state Safe form state.
	 * @param string|null             $form_id Optional unique form identifier.
	 * @throws RuntimeException When the packaged template is unavailable.
	 */
	public function render_form_to_string(
		TagEntryIntent $intent,
		string $action_url,
		ManualTagEntryFormState $state = ManualTagEntryFormState::READY,
		?string $form_id = null
	): string {
		$template = $this->plugin_dir . '/templates/public/tag-entry-form.php';

		if ( ! is_readable( $template ) ) {
			throw new RuntimeException( 'The manual Tag entry form template is unavailable.' );
		}

		$view = $this->view( $intent, $action_url, $state, false, $form_id );

		ob_start();
		require $template;
		$output = ob_get_clean();

		return $output;
	}

	/**
	 * Build the presentation-only view.
	 *
	 * @param TagEntryIntent          $intent Closed presentation intent.
	 * @param string                  $action_url Same-site form action.
	 * @param ManualTagEntryFormState $state Safe form state.
	 * @param bool                    $standalone Whether the view is standalone.
	 * @param string|null             $form_id Optional unique form identifier.
	 */
	private function view(
		TagEntryIntent $intent,
		string $action_url,
		ManualTagEntryFormState $state,
		bool $standalone,
		?string $form_id
	): ManualTagEntryView {
		[ $title, $introduction, $context ] = match ( $intent ) {
			TagEntryIntent::ACTIVATE => array(
				__( 'Activate your ForgeTag', 'tagcore' ),
				__( 'Enter the six-character ID printed on your tag.', 'tagcore' ),
				__( 'We will check the Tag and show the right activation or recovery step.', 'tagcore' ),
			),
			TagEntryIntent::REPORT => array(
				__( 'Report a found ForgeTag', 'tagcore' ),
				__( 'Enter the six-character ID printed on the tag you found.', 'tagcore' ),
				__( 'We will check the Tag and show the right private recovery step.', 'tagcore' ),
			),
		};

		return new ManualTagEntryView(
			$intent,
			$title,
			$introduction,
			$context,
			$action_url,
			wp_create_nonce( ManualTagEntryFormHandler::NONCE_ACTION ),
			$state,
			$form_id ?? wp_unique_id( 'returntag-tag-entry-' ),
			$standalone
		);
	}
}
