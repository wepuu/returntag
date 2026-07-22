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
		$table = $this->read_table( $table_name );

		if ( null === $table || 0 !== strcasecmp( $expected_engine, $table['engine'] ) ) {
			return false;
		}

		if ( '' !== $expected_collation && 0 !== strcasecmp( $expected_collation, $table['collation'] ) ) {
			return false;
		}

		$columns = $this->read_columns( $table_name );

		if ( array_keys( $expected_columns ) !== array_keys( $columns ) ) {
			return false;
		}

		foreach ( $expected_columns as $column_name => $requirement ) {
			if ( ! $this->column_matches( $columns[ $column_name ], $requirement ) ) {
				return false;
			}
		}

		return $expected_indexes === $this->read_indexes( $table_name );
	}

	/**
	 * Load the current table engine and collation.
	 *
	 * @param string $table_name Trusted table name.
	 * @return array{engine: string, collation: string}|null
	 */
	private function read_table( string $table_name ): ?array {
		$database_name = $this->database_name();

		if ( null === $database_name ) {
			return null;
		}

		$query = $this->database->prepare(
			'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
			$database_name,
			$table_name
		);
		$row   = $this->database->get_row( $query, ARRAY_A );

		if ( ! is_array( $row ) || ! is_string( $row['ENGINE'] ?? null ) || ! is_string( $row['TABLE_COLLATION'] ?? null ) ) {
			return null;
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
	 */
	private function read_columns( string $table_name ): array {
		$database_name = $this->database_name();

		if ( null === $database_name ) {
			return array();
		}

		$query = $this->database->prepare(
			'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_MAXIMUM_LENGTH, CHARACTER_SET_NAME, COLLATION_NAME, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s ORDER BY ORDINAL_POSITION',
			$database_name,
			$table_name
		);
		$rows  = $this->database->get_results( $query, ARRAY_A );
		$map   = array();

		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as $row ) {
			if ( ! is_string( $row['COLUMN_NAME'] ?? null ) ) {
				return array();
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
	 */
	private function read_indexes( string $table_name ): array {
		$database_name = $this->database_name();

		if ( null === $database_name ) {
			return array();
		}

		$query = $this->database->prepare(
			'SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s ORDER BY INDEX_NAME, SEQ_IN_INDEX',
			$database_name,
			$table_name
		);
		$rows  = $this->database->get_results( $query, ARRAY_A );
		$map   = array();

		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as $row ) {
			$index_name = $row['INDEX_NAME'] ?? null;
			$column     = $row['COLUMN_NAME'] ?? null;

			if ( ! is_string( $index_name ) || ! is_string( $column ) || null !== ( $row['SUB_PART'] ?? null ) ) {
				return array();
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
	 */
	private function database_name(): ?string {
		$database_name = $this->database->get_var( 'SELECT DATABASE()' );

		return is_string( $database_name ) && '' !== $database_name ? $database_name : null;
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
