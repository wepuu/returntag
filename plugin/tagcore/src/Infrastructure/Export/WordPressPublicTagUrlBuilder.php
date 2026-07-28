<?php
/**
 * WordPress public Tag URL builder.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Export;

use ReturnTag\TagCore\Application\Batch\Exception\BatchExportArtifactFailure;
use ReturnTag\TagCore\Application\Batch\PublicTagUrlBuilder;
use ReturnTag\TagCore\Domain\Tag\TagId;

/**
 * Uses the trusted WordPress home URL for QR destinations.
 */
final class WordPressPublicTagUrlBuilder implements PublicTagUrlBuilder {
	/**
	 * Return one absolute public Tag URL.
	 *
	 * @param TagId $tag_id Public Tag ID.
	 * @throws BatchExportArtifactFailure When the public URL is unsafe.
	 */
	public function for_tag( TagId $tag_id ): string {
		$url    = home_url( '/t/' . rawurlencode( $tag_id->value ) );
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! is_string( $scheme ) || ! is_string( $host ) || '' === $host ) {
			throw new BatchExportArtifactFailure( 'Public Tag URL configuration is unavailable.' );
		}

		if (
			'https' !== strtolower( $scheme )
			&& ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true )
		) {
			throw new BatchExportArtifactFailure( 'Public Tag URL configuration is unavailable.' );
		}

		return $url;
	}
}
