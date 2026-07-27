import {
	type BatchFormValues,
	validateBatchForm,
} from '../../plugin/tagcore/assets/src/admin/batch-form';

const validValues: BatchFormValues = {
	batch_code: 'RT-201-001',
	tag_type: 'smart_tag',
	model_code: 'SMART-01',
	smart_network: 'apple_find_my',
	requested_quantity: '2500',
	manufacturer: 'Northstar Manufacturing',
	sales_channel: 'direct',
	notes: 'Initial production run.',
};

describe( 'Batch form validation', () => {
	it( 'accepts canonical RT-201 input', () => {
		expect( validateBatchForm( validValues ) ).toEqual( {} );
	} );

	it( 'rejects invalid identifiers and quantities', () => {
		const errors = validateBatchForm( {
			...validValues,
			batch_code: 'RT 201',
			requested_quantity: '2.5',
		} );

		expect( errors ).toHaveProperty( 'batch_code' );
		expect( errors ).toHaveProperty( 'requested_quantity' );
	} );

	it( 'keeps smart-network metadata limited to Smart Tags', () => {
		const errors = validateBatchForm( {
			...validValues,
			tag_type: 'sticker',
		} );

		expect( errors ).toHaveProperty( 'smart_network' );
	} );

	it( 'measures notes by encoded bytes', () => {
		const errors = validateBatchForm( {
			...validValues,
			notes: '界'.repeat( 1667 ),
		} );

		expect( errors ).toHaveProperty( 'notes' );
	} );
} );
