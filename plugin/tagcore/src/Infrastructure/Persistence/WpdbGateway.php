<?php
/**
 * Safe low-level wpdb gateway.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceDuplicateKeyException;
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
	 * MySQL and MariaDB duplicate-key error number.
	 */
	private const DUPLICATE_KEY_ERROR = 1062;

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
	 * @throws PersistenceDuplicateKeyException When a unique key already exists.
	 * @throws PersistenceException When another insert failure occurs.
	 */
	public function insert_without_id( string $table, array $data, array $formats ): void {
		$outcome = $this->with_suppressed_errors(
			function () use ( $table, $data, $formats ): array {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Explicit write to a trusted custom product table.
				$result = $this->database->insert( $table, $data, $formats );

				return array(
					'result'     => $result,
					'error_code' => 1 === $result ? null : $this->read_database_error_code(),
				);
			}
		);

		if ( 1 === $outcome['result'] ) {
			return;
		}

		if ( self::DUPLICATE_KEY_ERROR === $outcome['error_code'] ) {
			throw new PersistenceDuplicateKeyException( 'Persistence operation failed because a unique key already exists.' );
		}

		throw new PersistenceException( 'Persistence operation failed.' );
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
	 * Read only the numeric code from the current connection error stack.
	 *
	 * The database message can contain SQL or record values and must never cross
	 * this boundary.
	 */
	private function read_database_error_code(): ?int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fixed diagnostic statement on the active connection.
		$error = $this->database->get_row( 'SHOW ERRORS LIMIT 1', ARRAY_A );

		if ( ! is_array( $error ) || ! array_key_exists( 'Code', $error ) ) {
			return null;
		}

		$code = $error['Code'];

		if ( is_int( $code ) ) {
			return $code;
		}

		if ( is_string( $code ) && ctype_digit( $code ) ) {
			return (int) $code;
		}

		return null;
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
