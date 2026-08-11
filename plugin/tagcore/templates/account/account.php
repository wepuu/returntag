<?php
/**
 * Theme-independent ForgeTag Owner Account page.
 *
 * @package ReturnTag\TagCore
 *
 * @var ReturnTag\TagCore\Account\AccountPageView $view Render-ready Account view.
 */

declare(strict_types=1);

use ReturnTag\TagCore\Account\AccountFormState;
use ReturnTag\TagCore\Account\AccountConversationFeedback;
use ReturnTag\TagCore\Account\AccountConversationFormHandler;
use ReturnTag\TagCore\Account\AccountRoute;
use ReturnTag\TagCore\Account\AccountSignInFormHandler;
use ReturnTag\TagCore\Account\AccountTagMutationFormHandler;
use ReturnTag\TagCore\Account\AccountTagMutationState;
use ReturnTag\TagCore\Application\Account\OwnerConversationAccessState;
use ReturnTag\TagCore\Application\Account\OwnerTagAccessState;
use ReturnTag\TagCore\Domain\Conversation\ConversationStatus;
use ReturnTag\TagCore\Domain\Tag\TagId;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tag_type_label = static fn( \ReturnTag\TagCore\Domain\Tag\TagType $type ): string => match ( $type ) {
	\ReturnTag\TagCore\Domain\Tag\TagType::STICKER => __( 'Sticker', 'tagcore' ),
	\ReturnTag\TagCore\Domain\Tag\TagType::CLASSIC_TAG => __( 'Classic Tag', 'tagcore' ),
	\ReturnTag\TagCore\Domain\Tag\TagType::SMART_TAG => __( 'Smart Tag', 'tagcore' ),
};
$status_label   = static fn( \ReturnTag\TagCore\Domain\Tag\TagStatus $status ): string => match ( $status ) {
	\ReturnTag\TagCore\Domain\Tag\TagStatus::UNREGISTERED => __( 'Unregistered', 'tagcore' ),
	\ReturnTag\TagCore\Domain\Tag\TagStatus::ACTIVE => __( 'Active', 'tagcore' ),
	\ReturnTag\TagCore\Domain\Tag\TagStatus::SUSPENDED => __( 'Suspended', 'tagcore' ),
	\ReturnTag\TagCore\Domain\Tag\TagStatus::RETIRED => __( 'Retired', 'tagcore' ),
};
$conversation_status_label = static fn( ConversationStatus $status ): string => match ( $status ) {
	ConversationStatus::PENDING_VERIFICATION => __( 'Pending verification', 'tagcore' ),
	ConversationStatus::OPEN => __( 'Open', 'tagcore' ),
	ConversationStatus::CLOSED => __( 'Closed', 'tagcore' ),
	ConversationStatus::BLOCKED => __( 'Blocked', 'tagcore' ),
	ConversationStatus::EXPIRED => __( 'Expired', 'tagcore' ),
};
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="referrer" content="no-referrer">
	<meta name="robots" content="noindex,nofollow,noarchive">
	<?php /* translators: %s: Current Owner Account page title. */ ?>
	<title><?php echo esc_html( sprintf( __( '%s - ForgeTag', 'tagcore' ), $view->title ) ); ?></title>
	<?php wp_print_styles( ReturnTag\TagCore\PublicSite\TagEntryLinkBlock::STYLE_HANDLE ); ?>
</head>
<body class="returntag-entry-page returntag-account-page">
	<header class="returntag-entry-page__header returntag-account__header">
		<a class="returntag-entry-page__wordmark" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php esc_html_e( 'ForgeTag', 'tagcore' ); ?>
		</a>
		<?php if ( AccountRoute::SIGN_IN !== $view->route ) : ?>
			<nav class="returntag-account__navigation" aria-label="<?php esc_attr_e( 'Owner account', 'tagcore' ); ?>">
				<a class="returntag-account__overview-link" href="<?php echo esc_url( $view->overview_url ); ?>">
					<?php esc_html_e( 'My Tags', 'tagcore' ); ?>
				</a>
				<a class="returntag-account__overview-link" href="<?php echo esc_url( $view->urls->conversations() ); ?>">
					<?php esc_html_e( 'Conversations', 'tagcore' ); ?>
				</a>
			</nav>
		<?php endif; ?>
	</header>

	<main class="returntag-entry-page__main returntag-account__main">
		<?php if ( AccountFormState::UNAVAILABLE === $view->form->state ) : ?>
			<section class="returntag-account__panel" aria-labelledby="returntag-account-title">
				<p class="returntag-entry__eyebrow"><?php esc_html_e( 'Owner account', 'tagcore' ); ?></p>
				<h1 id="returntag-account-title"><?php esc_html_e( 'Account unavailable', 'tagcore' ); ?></h1>
				<p class="returntag-entry__introduction"><?php esc_html_e( 'This account page is temporarily unavailable. Please try again later.', 'tagcore' ); ?></p>
			</section>
		<?php elseif ( AccountRoute::SIGN_IN === $view->route ) : ?>
			<section class="returntag-account__panel" aria-labelledby="returntag-account-title">
				<p class="returntag-entry__eyebrow"><?php esc_html_e( 'Owner account', 'tagcore' ); ?></p>
				<h1 id="returntag-account-title"><?php echo esc_html( $view->title ); ?></h1>
				<p class="returntag-entry__introduction"><?php esc_html_e( 'Use the email connected to your ForgeTag. We will send a six-digit verification code.', 'tagcore' ); ?></p>

				<?php if ( in_array( $view->form->state, array( AccountFormState::CODE_SENT, AccountFormState::VERIFICATION_INVALID ), true ) ) : ?>
					<div class="returntag-account__notice" role="status">
						<?php esc_html_e( 'If the address can receive a code, it will arrive shortly. Enter it below.', 'tagcore' ); ?>
					</div>
					<form class="returntag-entry__form" action="<?php echo esc_url( $view->action_url ); ?>" method="post">
						<input type="hidden" name="<?php echo esc_attr( AccountSignInFormHandler::NONCE_FIELD ); ?>" value="<?php echo esc_attr( $view->nonce ); ?>">
						<input type="hidden" name="<?php echo esc_attr( AccountSignInFormHandler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( AccountSignInFormHandler::VERIFY_ACTION ); ?>">
						<input type="hidden" name="<?php echo esc_attr( AccountSignInFormHandler::EMAIL_FIELD ); ?>" value="<?php echo esc_attr( $view->form->email ); ?>">
						<div class="returntag-entry__field">
							<label for="returntag-account-code"><?php esc_html_e( 'Verification code', 'tagcore' ); ?></label>
							<input class="returntag-entry__input returntag-account__code" id="returntag-account-code" name="<?php echo esc_attr( AccountSignInFormHandler::CODE_FIELD ); ?>" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required <?php echo AccountFormState::VERIFICATION_INVALID === $view->form->state ? 'aria-invalid="true" aria-describedby="returntag-account-code-error"' : ''; ?>>
							<?php if ( AccountFormState::VERIFICATION_INVALID === $view->form->state ) : ?>
								<p class="returntag-entry__error" id="returntag-account-code-error"><?php esc_html_e( 'The code could not be verified. Request a new code if it has expired.', 'tagcore' ); ?></p>
							<?php endif; ?>
						</div>
						<button class="returntag-entry__submit" type="submit"><?php esc_html_e( 'Verify and sign in', 'tagcore' ); ?></button>
					</form>
					<a class="returntag-account__text-link" href="<?php echo esc_url( $view->action_url ); ?>"><?php esc_html_e( 'Use a different email', 'tagcore' ); ?></a>
				<?php else : ?>
					<form class="returntag-entry__form" action="<?php echo esc_url( $view->action_url ); ?>" method="post">
						<input type="hidden" name="<?php echo esc_attr( AccountSignInFormHandler::NONCE_FIELD ); ?>" value="<?php echo esc_attr( $view->nonce ); ?>">
						<input type="hidden" name="<?php echo esc_attr( AccountSignInFormHandler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( AccountSignInFormHandler::REQUEST_ACTION ); ?>">
						<div class="returntag-entry__field">
							<label for="returntag-account-email"><?php esc_html_e( 'Email address', 'tagcore' ); ?></label>
							<input class="returntag-entry__input returntag-account__email" id="returntag-account-email" name="<?php echo esc_attr( AccountSignInFormHandler::EMAIL_FIELD ); ?>" type="email" inputmode="email" autocomplete="email" maxlength="254" required <?php echo AccountFormState::INVALID_EMAIL === $view->form->state ? 'aria-invalid="true" aria-describedby="returntag-account-email-error"' : ''; ?>>
							<?php if ( AccountFormState::INVALID_EMAIL === $view->form->state ) : ?>
								<p class="returntag-entry__error" id="returntag-account-email-error"><?php esc_html_e( 'Enter a valid email address.', 'tagcore' ); ?></p>
							<?php endif; ?>
						</div>
						<button class="returntag-entry__submit" type="submit"><?php esc_html_e( 'Send verification code', 'tagcore' ); ?></button>
					</form>
				<?php endif; ?>
			</section>
		<?php elseif ( AccountRoute::OVERVIEW === $view->route ) : ?>
			<section class="returntag-account__dashboard" aria-labelledby="returntag-account-title">
				<p class="returntag-entry__eyebrow"><?php esc_html_e( 'Owner account', 'tagcore' ); ?></p>
				<h1 id="returntag-account-title"><?php echo esc_html( $view->title ); ?></h1>
				<p class="returntag-entry__introduction"><?php esc_html_e( 'View the ForgeTags currently connected to this account.', 'tagcore' ); ?></p>

				<?php if ( null === $view->collection || OwnerTagAccessState::READY !== $view->collection->state || null === $view->collection->page ) : ?>
					<p class="returntag-account__empty"><?php esc_html_e( 'This Tag is unavailable.', 'tagcore' ); ?></p>
				<?php elseif ( array() === $view->collection->page->items ) : ?>
					<p class="returntag-account__empty"><?php esc_html_e( 'No ForgeTags are connected to this account yet.', 'tagcore' ); ?></p>
				<?php else : ?>
					<ul class="returntag-account__tag-list">
						<?php foreach ( $view->collection->page->items as $record ) : ?>
							<?php $tag = $record->data; ?>
							<li>
								<a class="returntag-account__tag-card" href="<?php echo esc_url( $view->urls->tag( TagId::from_canonical( $tag->tag_id ) ) ); ?>">
									<span class="returntag-account__tag-type"><?php echo esc_html( $tag_type_label( $tag->tag_type ) ); ?></span>
									<strong><?php echo esc_html( $tag->item_name ?? $tag->public_label ?? __( 'Unnamed item', 'tagcore' ) ); ?></strong>
									<span class="returntag-account__tag-meta"><?php echo esc_html( $tag->tag_id . ' · ' . $status_label( $tag->tag_status ) ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if ( null !== $view->collection->page->next_cursor ) : ?>
						<a class="returntag-account__next" href="<?php echo esc_url( $view->urls->overview( $view->collection->page->next_cursor ) ); ?>"><?php esc_html_e( 'View more Tags', 'tagcore' ); ?></a>
					<?php endif; ?>
				<?php endif; ?>
			</section>
		<?php elseif ( AccountRoute::CONVERSATIONS === $view->route ) : ?>
			<section class="returntag-account__dashboard" aria-labelledby="returntag-account-title">
				<p class="returntag-entry__eyebrow"><?php esc_html_e( 'Private recovery relay', 'tagcore' ); ?></p>
				<h1 id="returntag-account-title"><?php echo esc_html( $view->title ); ?></h1>
				<p class="returntag-entry__introduction"><?php esc_html_e( 'Review status and recent activity, then continue eligible conversations in the private Secure Reply workspace.', 'tagcore' ); ?></p>

				<?php if ( AccountConversationFeedback::UNAVAILABLE === $view->conversation_feedback ) : ?>
					<div class="returntag-account__feedback" role="status" aria-live="polite">
						<?php esc_html_e( 'That conversation cannot be continued securely. Refresh the page or try again later.', 'tagcore' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( null === $view->conversations || OwnerConversationAccessState::READY !== $view->conversations->state ) : ?>
					<p class="returntag-account__empty"><?php esc_html_e( 'Recovery conversations are unavailable.', 'tagcore' ); ?></p>
				<?php elseif ( array() === $view->conversations->items ) : ?>
					<p class="returntag-account__empty"><?php esc_html_e( 'No recovery conversations are available for this account.', 'tagcore' ); ?></p>
				<?php else : ?>
					<ul class="returntag-account__conversation-list">
						<?php foreach ( $view->conversations->items as $conversation ) : ?>
							<li>
								<article class="returntag-account__conversation-card">
									<div class="returntag-account__conversation-heading">
										<span class="returntag-account__conversation-label"><?php esc_html_e( 'Recovery conversation', 'tagcore' ); ?></span>
										<strong><?php echo esc_html( $conversation_status_label( $conversation->status ) ); ?></strong>
									</div>
									<dl class="returntag-account__conversation-activity">
										<div>
											<dt><?php esc_html_e( 'Last activity', 'tagcore' ); ?></dt>
											<dd><?php echo esc_html( wp_date( (string) get_option( 'date_format' ), $conversation->last_activity_at->getTimestamp() ) ); ?></dd>
										</div>
										<div>
											<dt><?php esc_html_e( 'Started', 'tagcore' ); ?></dt>
											<dd><?php echo esc_html( wp_date( (string) get_option( 'date_format' ), $conversation->created_at->getTimestamp() ) ); ?></dd>
										</div>
									</dl>
									<?php if ( $conversation->can_continue ) : ?>
										<form action="<?php echo esc_url( $view->urls->conversations() ); ?>" method="post">
											<input type="hidden" name="<?php echo esc_attr( AccountConversationFormHandler::NONCE_FIELD ); ?>" value="<?php echo esc_attr( $view->conversation_nonce ); ?>">
											<input type="hidden" name="<?php echo esc_attr( AccountConversationFormHandler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( AccountConversationFormHandler::CONTINUE_ACTION ); ?>">
											<input type="hidden" name="<?php echo esc_attr( AccountConversationFormHandler::CONVERSATION_FIELD ); ?>" value="<?php echo esc_attr( (string) $conversation->conversation_id ); ?>">
											<button class="returntag-entry__submit returntag-account__conversation-submit" type="submit"><?php esc_html_e( 'Continue securely', 'tagcore' ); ?></button>
										</form>
									<?php endif; ?>
								</article>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		<?php else : ?>
			<section class="returntag-account__dashboard" aria-labelledby="returntag-account-title">
				<a class="returntag-account__text-link" href="<?php echo esc_url( $view->overview_url ); ?>"><?php esc_html_e( 'Back to My Tags', 'tagcore' ); ?></a>
				<?php if ( null === $view->detail || OwnerTagAccessState::READY !== $view->detail->state || null === $view->detail->tag ) : ?>
					<h1 id="returntag-account-title"><?php esc_html_e( 'Tag unavailable', 'tagcore' ); ?></h1>
					<p class="returntag-account__empty"><?php esc_html_e( 'This Tag is unavailable.', 'tagcore' ); ?></p>
				<?php else : ?>
					<?php $tag = $view->detail->tag->data; ?>
					<p class="returntag-entry__eyebrow"><?php echo esc_html( $tag_type_label( $tag->tag_type ) ); ?></p>
					<h1 id="returntag-account-title"><?php echo esc_html( $tag->item_name ?? $tag->public_label ?? __( 'Unnamed item', 'tagcore' ) ); ?></h1>
					<dl class="returntag-account__details">
						<div class="returntag-account__detail"><dt><?php esc_html_e( 'Tag ID', 'tagcore' ); ?></dt><dd><?php echo esc_html( $tag->tag_id ); ?></dd></div>
						<div class="returntag-account__detail"><dt><?php esc_html_e( 'Status', 'tagcore' ); ?></dt><dd><?php echo esc_html( $status_label( $tag->tag_status ) ); ?></dd></div>
						<div class="returntag-account__detail"><dt><?php esc_html_e( 'Private item name', 'tagcore' ); ?></dt><dd><?php echo esc_html( $tag->item_name ?? __( 'Not added', 'tagcore' ) ); ?></dd></div>
						<div class="returntag-account__detail"><dt><?php esc_html_e( 'Public label', 'tagcore' ); ?></dt><dd><?php echo esc_html( $tag->public_label ?? __( 'Not added', 'tagcore' ) ); ?></dd></div>
						<div class="returntag-account__detail"><dt><?php esc_html_e( 'Lost Mode', 'tagcore' ); ?></dt><dd><?php echo esc_html( $tag->lost_mode ? __( 'On', 'tagcore' ) : __( 'Off', 'tagcore' ) ); ?></dd></div>
						<?php if ( $tag->lost_mode && null !== $tag->lost_message ) : ?>
							<div class="returntag-account__detail"><dt><?php esc_html_e( 'Lost Message', 'tagcore' ); ?></dt><dd><?php echo esc_html( $tag->lost_message ); ?></dd></div>
						<?php endif; ?>
						<div class="returntag-account__detail"><dt><?php esc_html_e( 'Last updated', 'tagcore' ); ?></dt><dd><?php echo esc_html( wp_date( (string) get_option( 'date_format' ), $tag->updated_at->getTimestamp() ) ); ?></dd></div>
					</dl>
					<?php if ( \ReturnTag\TagCore\Domain\Tag\TagStatus::ACTIVE !== $tag->tag_status ) : ?>
						<p class="returntag-account__notice"><?php esc_html_e( 'This Tag is read-only in its current status.', 'tagcore' ); ?></p>
					<?php else : ?>
						<?php if ( AccountTagMutationState::NONE !== $view->tag_feedback->state ) : ?>
							<div class="returntag-account__feedback" role="status" aria-live="polite">
								<?php
								$message = match ( $view->tag_feedback->state ) {
									AccountTagMutationState::UPDATED => __( 'Your Tag settings were updated.', 'tagcore' ),
									AccountTagMutationState::UNCHANGED => __( 'No changes were needed.', 'tagcore' ),
									AccountTagMutationState::SMART_SETUP_ACKNOWLEDGED => __( 'Smart Setup acknowledgement saved.', 'tagcore' ),
									AccountTagMutationState::INVALID_METADATA => __( 'Check the item name and public label. Use plain text up to 191 characters.', 'tagcore' ),
									AccountTagMutationState::INVALID_LOST_MESSAGE => __( 'Check the Lost Message. Use approved plain text up to 500 characters and do not include credentials, financial details, identity documents, or a complete home address.', 'tagcore' ),
									AccountTagMutationState::THROTTLED => __( 'Too many changes were attempted. Please try again later.', 'tagcore' ),
									AccountTagMutationState::UNAVAILABLE => __( 'This Tag action is unavailable.', 'tagcore' ),
									AccountTagMutationState::NONE => '',
								};
								echo esc_html( $message );
								?>
							</div>
						<?php endif; ?>

						<div class="returntag-account__edit-grid">
							<section class="returntag-account__edit-card" aria-labelledby="returntag-account-metadata-title">
								<p class="returntag-entry__eyebrow"><?php esc_html_e( 'Item details', 'tagcore' ); ?></p>
								<h2 id="returntag-account-metadata-title"><?php esc_html_e( 'Name your item', 'tagcore' ); ?></h2>
								<form class="returntag-entry__form" action="<?php echo esc_url( $view->urls->tag( TagId::from_canonical( $tag->tag_id ) ) ); ?>" method="post">
									<input type="hidden" name="<?php echo esc_attr( AccountTagMutationFormHandler::NONCE_FIELD ); ?>" value="<?php echo esc_attr( $view->tag_nonce ); ?>">
									<input type="hidden" name="<?php echo esc_attr( AccountTagMutationFormHandler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( AccountTagMutationFormHandler::UPDATE_METADATA ); ?>">
									<div class="returntag-entry__field">
										<label for="returntag-item-name"><?php esc_html_e( 'Private item name', 'tagcore' ); ?></label>
										<input class="returntag-entry__input" id="returntag-item-name" name="<?php echo esc_attr( AccountTagMutationFormHandler::ITEM_NAME_FIELD ); ?>" type="text" maxlength="191" value="<?php echo esc_attr( $tag->item_name ?? '' ); ?>" aria-describedby="returntag-item-name-help">
										<p class="returntag-account__field-help" id="returntag-item-name-help"><?php esc_html_e( 'Only you can see this name.', 'tagcore' ); ?></p>
									</div>
									<div class="returntag-entry__field">
										<label for="returntag-public-label"><?php esc_html_e( 'Public label', 'tagcore' ); ?></label>
										<input class="returntag-entry__input" id="returntag-public-label" name="<?php echo esc_attr( AccountTagMutationFormHandler::PUBLIC_LABEL_FIELD ); ?>" type="text" maxlength="191" value="<?php echo esc_attr( $tag->public_label ?? '' ); ?>" aria-describedby="returntag-public-label-help">
										<p class="returntag-account__field-help" id="returntag-public-label-help"><?php esc_html_e( 'Finders may see this label. Do not include private contact details.', 'tagcore' ); ?></p>
									</div>
									<button class="returntag-entry__submit" type="submit"><?php esc_html_e( 'Save item details', 'tagcore' ); ?></button>
								</form>
							</section>

							<section class="returntag-account__edit-card" aria-labelledby="returntag-account-lost-title">
								<p class="returntag-entry__eyebrow"><?php esc_html_e( 'Recovery settings', 'tagcore' ); ?></p>
								<h2 id="returntag-account-lost-title"><?php esc_html_e( 'Lost Mode', 'tagcore' ); ?></h2>
								<form class="returntag-entry__form" action="<?php echo esc_url( $view->urls->tag( TagId::from_canonical( $tag->tag_id ) ) ); ?>" method="post">
									<input type="hidden" name="<?php echo esc_attr( AccountTagMutationFormHandler::NONCE_FIELD ); ?>" value="<?php echo esc_attr( $view->tag_nonce ); ?>">
									<input type="hidden" name="<?php echo esc_attr( AccountTagMutationFormHandler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( AccountTagMutationFormHandler::UPDATE_LOST_STATE ); ?>">
									<label class="returntag-account__toggle" for="returntag-lost-mode">
										<input class="returntag-account__toggle-input" id="returntag-lost-mode" name="<?php echo esc_attr( AccountTagMutationFormHandler::LOST_MODE_FIELD ); ?>" type="checkbox" value="1" <?php checked( $tag->lost_mode ); ?>>
										<span class="returntag-account__toggle-copy"><strong><?php esc_html_e( 'Mark this item as lost', 'tagcore' ); ?></strong><small class="returntag-account__toggle-help"><?php esc_html_e( 'Finder contact remains available even when Lost Mode is off.', 'tagcore' ); ?></small></span>
									</label>
									<div class="returntag-entry__field">
										<label for="returntag-lost-message"><?php esc_html_e( 'Lost Message', 'tagcore' ); ?> <span><?php esc_html_e( '— optional', 'tagcore' ); ?></span></label>
										<textarea class="returntag-entry__input returntag-account__textarea" id="returntag-lost-message" name="<?php echo esc_attr( AccountTagMutationFormHandler::LOST_MESSAGE_FIELD ); ?>" maxlength="500" rows="5" aria-describedby="returntag-lost-message-help"><?php echo esc_textarea( $tag->lost_message ?? '' ); ?></textarea>
										<p class="returntag-account__field-help" id="returntag-lost-message-help"><?php esc_html_e( 'Finders may see this message while Lost Mode is on. Do not include passwords, codes, financial details, identity documents, or a complete home address.', 'tagcore' ); ?></p>
									</div>
									<button class="returntag-entry__submit" type="submit"><?php esc_html_e( 'Save recovery settings', 'tagcore' ); ?></button>
								</form>
							</section>
						</div>

						<?php if ( \ReturnTag\TagCore\Domain\Tag\TagType::SMART_TAG === $tag->tag_type ) : ?>
							<section class="returntag-account__edit-card returntag-account__smart-setup" aria-labelledby="returntag-account-smart-title">
								<p class="returntag-entry__eyebrow"><?php esc_html_e( 'Smart Tag guide', 'tagcore' ); ?></p>
								<h2 id="returntag-account-smart-title"><?php esc_html_e( 'Smart Setup acknowledgement', 'tagcore' ); ?></h2>
								<p><?php esc_html_e( 'This records only that you completed the static setup guide. ForgeTag does not verify pairing, location, battery, device state, or an Apple or Google account.', 'tagcore' ); ?></p>
								<?php if ( null === $tag->owner_pairing_ack_at ) : ?>
									<form action="<?php echo esc_url( $view->urls->tag( TagId::from_canonical( $tag->tag_id ) ) ); ?>" method="post">
										<input type="hidden" name="<?php echo esc_attr( AccountTagMutationFormHandler::NONCE_FIELD ); ?>" value="<?php echo esc_attr( $view->tag_nonce ); ?>">
										<input type="hidden" name="<?php echo esc_attr( AccountTagMutationFormHandler::ACTION_FIELD ); ?>" value="<?php echo esc_attr( AccountTagMutationFormHandler::ACKNOWLEDGE_SMART_SETUP ); ?>">
										<button class="returntag-entry__submit" type="submit"><?php esc_html_e( 'I completed the setup guide', 'tagcore' ); ?></button>
									</form>
								<?php else : ?>
									<p class="returntag-account__notice"><?php esc_html_e( 'Setup guide acknowledged. This is not pairing verification.', 'tagcore' ); ?></p>
								<?php endif; ?>
							</section>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	</main>
</body>
</html>
