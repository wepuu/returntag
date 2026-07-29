export type TagSearchMode = 'tag_id' | 'batch';
export type TagStatus = 'unregistered' | 'active' | 'suspended' | 'retired';
export type TagType = 'sticker' | 'classic_tag' | 'smart_tag';
export type BatchStatus =
	| 'draft'
	| 'generating'
	| 'generated'
	| 'exported'
	| 'released'
	| 'suspended'
	| 'voided';
export type TagActivationAvailability =
	| 'eligible'
	| 'awaiting_release'
	| 'paused_globally'
	| 'blocked_batch_control'
	| 'blocked_batch_suspended'
	| 'blocked_batch_voided'
	| 'blocked_tag_suspended'
	| 'blocked_tag_retired'
	| 'existing_activation_retained'
	| 'data_inconsistent';

export interface TagSearchValues {
	mode: TagSearchMode;
	tagId: string;
	batchCode: string;
	tagStatus: '' | TagStatus;
}

export interface TagSearchItem {
	tag_id: string;
	batch_id: number;
	batch_code: string;
	batch_status: BatchStatus;
	batch_activation_enabled: boolean;
	activation_availability: TagActivationAvailability;
	tag_type: TagType;
	model_code: string | null;
	tag_status: TagStatus;
	lost_mode: boolean;
	activated_at: string | null;
	created_at: string;
	updated_at: string;
}

export interface TagSearchResponse {
	items: TagSearchItem[];
	next_cursor: string | null;
	context: {
		global_activation_enabled: boolean;
	};
}

export function normalizeTagId( value: string ): string {
	return value.toUpperCase().replace( /[\s-]+/gu, '' );
}

export function validateTagSearch(
	values: TagSearchValues
): Record< string, string > {
	if ( values.mode === 'tag_id' ) {
		return /^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}$/u.test(
			normalizeTagId( values.tagId )
		)
			? {}
			: {
					tag_id: 'Enter a six-character Tag ID using the approved alphabet.',
			  };
	}

	const batchCode = values.batchCode.trim();

	if (
		batchCode.length < 1 ||
		batchCode.length > 191 ||
		! /^[\x20-\x7E]+$/u.test( batchCode )
	) {
		return { batch_code: 'Enter an exact valid Batch Code.' };
	}

	return {};
}

export function appendTagSearchItems(
	current: TagSearchItem[],
	next: TagSearchItem[]
): TagSearchItem[] {
	const known = new Set( current.map( ( item ) => item.tag_id ) );

	return [
		...current,
		...next.filter( ( item ) => {
			if ( known.has( item.tag_id ) ) {
				return false;
			}

			known.add( item.tag_id );
			return true;
		} ),
	];
}

export function buildTagSearchPath(
	restPath: string,
	values: TagSearchValues,
	cursor: string | null = null
): string {
	const query = new URLSearchParams( {
		mode: values.mode,
		per_page: '50',
	} );

	if ( values.mode === 'tag_id' ) {
		query.set( 'tag_id', normalizeTagId( values.tagId ) );
	} else {
		query.set( 'batch_code', values.batchCode.trim() );

		if ( values.tagStatus ) {
			query.set( 'tag_status', values.tagStatus );
		}

		if ( cursor ) {
			query.set( 'cursor', cursor );
		}
	}

	return `${ restPath }/tags?${ query.toString() }`;
}
