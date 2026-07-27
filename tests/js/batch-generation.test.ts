import {
	type BatchGenerationProgress,
	calculateProgressPercent,
	generationPollDelay,
	shouldPollGeneration,
} from '../../plugin/tagcore/assets/src/admin/batch-generation';

function progress(
	overrides: Partial< BatchGenerationProgress > = {}
): BatchGenerationProgress {
	return {
		batch_id: 1,
		batch_status: 'generating',
		requested_quantity: 1000,
		generated_quantity: 400,
		remaining_quantity: 600,
		failed_quantity: 0,
		progress_percent: 40,
		started_at: '2026-07-27T09:00:00+00:00',
		completed_at: null,
		last_progress_at: '2026-07-27T09:01:00+00:00',
		queue_state: 'running',
		can_start: false,
		can_retry: false,
		poll_after_ms: 3000,
		...overrides,
	};
}

describe( 'Batch generation progress helpers', () => {
	it( 'calculates a bounded whole-number percentage', () => {
		expect( calculateProgressPercent( 405, 1000 ) ).toBe( 40 );
		expect( calculateProgressPercent( -1, 1000 ) ).toBe( 0 );
		expect( calculateProgressPercent( 1200, 1000 ) ).toBe( 100 );
		expect( calculateProgressPercent( 1, 0 ) ).toBe( 0 );
	} );

	it( 'polls only visible active generation states', () => {
		expect( shouldPollGeneration( progress(), true ) ).toBe( true );
		expect(
			shouldPollGeneration(
				progress( { queue_state: 'scheduled' } ),
				true
			)
		).toBe( true );
		expect( shouldPollGeneration( progress(), false ) ).toBe( false );
		expect(
			shouldPollGeneration(
				progress( {
					batch_status: 'generated',
					queue_state: 'complete',
				} ),
				true
			)
		).toBe( false );
		expect(
			shouldPollGeneration(
				progress( { queue_state: 'needs_attention' } ),
				true
			)
		).toBe( false );
		expect( shouldPollGeneration( null, true ) ).toBe( false );
	} );

	it( 'enforces a safe polling interval', () => {
		expect( generationPollDelay( 0 ) ).toBe( 3000 );
		expect( generationPollDelay( 1000 ) ).toBe( 3000 );
		expect( generationPollDelay( 5000 ) ).toBe( 5000 );
		expect( generationPollDelay( 120_000 ) ).toBe( 30_000 );
	} );
} );
