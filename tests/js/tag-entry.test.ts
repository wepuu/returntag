import {
	normalizeManualTagId,
	shouldEnhanceEntryClick,
} from '../../plugin/tagcore/assets/src/public';

describe( 'TagCore manual Tag entry', () => {
	it( 'normalizes the approved human-friendly input', () => {
		expect( normalizeManualTagId( 'a7-r2 w9' ) ).toBe( 'A7R2W9' );
	} );

	it( 'enhances only an unmodified primary desktop click', () => {
		const click = {
			button: 0,
			ctrlKey: false,
			metaKey: false,
			shiftKey: false,
			altKey: false,
		};

		expect( shouldEnhanceEntryClick( click, true ) ).toBe( true );
		expect( shouldEnhanceEntryClick( click, false ) ).toBe( false );
		expect(
			shouldEnhanceEntryClick( { ...click, metaKey: true }, true )
		).toBe( false );
		expect(
			shouldEnhanceEntryClick( { ...click, ctrlKey: true }, true )
		).toBe( false );
		expect(
			shouldEnhanceEntryClick( { ...click, shiftKey: true }, true )
		).toBe( false );
		expect(
			shouldEnhanceEntryClick( { ...click, altKey: true }, true )
		).toBe( false );
		expect( shouldEnhanceEntryClick( { ...click, button: 1 }, true ) ).toBe(
			false
		);
	} );
} );
