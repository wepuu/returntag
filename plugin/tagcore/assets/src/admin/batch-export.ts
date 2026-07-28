import type { BatchStatus } from './batch-generation';

export interface BatchExportRecord {
	export_version: number;
	row_count: number;
	file_format: string;
	file_checksum: string;
	created_by: number;
	created_by_name: string;
	created_at: string;
}

export interface BatchExportHistoryResponse {
	items: BatchExportRecord[];
	next_cursor: string | null;
}

export interface BatchExportDownloadMetadata {
	version: number;
	rowCount: number;
	checksum: string;
	createdAt: string;
	batchStatus: BatchStatus;
	filename: string;
}

export function canExportBatch(
	status: BatchStatus,
	generatedQuantity: number,
	requestedQuantity: number
): boolean {
	return (
		[ 'generated', 'exported', 'released' ].includes( status ) &&
		requestedQuantity > 0 &&
		generatedQuantity === requestedQuantity
	);
}

export function isReExport( status: BatchStatus ): boolean {
	return [ 'exported', 'released' ].includes( status );
}

export function appendExportRecords(
	current: BatchExportRecord[],
	incoming: BatchExportRecord[]
): BatchExportRecord[] {
	const seen = new Set( current.map( ( item ) => item.export_version ) );

	return [
		...current,
		...incoming.filter( ( item ) => {
			if ( seen.has( item.export_version ) ) {
				return false;
			}

			seen.add( item.export_version );
			return true;
		} ),
	];
}

export function readDownloadMetadata(
	response: Response
): BatchExportDownloadMetadata {
	const version = Number.parseInt(
		response.headers.get( 'X-ReturnTag-Export-Version' ) ?? '',
		10
	);
	const rowCount = Number.parseInt(
		response.headers.get( 'X-ReturnTag-Row-Count' ) ?? '',
		10
	);
	const checksum = response.headers.get( 'X-ReturnTag-SHA256' ) ?? '';
	const createdAt = response.headers.get( 'X-ReturnTag-Created-At' ) ?? '';
	const batchStatus =
		response.headers.get( 'X-ReturnTag-Batch-Status' ) ?? '';
	const disposition = response.headers.get( 'Content-Disposition' ) ?? '';
	const filenameMatch = disposition.match(
		/filename="(tagcore-[A-Za-z0-9-]+-v[1-9][0-9]*\.csv)"/
	);

	if (
		! Number.isSafeInteger( version ) ||
		version < 1 ||
		! Number.isSafeInteger( rowCount ) ||
		rowCount < 0 ||
		! /^[a-f0-9]{64}$/.test( checksum ) ||
		! createdAt ||
		! [ 'exported', 'released' ].includes( batchStatus ) ||
		! filenameMatch
	) {
		throw new Error( 'Batch export response metadata is invalid.' );
	}

	return {
		version,
		rowCount,
		checksum,
		createdAt,
		batchStatus: batchStatus as BatchStatus,
		filename: filenameMatch[ 1 ],
	};
}
