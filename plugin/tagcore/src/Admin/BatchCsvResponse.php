<?php
/**
 * Prepared Batch CSV download response.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use ReturnTag\TagCore\Application\Batch\BatchExportResult;
use WP_REST_Response;

/**
 * Owns one temporary artifact until REST streams or abandons it.
 *
 * Keeping the artifact on the response object prevents REST response-data
 * normalization from converting the private download carrier into an array.
 */
final class BatchCsvResponse extends WP_REST_Response {
	/**
	 * Create a download response.
	 *
	 * @param BatchExportResult $result Audited export result.
	 */
	public function __construct( public readonly BatchExportResult $result ) {
		parent::__construct( array() );
	}

	/**
	 * Remove an artifact that never reached REST serving.
	 */
	public function __destruct() {
		$this->result->artifact->cleanup();
	}

	/**
	 * Return a safe attachment filename.
	 */
	public function filename(): string {
		return sprintf(
			'tagcore-%s-v%d.csv',
			$this->result->batch_code,
			$this->result->record->data->export_version
		);
	}

	/**
	 * Stream and remove the exact audited bytes.
	 */
	public function serve(): void {
		$this->result->artifact->stream();
	}
}
