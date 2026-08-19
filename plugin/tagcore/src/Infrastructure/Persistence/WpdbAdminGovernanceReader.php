<?php
/**
 * Governance console read projections.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/** Executes bounded metadata-free Audit and retention-health queries. */
final readonly class WpdbAdminGovernanceReader {
	/**
	 * Create the query reader.
	 *
	 * @param WpdbGateway $gateway Database gateway.
	 * @param TableNames  $tables Trusted TagCore table names.
	 */
	public function __construct( private WpdbGateway $gateway, private TableNames $tables ) {}

	/**
	 * Return one bounded metadata-free Audit page.
	 *
	 * @param array<string, int|string> $criteria Normalized criteria.
	 * @param string|null               $before_time Optional keyset time.
	 * @param int|null                  $before_id Optional keyset Event ID.
	 * @param int                       $limit Bounded result limit.
	 * @return list<array<string, mixed>>
	 */
	public function search_audit_events( array $criteria, ?string $before_time, ?int $before_id, int $limit ): array {
		$where = array( 'created_at >= %s', 'created_at <= %s' );
		$args  = array( $this->tables->events(), $criteria['from'], $criteria['to'] );
		if ( isset( $criteria['actor_user_id'] ) ) {
			$where[] = "actor_type = 'user' AND actor_id = %d";
			$args[]  = $criteria['actor_user_id'];
		}
		foreach ( array( 'target_type', 'target_id', 'event_type' ) as $key ) {
			if ( isset( $criteria[ $key ] ) ) {
				$where[] = $key . ' = %s';
				$args[]  = $criteria[ $key ];
			}
		}
		if ( isset( $criteria['result'] ) ) {
			$where[] = 'event_result = %s';
			$args[]  = $criteria['result'];
		}
		if ( null !== $before_time && null !== $before_id ) {
			$where[] = '(created_at < %s OR (created_at = %s AND event_id < %d))';
			array_push( $args, $before_time, $before_time, $before_id );
		}
		$args[] = max( 1, min( 101, $limit ) );
		return $this->gateway->rows(
			'SELECT event_id, event_type, actor_type, actor_id, target_type, target_id, event_result result, created_at FROM %i WHERE ' . implode( ' AND ', $where ) . ' ORDER BY created_at DESC, event_id DESC LIMIT %d',
			$args
		);
	}

	/**
	 * Return a capped, privacy-safe pending cleanup count.
	 *
	 * @param string $task_id Fixed Task ID.
	 * @param string $now Current UTC database time.
	 */
	public function retention_backlog( string $task_id, string $now ): int {
		if ( 'finder-evidence' === $task_id ) {
			$row = $this->gateway->row( "SELECT COUNT(*) count FROM (SELECT 1 FROM %i WHERE retention_until <= %s AND (hold_until IS NULL OR hold_until <= %s) AND media_status <> 'deleted' LIMIT 1001) pending", array( $this->tables->finder_report_media(), $now, $now ) );
		} elseif ( in_array( $task_id, array( 'activation-otp', 'account-otp', 'finder-email' ), true ) ) {
			$purpose = array(
				'activation-otp' => 'activation_otp',
				'account-otp'    => 'account_otp',
				'finder-email'   => 'finder_email_otp',
			)[ $task_id ];
			$row     = $this->gateway->row( 'SELECT COUNT(*) count FROM (SELECT 1 FROM %i WHERE purpose = %s AND expires_at <= %s LIMIT 1001) pending', array( $this->tables->auth_challenges(), $purpose, $now ) );
		} else {
			return 0;
		}
		return min( 1001, (int) ( $row['count'] ?? 0 ) );
	}
}
