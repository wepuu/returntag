import {
	availableLifecycleActions,
	canConfirmVoid,
} from '../../plugin/tagcore/assets/src/admin/batch-lifecycle';

describe( 'RT-208 Batch lifecycle helpers', () => {
	it( 'offers only approved state actions', () => {
		expect( availableLifecycleActions( 'draft' ) ).toEqual( [] );
		expect( availableLifecycleActions( 'generating' ) ).toEqual( [] );
		expect( availableLifecycleActions( 'generated' ) ).toEqual( [
			'suspend',
			'void',
		] );
		expect( availableLifecycleActions( 'exported' ) ).toEqual( [
			'release',
			'suspend',
			'void',
		] );
		expect( availableLifecycleActions( 'released' ) ).toEqual( [
			'suspend',
			'void',
		] );
		expect( availableLifecycleActions( 'suspended' ) ).toEqual( [
			'release',
			'void',
		] );
		expect( availableLifecycleActions( 'voided' ) ).toEqual( [] );
		expect( availableLifecycleActions( 'exported', false ) ).toEqual( [
			'suspend',
			'void',
		] );
		expect( availableLifecycleActions( 'suspended', false ) ).toEqual( [
			'void',
		] );
	} );

	it( 'requires an exact case-sensitive Batch Code for void', () => {
		expect( canConfirmVoid( 'RT-208-001', 'RT-208-001' ) ).toBe( true );
		expect( canConfirmVoid( 'rt-208-001', 'RT-208-001' ) ).toBe( false );
		expect( canConfirmVoid( ' RT-208-001 ', 'RT-208-001' ) ).toBe( false );
	} );
} );
