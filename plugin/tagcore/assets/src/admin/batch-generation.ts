export type BatchStatus =
	| 'draft'
	| 'generating'
	| 'generated'
	| 'exported'
	| 'released'
	| 'suspended'
	| 'voided';

export type BatchGenerationQueueState =
	| 'idle'
	| 'scheduled'
	| 'running'
	| 'needs_attention'
	| 'complete'
	| 'unavailable';

export interface BatchGenerationProgress {
	batch_id: number;
	batch_status: BatchStatus;
	requested_quantity: number;
	generated_quantity: number;
	remaining_quantity: number;
	failed_quantity: number;
	progress_percent: number;
	started_at: string | null;
	completed_at: string | null;
	last_progress_at: string;
	queue_state: BatchGenerationQueueState;
	can_start: boolean;
	can_retry: boolean;
	poll_after_ms: number;
}

export function calculateProgressPercent(
	generatedQuantity: number,
	requestedQuantity: number
): number {
	if ( requestedQuantity <= 0 ) {
		return 0;
	}

	return Math.min(
		100,
		Math.max(
			0,
			Math.floor( ( generatedQuantity * 100 ) / requestedQuantity )
		)
	);
}

export function shouldPollGeneration(
	progress: BatchGenerationProgress | null,
	documentVisible: boolean
): boolean {
	return Boolean(
		documentVisible &&
			progress &&
			[ 'scheduled', 'running' ].includes( progress.queue_state ) &&
			progress.batch_status === 'generating'
	);
}

export function generationPollDelay( pollAfterMs: number ): number {
	return Math.max( 3000, Math.min( pollAfterMs || 3000, 30_000 ) );
}
