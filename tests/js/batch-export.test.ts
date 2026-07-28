import {
	appendExportRecords,
	canExportBatch,
	isReExport,
	readDownloadMetadata,
} from '../../plugin/tagcore/assets/src/admin/batch-export';

describe( 'RT-207 Batch export helpers', () => {
	it.each( [ 'generated', 'exported', 'released' ] as const )(
		'allows complete exportable state %s',
		( status ) => {
			expect( canExportBatch( status, 2500, 2500 ) ).toBe( true );
		}
	);

	it.each( [ 'draft', 'generating', 'suspended', 'voided' ] as const )(
		'rejects state %s',
		( status ) => {
			expect( canExportBatch( status, 2500, 2500 ) ).toBe( false );
		}
	);

	it( 'rejects incomplete counts and distinguishes re-export states', () => {
		expect( canExportBatch( 'generated', 2499, 2500 ) ).toBe( false );
		expect( isReExport( 'generated' ) ).toBe( false );
		expect( isReExport( 'exported' ) ).toBe( true );
		expect( isReExport( 'released' ) ).toBe( true );
	} );

	it( 'deduplicates paginated export history by version', () => {
		const current = [
			{
				export_version: 2,
				row_count: 2,
				file_format: 'csv',
				file_checksum: 'a'.repeat( 64 ),
				created_by: 1,
				created_by_name: 'Operator',
				created_at: '2026-07-28T10:00:00+00:00',
			},
		];
		const incoming = [
			current[ 0 ],
			{
				...current[ 0 ],
				export_version: 1,
			},
		];

		expect( appendExportRecords( current, incoming ) ).toHaveLength( 2 );
	} );

	it( 'reads strict download metadata from response headers', () => {
		const response = responseWithHeaders( {
			'Content-Disposition':
				'attachment; filename="tagcore-RT-207-001-v3.csv"',
			'X-ReturnTag-Export-Version': '3',
			'X-ReturnTag-Row-Count': '2500',
			'X-ReturnTag-SHA256': 'b'.repeat( 64 ),
			'X-ReturnTag-Created-At': '2026-07-28T10:00:00+00:00',
			'X-ReturnTag-Batch-Status': 'exported',
		} );

		expect( readDownloadMetadata( response ) ).toEqual( {
			version: 3,
			rowCount: 2500,
			checksum: 'b'.repeat( 64 ),
			createdAt: '2026-07-28T10:00:00+00:00',
			batchStatus: 'exported',
			filename: 'tagcore-RT-207-001-v3.csv',
		} );
	} );

	it( 'rejects untrusted or incomplete response metadata', () => {
		const response = responseWithHeaders( {
			'Content-Disposition': 'attachment; filename="../unsafe.csv"',
			'X-ReturnTag-Export-Version': '1',
			'X-ReturnTag-Row-Count': '2',
			'X-ReturnTag-SHA256': 'c'.repeat( 64 ),
			'X-ReturnTag-Created-At': '2026-07-28T10:00:00+00:00',
			'X-ReturnTag-Batch-Status': 'exported',
		} );

		expect( () => readDownloadMetadata( response ) ).toThrow();
	} );
} );

function responseWithHeaders( values: Record< string, string > ): Response {
	const normalized = Object.fromEntries(
		Object.entries( values ).map( ( [ key, value ] ) => [
			key.toLowerCase(),
			value,
		] )
	);

	return {
		headers: {
			get: ( name: string ) => normalized[ name.toLowerCase() ] ?? null,
		},
	} as Response;
}
