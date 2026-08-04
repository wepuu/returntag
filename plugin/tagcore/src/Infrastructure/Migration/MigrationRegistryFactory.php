<?php
/**
 * Numbered Migration registry composition.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/**
 * Builds the complete ordered registry supported by the current code.
 */
final class MigrationRegistryFactory {
	/**
	 * Create the registry factory.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	public function __construct( private readonly wpdb $database ) {
	}

	/**
	 * Return every registered Migration in version order.
	 */
	public function create(): MigrationRegistry {

		$table_names   = new TableNames( $this->database->prefix );
		$inspector     = new WordPressSchemaInspector( $this->database );
		$batches       = new CreateBatchesTableMigration( $this->database, $table_names, $inspector );
		$tags          = new CreateTagsTableMigration( $this->database, $table_names, $inspector, $batches );
		$exports       = new CreateBatchExportsTableMigration( $this->database, $table_names, $inspector, $tags );
		$challenges    = new CreateAuthChallengesTableMigration( $this->database, $table_names, $inspector, $exports );
		$conversations = new CreateConversationsTableMigration( $this->database, $table_names, $inspector, $challenges );
		$messages      = new CreateMessagesTableMigration( $this->database, $table_names, $inspector, $conversations );
		$access_tokens = new CreateAccessTokensTableMigration( $this->database, $table_names, $inspector, $messages );
		$events        = new CreateEventsTableMigration( $this->database, $table_names, $inspector, $access_tokens );
		$reports       = new CreateFinderReportsTableMigration( $this->database, $table_names, $inspector, $events );

		return new MigrationRegistry(
			array(
				$batches,
				$tags,
				$exports,
				$challenges,
				$conversations,
				$messages,
				$access_tokens,
				$events,
				$reports,
				new CreateFinderReportMediaTableMigration( $this->database, $table_names, $inspector, $reports ),
			)
		);
	}
}
