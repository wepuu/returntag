<?php
/**
 * Batch administration REST adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Batch\BatchExportResult;
use ReturnTag\TagCore\Application\Batch\Exception\BatchExportArtifactFailure;
use ReturnTag\TagCore\Application\Batch\Exception\BatchExportIntegrityViolation;
use ReturnTag\TagCore\Application\Batch\Exception\BatchExportNotAllowed;
use ReturnTag\TagCore\Application\Batch\Exception\BatchExportNotFound;
use ReturnTag\TagCore\Application\Batch\CreateBatch;
use ReturnTag\TagCore\Application\Batch\CreateBatchInput;
use ReturnTag\TagCore\Application\Batch\BatchTagInventoryItem;
use ReturnTag\TagCore\Application\Batch\Exception\BatchTagInventoryNotFound;
use ReturnTag\TagCore\Application\Batch\Exception\BatchTagInventoryUnavailable;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationIntegrityViolation;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationNotAllowed;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationNotFound;
use ReturnTag\TagCore\Application\Batch\Exception\BatchGenerationQueueUnavailable;
use ReturnTag\TagCore\Application\Batch\Exception\BatchCodeAlreadyExists;
use ReturnTag\TagCore\Application\Batch\GetBatch;
use ReturnTag\TagCore\Application\Batch\GetBatchGenerationProgress;
use ReturnTag\TagCore\Application\Batch\ExportBatchCsv;
use ReturnTag\TagCore\Application\Batch\ListBatchTagInventory;
use ReturnTag\TagCore\Application\Batch\ListBatchExports;
use ReturnTag\TagCore\Application\Batch\ListBatches;
use ReturnTag\TagCore\Application\Batch\StartBatchGeneration;
use ReturnTag\TagCore\Application\Persistence\Pagination\BatchExportCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\BatchCursor;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\Record\BatchExportRecord;
use ReturnTag\TagCore\Application\Persistence\Record\BatchRecord;
use ReturnTag\TagCore\Application\Persistence\Record\BatchSummaryRecord;
use ReturnTag\TagCore\Domain\Tag\SmartNetwork;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;
use Throwable;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Maps authorized REST requests to Batch application services.
 */
final readonly class BatchRestController {
	private const NAMESPACE = 'tagcore/v1';

	/**
	 * Create the controller.
	 *
	 * @param CreateBatch                  $create_batch Create use case.
	 * @param StartBatchGeneration         $start_generation Start generation use case.
	 * @param GetBatchGenerationProgress   $get_generation_progress Progress query.
	 * @param ListBatchTagInventory        $list_batch_tags Inventory query.
	 * @param ExportBatchCsv               $export_batch Export command.
	 * @param ListBatchExports             $list_batch_exports Export history query.
	 * @param ListBatches                  $list_batches List query.
	 * @param GetBatch                     $get_batch Detail query.
	 * @param BatchTagInventoryCursorCodec $inventory_cursors REST cursor codec.
	 * @param BatchExportCursorCodec       $export_cursors Export-history cursor codec.
	 * @param BatchCsvDownloadStore        $csv_downloads Request-scoped download store.
	 * @param SchemaState                  $schema_state Schema readiness.
	 */
	public function __construct(
		private CreateBatch $create_batch,
		private StartBatchGeneration $start_generation,
		private GetBatchGenerationProgress $get_generation_progress,
		private ListBatchTagInventory $list_batch_tags,
		private ExportBatchCsv $export_batch,
		private ListBatchExports $list_batch_exports,
		private ListBatches $list_batches,
		private GetBatch $get_batch,
		private BatchTagInventoryCursorCodec $inventory_cursors,
		private BatchExportCursorCodec $export_cursors,
		private BatchCsvDownloadStore $csv_downloads,
		private SchemaState $schema_state
	) {
	}

	/**
	 * Register the approved Batch collection and item routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/batches',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => array( $this, 'authorize' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'authorize' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/batches/(?P<batch_id>[1-9][0-9]*)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'authorize' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/batches/(?P<batch_id>[1-9][0-9]*)/generation',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_generation_progress' ),
					'permission_callback' => array( $this, 'authorize' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'start_generation' ),
					'permission_callback' => array( $this, 'authorize' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/batches/(?P<batch_id>[1-9][0-9]*)/tags',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_tags' ),
				'permission_callback' => array( $this, 'authorize' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/batches/(?P<batch_id>[1-9][0-9]*)/exports',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_exports' ),
					'permission_callback' => array( $this, 'authorize' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'export_batch' ),
					'permission_callback' => array( $this, 'authorize' ),
				),
			)
		);
	}

	/**
	 * Require the dedicated Batch management capability.
	 */
	public function authorize(): bool|WP_Error {
		if ( current_user_can( Capability::MANAGE_BATCHES ) ) {
			return true;
		}

		return new WP_Error(
			'returntag_forbidden',
			__( 'You are not allowed to manage ReturnTag batches.', 'tagcore' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Apply no-store headers to every TagCore Batch REST response, including errors.
	 *
	 * @param WP_HTTP_Response $response REST response.
	 * @param WP_REST_Server   $server REST server.
	 * @param WP_REST_Request  $request REST request.
	 */
	public function apply_no_store_headers(
		WP_HTTP_Response $response,
		WP_REST_Server $server,
		WP_REST_Request $request
	): WP_HTTP_Response {
		unset( $server );

		if ( str_starts_with( $request->get_route(), '/' . self::NAMESPACE . '/batches' ) ) {
			$response->header( 'Cache-Control', 'no-store, private' );
			$response->header( 'Pragma', 'no-cache' );
		}

		return $response;
	}

	/**
	 * Stream a prepared CSV instead of JSON-encoding its private response object.
	 *
	 * @param bool             $served Whether another callback served the response.
	 * @param WP_HTTP_Response $response REST response.
	 * @param WP_REST_Request  $request REST request.
	 * @param WP_REST_Server   $server REST server.
	 */
	public function serve_csv_download(
		bool $served,
		WP_HTTP_Response $response,
		WP_REST_Request $request,
		WP_REST_Server $server
	): bool {
		unset( $server );

		if ( $served ) {
			return true;
		}

		unset( $request );

		$headers = $response->get_headers();

		if ( ! isset( $headers['X-ReturnTag-Download-Key'] ) || ! is_string( $headers['X-ReturnTag-Download-Key'] ) ) {
			return false;
		}

		$download = $this->csv_downloads->take( $headers['X-ReturnTag-Download-Key'] );

		if ( ! $download instanceof BatchCsvDownload ) {
			return false;
		}

		$download->serve();

		return true;
	}

	/**
	 * Return one bounded Batch summary page.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function list_items( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->schema_state->is_current() ) {
			return $this->schema_unavailable();
		}

		$per_page = $this->positive_integer( $request->get_param( 'per_page' ) ?? PageSize::DEFAULT );
		$cursor   = $request->get_param( 'cursor' );

		if ( null === $per_page || $per_page > PageSize::MAXIMUM ) {
			return $this->invalid_request( array( 'per_page' => __( 'Choose a page size between 1 and 100.', 'tagcore' ) ) );
		}

		$batch_cursor = null;

		if ( null !== $cursor && '' !== $cursor ) {
			$cursor_id = $this->positive_integer( $cursor );

			if ( null === $cursor_id ) {
				return $this->invalid_request( array( 'cursor' => __( 'The Batch cursor is invalid.', 'tagcore' ) ) );
			}

			$batch_cursor = new BatchCursor( $cursor_id );
		}

		try {
			$page = $this->list_batches->execute( $batch_cursor, new PageSize( $per_page ) );
		} catch ( Throwable ) {
			return $this->operation_failed();
		}

		$response = new WP_REST_Response(
			array(
				'items'       => array_map( array( $this, 'prepare_summary' ), $page->items ),
				'next_cursor' => $page->next_cursor?->batch_id,
			)
		);

		return $this->no_store( $response );
	}

	/**
	 * Return one Batch detail.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->schema_state->is_current() ) {
			return $this->schema_unavailable();
		}

		$batch_id = $this->positive_integer( $request->get_param( 'batch_id' ) );

		if ( null === $batch_id ) {
			return $this->invalid_request( array( 'batch_id' => __( 'The Batch identifier is invalid.', 'tagcore' ) ) );
		}

		try {
			$batch = $this->get_batch->execute( $batch_id );
		} catch ( Throwable ) {
			return $this->operation_failed();
		}

		if ( null === $batch ) {
			return new WP_Error(
				'returntag_batch_not_found',
				__( 'The requested Batch was not found.', 'tagcore' ),
				array( 'status' => 404 )
			);
		}

		return $this->no_store( new WP_REST_Response( $this->prepare_record( $batch ) ) );
	}

	/**
	 * Return one privacy-minimized page of generated Batch Tag IDs.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function list_tags( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->schema_state->is_current() ) {
			return $this->schema_unavailable();
		}

		$batch_id = $this->positive_integer( $request->get_param( 'batch_id' ) );
		$per_page = $this->positive_integer( $request->get_param( 'per_page' ) ?? PageSize::DEFAULT );

		if ( null === $batch_id ) {
			return $this->invalid_request( array( 'batch_id' => __( 'The Batch identifier is invalid.', 'tagcore' ) ) );
		}

		if ( null === $per_page || $per_page > PageSize::MAXIMUM ) {
			return $this->invalid_request( array( 'per_page' => __( 'Choose a page size between 1 and 100.', 'tagcore' ) ) );
		}

		$cursor_value = $request->get_param( 'cursor' );
		$cursor       = null;

		if ( null !== $cursor_value && '' !== $cursor_value ) {
			if ( ! is_string( $cursor_value ) ) {
				return $this->invalid_request( array( 'cursor' => __( 'The Tag inventory cursor is invalid.', 'tagcore' ) ) );
			}

			try {
				$cursor = $this->inventory_cursors->decode( $cursor_value );
			} catch ( InvalidArgumentException ) {
				return $this->invalid_request( array( 'cursor' => __( 'The Tag inventory cursor is invalid.', 'tagcore' ) ) );
			}
		}

		try {
			$page = $this->list_batch_tags->execute( $batch_id, $cursor, new PageSize( $per_page ) );
		} catch ( BatchTagInventoryNotFound ) {
			return new WP_Error(
				'returntag_batch_not_found',
				__( 'The requested Batch was not found.', 'tagcore' ),
				array( 'status' => 404 )
			);
		} catch ( BatchTagInventoryUnavailable ) {
			return new WP_Error(
				'returntag_batch_tag_inventory_unavailable',
				__( 'Generated Tag IDs are available after the Batch finishes generation.', 'tagcore' ),
				array( 'status' => 409 )
			);
		} catch ( Throwable ) {
			return $this->operation_failed();
		}

		return $this->no_store(
			new WP_REST_Response(
				array(
					'items'       => array_map( array( $this, 'prepare_inventory_item' ), $page->items ),
					'next_cursor' => null === $page->next_cursor
						? null
						: $this->inventory_cursors->encode( $page->next_cursor ),
				)
			)
		);
	}

	/**
	 * Return one bounded page of immutable Batch export audit records.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function list_exports( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->schema_state->is_current() ) {
			return $this->schema_unavailable();
		}

		$batch_id = $this->positive_integer( $request->get_param( 'batch_id' ) );
		$per_page = $this->positive_integer( $request->get_param( 'per_page' ) ?? 20 );

		if ( null === $batch_id ) {
			return $this->invalid_request( array( 'batch_id' => __( 'The Batch identifier is invalid.', 'tagcore' ) ) );
		}

		if ( null === $per_page || $per_page > PageSize::MAXIMUM ) {
			return $this->invalid_request( array( 'per_page' => __( 'Choose a page size between 1 and 100.', 'tagcore' ) ) );
		}

		$cursor       = null;
		$cursor_value = $request->get_param( 'cursor' );

		if ( null !== $cursor_value && '' !== $cursor_value ) {
			if ( ! is_string( $cursor_value ) ) {
				return $this->invalid_request( array( 'cursor' => __( 'The export history cursor is invalid.', 'tagcore' ) ) );
			}

			try {
				$cursor = $this->export_cursors->decode( $cursor_value );
			} catch ( InvalidArgumentException ) {
				return $this->invalid_request( array( 'cursor' => __( 'The export history cursor is invalid.', 'tagcore' ) ) );
			}
		}

		try {
			$page = $this->list_batch_exports->execute( $batch_id, $cursor, new PageSize( $per_page ) );
		} catch ( BatchExportNotFound ) {
			return new WP_Error(
				'returntag_batch_not_found',
				__( 'The requested Batch was not found.', 'tagcore' ),
				array( 'status' => 404 )
			);
		} catch ( Throwable ) {
			return $this->operation_failed();
		}

		return $this->no_store(
			new WP_REST_Response(
				array(
					'items'       => array_map( array( $this, 'prepare_export_record' ), $page->items ),
					'next_cursor' => null === $page->next_cursor
						? null
						: $this->export_cursors->encode( $page->next_cursor ),
				)
			)
		);
	}

	/**
	 * Create one audited CSV export and return its exact bytes.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function export_batch( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->schema_state->is_current() ) {
			return $this->schema_unavailable();
		}

		$batch_id    = $this->positive_integer( $request->get_param( 'batch_id' ) );
		$operator_id = get_current_user_id();

		if ( null === $batch_id || $operator_id < 1 ) {
			return $this->invalid_request( array( 'batch_id' => __( 'The Batch identifier is invalid.', 'tagcore' ) ) );
		}

		try {
			$result = $this->export_batch->execute( $batch_id, $operator_id );
		} catch ( BatchExportNotFound ) {
			return new WP_Error(
				'returntag_batch_not_found',
				__( 'The requested Batch was not found.', 'tagcore' ),
				array( 'status' => 404 )
			);
		} catch ( BatchExportNotAllowed ) {
			return new WP_Error(
				'returntag_batch_export_conflict',
				__( 'This Batch cannot be exported in its current state.', 'tagcore' ),
				array( 'status' => 409 )
			);
		} catch ( BatchExportIntegrityViolation ) {
			return new WP_Error(
				'returntag_batch_export_inconsistent',
				__( 'The export was stopped because the Batch data did not match its immutable manufacturing record.', 'tagcore' ),
				array( 'status' => 409 )
			);
		} catch ( BatchExportArtifactFailure ) {
			return new WP_Error(
				'returntag_batch_export_unavailable',
				__( 'TagCore could not prepare the CSV export. Please try again.', 'tagcore' ),
				array( 'status' => 500 )
			);
		} catch ( Throwable ) {
			return $this->operation_failed();
		}

		return $this->download_response( $result );
	}

	/**
	 * Return aggregate generation progress and queue health.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function get_generation_progress( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->schema_state->is_current() ) {
			return $this->schema_unavailable();
		}

		$batch_id = $this->positive_integer( $request->get_param( 'batch_id' ) );

		if ( null === $batch_id ) {
			return $this->invalid_request( array( 'batch_id' => __( 'The Batch identifier is invalid.', 'tagcore' ) ) );
		}

		try {
			$progress = $this->get_generation_progress->execute( $batch_id );
		} catch ( BatchGenerationNotFound ) {
			return new WP_Error(
				'returntag_batch_not_found',
				__( 'The requested Batch was not found.', 'tagcore' ),
				array( 'status' => 404 )
			);
		} catch ( BatchGenerationIntegrityViolation ) {
			return new WP_Error(
				'returntag_batch_generation_inconsistent',
				__( 'Batch generation is paused because its stored progress is inconsistent.', 'tagcore' ),
				array( 'status' => 409 )
			);
		} catch ( Throwable ) {
			return $this->operation_failed();
		}

		return $this->no_store(
			new WP_REST_Response(
				array(
					'batch_id'           => $progress->batch_id,
					'batch_status'       => $progress->batch_status->value,
					'requested_quantity' => $progress->requested_quantity,
					'generated_quantity' => $progress->generated_quantity,
					'remaining_quantity' => $progress->remaining_quantity,
					'failed_quantity'    => $progress->failed_quantity,
					'progress_percent'   => $progress->progress_percent,
					'started_at'         => $progress->started_at?->format( DATE_ATOM ),
					'completed_at'       => $progress->completed_at?->format( DATE_ATOM ),
					'last_progress_at'   => $progress->last_progress_at->format( DATE_ATOM ),
					'queue_state'        => $progress->queue_state->value,
					'can_start'          => $progress->can_start,
					'can_retry'          => $progress->can_retry,
					'poll_after_ms'      => $progress->poll_after_ms,
				)
			)
		);
	}

	/**
	 * Create one disabled draft Batch.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function create_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->schema_state->is_current() ) {
			return $this->schema_unavailable();
		}

		$input = $this->create_input( $request );

		if ( $input instanceof WP_Error ) {
			return $input;
		}

		try {
			$batch = $this->create_batch->execute( $input );
		} catch ( BatchCodeAlreadyExists ) {
			return new WP_Error(
				'returntag_batch_code_conflict',
				__( 'A Batch with this code already exists.', 'tagcore' ),
				array(
					'status' => 409,
					'fields' => array(
						'batch_code' => __( 'This Batch Code is already in use.', 'tagcore' ),
					),
				)
			);
		} catch ( Throwable ) {
			return $this->operation_failed();
		}

		$response = new WP_REST_Response( $this->prepare_record( $batch ), 201 );
		$response->header( 'Location', rest_url( self::NAMESPACE . '/batches/' . $batch->batch_id ) );

		return $this->no_store( $response );
	}

	/**
	 * Start or resume background ID generation.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function start_generation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->schema_state->is_current() ) {
			return $this->schema_unavailable();
		}

		$batch_id = $this->positive_integer( $request->get_param( 'batch_id' ) );
		$user_id  = get_current_user_id();

		if ( null === $batch_id || $user_id < 1 ) {
			return $this->invalid_request( array( 'batch_id' => __( 'The Batch identifier is invalid.', 'tagcore' ) ) );
		}

		try {
			$result = $this->start_generation->execute( $batch_id, $user_id );
		} catch ( BatchGenerationNotFound ) {
			return new WP_Error(
				'returntag_batch_not_found',
				__( 'The requested Batch was not found.', 'tagcore' ),
				array( 'status' => 404 )
			);
		} catch ( BatchGenerationNotAllowed ) {
			return new WP_Error(
				'returntag_batch_generation_conflict',
				__( 'This Batch cannot start ID generation in its current state.', 'tagcore' ),
				array( 'status' => 409 )
			);
		} catch ( BatchGenerationIntegrityViolation ) {
			return new WP_Error(
				'returntag_batch_generation_inconsistent',
				__( 'Batch generation is paused because its stored progress is inconsistent.', 'tagcore' ),
				array( 'status' => 409 )
			);
		} catch ( BatchGenerationQueueUnavailable ) {
			return new WP_Error(
				'returntag_batch_generation_queue_unavailable',
				__( 'Batch generation could not be queued. Please try again.', 'tagcore' ),
				array( 'status' => 503 )
			);
		} catch ( Throwable ) {
			return $this->operation_failed();
		}

		$status = null === $result->schedule_status ? 200 : 202;

		return $this->no_store(
			new WP_REST_Response(
				array(
					'batch_id'           => $result->batch_id,
					'batch_status'       => $result->batch_status->value,
					'generated_quantity' => $result->generated_quantity,
					'queue_status'       => $result->schedule_status?->value,
				),
				$status
			)
		);
	}

	/**
	 * Validate and normalize an administrative create request.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	private function create_input( WP_REST_Request $request ): CreateBatchInput|WP_Error {
		$fields     = array();
		$batch_code = $this->text( $request->get_param( 'batch_code' ) );

		if ( null === $batch_code || 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9-]{0,190}$/D', $batch_code ) ) {
			$fields['batch_code'] = __( 'Use 1–191 letters, numbers, or hyphens.', 'tagcore' );
		}

		$tag_type_value = $this->text( $request->get_param( 'tag_type' ) );
		$tag_type       = null === $tag_type_value ? null : TagType::tryFrom( $tag_type_value );

		if ( null === $tag_type ) {
			$fields['tag_type'] = __( 'Choose a supported Tag type.', 'tagcore' );
		}

		$network_value = $this->text( $request->get_param( 'smart_network' ) );
		$network       = null === $network_value ? null : SmartNetwork::tryFrom( $network_value );

		if ( null === $network ) {
			$fields['smart_network'] = __( 'Choose a supported Smart Network value.', 'tagcore' );
		}

		if ( null !== $tag_type && TagType::SMART_TAG !== $tag_type && SmartNetwork::NONE !== $network ) {
			$fields['smart_network'] = __( 'Smart Network applies only to Smart Tags.', 'tagcore' );
		}

		$quantity = $this->positive_integer( $request->get_param( 'requested_quantity' ) );

		if ( null === $quantity || $quantity > 4294967295 ) {
			$fields['requested_quantity'] = __( 'Enter a whole quantity of at least 1.', 'tagcore' );
		}

		$model_raw        = $request->get_param( 'model_code' );
		$manufacturer_raw = $request->get_param( 'manufacturer' );
		$sales_raw        = $request->get_param( 'sales_channel' );
		$notes_raw        = $request->get_param( 'notes' );

		foreach (
			array(
				'model_code'    => $model_raw,
				'manufacturer'  => $manufacturer_raw,
				'sales_channel' => $sales_raw,
				'notes'         => $notes_raw,
			) as $field => $value
		) {
			if ( null !== $value && ! is_string( $value ) ) {
				$fields[ $field ] = __( 'Enter text for this field.', 'tagcore' );
			}
		}

		$model_code    = $this->nullable_text( $model_raw );
		$manufacturer  = $this->nullable_text( $manufacturer_raw );
		$sales_channel = $this->nullable_text( $sales_raw );
		$notes         = $this->nullable_textarea( $notes_raw );

		if (
			null !== $model_code
			&& ( strlen( $model_code ) > 191 || 1 !== preg_match( '/^[\x20-\x7E]+$/D', $model_code ) )
		) {
			$fields['model_code'] = __( 'Use no more than 191 ASCII characters.', 'tagcore' );
		}

		if ( null !== $manufacturer && mb_strlen( $manufacturer, 'UTF-8' ) > 191 ) {
			$fields['manufacturer'] = __( 'Use no more than 191 characters.', 'tagcore' );
		}

		if (
			null !== $sales_channel
			&& ( strlen( $sales_channel ) > 64 || 1 !== preg_match( '/^[\x20-\x7E]+$/D', $sales_channel ) )
		) {
			$fields['sales_channel'] = __( 'Use no more than 64 ASCII characters.', 'tagcore' );
		}

		if ( null !== $notes && strlen( $notes ) > 5000 ) {
			$fields['notes'] = __( 'Use no more than 5,000 bytes of notes.', 'tagcore' );
		}

		$user_id = get_current_user_id();

		if ( $user_id < 1 ) {
			$fields['authorization'] = __( 'Sign in again before creating a Batch.', 'tagcore' );
		}

		if ( array() !== $fields || null === $batch_code || null === $tag_type || null === $network || null === $quantity ) {
			return $this->invalid_request( $fields );
		}

		try {
			return new CreateBatchInput(
				$batch_code,
				$tag_type,
				$model_code,
				$network,
				$quantity,
				$manufacturer,
				$sales_channel,
				$notes,
				$user_id
			);
		} catch ( InvalidArgumentException ) {
			return $this->invalid_request( array( 'request' => __( 'Review the Batch details and try again.', 'tagcore' ) ) );
		}
	}

	/**
	 * Return a trimmed single-line value or null.
	 *
	 * @param mixed $value Candidate value.
	 */
	private function text( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = trim( sanitize_text_field( $value ) );

		return '' === $value ? null : $value;
	}

	/**
	 * Return an optional normalized single-line value.
	 *
	 * @param mixed $value Candidate value.
	 */
	private function nullable_text( mixed $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}

		return $this->text( $value );
	}

	/**
	 * Return optional normalized textarea content.
	 *
	 * @param mixed $value Candidate value.
	 */
	private function nullable_textarea( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = trim( sanitize_textarea_field( $value ) );

		return '' === $value ? null : $value;
	}

	/**
	 * Parse a positive decimal integer without accepting floats.
	 *
	 * @param mixed $value Candidate value.
	 */
	private function positive_integer( mixed $value ): ?int {
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : null;
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
			return null;
		}

		$integer = filter_var( $value, FILTER_VALIDATE_INT );

		return false === $integer || $integer < 1 ? null : $integer;
	}

	/**
	 * Map a full Batch persistence record to safe REST data.
	 *
	 * @param BatchRecord $batch Batch record.
	 * @return array<string, int|string|bool|null>
	 */
	private function prepare_record( BatchRecord $batch ): array {
		return array(
			'batch_id'           => $batch->batch_id,
			'batch_code'         => $batch->data->batch_code,
			'tag_type'           => $batch->data->tag_type->value,
			'model_code'         => $batch->data->model_code,
			'smart_network'      => $batch->data->smart_network->value,
			'manufacturer'       => $batch->data->manufacturer,
			'sales_channel'      => $batch->data->sales_channel,
			'requested_quantity' => $batch->data->requested_quantity,
			'generated_quantity' => $batch->data->generated_quantity,
			'batch_status'       => $batch->data->batch_status->value,
			'activation_enabled' => $batch->data->activation_enabled,
			'notes'              => $batch->data->notes,
			'created_by'         => $batch->data->created_by,
			'created_at'         => $batch->data->created_at->format( DATE_ATOM ),
			'updated_at'         => $batch->data->updated_at->format( DATE_ATOM ),
		);
	}

	/**
	 * Map a narrow Batch summary to safe REST data.
	 *
	 * @param BatchSummaryRecord $batch Batch summary.
	 * @return array<string, int|string|bool|null>
	 */
	private function prepare_summary( BatchSummaryRecord $batch ): array {
		return array(
			'batch_id'           => $batch->batch_id,
			'batch_code'         => $batch->batch_code,
			'tag_type'           => $batch->tag_type->value,
			'model_code'         => $batch->model_code,
			'requested_quantity' => $batch->requested_quantity,
			'generated_quantity' => $batch->generated_quantity,
			'batch_status'       => $batch->batch_status->value,
			'activation_enabled' => $batch->activation_enabled,
			'created_at'         => $batch->created_at->format( DATE_ATOM ),
		);
	}

	/**
	 * Map one narrow inventory item to approved REST fields.
	 *
	 * @param BatchTagInventoryItem $item Inventory item.
	 * @return array<string, string>
	 */
	private function prepare_inventory_item( BatchTagInventoryItem $item ): array {
		return array(
			'tag_id'     => $item->tag_id->value,
			'tag_status' => $item->tag_status->value,
			'created_at' => $item->created_at->format( DATE_ATOM ),
		);
	}

	/**
	 * Map one append-only export record to privacy-safe REST data.
	 *
	 * @param BatchExportRecord $record Export audit record.
	 * @return array<string, int|string>
	 */
	private function prepare_export_record( BatchExportRecord $record ): array {
		$user = get_userdata( $record->data->created_by );

		return array(
			'export_version'  => $record->data->export_version,
			'row_count'       => $record->data->row_count,
			'file_format'     => $record->data->file_format,
			'file_checksum'   => $record->data->file_checksum,
			'created_by'      => $record->data->created_by,
			'created_by_name' => false === $user
				? sprintf(
					/* translators: %d: WordPress User ID. */
					__( 'User #%d', 'tagcore' ),
					$record->data->created_by
				)
				: $user->display_name,
			'created_at'      => $record->data->created_at->format( DATE_ATOM ),
		);
	}

	/**
	 * Build a streamed attachment response for one audited artifact.
	 *
	 * @param BatchExportResult $result Audited export result.
	 */
	private function download_response( BatchExportResult $result ): WP_REST_Response {
		$download = new BatchCsvDownload( $result );
		$record   = $result->record->data;
		$response = new WP_REST_Response( array() );
		$key      = $this->csv_downloads->attach( $download );

		$response->header( 'Content-Type', 'text/csv; charset=UTF-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="' . $download->filename() . '"' );
		$response->header( 'Content-Length', (string) $result->artifact->byte_size() );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'Referrer-Policy', 'no-referrer' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		$response->header( 'X-ReturnTag-Export-Version', (string) $record->export_version );
		$response->header( 'X-ReturnTag-Row-Count', (string) $record->row_count );
		$response->header( 'X-ReturnTag-SHA256', $record->file_checksum );
		$response->header( 'X-ReturnTag-Created-At', $record->created_at->format( DATE_ATOM ) );
		$response->header( 'X-ReturnTag-Batch-Status', $result->batch_status->value );
		$response->header( 'X-ReturnTag-Download-Key', $key );

		return $this->no_store( $response );
	}

	/**
	 * Return a field-addressable validation response.
	 *
	 * @param array<string, string> $fields Field errors.
	 */
	private function invalid_request( array $fields ): WP_Error {
		return new WP_Error(
			'returntag_invalid_batch_request',
			__( 'Please correct the highlighted Batch details.', 'tagcore' ),
			array(
				'status' => 400,
				'fields' => $fields,
			)
		);
	}

	/**
	 * Return a privacy-safe generic operation failure.
	 */
	private function operation_failed(): WP_Error {
		return new WP_Error(
			'returntag_batch_operation_failed',
			__( 'TagCore could not complete the Batch operation. Please try again.', 'tagcore' ),
			array( 'status' => 500 )
		);
	}

	/**
	 * Return a fail-closed schema readiness response.
	 */
	private function schema_unavailable(): WP_Error {
		return new WP_Error(
			'returntag_schema_unavailable',
			__( 'TagCore batch administration is temporarily unavailable.', 'tagcore' ),
			array( 'status' => 503 )
		);
	}

	/**
	 * Apply administrative no-store response headers.
	 *
	 * @param WP_REST_Response $response REST response.
	 */
	private function no_store( WP_REST_Response $response ): WP_REST_Response {
		$response->header( 'Cache-Control', 'no-store, private' );
		$response->header( 'Pragma', 'no-cache' );

		return $response;
	}
}
