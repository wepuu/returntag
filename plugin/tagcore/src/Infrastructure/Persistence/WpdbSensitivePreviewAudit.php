<?php
/**
 * Metadata-free sensitive preview audit adapter.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use InvalidArgumentException;
use ReturnTag\TagCore\Application\Admin\SensitivePreviewAudit;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/** Writes a fixed projection with no metadata, correlation, or private value. */
final readonly class WpdbSensitivePreviewAudit implements SensitivePreviewAudit {
	/**
	 * Create the audit adapter.
	 *
	 * @param WpdbGateway           $gateway Database gateway.
	 * @param TableNames            $tables Trusted table names.
	 * @param DatabaseDateTimeCodec $dates UTC database codec.
	 */
	public function __construct(
		private WpdbGateway $gateway,
		private TableNames $tables,
		private DatabaseDateTimeCodec $dates
	) {
	}

	/**
	 * Record one metadata-free successful reveal.
	 *
	 * @param string            $event_type Approved reveal event type.
	 * @param int               $operator_id WordPress operator User ID.
	 * @param int               $finder_report_id Finder Report identifier.
	 * @param DateTimeImmutable $occurred_at UTC event time.
	 * @throws InvalidArgumentException When the audit identity is invalid.
	 */
	public function record( string $event_type, int $operator_id, int $finder_report_id, DateTimeImmutable $occurred_at ): void {
		if ( ! in_array( $event_type, array( 'finder_report_message_viewed', 'finder_report_evidence_viewed' ), true ) || $operator_id < 1 || $finder_report_id < 1 ) {
			throw new InvalidArgumentException( 'Sensitive preview audit identity is invalid.' );
		}

		$this->gateway->insert(
			$this->tables->events(),
			array(
				'event_type'     => $event_type,
				'actor_type'     => 'user',
				'actor_id'       => $operator_id,
				'target_type'    => 'finder_report',
				'target_id'      => (string) $finder_report_id,
				'event_result'   => 'success',
				'correlation_id' => null,
				'metadata_json'  => null,
				'created_at'     => $this->dates->format( $occurred_at ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
