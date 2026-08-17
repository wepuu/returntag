<?php
/**
 * Sensitive Finder Report preview composition.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\FinderReport;

use ReturnTag\TagCore\Application\Admin\AdminSensitivePreview;
use ReturnTag\TagCore\Infrastructure\Media\PrivateMediaSecrets;
use ReturnTag\TagCore\Infrastructure\Media\SodiumFilesystemPrivateMediaStorage;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbFinderReportMediaRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbFinderReportRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbSensitivePreviewAudit;
use ReturnTag\TagCore\Infrastructure\Security\FinderReportMessageSecrets;
use ReturnTag\TagCore\Infrastructure\Security\SodiumFinderReportMessageProtector;
use ReturnTag\TagCore\Infrastructure\SystemClock;
use ReturnTag\TagCore\Infrastructure\WordPressOptionFeatureFlagReader;
use Throwable;
use wpdb;

/** Returns null unless every external secret and private path is valid. */
final class AdminSensitivePreviewFactory {
	/**
	 * Compose the preview use case only when every dependency is available.
	 *
	 * @param wpdb $database Active WordPress database adapter.
	 */
	public static function create( wpdb $database ): ?AdminSensitivePreview {
		try {
			$root = defined( FinderReportRuntimeFactory::ROOT_NAME ) ? constant( FinderReportRuntimeFactory::ROOT_NAME ) : getenv( FinderReportRuntimeFactory::ROOT_NAME );
			if ( ! is_string( $root ) || '' === trim( $root ) ) {
				return null;
			}
			$uploads = wp_upload_dir( null, false );
			$gateway = new WpdbGateway( $database );
			$tables  = new TableNames( $database->prefix );
			$dates   = new DatabaseDateTimeCodec();
			$storage = new SodiumFilesystemPrivateMediaStorage( $root, PrivateMediaSecrets::load(), array( $uploads['basedir'] ) );
			return new AdminSensitivePreview(
				new WordPressOptionFeatureFlagReader(),
				new WpdbFinderReportRepository( $gateway, $tables, $dates ),
				new WpdbFinderReportMediaRepository( $gateway, $tables, $dates ),
				new SodiumFinderReportMessageProtector( FinderReportMessageSecrets::load() ),
				$storage,
				new WpdbSensitivePreviewAudit( $gateway, $tables, $dates ),
				new SystemClock()
			);
		} catch ( Throwable ) {
			return null;
		}
	}
}
