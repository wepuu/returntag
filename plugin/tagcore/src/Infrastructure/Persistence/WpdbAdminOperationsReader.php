<?php
/**
 * Narrow read projections for the TagCore operations console.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/** Executes bounded, exact-anchor operational queries against approved indexes. */
final readonly class WpdbAdminOperationsReader {
	/**
	 * Create the query reader.
	 *
	 * @param WpdbGateway $gateway Database gateway.
	 * @param TableNames  $tables Trusted TagCore table names.
	 * @param string      $users_table Trusted WordPress users table name.
	 */
	public function __construct(
		private WpdbGateway $gateway,
		private TableNames $tables,
		private string $users_table
	) {
	}

	/**
	 * Resolve a complete email address without returning it to callers.
	 *
	 * @param string $email Complete validated email address.
	 * @return list<int>
	 */
	public function user_ids_for_email( string $email ): array {
		$rows = $this->gateway->rows(
			'SELECT ID FROM %i WHERE user_email = %s ORDER BY ID ASC LIMIT 2',
			array( $this->users_table, $email )
		);

		return array_map( static fn( array $row ): int => (int) $row['ID'], $rows );
	}

	/**
	 * Return a bounded Tag page with no private item fields.
	 *
	 * @param array       $criteria Normalized exact-anchor criteria.
	 * @param string|null $after_tag_id Optional keyset position.
	 * @param int         $limit Bounded result limit.
	 * @return list<array<string, mixed>>
	 * @phpstan-param array<string, mixed> $criteria
	 */
	public function search_tags( array $criteria, ?string $after_tag_id, int $limit ): array {
		$where = array();
		$args  = array( $this->tables->tags(), $this->tables->batches(), $this->tables->finder_reports(), $this->tables->conversations() );

		switch ( $criteria['mode'] ) {
			case 'tag_id':
				$where[] = 'tags.tag_id = %s';
				$args[]  = $criteria['tag_id'];
				break;
			case 'batch':
				$where[] = 'batches.batch_code = %s';
				$args[]  = $criteria['batch_code'];
				break;
			default:
				$where[] = 'tags.owner_id = %d';
				$args[]  = $criteria['owner_id'];
		}

		$filters = array(
			'tag_type'   => 'tags.tag_type = %s',
			'tag_status' => 'tags.tag_status = %s',
		);
		foreach ( $filters as $key => $fragment ) {
			if ( isset( $criteria[ $key ] ) && '' !== $criteria[ $key ] ) {
				$where[] = $fragment;
				$args[]  = $criteria[ $key ];
			}
		}

		if ( array_key_exists( 'lost_mode', $criteria ) && null !== $criteria['lost_mode'] ) {
			$where[] = 'tags.lost_mode = %d';
			$args[]  = $criteria['lost_mode'] ? 1 : 0;
		}
		if ( ! empty( $criteria['activated_from'] ) ) {
			$where[] = 'tags.activated_at >= %s';
			$args[]  = $criteria['activated_from'];
		}
		if ( ! empty( $criteria['activated_to'] ) ) {
			$where[] = 'tags.activated_at <= %s';
			$args[]  = $criteria['activated_to'];
		}
		if ( null !== $after_tag_id ) {
			$where[] = 'tags.tag_id > %s';
			$args[]  = $after_tag_id;
		}

		$args[] = $limit;
		$sql    = 'SELECT tags.tag_id, tags.owner_id, tags.tag_type, tags.model_code, tags.tag_status, tags.lost_mode, tags.activated_at, tags.owner_changed_at, tags.last_scanned_at, tags.created_at, tags.updated_at, batches.batch_id, batches.batch_code, batches.batch_status, batches.activation_enabled, (SELECT COUNT(*) FROM %i reports WHERE reports.tag_id = tags.tag_id) finder_report_count, (SELECT COUNT(*) FROM %i conversations WHERE conversations.tag_id = tags.tag_id) conversation_count FROM %i tags INNER JOIN %i batches ON batches.batch_id = tags.batch_id WHERE ' . implode( ' AND ', $where ) . ' ORDER BY tags.tag_id ASC LIMIT %d';

		/* Reorder trusted identifiers to match the final query. */
		$query_args = array( $this->tables->finder_reports(), $this->tables->conversations(), $this->tables->tags(), $this->tables->batches(), ...array_slice( $args, 4 ) );
		return $this->gateway->rows( $sql, $query_args );
	}

	/**
	 * Return one Tag detail using only approved fields.
	 *
	 * @param string $tag_id Canonical Tag ID.
	 * @return array<string, mixed>|null
	 */
	public function tag( string $tag_id ): ?array {
		$items = $this->search_tags(
			array(
				'mode'   => 'tag_id',
				'tag_id' => $tag_id,
			),
			null,
			1
		);
		return $items[0] ?? null;
	}

	/**
	 * Return a bounded Finder Report page without sensitive fields.
	 *
	 * @param array    $criteria Normalized exact-anchor criteria.
	 * @param int|null $before_id Optional descending keyset position.
	 * @param int      $limit Bounded result limit.
	 * @return list<array<string, mixed>>
	 * @phpstan-param array<string, mixed> $criteria
	 */
	public function search_finder_reports( array $criteria, ?int $before_id, int $limit ): array {
		$where = array();
		$args  = array( $this->tables->finder_reports(), $this->tables->finder_report_media() );

		switch ( $criteria['mode'] ) {
			case 'report_id':
				$where[] = 'reports.finder_report_id = %d';
				$args[]  = $criteria['finder_report_id'];
				break;
			case 'tag_id':
				$where[] = 'reports.tag_id = %s';
				$args[]  = $criteria['tag_id'];
				break;
			default:
				$where[] = 'reports.owner_id_at_submission = %d';
				$args[]  = $criteria['owner_id'];
		}

		foreach ( array( 'report_status', 'evidence_status', 'owner_notification_status' ) as $key ) {
			if ( isset( $criteria[ $key ] ) && '' !== $criteria[ $key ] ) {
				$where[] = 'reports.' . $key . ' = %s';
				$args[]  = $criteria[ $key ];
			}
		}
		if ( ! empty( $criteria['created_from'] ) ) {
			$where[] = 'reports.created_at >= %s';
			$args[]  = $criteria['created_from'];
		}
		if ( ! empty( $criteria['created_to'] ) ) {
			$where[] = 'reports.created_at <= %s';
			$args[]  = $criteria['created_to'];
		}
		if ( null !== $before_id ) {
			$where[] = 'reports.finder_report_id < %d';
			$args[]  = $before_id;
		}
		$args[] = $limit;

		return $this->gateway->rows(
			'SELECT reports.finder_report_id, reports.conversation_id, reports.tag_id, reports.owner_id_at_submission, reports.report_status, reports.evidence_status, reports.owner_notification_status, reports.owner_notified_at, reports.expires_at, reports.created_at, reports.updated_at, media.media_status, media.retention_until, CASE WHEN reports.message_ciphertext IS NULL THEN 0 ELSE 1 END has_message, CASE WHEN media.review_reference_ciphertext IS NULL THEN 0 ELSE 1 END has_review_evidence FROM %i reports LEFT JOIN %i media ON media.finder_report_id = reports.finder_report_id WHERE ' . implode( ' AND ', $where ) . ' ORDER BY reports.finder_report_id DESC LIMIT %d',
			$args
		);
	}

	/**
	 * Return one Finder Report detail using the same safe projection.
	 *
	 * @param int $finder_report_id Finder Report identifier.
	 * @return array<string, mixed>|null
	 */
	public function finder_report( int $finder_report_id ): ?array {
		$items = $this->search_finder_reports(
			array(
				'mode'             => 'report_id',
				'finder_report_id' => $finder_report_id,
			),
			null,
			1
		);
		return $items[0] ?? null;
	}

	/**
	 * Return one exact WordPress user support projection.
	 *
	 * @param int $user_id WordPress User ID.
	 * @return array<string, mixed>|null
	 */
	public function user( int $user_id ): ?array {
		$row = $this->gateway->row(
			'SELECT ID user_id, user_email, user_registered FROM %i WHERE ID = %d LIMIT 1',
			array( $this->users_table, $user_id )
		);
		if ( null === $row ) {
			return null;
		}

		$tag_rows = $this->gateway->rows(
			'SELECT tag_status, COUNT(*) count FROM %i WHERE owner_id = %d GROUP BY tag_status ORDER BY tag_status ASC',
			array( $this->tables->tags(), $user_id )
		);
		$counts   = $this->gateway->row(
			'SELECT (SELECT COUNT(*) FROM %i WHERE owner_id = %d) tag_count, (SELECT COUNT(*) FROM %i WHERE owner_id_at_submission = %d) finder_report_count, (SELECT COUNT(*) FROM %i WHERE owner_id_snapshot = %d) conversation_count',
			array( $this->tables->tags(), $user_id, $this->tables->finder_reports(), $user_id, $this->tables->conversations(), $user_id )
		);

		$row['tag_status_counts']   = array_column( $tag_rows, 'count', 'tag_status' );
		$row['tag_count']           = (int) ( $counts['tag_count'] ?? 0 );
		$row['finder_report_count'] = (int) ( $counts['finder_report_count'] ?? 0 );
		$row['conversation_count']  = (int) ( $counts['conversation_count'] ?? 0 );
		return $row;
	}

	/**
	 * Return a metadata-free audit timeline for one approved target.
	 *
	 * @param string $target_type Approved Event target type.
	 * @param string $target_id Approved Event target identifier.
	 * @param int    $limit Bounded result limit.
	 * @return list<array<string, mixed>>
	 */
	public function audit( string $target_type, string $target_id, int $limit = 50 ): array {
		return $this->gateway->rows(
			'SELECT event_id, event_type, actor_type, actor_id, event_result, created_at FROM %i WHERE target_type = %s AND target_id = %s ORDER BY created_at DESC, event_id DESC LIMIT %d',
			array( $this->tables->events(), $target_type, $target_id, max( 1, min( 100, $limit ) ) )
		);
	}
}
