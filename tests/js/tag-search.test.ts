import {
	appendTagSearchItems,
	buildTagSearchPath,
	normalizeTagId,
	type TagSearchItem,
	validateTagSearch,
} from '../../plugin/tagcore/assets/src/admin/tag-search';

const item = ( tagId: string ): TagSearchItem => ( {
	tag_id: tagId,
	batch_id: 7,
	batch_code: 'RT-209',
	batch_status: 'released',
	batch_activation_enabled: true,
	activation_availability: 'eligible',
	tag_type: 'classic_tag',
	model_code: 'CLASSIC-01',
	tag_status: 'unregistered',
	lost_mode: false,
	activated_at: null,
	created_at: '2026-07-29T00:00:00+00:00',
	updated_at: '2026-07-29T00:00:00+00:00',
} );

describe( 'Tag search', () => {
	it( 'normalizes the canonical public Tag ID', () => {
		expect( normalizeTagId( ' 2a-b c34 ' ) ).toBe( '2ABC34' );
	} );

	it( 'requires one valid exact anchor', () => {
		expect(
			validateTagSearch( {
				mode: 'tag_id',
				tagId: 'ABC10O',
				batchCode: '',
				tagStatus: '',
			} )
		).toHaveProperty( 'tag_id' );
		expect(
			validateTagSearch( {
				mode: 'batch',
				tagId: '',
				batchCode: ' ',
				tagStatus: '',
			} )
		).toHaveProperty( 'batch_code' );
	} );

	it( 'binds the request to the selected exact mode', () => {
		expect(
			buildTagSearchPath( '/tagcore/v1', {
				mode: 'batch',
				tagId: '',
				batchCode: 'RT-209',
				tagStatus: 'active',
			} )
		).toBe(
			'/tagcore/v1/tags?mode=batch&per_page=50&batch_code=RT-209&tag_status=active'
		);
	} );

	it( 'appends pages without duplicate Tag IDs', () => {
		expect(
			appendTagSearchItems(
				[ item( '234567' ), item( '234568' ) ],
				[ item( '234568' ), item( '234569' ) ]
			).map( ( entry ) => entry.tag_id )
		).toEqual( [ '234567', '234568', '234569' ] );
	} );
} );
