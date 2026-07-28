import {
	appendInventoryItems,
	shouldShowBatchInventory,
	type BatchTagInventoryItem,
} from '../../plugin/tagcore/assets/src/admin/batch-inventory';

const item = ( tagId: string ): BatchTagInventoryItem => ( {
	tag_id: tagId,
	tag_status: 'unregistered',
	created_at: '2026-07-27T09:00:00+00:00',
} );

describe( 'Batch Tag inventory', () => {
	it.each( [ 'generated', 'exported', 'released', 'suspended', 'voided' ] )(
		'shows a complete %s inventory',
		( status ) => {
			expect(
				shouldShowBatchInventory(
					status as Parameters<
						typeof shouldShowBatchInventory
					>[ 0 ],
					2500,
					2500
				)
			).toBe( true );
		}
	);

	it.each( [
		[ 'draft', 0 ],
		[ 'generating', 2499 ],
		[ 'exported', 2499 ],
	] )( 'hides incomplete %s inventory', ( status, generatedQuantity ) => {
		expect(
			shouldShowBatchInventory(
				status as Parameters< typeof shouldShowBatchInventory >[ 0 ],
				generatedQuantity as number,
				2500
			)
		).toBe( false );
	} );

	it( 'appends pages without duplicate Tag IDs', () => {
		expect(
			appendInventoryItems(
				[ item( '234567' ), item( '234568' ) ],
				[ item( '234568' ), item( '234569' ) ]
			).map( ( entry ) => entry.tag_id )
		).toEqual( [ '234567', '234568', '234569' ] );
	} );
} );
