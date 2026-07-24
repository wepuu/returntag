<?php
/**
 * Safe low-level wpdb gateway.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use wpdb;

/**
 * Centralizes parameter preparation and privacy-safe database failures.
 *
 * This is an internal Infrastructure helper, not a generic Repository API.
 */
final class WpdbGateway {
	/**
	 * Create the gateway.
	 *
	 * @param wpdb $database Active WordPress database adapter.
	 */
	public function __construct( private readonly wpdb $database ) {
	}

	/**
	 * Return one prepared row or null.
	 *
	 * @param string $query Prepared-query template.
	 * @param array  $arguments Query arguments.
	 * @return array<string, mixed>|null
	 * @phpstan-param list<mixed> $arguments
	 * @throws PersistenceMappingException When the result shape is invalid.
	 */
	public function row( string $query, array $arguments ): ?array {
		$prepared = $this->prepare( $query, $arguments );
		$result   = $this->with_suppressed_errors(
			function () use ( $prepared ): ?array {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above; custom product tables intentionally bypass object caching.
				return $this->database->get_row( $prepared, ARRAY_A );
			}
		);
		$this->assert_query_succeeded();

		if ( null === $result ) {
			return null;
		}

		return $result;
	}

	/**
	 * Return prepared rows.
	 *
	 * @param string $query Prepared-query template.
	 * @param array  $arguments Query arguments.
	 * @return list<array<string, mixed>>
	 * @phpstan-param list<mixed> $arguments
	 * @throws PersistenceMappingException When a result shape is invalid.
	 */
	public function rows( string $query, array $arguments ): array {
		$prepared = $this->prepare( $query, $arguments );
		$result   = $this->with_suppressed_errors(
			function () use ( $prepared ): ?array {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above; custom product tables intentionally bypass object caching.
				return $this->database->get_results( $prepared, ARRAY_A );
			}
		);
		$this->assert_query_succeeded();

		if ( null === $result ) {
			throw new PersistenceMappingException( 'Stored records have an invalid shape.' );
		}

		$rows = array();

		foreach ( $result as $row ) {
			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Execute one prepared write and return affected rows.
	 *
	 * @param string $query Prepared-query template.
	 * @param array  $arguments Query arguments.
	 * @phpstan-param list<mixed> $arguments
	 * @throws PersistenceException When the write fails.
	 */
	public function execute( string $query, array $arguments ): int {
		$prepared = $this->prepare( $query, $arguments );
		$result   = $this->with_suppressed_errors(
			function () use ( $prepared ): int|bool {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above; explicit product write.
				return $this->database->query( $prepared );
			}
		);

		if ( false === $result ) {
			throw new PersistenceException( 'Persistence operation failed.' );
		}

		return (int) $result;
	}

	/**
	 * Insert one typed record and return its auto-increment identifier.
	 *
	 * @param string               $table Trusted TableNames identifier.
	 * @param array<string, mixed> $data Typed record data.
	 * @param array                $formats wpdb value formats.
	 * @phpstan-param list<string> $formats
	 * @throws PersistenceException When the insert fails.
	 */
	public function insert( string $table, array $data, array $formats ): int {
		$result = $this->with_suppressed_errors(
			function () use ( $table, $data, $formats ): int|false {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Explicit write to a trusted custom product table.
				return $this->database->insert( $table, $data, $formats );
			}
		);

		if ( false === $result || $this->database->insert_id < 1 ) {
			throw new PersistenceException( 'Persistence operation failed.' );
		}

		return $this->database->insert_id;
	}

	/**
	 * Insert one typed record whose primary key is application supplied.
	 *
	 * @param string               $table Trusted TableNames identifier.
	 * @param array<string, mixed> $data Typed record data.
	 * @param array                $formats wpdb value formats.
	 * @phpstan-param list<string> $formats
	 * @throws PersistenceException When the insert fails.
	 */
	public function insert_without_id( string $table, array $data, array $formats ): void {
		$result = $this->with_suppressed_errors(
			function () use ( $table, $data, $formats ): int|false {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Explicit write to a trusted custom product table.
				return $this->database->insert( $table, $data, $formats );
			}
		);

		if ( 1 !== $result ) {
			throw new PersistenceException( 'Persistence operation failed.' );
		}
	}

	/**
	 * Prepare one query using wpdb placeholders, including trusted identifiers.
	 *
	 * @param string $query Query template.
	 * @param array  $arguments Query arguments.
	 * @phpstan-param list<mixed> $arguments
	 * @throws PersistenceException When preparation fails.
	 */
	private function prepare( string $query, array $arguments ): string {
		// Query templates are closed internal literals assembled only from trusted fixed fragments.
		// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar -- PHPStan directives cannot end in punctuation.
		// @phpstan-ignore argument.type (The wpdb API accepts the typed argument list.)
		$prepared = $this->database->prepare( $query, $arguments );
		// phpcs:enable Squiz.Commenting.InlineComment.InvalidEndChar

		if ( ! is_string( $prepared ) ) {
			throw new PersistenceException( 'Persistence operation failed.' );
		}

		return $prepared;
	}

	/**
	 * Convert database errors into a fixed privacy-safe failure.
	 *
	 * @throws PersistenceException When wpdb reports a query error.
	 */
	private function assert_query_succeeded(): void {
		if ( '' !== $this->database->last_error ) {
			throw new PersistenceException( 'Persistence operation failed.' );
		}
	}

	/**
	 * Prevent wpdb from printing or logging raw SQL while preserving caller state.
	 *
	 * @template T
	 * @param callable(): T $operation Database operation.
	 * @return T
	 */
	private function with_suppressed_errors( callable $operation ): mixed {
		$previous = $this->database->suppress_errors( true );

		try {
			return $operation();
		} finally {
			$this->database->suppress_errors( $previous );
		}
	}
}
