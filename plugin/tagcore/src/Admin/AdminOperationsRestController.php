<?php
/**
 * Internal operations console REST adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use ReturnTag\TagCore\Application\Admin\AdminSensitivePreview;
use ReturnTag\TagCore\Application\FeatureFlag;
use ReturnTag\TagCore\Application\FeatureFlagReader;
use ReturnTag\TagCore\Application\Tag\TagActivationAvailabilityPolicy;
use ReturnTag\TagCore\Application\Tag\TagIdInputNormalizer;
use ReturnTag\TagCore\Application\Tag\TagSearchInputNormalizer;
use ReturnTag\TagCore\Infrastructure\Migration\SchemaState;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAdminOperationsReader;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use Throwable;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/** Maps exact, authorized operations queries to privacy-safe projections. */
final readonly class AdminOperationsRestController {
	private const NAMESPACE = 'tagcore/v1';

	/**
	 * Create the operations adapter.
	 *
	 * @param WpdbAdminOperationsReader       $reader Safe query projections.
	 * @param TagSearchInputNormalizer        $normalizer Tag and Batch normalizer.
	 * @param AdminOperationsCursorCodec      $cursors Criteria-bound cursors.
	 * @param SchemaState                     $schema_state Schema readiness.
	 * @param AdminSensitivePreview|null      $preview Optional private runtime.
	 * @param FeatureFlagReader               $feature_flags Operational controls.
	 * @param TagActivationAvailabilityPolicy $availability Activation display policy.
	 */
	public function __construct(
		private WpdbAdminOperationsReader $reader,
		private TagSearchInputNormalizer $normalizer,
		private AdminOperationsCursorCodec $cursors,
		private SchemaState $schema_state,
		private ?AdminSensitivePreview $preview,
		private FeatureFlagReader $feature_flags,
		private TagActivationAvailabilityPolicy $availability
	) {
	}

	/** Register the internal operations route set. */
	public function register_routes(): void {
		$this->route( '/admin/tags/search', WP_REST_Server::CREATABLE, 'search_tags', Capability::MANAGE_TAGS );
		$this->route( '/admin/tags/(?P<tag_id>[A-Za-z0-9 -]+)', WP_REST_Server::READABLE, 'get_tag', Capability::MANAGE_TAGS );
		$this->route( '/admin/finder-reports/search', WP_REST_Server::CREATABLE, 'search_finder_reports', Capability::MANAGE_DISPUTES );
		$this->route( '/admin/finder-reports/(?P<id>[1-9][0-9]*)', WP_REST_Server::READABLE, 'get_finder_report', Capability::MANAGE_DISPUTES );
		$this->route( '/admin/finder-reports/(?P<id>[1-9][0-9]*)/reveal-message', WP_REST_Server::CREATABLE, 'reveal_message', Capability::MANAGE_DISPUTES );
		$this->route( '/admin/finder-reports/(?P<id>[1-9][0-9]*)/reveal-evidence', WP_REST_Server::CREATABLE, 'reveal_evidence', Capability::MANAGE_DISPUTES );
		$this->route( '/admin/users/search', WP_REST_Server::CREATABLE, 'search_users', Capability::VIEW_USERS );
		$this->route( '/admin/users/(?P<id>[1-9][0-9]*)', WP_REST_Server::READABLE, 'get_user', Capability::VIEW_USERS );
	}

	/**
	 * Register one capability-bound route.
	 *
	 * @param string $path Route path.
	 * @param string $methods Allowed methods.
	 * @param string $callback Controller callback name.
	 * @param string $capability Required capability.
	 * @phpstan-param non-falsy-string $path
	 */
	private function route( string $path, string $methods, string $callback, string $capability ): void {
		register_rest_route(
			self::NAMESPACE,
			$path,
			array(
				'methods'             => $methods,
				'callback'            => array( $this, $callback ),
				'permission_callback' => fn( WP_REST_Request $request ): bool|WP_Error => $this->authorize( $request, $capability ),
			)
		);
	}

	/**
	 * Require the REST nonce and route capability.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @param string          $capability Required capability.
	 */
	private function authorize( WP_REST_Request $request, string $capability ): bool|WP_Error {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'returntag_invalid_nonce', __( 'Your secure session expired. Refresh this page and try again.', 'tagcore' ), array( 'status' => 403 ) );
		}
		if ( ! current_user_can( $capability ) ) {
			return new WP_Error( 'returntag_forbidden', __( 'You are not allowed to use this operations view.', 'tagcore' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Search Tags by one exact anchor.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function search_tags( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->guard(
			function () use ( $request ): WP_REST_Response {
				$criteria = $this->tag_criteria( $request );
				$limit    = $this->page_size( $request );
				$after    = null;
				$cursor   = $request->get_param( 'cursor' );
				if ( is_string( $cursor ) && '' !== $cursor ) {
					$after = $this->cursors->decode( 'tags', $criteria, $cursor );
				}
				$items = $this->reader->search_tags( $criteria, $after, $limit + 1 );
				$more  = count( $items ) > $limit;
				if ( $more ) {
					array_pop( $items );
				}
				return $this->response(
					array(
						'items'       => array_map(
							function ( array $item ): array {
								$data = $this->prepare_tag( $item );
								if ( ! current_user_can( Capability::VIEW_USERS ) ) {
									unset( $data['owner_id'] );
								}
								return $data;
							},
							$items
						),
						'next_cursor' => $more ? $this->cursors->encode( 'tags', $criteria, (string) $items[ count( $items ) - 1 ]['tag_id'] ) : null,
					)
				);
			}
		);
	}

	/**
	 * Return one privacy-safe Tag detail.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function get_tag( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->guard(
			function () use ( $request ): WP_REST_Response|WP_Error {
				$value = $request->get_param( 'tag_id' );
				$tag   = $this->reader->tag( $this->normalizer->tag_id( is_string( $value ) ? $value : '' )->value );
				if ( null === $tag ) {
					return $this->not_found();
				}
				$data = $this->prepare_tag( $tag );
				if ( ! current_user_can( Capability::VIEW_USERS ) && ! current_user_can( Capability::MANAGE_TAG_LIFECYCLE ) ) {
					unset( $data['owner_id'] );
				}
				if ( current_user_can( Capability::VIEW_AUDIT_LOGS ) ) {
					$data['audit'] = $this->reader->audit( 'tag', $data['tag_id'] );
				}
				return $this->response( $data );
			}
		);
	}

	/**
	 * Search Finder Reports by one exact anchor.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function search_finder_reports( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->guard(
			function () use ( $request ): WP_REST_Response {
				$criteria = $this->report_criteria( $request );
				$limit    = $this->page_size( $request );
				$before   = null;
				$cursor   = $request->get_param( 'cursor' );
				if ( is_string( $cursor ) && '' !== $cursor ) {
					$position = $this->cursors->decode( 'finder_reports', $criteria, $cursor );
					if ( ! ctype_digit( $position ) ) {
						throw new InvalidArgumentException( 'Cursor is invalid.' );
					}
					$before = (int) $position;
				}
				$items = $this->reader->search_finder_reports( $criteria, $before, $limit + 1 );
				$more  = count( $items ) > $limit;
				if ( $more ) {
					array_pop( $items );
				}
				return $this->response(
					array(
						'items'       => array_map(
							function ( array $item ): array {
								$data = $this->prepare_report( $item );
								unset( $data['has_message'], $data['has_review_evidence'] );
								if ( ! current_user_can( Capability::VIEW_USERS ) ) {
									unset( $data['owner_id'] );
								}
								return $data;
							},
							$items
						),
						'next_cursor' => $more ? $this->cursors->encode( 'finder_reports', $criteria, (string) $items[ count( $items ) - 1 ]['finder_report_id'] ) : null,
					)
				);
			}
		);
	}

	/**
	 * Return one safe Finder Report detail.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function get_finder_report( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->guard(
			function () use ( $request ): WP_REST_Response|WP_Error {
				$id     = $this->positive_id( $request->get_param( 'id' ) );
				$report = $this->reader->finder_report( $id );
				if ( null === $report ) {
					return $this->not_found();
				}
				$data = $this->prepare_report( $report );
				if ( ! current_user_can( Capability::VIEW_USERS ) ) {
					unset( $data['owner_id'] );
				}
				if ( current_user_can( Capability::VIEW_AUDIT_LOGS ) ) {
					$data['audit'] = $this->reader->audit( 'finder_report', (string) $id );
				}
				return $this->response( $data );
			}
		);
	}

	/**
	 * Reveal one eligible message through an explicit POST.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function reveal_message( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->guard(
			function () use ( $request ): WP_REST_Response {
				if ( null === $this->preview ) {
					throw new \RuntimeException( 'Preview is unavailable.' );
				}
				$message = $this->preview->reveal_message( $this->positive_id( $request->get_param( 'id' ) ), get_current_user_id(), current_user_can( Capability::MANAGE_FINDER_REPORT_DECISIONS ) );
				return $this->response( array( 'message' => $message ) );
			}
		);
	}

	/**
	 * Reveal one eligible processed Review derivative.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function reveal_evidence( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->guard(
			function () use ( $request ): WP_REST_Response {
				if ( null === $this->preview ) {
					throw new \RuntimeException( 'Preview is unavailable.' );
				}
				$bytes    = $this->preview->reveal_evidence( $this->positive_id( $request->get_param( 'id' ) ), get_current_user_id(), current_user_can( Capability::MANAGE_FINDER_REPORT_DECISIONS ) );
				$response = $this->response( new AdminEvidencePreview( $bytes ) );
				$response->header( 'Content-Type', 'image/jpeg' );
				$response->header( 'Content-Disposition', 'inline' );
				return $response;
			}
		);
	}

	/**
	 * Search WordPress users by exact ID or complete email.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function search_users( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->guard(
			function () use ( $request ): WP_REST_Response|WP_Error {
				$mode = $request->get_param( 'mode' );
				if ( 'user_id' === $mode ) {
					$id = $this->positive_id( $request->get_param( 'user_id' ) );
				} elseif ( 'email' === $mode ) {
					$email = $request->get_param( 'email' );
					if ( ! is_string( $email ) || ! is_email( $email ) ) {
						throw new InvalidArgumentException( 'Email is invalid.' );
					}
					$ids = $this->reader->user_ids_for_email( $email );
					if ( count( $ids ) > 1 ) {
						return $this->invalid( __( 'More than one account uses this email. Search by User ID.', 'tagcore' ) );
					}
					$id = $ids[0] ?? 0;
				} else {
					throw new InvalidArgumentException( 'User search mode is invalid.' );
				}
				if ( $id < 1 ) {
					return $this->response( array( 'items' => array() ) );
				}
				$user = $this->prepare_user( $this->reader->user( $id ) );
				return $this->response( array( 'items' => null === $user ? array() : array( $user ) ) );
			}
		);
	}

	/**
	 * Return one User support detail.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function get_user( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->guard(
			function () use ( $request ): WP_REST_Response|WP_Error {
				$id   = $this->positive_id( $request->get_param( 'id' ) );
				$user = $this->prepare_user( $this->reader->user( $id ) );
				if ( null === $user ) {
					return $this->not_found();
				}
				if ( current_user_can( Capability::VIEW_AUDIT_LOGS ) ) {
					$user['audit'] = $this->reader->audit( 'user', (string) $id );
				}
				return $this->response( $user );
			}
		);
	}

	/**
	 * Apply private response headers to every operations route.
	 *
	 * @param WP_HTTP_Response $response REST response.
	 * @param WP_REST_Server   $server REST server.
	 * @param WP_REST_Request  $request REST request.
	 */
	public function apply_security_headers( WP_HTTP_Response $response, WP_REST_Server $server, WP_REST_Request $request ): WP_HTTP_Response {
		unset( $server );
		if ( str_starts_with( $request->get_route(), '/' . self::NAMESPACE . '/admin/' ) ) {
			$response->header( 'Cache-Control', 'no-store, private' );
			$response->header( 'Pragma', 'no-cache' );
			$response->header( 'Referrer-Policy', 'no-referrer' );
			$response->header( 'X-Content-Type-Options', 'nosniff' );
		}
		return $response;
	}

	/**
	 * Stream controlled binary evidence outside JSON serialization.
	 *
	 * @param bool             $served Whether another callback served the response.
	 * @param WP_HTTP_Response $result REST response.
	 * @param WP_REST_Request  $request REST request.
	 * @param WP_REST_Server   $server REST server.
	 */
	public function serve_evidence( bool $served, WP_HTTP_Response $result, WP_REST_Request $request, WP_REST_Server $server ): bool {
		unset( $request, $server );
		$data = $result->get_data();
		if ( ! $data instanceof AdminEvidencePreview ) {
			return $served;
		}
		echo $data->bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Authenticated, controlled binary response.
		return true;
	}

	/**
	 * Build normalized exact-anchor Tag criteria.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string, mixed>
	 * @throws InvalidArgumentException When input is invalid or ambiguous.
	 */
	private function tag_criteria( WP_REST_Request $request ): array {
		$mode = $request->get_param( 'mode' );
		if ( ! in_array( $mode, array( 'tag_id', 'batch', 'owner_id', 'owner_email' ), true ) ) {
			throw new InvalidArgumentException( 'Anchor is required.' );
		}
		$criteria = array( 'mode' => $mode );
		if ( 'tag_id' === $mode ) {
			$value              = $request->get_param( 'tag_id' );
			$criteria['tag_id'] = $this->normalizer->tag_id( is_string( $value ) ? $value : '' )->value;
		} elseif ( 'batch' === $mode ) {
			$value                  = $request->get_param( 'batch_code' );
			$criteria['batch_code'] = $this->normalizer->batch_code( is_string( $value ) ? $value : '' );
		} elseif ( 'owner_id' === $mode ) {
			$criteria['owner_id'] = $this->positive_id( $request->get_param( 'owner_id' ) );
		} else {
			$email = $request->get_param( 'owner_email' );
			if ( ! is_string( $email ) || ! is_email( $email ) ) {
				throw new InvalidArgumentException( 'Owner email is invalid.' );
			}
			$ids = $this->reader->user_ids_for_email( $email );
			if ( 1 !== count( $ids ) ) {
				throw new InvalidArgumentException( count( $ids ) > 1 ? 'Use Owner User ID.' : 'Owner is unavailable.' );
			}
			$criteria = array(
				'mode'     => 'owner_id',
				'owner_id' => $ids[0],
			);
		}

		$this->optional_enum( $request, $criteria, 'tag_type', array( 'sticker', 'classic_tag', 'smart_tag' ) );
		$this->optional_enum( $request, $criteria, 'tag_status', array( 'unregistered', 'active', 'suspended', 'retired' ) );
		$lost = $request->get_param( 'lost_mode' );
		if ( null !== $lost && '' !== $lost ) {
			if ( ! in_array( $lost, array( true, false, 1, 0, '1', '0' ), true ) ) {
				throw new InvalidArgumentException( 'Lost Mode is invalid.' );
			}
			$criteria['lost_mode'] = in_array( $lost, array( true, 1, '1' ), true );
		}
		$this->dates( $request, $criteria, 'activated' );
		return $criteria;
	}

	/**
	 * Build normalized exact-anchor Finder Report criteria.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string, mixed>
	 * @throws InvalidArgumentException When input is invalid.
	 */
	private function report_criteria( WP_REST_Request $request ): array {
		$mode = $request->get_param( 'mode' );
		if ( 'report_id' === $mode ) {
			$criteria = array(
				'mode'             => $mode,
				'finder_report_id' => $this->positive_id( $request->get_param( 'finder_report_id' ) ),
			);
		} elseif ( 'tag_id' === $mode ) {
			$value    = $request->get_param( 'tag_id' );
			$criteria = array(
				'mode'   => $mode,
				'tag_id' => $this->normalizer->tag_id( is_string( $value ) ? $value : '' )->value,
			);
		} elseif ( 'owner_id' === $mode ) {
			$criteria = array(
				'mode'     => $mode,
				'owner_id' => $this->positive_id( $request->get_param( 'owner_id' ) ),
			);
		} else {
			throw new InvalidArgumentException( 'Anchor is required.' );
		}
		$this->optional_enum( $request, $criteria, 'report_status', array( 'received', 'processing', 'ready', 'notified', 'blocked', 'expired' ) );
		$this->optional_enum( $request, $criteria, 'evidence_status', array( 'quarantined', 'processing', 'ready', 'rejected', 'deleted' ) );
		$this->optional_enum( $request, $criteria, 'owner_notification_status', array( 'queued', 'sent', 'delivered', 'deferred', 'failed', 'bounced', 'complained' ) );
		$this->dates( $request, $criteria, 'created' );
		return $criteria;
	}

	/**
	 * Add one optional allowlisted enum filter.
	 *
	 * @param WP_REST_Request      $request REST request.
	 * @param array<string, mixed> $criteria Criteria under construction.
	 * @param string               $key Request key.
	 * @param array                $allowed Allowed values.
	 * @phpstan-param list<string> $allowed
	 * @throws InvalidArgumentException When a supplied value is invalid.
	 */
	private function optional_enum( WP_REST_Request $request, array &$criteria, string $key, array $allowed ): void {
		$value = $request->get_param( $key );
		if ( null === $value || '' === $value ) {
			return;
		}
		if ( ! is_string( $value ) || ! in_array( $value, $allowed, true ) ) {
			throw new InvalidArgumentException( 'Filter is invalid.' );
		}
		$criteria[ $key ] = $value;
	}

	/**
	 * Add optional UTC date-boundary filters.
	 *
	 * @param WP_REST_Request      $request REST request.
	 * @param array<string, mixed> $criteria Criteria under construction.
	 * @param string               $prefix Filter prefix.
	 * @throws InvalidArgumentException When a date is invalid.
	 */
	private function dates( WP_REST_Request $request, array &$criteria, string $prefix ): void {
		foreach ( array(
			'from' => '00:00:00',
			'to'   => '23:59:59',
		) as $suffix => $time ) {
			$value = $request->get_param( $prefix . '_' . $suffix );
			if ( null === $value || '' === $value ) {
				continue;
			}
			$normalized = is_string( $value ) ? $value . ' ' . $time : '';
			$date       = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $normalized, new DateTimeZone( 'UTC' ) );
			$errors     = DateTimeImmutable::getLastErrors();
			if (
				! is_string( $value )
				|| 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value )
				|| false === $date
				|| ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) )
				|| $date->format( 'Y-m-d H:i:s' ) !== $normalized
			) {
				throw new InvalidArgumentException( 'Date filter is invalid.' );
			}
			$criteria[ $prefix . '_' . $suffix ] = $normalized;
		}
	}

	/**
	 * Parse the 1 through 100 page size.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @throws InvalidArgumentException When the page size is invalid.
	 */
	private function page_size( WP_REST_Request $request ): int {
		$value = $request->get_param( 'per_page' ) ?? 50;
		$id    = $this->positive_id( $value );
		if ( $id > 100 ) {
			throw new InvalidArgumentException( 'Page size is invalid.' );
		}
		return $id;
	}

	/**
	 * Parse one strict positive integer.
	 *
	 * @param mixed $value External value.
	 * @throws InvalidArgumentException When the value is invalid.
	 */
	private function positive_id( mixed $value ): int {
		if ( is_int( $value ) && $value > 0 ) {
			return $value;
		}
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
			throw new InvalidArgumentException( 'Identifier is invalid.' );
		}
		return (int) $value;
	}

	/**
	 * Map one Tag row to the approved response projection.
	 *
	 * @param array<string, mixed> $row Stored projection.
	 * @return array<string, mixed>
	 */
	private function prepare_tag( array $row ): array {
		$activated_at = is_string( $row['activated_at'] ) ? new DateTimeImmutable( $row['activated_at'], new DateTimeZone( 'UTC' ) ) : null;
		return array(
			'tag_id'                   => (string) $row['tag_id'],
			'batch_id'                 => (int) $row['batch_id'],
			'batch_code'               => (string) $row['batch_code'],
			'batch_status'             => (string) $row['batch_status'],
			'batch_activation_enabled' => (bool) $row['activation_enabled'],
			'activation_availability'  => $this->availability->decide(
				TagStatus::from( (string) $row['tag_status'] ),
				BatchStatus::from( (string) $row['batch_status'] ),
				(bool) $row['activation_enabled'],
				$this->feature_flags->is_enabled( FeatureFlag::GLOBAL_ACTIVATION ),
				$activated_at
			)->value,
			'owner_id'                 => null === $row['owner_id'] ? null : (int) $row['owner_id'],
			'tag_type'                 => (string) $row['tag_type'],
			'model_code'               => $row['model_code'],
			'tag_status'               => (string) $row['tag_status'],
			'lost_mode'                => (bool) $row['lost_mode'],
			'activated_at'             => $row['activated_at'],
			'owner_changed_at'         => $row['owner_changed_at'],
			'last_scanned_at'          => $row['last_scanned_at'],
			'created_at'               => $row['created_at'],
			'updated_at'               => $row['updated_at'],
			'finder_report_count'      => (int) $row['finder_report_count'],
			'conversation_count'       => (int) $row['conversation_count'],
		);
	}

	/**
	 * Map one Finder Report row to the approved response projection.
	 *
	 * @param array<string, mixed> $row Stored projection.
	 * @return array<string, mixed>
	 */
	private function prepare_report( array $row ): array {
		return array(
			'finder_report_id'    => (int) $row['finder_report_id'],
			'has_conversation'    => null !== $row['conversation_id'],
			'tag_id'              => (string) $row['tag_id'],
			'owner_id'            => (int) $row['owner_id_at_submission'],
			'report_status'       => (string) $row['report_status'],
			'evidence_status'     => (string) $row['evidence_status'],
			'notification_status' => $row['owner_notification_status'],
			'owner_notified_at'   => $row['owner_notified_at'],
			'expires_at'          => $row['expires_at'],
			'retention_until'     => $row['retention_until'],
			'hold_until'          => $row['hold_until'],
			'hold_active'         => null !== $row['hold_until'] && strtotime( (string) $row['hold_until'] . ' UTC' ) > time(),
			'created_at'          => $row['created_at'],
			'updated_at'          => $row['updated_at'],
			'has_message'         => (bool) $row['has_message'],
			'has_review_evidence' => (bool) $row['has_review_evidence'],
		);
	}

	/**
	 * Map one WordPress user row to the support projection.
	 *
	 * @param array<string, mixed>|null $row Stored projection.
	 * @return array<string, mixed>|null
	 */
	private function prepare_user( ?array $row ): ?array {
		if ( null === $row ) {
			return null;
		}
		$user = get_userdata( (int) $row['user_id'] );
		return array(
			'user_id'             => (int) $row['user_id'],
			'email'               => (string) $row['user_email'],
			'registered_at'       => (string) $row['user_registered'],
			'roles'               => $user ? array_values( $user->roles ) : array(),
			'tag_status_counts'   => $row['tag_status_counts'],
			'tag_count'           => (int) $row['tag_count'],
			'finder_report_count' => (int) $row['finder_report_count'],
			'conversation_count'  => (int) $row['conversation_count'],
			'wordpress_user_url'  => current_user_can( 'edit_users' ) ? get_edit_user_link( (int) $row['user_id'] ) : null,
		);
	}

	/**
	 * Enforce Schema readiness and uniform privacy-safe failure mapping.
	 *
	 * @param callable(): (WP_REST_Response|WP_Error) $operation Guarded operation.
	 */
	private function guard( callable $operation ): WP_REST_Response|WP_Error {
		if ( ! $this->schema_state->is_current() ) {
			return new WP_Error( 'returntag_schema_unavailable', __( 'TagCore database preparation is incomplete.', 'tagcore' ), array( 'status' => 503 ) );
		}
		try {
			return $operation();
		} catch ( InvalidArgumentException $exception ) {
			if ( 'Use Owner User ID.' === $exception->getMessage() ) {
				return $this->invalid( __( 'More than one account uses this email. Search by Owner User ID.', 'tagcore' ) );
			}
			return $this->invalid( __( 'Review the exact search values and try again.', 'tagcore' ) );
		} catch ( Throwable ) {
			return new WP_Error( 'returntag_admin_query_failed', __( 'TagCore could not complete this secure operation.', 'tagcore' ), array( 'status' => 503 ) );
		}
	}

	/**
	 * Create a private no-store response.
	 *
	 * @param mixed $data Response data.
	 */
	private function response( mixed $data ): WP_REST_Response {
		$response = new WP_REST_Response( $data );
		$response->header( 'Cache-Control', 'no-store, private' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Referrer-Policy', 'no-referrer' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		return $response;
	}

	/**
	 * Return a safe validation failure.
	 *
	 * @param string $message Translatable user-facing message.
	 */
	private function invalid( string $message ): WP_Error {
		return new WP_Error( 'returntag_invalid_request', $message, array( 'status' => 400 ) );
	}

	/** Return a privacy-safe missing-record response. */
	private function not_found(): WP_Error {
		return new WP_Error( 'returntag_not_found', __( 'The requested record is unavailable.', 'tagcore' ), array( 'status' => 404 ) );
	}
}
