<?php
/**
 * Defines index constraints for use in CREATE TABLE statements.
 *
 * @package WPTechnix\WP_Tables_Schema\Schema
 */

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Schema;

use WPTechnix\WP_Tables_Schema\Constants\Index_Algorithm;
use WPTechnix\WP_Tables_Schema\Constants\Index_Type;
use WPTechnix\WP_Tables_Schema\Exceptions\Schema_Exception;
use WPTechnix\WP_Tables_Schema\Util;

/**
 * Encapsulates an index definition within a CREATE TABLE schema.
 *
 * @internal
 *
 * @package WPTechnix\WP_Tables_Schema\Schema
 */
final class Index_Definition {

	/**
	 * The name of the index.
	 *
	 * @var string
	 * @phpstan-var non-empty-string
	 */
	private string $name;

	/**
	 * The list of columns to be indexed.
	 *
	 * @var array
	 * @phpstan-var list<non-empty-string>
	 */
	private array $columns;

	/**
	 * The type of the index (e.g., INDEX, UNIQUE).
	 *
	 * @var string
	 * @phpstan-var Index_Type::INDEX|Index_Type::UNIQUE|Index_Type::FULLTEXT|Index_Type::SPATIAL
	 */
	private string $type;

	/**
	 * The algorithm used for the index (BTREE or HASH).
	 *
	 * @var string|null
	 * @phpstan-var Index_Algorithm::*|null
	 */
	private ?string $using = null;

	/**
	 * Column prefix lengths for partial indexes, mapping column name to length.
	 *
	 * @var array
	 * @phpstan-var array<non-empty-string, positive-int>
	 */
	private array $column_lengths = [];

	/**
	 * Constructs an Index_Definition instance.
	 *
	 * @param string $name    The name for the index.
	 * @param array  $columns The columns to include in the index. Must not be empty.
	 * @param string $type    The type of index. Use `Index_Type` constants.
	 *
	 * @phpstan-param non-empty-string $name
	 * @phpstan-param list<non-empty-string> $columns
	 * @phpstan-param Index_Type::INDEX|Index_Type::UNIQUE|Index_Type::FULLTEXT|Index_Type::SPATIAL $type
	 *
	 * @throws Schema_Exception If any provided parameters are invalid.
	 */
	public function __construct(
		string $name,
		array $columns,
		string $type = Index_Type::INDEX
	) {
		if ( ! Util::valid_sql_identifier( $name ) ) {
			throw new Schema_Exception(
				sprintf(
					'The provided index name "%s" is invalid. It must be between 1 and %d characters long' .
					' and contain only alphanumeric characters and underscores.',
					$name,
					Util::MAX_IDENTIFIER_LENGTH
				)
			);
		}

		if ( empty( $columns ) ) {
			throw new Schema_Exception(
				sprintf(
					'Cannot define the index "%s" with an empty list of columns.' .
					' An index must cover at least one column.',
					$name
				)
			);
		}

		$valid_types = Index_Type::get_all( include_primary: false );
		if ( ! in_array( $type, $valid_types, true ) ) {
			throw new Schema_Exception(
				sprintf(
					'The specified index type "%s" is not valid. Please use one of: "%s".',
					$type,
					implode( '", "', $valid_types )
				)
			);
		}

		foreach ( $columns as $column ) {
			if ( ! Util::valid_sql_identifier( $column ) ) {
				throw new Schema_Exception(
					sprintf(
						'The column name "%s" provided for index "%s" is not a valid SQL identifier.',
						is_string( $column ) ? $column : 'NOT_A_STRING',
						$name
					)
				);
			}
		}

		$this->name    = $name;
		$this->type    = $type;
		$this->columns = $columns;
	}

	/**
	 * Sets the index algorithm (e.g., BTREE or HASH).
	 *
	 * @param string $algorithm The index algorithm. Use `Index_Algorithm` constants.
	 * @phpstan-param Index_Algorithm::* $algorithm
	 *
	 * @return self The current instance for fluent method chaining.
	 *
	 * @throws Schema_Exception If the algorithm is invalid.
	 */
	public function using( string $algorithm ): self {
		$valid_algorithms = Index_Algorithm::get_all();
		if ( ! in_array( $algorithm, $valid_algorithms, true ) ) {
			throw new Schema_Exception(
				sprintf(
					'The specified index algorithm "%s" on index "%s" is not valid. Please use one of: "%s".',
					$algorithm,
					$this->name,
					implode( '", "', $valid_algorithms )
				)
			);
		}
		$this->using = $algorithm;

		return $this;
	}

	/**
	 * Sets a prefix length for a specific column in the index.
	 *
	 * @param string $column The name of the column.
	 * @param int    $length The number of characters/bytes to index. Must be a positive integer.
	 * @phpstan-param non-empty-string $column
	 * @phpstan-param positive-int $length
	 *
	 * @return self The current instance for fluent method chaining.
	 *
	 * @throws Schema_Exception If the specified column is not part of this index.
	 */
	public function length( string $column, int $length ): self {
		if ( ! in_array( $column, $this->columns, true ) ) {
			throw new Schema_Exception(
				sprintf(
					'Cannot set a prefix length for column "%s" because it is not part of the index "%s".',
					$column,
					$this->name
				)
			);
		}

		$this->column_lengths[ $column ] = max( 1, $length );

		return $this;
	}

	/**
	 * Generates the SQL definition fragment for this index.
	 *
	 * @return string The SQL fragment for the CREATE TABLE statement.
	 * @phpstan-return non-empty-string
	 */
	public function to_sql(): string {
		$type_sql = match ( $this->type ) {
			Index_Type::UNIQUE   => 'UNIQUE KEY',
			Index_Type::FULLTEXT => 'FULLTEXT KEY',
			Index_Type::SPATIAL  => 'SPATIAL KEY',
			default              => 'KEY',
		};

		$column_definitions = [];
		foreach ( $this->columns as $column ) {
			$definition = "`{$column}`";
			if ( isset( $this->column_lengths[ $column ] ) ) {
				$definition .= "({$this->column_lengths[$column]})";
			}
			$column_definitions[] = $definition;
		}

		$columns_sql = '(' . implode( ', ', $column_definitions ) . ')';
		$sql_parts   = [ $type_sql, "`{$this->name}`", $columns_sql ];

		if ( null !== $this->using ) {
			$sql_parts[] = "USING {$this->using}";
		}

		return implode( ' ', $sql_parts );
	}

	/**
	 * Gets the type of the index.
	 *
	 * @return string The index type (e.g., INDEX, UNIQUE).
	 *
	 * @phpstan-return Index_Type::INDEX|Index_Type::UNIQUE|Index_Type::FULLTEXT|Index_Type::SPATIAL
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Gets the columns included in the index.
	 *
	 * @return array The list of column names.
	 *
	 * @phpstan-return list<non-empty-string>
	 */
	public function get_columns(): array {
		return $this->columns;
	}

	/**
	 * Gets the name of the index.
	 *
	 * @return string The index name.
	 *
	 * @phpstan-return non-empty-string
	 */
	public function get_name(): string {
		return $this->name;
	}
}
