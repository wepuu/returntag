<?php
/**
 * Deterministic temporary CSV Batch export builder.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Export;

use ReturnTag\TagCore\Application\Batch\BatchExportArtifact;
use ReturnTag\TagCore\Application\Batch\BatchExportArtifactBuilder;
use ReturnTag\TagCore\Application\Batch\BatchExportSourceTag;
use ReturnTag\TagCore\Application\Batch\Exception\BatchExportArtifactFailure;
use ReturnTag\TagCore\Application\Batch\Exception\BatchExportIntegrityViolation;
use ReturnTag\TagCore\Application\Batch\PublicTagUrlBuilder;
use ReturnTag\TagCore\Application\Persistence\Record\BatchRecord;
use ReturnTag\TagCore\Domain\Tag\TagType;
use Throwable;

/**
 * Builds UTF-8, BOM-free, CRLF CSV bytes in a private temporary file.
 */
final readonly class TemporaryCsvBatchExportBuilder implements BatchExportArtifactBuilder {
	/**
	 * Create the builder.
	 *
	 * @param PublicTagUrlBuilder $urls Trusted QR URL builder.
	 */
	public function __construct( private PublicTagUrlBuilder $urls ) {
	}

	/**
	 * Build one exact manufacturing CSV.
	 *
	 * @param BatchRecord $batch Batch manufacturing snapshot.
	 * @param iterable    $tags Deterministically ordered Tags.
	 * @phpstan-param iterable<BatchExportSourceTag> $tags
	 * @throws BatchExportArtifactFailure When the exact CSV cannot be prepared.
	 * @throws Throwable When a source or URL dependency fails.
	 */
	public function build( BatchRecord $batch, iterable $tags ): BatchExportArtifact {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_tempnam -- Native tempnam creates the private file atomically in the trusted system temp directory.
		$path = tempnam( sys_get_temp_dir(), 'tagcore-' );

		if ( false === $path ) {
			throw new BatchExportArtifactFailure( 'Batch export artifact could not be prepared.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Private temporary artifact requires an exact byte stream.
		$handle = fopen( $path, 'wb' );

		if ( false === $handle ) {
			wp_delete_file( $path );
			throw new BatchExportArtifactFailure( 'Batch export artifact could not be prepared.' );
		}

		$row_count = 0;

		try {
			$this->write_row(
				$handle,
				array(
					'sequence_no',
					'batch_code',
					'tag_id',
					'tag_type',
					'model_code',
					'smart_network',
					'qr_url',
				)
			);

			foreach ( $tags as $tag ) {
				++$row_count;
				$this->assert_tag_snapshot( $batch, $tag );
				$network = TagType::SMART_TAG === $tag->tag_type
					? $batch->data->smart_network->value
					: '';

				$this->write_row(
					$handle,
					array(
						(string) $row_count,
						$this->spreadsheet_safe( $batch->data->batch_code ),
						$tag->tag_id->value,
						$tag->tag_type->value,
						$this->spreadsheet_safe( $tag->model_code ?? '' ),
						$network,
						$this->urls->for_tag( $tag->tag_id ),
					)
				);
			}
		} catch ( Throwable $exception ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the exact private stream on failure.
			wp_delete_file( $path );
			throw $exception;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Finalizes the exact private stream.
		if ( ! fclose( $handle ) ) {
			wp_delete_file( $path );
			throw new BatchExportArtifactFailure( 'Batch export artifact could not be finalized.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_hash_file -- Digest covers exact private artifact bytes.
		$checksum  = hash_file( 'sha256', $path );
		$byte_size = filesize( $path );

		if ( ! is_string( $checksum ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $checksum ) || ! is_int( $byte_size ) ) {
			wp_delete_file( $path );
			throw new BatchExportArtifactFailure( 'Batch export artifact could not be verified.' );
		}

		return new TemporaryBatchExportArtifact( $path, $row_count, $checksum, $byte_size );
	}

	/**
	 * Write one RFC 4180-style row with a fixed CRLF ending.
	 *
	 * @param resource $handle Temporary output stream.
	 * @param array    $fields Exact cell values.
	 * @phpstan-param list<string> $fields
	 * @throws BatchExportArtifactFailure When the row cannot be written.
	 */
	private function write_row( $handle, array $fields ): void {
		if ( false === fputcsv( $handle, $fields, ',', '"', '', "\r\n" ) ) {
			throw new BatchExportArtifactFailure( 'Batch export artifact could not be written.' );
		}
	}

	/**
	 * Reject Tag manufacturing snapshots that differ from the Batch.
	 *
	 * @param BatchRecord          $batch Batch snapshot.
	 * @param BatchExportSourceTag $tag Tag snapshot.
	 * @throws BatchExportIntegrityViolation When the Tag snapshot differs.
	 */
	private function assert_tag_snapshot( BatchRecord $batch, BatchExportSourceTag $tag ): void {
		if (
			$tag->tag_type !== $batch->data->tag_type
			|| $tag->model_code !== $batch->data->model_code
		) {
			throw new BatchExportIntegrityViolation( 'Batch export source is inconsistent.' );
		}
	}

	/**
	 * Neutralize spreadsheet formula prefixes in operator-controlled cells.
	 *
	 * @param string $value Raw validated value.
	 */
	private function spreadsheet_safe( string $value ): string {
		return 1 === preg_match( '/^[=+\-@]/D', $value )
			? "'" . $value
			: $value;
	}
}
