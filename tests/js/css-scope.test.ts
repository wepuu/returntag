import {
	ADMIN_ROOT_CLASS,
	PUBLIC_ROOT_CLASS,
} from '../../plugin/tagcore/assets/src/shared/css-scope';

describe( 'stylesheet isolation', () => {
	it( 'uses ReturnTag-prefixed root classes', () => {
		expect( ADMIN_ROOT_CLASS ).toBe( 'returntag-admin' );
		expect( PUBLIC_ROOT_CLASS ).toBe( 'returntag-public' );
	} );
} );
