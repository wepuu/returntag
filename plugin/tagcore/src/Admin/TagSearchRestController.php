<?php
/**
 * Read-only Tag administration REST adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Tag\SearchTags;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Application\Tag\TagSearchCriteria;
use ReturnTag\TagCore\Application\Tag\TagSearchInputNormalizer;
use ReturnTag\TagCore\Application\Tag\TagSearchItem;
use ReturnTag\TagCore\Application\Tag\TagSearchMode;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;
use Throwable;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Maps authorized exact-anchor searches to the narrow RT-209 projection.
 */
final readonly class TagSearchRestController {
	private const NAMESPACE = 'tagcore/v1';

	/**
	 * Create the controller.
	 *
	 * @param SearchTags                      $search_tags Search use case.
	 * @param TagSearchInputNormalizer        $normalizer Boundary normalizer.
	 * @param TagSearchCursorCodec            $cursors Opaque cursor codec.
	 * @param SchemaState                     $schema_state Schema readiness.
	 * @param FeatureFlagReader               $feature_flags Operational feature flags.
	 * @param TagActivationAvailabilityPolicy $availability Activation availability policy.
	 */
	public function __construct(
		private SearchTags $search_tags,
		private TagSearchInputNormalizer $normalizer,
		private TagSearchCursorCodec $cursors,
		private SchemaState $schema_state,
		private FeatureFlagReader $feature_flags,
		private TagActivationAvailabilityPolicy $availability
	) {
	}

	/**
	 * Register the read-only Tag collection route.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/tags',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => array( $this, 'authorize' ),
			)
		);
	}

	/**
	 * Require the dedicated Tag management capability.
	 */
	public function authorize(): bool|WP_Error {
		if ( current_user_can( Capability::MANAGE_TAGS ) ) {
			return true;
		}

		return new WP_Error(
			'returntag_forbidden',
			__( 'You are not allowed to search ReturnTag tags.', 'tagcore' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Execute one validated exact-anchor search.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function search( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->schema_state->is_current() ) {
			return new WP_Error(
				'returntag_schema_unavailable',
				__( 'TagCore database preparation is incomplete.', 'tagcore' ),
				array( 'status' => 503 )
			);
		}

		try {
			$criteria = $this->criteria( $request );
			$per_page = $this->positive_integer( $request->get_param( 'per_page' ) ?? PageSize::DEFAULT );

			if ( null === $per_page || $per_page > PageSize::MAXIMUM ) {
				return $this->invalid( array( 'per_page' => __( 'Choose a page size between 1 and 100.', 'tagcore' ) ) );
			}

			$cursor       = null;
			$cursor_value = $request->get_param( 'cursor' );

			if ( null !== $cursor_value && '' !== $cursor_value ) {
				if ( ! is_string( $cursor_value ) || TagSearchMode::TAG_ID === $criteria->mode ) {
					return $this->invalid( array( 'cursor' => __( 'The Tag search cursor is invalid.', 'tagcore' ) ) );
				}

				$cursor = $this->cursors->decode( $criteria, $cursor_value );
			}

			$page                      = $this->search_tags->execute( $criteria, $cursor, new PageSize( $per_page ) );
			$global_activation_enabled = $this->feature_flags->is_enabled( FeatureFlag::GLOBAL_ACTIVATION );
		} catch ( InvalidArgumentException ) {
			return $this->invalid( array( 'search' => __( 'Enter a valid exact Tag ID or Batch Code search.', 'tagcore' ) ) );
		} catch ( Throwable ) {
			return new WP_Error(
				'returntag_tag_search_failed',
				__( 'TagCore could not complete the Tag search.', 'tagcore' ),
				array( 'status' => 500 )
			);
		}

		$response = new WP_REST_Response(
			array(
				'items'       => array_map(
					fn( TagSearchItem $item ): array => $this->prepare_item( $item, $global_activation_enabled ),
					$page->items
				),
				'next_cursor' => null === $page->next_cursor
					? null
					: $this->cursors->encode( $criteria, $page->next_cursor ),
				'context'     => array(
					'global_activation_enabled' => $global_activation_enabled,
				),
			)
		);

		return $this->no_store( $response );
	}

	/**
	 * Apply no-store headers to every Tag search response.
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

		if ( '/' . self::NAMESPACE . '/tags' === $request->get_route() ) {
			$response->header( 'Cache-Control', 'no-store, private' );
			$response->header( 'Pragma', 'no-cache' );
		}

		return $response;
	}

	/**
	 * Map request values to one validated criteria object.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @throws InvalidArgumentException When the request does not contain one valid exact anchor.
	 */
	private function criteria( WP_REST_Request $request ): TagSearchCriteria {
		$mode_value = $request->get_param( 'mode' );

		if ( ! is_string( $mode_value ) ) {
			throw new InvalidArgumentException( 'Tag search mode is invalid.' );
		}

		$mode = TagSearchMode::tryFrom( $mode_value );

		if ( TagSearchMode::TAG_ID === $mode ) {
			$value = $request->get_param( 'tag_id' );

			if ( ! is_string( $value ) ) {
				throw new InvalidArgumentException( 'Tag ID search is invalid.' );
			}

			return TagSearchCriteria::for_tag_id( $this->normalizer->tag_id( $value ) );
		}

		if ( TagSearchMode::BATCH !== $mode ) {
			throw new InvalidArgumentException( 'Tag search mode is invalid.' );
		}

		$batch_code = $request->get_param( 'batch_code' );
		$status     = $request->get_param( 'tag_status' );

		if ( ! is_string( $batch_code ) || ( null !== $status && '' !== $status && ! is_string( $status ) ) ) {
			throw new InvalidArgumentException( 'Batch search is invalid.' );
		}

		$tag_status = null;

		if ( is_string( $status ) && '' !== $status ) {
			$tag_status = TagStatus::tryFrom( $status );

			if ( null === $tag_status ) {
				throw new InvalidArgumentException( 'Tag status is invalid.' );
			}
		}

		return TagSearchCriteria::for_batch( $this->normalizer->batch_code( $batch_code ), $tag_status );
	}

	/**
	 * Map one narrow item to the approved REST response.
	 *
	 * @param TagSearchItem $item Search result.
	 * @param bool          $global_activation_enabled Global activation control.
	 * @return array<string, bool|int|string|null>
	 */
	private function prepare_item( TagSearchItem $item, bool $global_activation_enabled ): array {
		return array(
			'tag_id'                   => $item->tag_id->value,
			'batch_id'                 => $item->batch_id,
			'batch_code'               => $item->batch_code,
			'batch_status'             => $item->batch_status->value,
			'batch_activation_enabled' => $item->batch_activation_enabled,
			'activation_availability'  => $this->availability->decide(
				$item->tag_status,
				$item->batch_status,
				$item->batch_activation_enabled,
				$global_activation_enabled,
				$item->activated_at
			)->value,
			'tag_type'                 => $item->tag_type->value,
			'model_code'               => $item->model_code,
			'tag_status'               => $item->tag_status->value,
			'lost_mode'                => $item->lost_mode,
			'activated_at'             => $item->activated_at?->format( DATE_ATOM ),
			'created_at'               => $item->created_at->format( DATE_ATOM ),
			'updated_at'               => $item->updated_at->format( DATE_ATOM ),
		);
	}

	/**
	 * Return one privacy-safe validation error.
	 *
	 * @param array<string, string> $fields Field errors.
	 */
	private function invalid( array $fields ): WP_Error {
		return new WP_Error(
			'returntag_invalid_request',
			__( 'Review the Tag search and try again.', 'tagcore' ),
			array(
				'status' => 400,
				'fields' => $fields,
			)
		);
	}

	/**
	 * Parse a strict positive integer.
	 *
	 * @param mixed $value External value.
	 */
	private function positive_integer( mixed $value ): ?int {
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : null;
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
			return null;
		}

		return (int) $value;
	}

	/**
	 * Attach private no-cache headers.
	 *
	 * @param WP_REST_Response $response REST response.
	 */
	private function no_store( WP_REST_Response $response ): WP_REST_Response {
		$response->header( 'Cache-Control', 'no-store, private' );
		$response->header( 'Pragma', 'no-cache' );

		return $response;
	}
}
