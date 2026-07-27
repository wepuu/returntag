import { __ } from '@wordpress/i18n';

export type TagType = 'sticker' | 'classic_tag' | 'smart_tag';
export type SmartNetwork =
	| 'none'
	| 'apple_find_my'
	| 'google_find_hub'
	| 'other';
export type SalesChannel = 'direct' | 'amazon' | 'mixed' | 'other';

export interface BatchFormValues {
	batch_code: string;
	tag_type: TagType;
	model_code: string;
	smart_network: SmartNetwork;
	requested_quantity: string;
	manufacturer: string;
	sales_channel: SalesChannel;
	notes: string;
}

/**
 * Mirror the safe client-visible subset of server-side Batch validation.
 *
 * @param values Current form values.
 */
export function validateBatchForm(
	values: BatchFormValues
): Record< string, string > {
	const errors: Record< string, string > = {};

	if ( ! /^[A-Za-z0-9][A-Za-z0-9-]{0,190}$/.test( values.batch_code ) ) {
		errors.batch_code = __(
			'Use 1–191 letters, numbers, or hyphens.',
			'tagcore'
		);
	}

	if (
		! /^[1-9][0-9]*$/.test( values.requested_quantity ) ||
		Number( values.requested_quantity ) > 4_294_967_295
	) {
		errors.requested_quantity = __(
			'Enter a whole quantity of at least 1.',
			'tagcore'
		);
	}

	if (
		values.model_code.length > 191 ||
		( values.model_code !== '' &&
			! /^[\x20-\x7E]+$/.test( values.model_code ) )
	) {
		errors.model_code = __(
			'Use no more than 191 ASCII characters.',
			'tagcore'
		);
	}

	if ( Array.from( values.manufacturer ).length > 191 ) {
		errors.manufacturer = __(
			'Use no more than 191 characters.',
			'tagcore'
		);
	}

	if ( values.tag_type !== 'smart_tag' && values.smart_network !== 'none' ) {
		errors.smart_network = __(
			'Smart Network applies only to Smart Tags.',
			'tagcore'
		);
	}

	if ( utf8ByteLength( values.notes ) > 5_000 ) {
		errors.notes = __(
			'Use no more than 5,000 bytes of notes.',
			'tagcore'
		);
	}

	return errors;
}

/**
 * Count encoded UTF-8 bytes without depending on a browser-only encoder.
 *
 * @param value Candidate text.
 */
function utf8ByteLength( value: string ): number {
	let bytes = 0;

	for ( const character of value ) {
		const codePoint = character.codePointAt( 0 ) ?? 0;

		if ( codePoint <= 0x7f ) {
			bytes += 1;
		} else if ( codePoint <= 0x7ff ) {
			bytes += 2;
		} else if ( codePoint <= 0xffff ) {
			bytes += 3;
		} else {
			bytes += 4;
		}
	}

	return bytes;
}
