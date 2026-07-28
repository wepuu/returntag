import type { BatchStatus } from './batch-generation';

export type BatchLifecycleAction = 'release' | 'suspend' | 'void';

export interface BatchLifecycleTagCounts {
	total: number;
	unregistered: number;
	active: number;
	suspended: number;
	retired: number;
}

export interface BatchLifecycleResponse {
	batch_id: number;
	batch_code: string;
	batch_status: BatchStatus;
	activation_enabled: boolean;
	global_activation_enabled: boolean;
	effective_activation_enabled: boolean;
	release_ready: boolean;
	tag_counts: BatchLifecycleTagCounts;
	updated_at: string;
	changed: boolean;
}

export function availableLifecycleActions(
	status: BatchStatus,
	releaseReady = true
): BatchLifecycleAction[] {
	switch ( status ) {
		case 'generated':
			return [ 'suspend', 'void' ];
		case 'exported':
			return releaseReady
				? [ 'release', 'suspend', 'void' ]
				: [ 'suspend', 'void' ];
		case 'released':
			return [ 'suspend', 'void' ];
		case 'suspended':
			return releaseReady ? [ 'release', 'void' ] : [ 'void' ];
		default:
			return [];
	}
}

export function canConfirmVoid(
	confirmation: string,
	batchCode: string
): boolean {
	return confirmation === batchCode;
}
