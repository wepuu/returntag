<?php
/**
 * Schema postcondition verifier.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Migration;

use wpdb;

/**
 * Verifies an exact table contract without exposing raw database errors.
 *
 * @phpstan-type ColumnRequirement array{
 *     data_type: string,
 *     unsigned: bool,
 *     nullable: bool,
 *     default: int|string|null,
 *     maximum_length?: int,
 *     character_set?: string,
 *     collation?: string,
 *     auto_increment?: bool
 * }
 * @phpstan-type IndexRequirement array{unique: bool, columns: list<string>}
 */
final class WordPressSchemaInspector {
	/**
	 * WordPress database adapter.
	 *
	 * @var wpdb
	 */
	private wpdb $database;

	/**
	 * Create an inspector for the active WordPress database.
	 *
	 * @param wpdb $database WordPress database adapter.
	 */
	public function __construct( wpdb $database ) {
		$this->database = $database;
	}

	/**
	 * Verify table, column, and index postconditions.
	 *
	 * @param string $table_name      Trusted table name.
	 * @param string $expected_engine Required storage engine.
	 * @param string $expected_collation Required table collation; empty skips the collation comparison.
	 * @param array  $expected_columns Exact required column map.
	 * @param array  $expected_indexes Exact required index map.
	 * @phpstan-param array<string, ColumnRequirement> $expected_columns
	 * @phpstan-param array<string, IndexRequirement> $expected_indexes
	 */
	public function verify_table(
		string $table_name,
		string $expected_engine,
		string $expected_collation,
		array $expected_columns,
		array $expected_indexes
	): bool {
		return SchemaTableState::EXACT === $this->inspect_table(
			$table_name,
			$expected_engine,
			$expected_collation,
			$expected_columns,
			$expected_indexes
		);
	}

	/**
	 * Classify the table before any potentially mutating dbDelta call.
	 *
	 * Only an absent table or missing expected indexes are safe to repair.
	 * Existing column, engine, collation, or index-definition drift is blocked.
	 *
	 * @param string $table_name        Trusted table name.
	 * @param string $expected_engine   Required storage engine.
	 * @param string $expected_collation Required table collation; empty skips the collation comparison.
	 * @param array  $expected_columns  Exact required column map.
	 * @param array  $expected_indexes  Exact required index map.
	 * @phpstan-param array<string, ColumnRequirement> $expected_columns
	 * @phpstan-param array<string, IndexRequirement> $expected_indexes
	 */
	public function inspect_table(
		string $table_name,
		string $expected_engine,
		string $expected_collation,
		array $expected_columns,
		array $expected_indexes
	): SchemaTableState {
		$table = $this->read_table( $table_name );

		if ( null === $table ) {
			return SchemaTableState::ABSENT;
		}

		if ( 0 !== strcasecmp( $expected_engine, $table['engine'] ) ) {
			return SchemaTableState::INCOMPATIBLE;
		}

		if ( '' !== $expected_collation && 0 !== strcasecmp( $expected_collation, $table['collation'] ) ) {
			return SchemaTableState::INCOMPATIBLE;
		}

		$columns = $this->read_columns( $table_name );

		if ( array_keys( $expected_columns ) !== array_keys( $columns ) ) {
			return SchemaTableState::INCOMPATIBLE;
		}

		foreach ( $expected_columns as $column_name => $requirement ) {
			if ( ! $this->column_matches( $columns[ $column_name ], $requirement ) ) {
				return SchemaTableState::INCOMPATIBLE;
			}
		}

		$actual_indexes = $this->read_indexes( $table_name );

		if ( $expected_indexes === $actual_indexes ) {
			return SchemaTableState::EXACT;
		}

		foreach ( $actual_indexes as $index_name => $index ) {
			if ( ! isset( $expected_indexes[ $index_name ] ) || $expected_indexes[ $index_name ] !== $index ) {
				return SchemaTableState::INCOMPATIBLE;
			}
		}

		return SchemaTableState::REPAIRABLE_INDEX_DRIFT;
	}

	/**
	 * Load the current table engine and collation.
	 *
	 * @param string $table_name Trusted table name.
	 * @return array{engine: string, collation: string}|null
	 * @throws MigrationException When schema metadata cannot be read safely.
	 */
	private function read_table( string $table_name ): ?array {
		$database_name = $this->database_name();
		$query         = $this->prepare(
			'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
			array( $database_name, $table_name )
		);
		$row           = $this->with_suppressed_errors(
			fn(): ?array => $this->database->get_row( $query, ARRAY_A )
		);

		if ( null === $row ) {
			return null;
		}

		if ( ! is_string( $row['ENGINE'] ?? null ) || ! is_string( $row['TABLE_COLLATION'] ?? null ) ) {
			throw new MigrationException( 'Schema inspection failed.' );
		}

		return array(
			'engine'    => $row['ENGINE'],
			'collation' => $row['TABLE_COLLATION'],
		);
	}

	/**
	 * Load normalized column metadata in ordinal order.
	 *
	 * @param string $table_name Trusted table name.
	 * @return array<string, array{
	 *     data_type: string,
	 *     column_type: string,
	 *     nullable: bool,
	 *     default: int|string|null,
	 *     maximum_length: int|null,
	 *     character_set: string|null,
	 *     collation: string|null,
	 *     extra: string
	 * }>
	 * @throws MigrationException When schema metadata cannot be read safely.
	 */
	private function read_columns( string $table_name ): array {
		$database_name = $this->database_name();
		$query         = $this->prepare(
			'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_MAXIMUM_LENGTH, CHARACTER_SET_NAME, COLLATION_NAME, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s ORDER BY ORDINAL_POSITION',
			array( $database_name, $table_name )
		);
		$rows          = $this->with_suppressed_errors(
			fn(): ?array => $this->database->get_results( $query, ARRAY_A )
		);
		$map           = array();

		if ( null === $rows ) {
			throw new MigrationException( 'Schema inspection failed.' );
		}

		foreach ( $rows as $row ) {
			if ( ! is_string( $row['COLUMN_NAME'] ?? null ) ) {
				throw new MigrationException( 'Schema inspection failed.' );
			}

			$column_name         = $row['COLUMN_NAME'];
			$map[ $column_name ] = array(
				'data_type'      => strtolower( (string) ( $row['DATA_TYPE'] ?? '' ) ),
				'column_type'    => strtolower( (string) ( $row['COLUMN_TYPE'] ?? '' ) ),
				'nullable'       => 'YES' === ( $row['IS_NULLABLE'] ?? null ),
				'default'        => $row['COLUMN_DEFAULT'] ?? null,
				'maximum_length' => isset( $row['CHARACTER_MAXIMUM_LENGTH'] ) ? (int) $row['CHARACTER_MAXIMUM_LENGTH'] : null,
				'character_set'  => is_string( $row['CHARACTER_SET_NAME'] ?? null ) ? strtolower( $row['CHARACTER_SET_NAME'] ) : null,
				'collation'      => is_string( $row['COLLATION_NAME'] ?? null ) ? strtolower( $row['COLLATION_NAME'] ) : null,
				'extra'          => strtolower( (string) ( $row['EXTRA'] ?? '' ) ),
			);
		}

		return $map;
	}

	/**
	 * Determine whether one actual column meets its exact requirement.
	 *
	 * @param array $actual Actual normalized column metadata.
	 * @param array $expected Expected column contract.
	 * @phpstan-param array{
	 *     data_type: string,
	 *     column_type: string,
	 *     nullable: bool,
	 *     default: int|string|null,
	 *     maximum_length: int|null,
	 *     character_set: string|null,
	 *     collation: string|null,
	 *     extra: string
	 * } $actual
	 * @phpstan-param ColumnRequirement $expected
	 */
	private function column_matches( array $actual, array $expected ): bool {
		if ( strtolower( $expected['data_type'] ) !== $actual['data_type'] ) {
			return false;
		}

		if ( str_contains( $actual['column_type'], 'unsigned' ) !== $expected['unsigned'] ) {
			return false;
		}

		if ( $expected['nullable'] !== $actual['nullable'] || $this->normalize_default( $expected['default'] ) !== $this->normalize_default( $actual['default'] ) ) {
			return false;
		}

		if ( isset( $expected['maximum_length'] ) && $expected['maximum_length'] !== $actual['maximum_length'] ) {
			return false;
		}

		if ( isset( $expected['character_set'] ) && strtolower( $expected['character_set'] ) !== $actual['character_set'] ) {
			return false;
		}

		if ( isset( $expected['collation'] ) && strtolower( $expected['collation'] ) !== $actual['collation'] ) {
			return false;
		}

		$auto_increment = str_contains( $actual['extra'], 'auto_increment' );

		return ( $expected['auto_increment'] ?? false ) === $auto_increment;
	}

	/**
	 * Load normalized index metadata in database order.
	 *
	 * @param string $table_name Trusted table name.
	 * @return array<string, array{unique: bool, columns: list<string>}>
	 * @throws MigrationException When schema metadata cannot be read safely.
	 */
	private function read_indexes( string $table_name ): array {
		$database_name = $this->database_name();
		$query         = $this->prepare(
			'SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s ORDER BY INDEX_NAME, SEQ_IN_INDEX',
			array( $database_name, $table_name )
		);
		$rows          = $this->with_suppressed_errors(
			fn(): ?array => $this->database->get_results( $query, ARRAY_A )
		);
		$map           = array();

		if ( null === $rows ) {
			throw new MigrationException( 'Schema inspection failed.' );
		}

		foreach ( $rows as $row ) {
			$index_name = $row['INDEX_NAME'] ?? null;
			$column     = $row['COLUMN_NAME'] ?? null;

			if ( ! is_string( $index_name ) || ! is_string( $column ) || null !== ( $row['SUB_PART'] ?? null ) ) {
				throw new MigrationException( 'Schema inspection failed.' );
			}

			if ( ! isset( $map[ $index_name ] ) ) {
				$map[ $index_name ] = array(
					'unique'  => '0' === (string) ( $row['NON_UNIQUE'] ?? '' ),
					'columns' => array(),
				);
			}

			$map[ $index_name ]['columns'][] = $column;
		}

		ksort( $map );

		return $map;
	}

	/**
	 * Read the active database name without accessing protected wpdb state.
	 *
	 * @throws MigrationException When the active database cannot be determined.
	 */
	private function database_name(): string {
		$database_name = $this->with_suppressed_errors(
			fn(): mixed => $this->database->get_var( 'SELECT DATABASE()' )
		);

		if ( ! is_string( $database_name ) || '' === $database_name ) {
			throw new MigrationException( 'Schema inspection failed.' );
		}

		return $database_name;
	}

	/**
	 * Prepare one information_schema query.
	 *
	 * @param string $query Query template.
	 * @param array  $arguments Query values.
	 * @phpstan-param literal-string $query
	 * @phpstan-param list<string> $arguments
	 * @throws MigrationException When query preparation fails.
	 */
	private function prepare( string $query, array $arguments ): string {
		$prepared = $this->database->prepare( $query, $arguments );

		if ( ! is_string( $prepared ) ) {
			throw new MigrationException( 'Schema inspection failed.' );
		}

		return $prepared;
	}

	/**
	 * Execute one metadata query without exposing raw database errors.
	 *
	 * @template T
	 * @param callable(): T $operation Metadata query.
	 * @return T
	 * @throws MigrationException When the database reports a metadata-query failure.
	 */
	private function with_suppressed_errors( callable $operation ): mixed {
		$previous = $this->database->suppress_errors( true );

		try {
			$result = $operation();

			if ( '' !== $this->database->last_error ) {
				throw new MigrationException( 'Schema inspection failed.' );
			}

			return $result;
		} finally {
			$this->database->suppress_errors( $previous );
		}
	}

	/**
	 * Normalize database scalar defaults without collapsing NULL and empty text.
	 *
	 * @param int|string|null $value Default value.
	 */
	private function normalize_default( int|string|null $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$normalized = (string) $value;

		if ( 'NULL' === strtoupper( $normalized ) ) {
			return null;
		}

		if ( 2 <= strlen( $normalized ) && "'" === $normalized[0] && "'" === $normalized[ strlen( $normalized ) - 1 ] ) {
			$normalized = substr( $normalized, 1, -1 );
			$normalized = str_replace( "''", "'", $normalized );
		}

		return $normalized;
	}
}
