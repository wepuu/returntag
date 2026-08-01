import './styles/public.css';

export { PUBLIC_ROOT_CLASS } from './shared/css-scope';

const DESKTOP_ENTRY_QUERY = '(min-width: 768px)';
const TAG_ID_PATTERN = /^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}$/;

export function normalizeManualTagId( value: string ): string {
	return value.replace( /[\s-]+/g, '' ).toUpperCase();
}

export function shouldEnhanceEntryClick(
	event: Pick<
		MouseEvent,
		'button' | 'ctrlKey' | 'metaKey' | 'shiftKey' | 'altKey'
	>,
	matchesDesktop: boolean
): boolean {
	return (
		matchesDesktop &&
		event.button === 0 &&
		! event.ctrlKey &&
		! event.metaKey &&
		! event.shiftKey &&
		! event.altKey
	);
}

function prepareForm( form: HTMLFormElement ): void {
	form.addEventListener( 'submit', ( event ) => {
		const input = form.querySelector< HTMLInputElement >(
			'input[name="returntag_tag_id"]'
		);

		if ( ! input ) {
			return;
		}

		const normalized = normalizeManualTagId( input.value );
		const valid = TAG_ID_PATTERN.test( normalized );

		input.setCustomValidity(
			valid ? '' : 'Enter a valid six-character Tag ID.'
		);

		if ( ! valid ) {
			event.preventDefault();
			input.reportValidity();
			return;
		}

		input.value = normalized;
	} );

	form.addEventListener( 'input', () => {
		const input = form.querySelector< HTMLInputElement >(
			'input[name="returntag_tag_id"]'
		);
		input?.setCustomValidity( '' );
	} );
}

function prepareEntry( root: HTMLElement ): void {
	const trigger = root.querySelector< HTMLAnchorElement >(
		'[data-returntag-tag-entry-trigger]'
	);
	const dialog = root.querySelector< HTMLDialogElement >(
		'[data-returntag-tag-entry-dialog]'
	);
	const close = dialog?.querySelector< HTMLButtonElement >(
		'[data-returntag-tag-entry-close]'
	);

	if ( ! trigger || ! dialog || typeof dialog.showModal !== 'function' ) {
		return;
	}

	trigger.addEventListener( 'click', ( event ) => {
		if (
			! shouldEnhanceEntryClick(
				event,
				window.matchMedia( DESKTOP_ENTRY_QUERY ).matches
			)
		) {
			return;
		}

		event.preventDefault();
		dialog.showModal();
		window.requestAnimationFrame( () => {
			dialog
				.querySelector< HTMLInputElement >(
					'input[name="returntag_tag_id"]'
				)
				?.focus();
		} );
	} );

	close?.addEventListener( 'click', () => dialog.close() );
	dialog.addEventListener( 'close', () => trigger.focus() );
}

function initializeManualTagEntry(): void {
	document
		.querySelectorAll< HTMLElement >( '[data-returntag-tag-entry]' )
		.forEach( prepareEntry );
	document
		.querySelectorAll< HTMLFormElement >(
			'[data-returntag-tag-entry-form]'
		)
		.forEach( prepareForm );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initializeManualTagEntry, {
		once: true,
	} );
} else {
	initializeManualTagEntry();
}
