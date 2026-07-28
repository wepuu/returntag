import type { BatchStatus } from './batch-generation';

export type TagStatus = 'unregistered' | 'active' | 'suspended' | 'retired';

export interface BatchTagInventoryItem {
	tag_id: string;
	tag_status: TagStatus;
	created_at: string;
}

export interface BatchTagInventoryResponse {
	items: BatchTagInventoryItem[];
	next_cursor: string | null;
}

export function shouldShowBatchInventory(
	status: BatchStatus,
	generatedQuantity: number,
	requestedQuantity: number
): boolean {
	return (
		! [ 'draft', 'generating' ].includes( status ) &&
		requestedQuantity > 0 &&
		generatedQuantity === requestedQuantity
	);
}

export function appendInventoryItems(
	current: BatchTagInventoryItem[],
	incoming: BatchTagInventoryItem[]
): BatchTagInventoryItem[] {
	const seen = new Set( current.map( ( item ) => item.tag_id ) );

	return [
		...current,
		...incoming.filter( ( item ) => {
			if ( seen.has( item.tag_id ) ) {
				return false;
			}

			seen.add( item.tag_id );
			return true;
		} ),
	];
}
