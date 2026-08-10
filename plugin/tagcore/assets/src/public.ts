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

function prepareFinderReport( form: HTMLFormElement ): void {
	if ( form.classList.contains( 'is-enhanced' ) ) {
		return;
	}

	const steps = Array.from(
		form.querySelectorAll< HTMLFieldSetElement >(
			'[data-returntag-finder-step]'
		)
	);
	const message = form.querySelector< HTMLTextAreaElement >(
		'#returntag-finder-message'
	);
	const photo = form.querySelector< HTMLInputElement >(
		'#returntag-finder-photo'
	);

	if ( steps.length !== 2 || ! message || ! photo ) {
		return;
	}
	const progress = Array.from(
		form.parentElement?.querySelectorAll< HTMLLIElement >(
			'.returntag-public__finder-progress li'
		) ?? []
	);

	form.classList.add( 'is-enhanced' );
	const showStep = ( index: number, shouldFocus = true ): void => {
		steps.forEach( ( step, stepIndex ) => {
			step.hidden = stepIndex !== index;
		} );
		progress.forEach( ( item, itemIndex ) => {
			if ( itemIndex === index ) {
				item.setAttribute( 'aria-current', 'step' );
			} else {
				item.removeAttribute( 'aria-current' );
			}
		} );
		if ( shouldFocus ) {
			steps[ index ].querySelector< HTMLElement >( 'legend' )?.focus();
		}
	};

	form
		.querySelector< HTMLButtonElement >( '[data-returntag-finder-next]' )
		?.addEventListener( 'click', () => {
			const length = Array.from( message.value.trim() ).length;

			message.setCustomValidity(
				length === 0 || ( length >= 10 && length <= 500 )
					? ''
					: form.dataset.messageError ?? ''
			);
			if ( ! message.reportValidity() || ! photo.reportValidity() ) {
				return;
			}

			const messageReview = form.querySelector< HTMLElement >(
				'[data-returntag-finder-message-review]'
			);
			const photoReview = form.querySelector< HTMLElement >(
				'[data-returntag-finder-photo-review]'
			);
			if ( messageReview ) {
				if ( message.value.trim() ) {
					messageReview.textContent = message.value.trim();
				}
			}
			if ( photoReview ) {
				if ( photo.files?.[ 0 ]?.name ) {
					photoReview.textContent = photo.files[ 0 ].name;
				}
			}
			showStep( 1 );
		} );

	form
		.querySelector< HTMLButtonElement >( '[data-returntag-finder-back]' )
		?.addEventListener( 'click', () => showStep( 0 ) );
	message.addEventListener( 'input', () => message.setCustomValidity( '' ) );
	showStep( 0, false );
}

function initializeFinderReports(): void {
	document
		.querySelectorAll< HTMLFormElement >( '[data-returntag-finder-form]' )
		.forEach( prepareFinderReport );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initializeManualTagEntry, {
		once: true,
	} );
	document.addEventListener( 'DOMContentLoaded', initializeFinderReports, {
		once: true,
	} );
} else {
	initializeManualTagEntry();
	initializeFinderReports();
}
