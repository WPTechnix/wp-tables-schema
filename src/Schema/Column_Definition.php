<?php
/**
 * Defines the schema details of a column used in a CREATE TABLE statement.
 *
 * @package WPTechnix\WP_Tables_Schema\Schema
 */

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Schema;

use WPTechnix\WP_Tables_Schema\Constants\Column_Type;
use WPTechnix\WP_Tables_Schema\Exceptions\Schema_Exception;
use WPTechnix\WP_Tables_Schema\Util;

/**
 * Provides the structure and constraints of a column within a CREATE TABLE schema.
 *
 * @package WPTechnix\WP_Tables_Schema\Schema
 *
 * @internal
 */
final class Column_Definition {

	/**
	 * Column name.
	 *
	 * @var string
	 * @phpstan-var non-empty-string
	 */
	private string $name;

	/**
	 * Column data type (e.g., 'VARCHAR', 'BIGINT').
	 *
	 * @var string
	 * @phpstan-var Column_Type::*
	 */
	private string $type;

	/**
	 * Arguments for the data type (e.g., length, precision).
	 *
	 * @var array
	 * @phpstan-var list<int|string>
	 */
	private array $args;

	/**
	 * Whether the column can store NULL values.
	 *
	 * @var bool
	 */
	private bool $is_nullable = false;

	/**
	 * The SQL-formatted default value for the column.
	 *
	 * @var string|null
	 */
	private ?string $default = null;

	/**
	 * Whether the column is an UNSIGNED numeric type.
	 *
	 * @var bool
	 */
	private bool $is_unsigned = false;

	/**
	 * Whether the column auto-increments.
	 *
	 * @var bool
	 */
	private bool $auto_increments = false;

	/**
	 * A descriptive comment for the column.
	 *
	 * @var string|null
	 * @phpstan-var non-empty-string|null
	 */
	private ?string $comment = null;

	/**
	 * Whether to add the 'ON UPDATE CURRENT_TIMESTAMP' attribute.
	 *
	 * @var bool
	 */
	private bool $on_update_current = false;

	/**
	 * The character set for the column.
	 *
	 * @var string|null
	 * @phpstan-var non-empty-string|null
	 */
	private ?string $charset = null;

	/**
	 * The collation for the column.
	 *
	 * @var string|null
	 * @phpstan-var non-empty-string|null
	 */
	private ?string $collation = null;

	/**
	 * Whether the column is intended to be a primary key.
	 *
	 * @var bool
	 */
	private bool $is_primary = false;

	/**
	 * Whether the column should have a UNIQUE index.
	 *
	 * @var bool
	 */
	private bool $is_unique = false;

	/**
	 * The custom name for the UNIQUE index, if specified.
	 *
	 * @var string|null
	 * @phpstan-var non-empty-string|null
	 */
	private ?string $unique_key_name = null;

	/**
	 * Whether the column should have a standard INDEX.
	 *
	 * @var bool
	 */
	private bool $has_index = false;

	/**
	 * The custom name for the INDEX, if specified.
	 *
	 * @var string|null
	 * @phpstan-var non-empty-string|null
	 */
	private ?string $index_name = null;

	/**
	 * Whether the column should have a FULLTEXT key.
	 *
	 * @var bool
	 */
	private bool $has_fulltext = false;

	/**
	 * The custom name for the FULLTEXT key, if specified.
	 *
	 * @var string|null
	 * @phpstan-var non-empty-string|null
	 */
	private ?string $fulltext_key_name = null;

	/**
	 * Whether the column should have a SPATIAL key.
	 *
	 * @var bool
	 */
	private bool $has_spatial = false;

	/**
	 * The custom name for the SPATIAL key, if specified.
	 *
	 * @var string|null
	 * @phpstan-var non-empty-string|null
	 */
	private ?string $spatial_key_name = null;

	/**
	 * Constructs a Column_Definition instance.
	 *
	 * @param string $name The name of the column.
	 * @param string $type The data type from `Column_Type` constants.
	 * @param array  $args Optional arguments for the type (e.g., `[255]` for VARCHAR).
	 *
	 * @throws Schema_Exception If the name or type is invalid.
	 *
	 * @phpstan-param non-empty-string $name
	 * @phpstan-param Column_Type::* $type
	 * @phpstan-param list<int|string> $args
	 */
	public function __construct( string $name, string $type, array $args = [] ) {
		if ( ! Util::valid_sql_identifier( $name ) ) {
			throw new Schema_Exception(
				sprintf(
					'The column name "%s" is invalid. It must be between 1 and %d characters long' .
					' and contain only alphanumeric characters and underscores.',
					$name,
					Util::MAX_IDENTIFIER_LENGTH
				)
			);
		}

		$type = strtoupper( trim( $type ) );
		if ( ! in_array( $type, Column_Type::get_all(), true ) ) {
			throw new Schema_Exception(
				sprintf(
					'The specified column type "%s" is not a valid or supported type.',
					$type
				)
			);
		}

		/** @phpstan-var Column_Type::* $type */

		$this->type = $type;
		$this->name = $name;
		$this->args = $args;
	}

	/**
	 * Allows the column to store NULL values.
	 *
	 * @return self The current instance for fluent method chaining.
	 *
	 * @throws Schema_Exception If the column is configured as AUTO_INCREMENT or PRIMARY KEY.
	 */
	public function nullable(): self {
		if ( $this->auto_increments ) {
			throw new Schema_Exception(
				sprintf(
					'Cannot make column "%s" nullable because it is defined as AUTO_INCREMENT. Auto-incrementing columns must be NOT NULL.',
					$this->name
				)
			);
		}
		if ( $this->is_primary ) {
			throw new Schema_Exception(
				sprintf(
					'Cannot make column "%s" nullable because it is defined as a PRIMARY KEY. Primary keys must be NOT NULL.',
					$this->name
				)
			);
		}
		if ( $this->has_spatial ) {
			throw new Schema_Exception(
				sprintf(
					'Cannot make column "%s" nullable because it has a SPATIAL key. Spatially indexed columns must be NOT NULL.',
					$this->name
				)
			);
		}
		$this->is_nullable = true;
		return $this;
	}

	/**
	 * Sets a default value for the column.
	 *
	 * If the value is `null`, the column will automatically be made nullable.
	 *
	 * @param string|int|float|bool|null $value The default value.
	 * @param bool                       $is_expression Set to true if `$value` is a SQL function (e.g., 'CURRENT_TIMESTAMP').
	 *
	 * @return self The current instance for fluent method chaining.
	 *
	 * @throws Schema_Exception If a default value is set on an unsupported type (e.g., TEXT, BLOB, JSON).
	 * @throws Schema_Exception If a default value is set on an AUTO_INCREMENT column.
	 */
	public function default( string|int|float|bool|null $value, bool $is_expression = false ): self {
		if ( $this->auto_increments ) {
			throw new Schema_Exception(
				sprintf( 'Cannot set a default value on column "%s" because it is defined as AUTO_INCREMENT.', $this->name )
			);
		}

		$disallowed_types = array_merge(
			Column_Type::get_text_types(),
			Column_Type::get_blob_types(),
			[ Column_Type::JSON, Column_Type::GEOMETRY ]
		);

		if ( in_array( $this->type, $disallowed_types, true ) ) {
			throw new Schema_Exception(
				sprintf(
					'Cannot set a default value on column "%s" because its type, "%s", does not support default values.',
					$this->name,
					$this->type
				)
			);
		}

		if ( null === $value || ( is_string( $value ) && 'NULL' === strtoupper( $value ) ) ) {
			if ( $this->is_primary ) {
				throw new Schema_Exception(
					sprintf(
						'Cannot set a default value of NULL on column "%s" because it is defined as a ' .
						'PRIMARY KEY. Primary keys must be NOT NULL.',
						$this->name
					)
				);
			}

			$this->default     = 'NULL';
			$this->is_nullable = true; // A column with a default of NULL must be nullable.
		} elseif ( is_bool( $value ) ) {
			$this->default = $value ? '1' : '0';
		} elseif ( $is_expression ) {
			$this->default = (string) $value;
		} elseif ( is_numeric( $value ) ) {
			$this->default = (string) $value;
		} else {
			$this->default = "'" . Util::escape_sql( $value ) . "'";
		}

		return $this;
	}

	/**
	 * Sets the default value to CURRENT_TIMESTAMP.
	 *
	 * @return self The current instance for fluent method chaining.
	 */
	public function current_timestamp_as_default(): self {
		return $this->default( 'CURRENT_TIMESTAMP', true );
	}

	/**
	 * Marks the column as UNSIGNED.
	 *
	 * @return self The current instance for fluent method chaining.
	 *
	 * @throws Schema_Exception If called on a non-numeric column type.
	 */
	public function unsigned(): self {
		if ( ! in_array( $this->type, Column_Type::get_numeric_types(), true ) ) {
			throw new Schema_Exception(
				sprintf(
					'Cannot apply UNSIGNED to column "%s" because its type, "%s", is not numeric.',
					$this->name,
					$this->type
				)
			);
		}
		$this->is_unsigned = true;
		return $this;
	}

	/**
	 * Marks the column to AUTO_INCREMENT.
	 *
	 * An auto-incrementing column must be an integer type, must be NOT NULL, and cannot have a default value.
	 * This method enforces these constraints.
	 *
	 * @return self The current instance for fluent method chaining.
	 * @throws Schema_Exception If called on a non-integer, nullable, or default-valued column.
	 */
	public function auto_increment(): self {
		if ( ! in_array( $this->type, Column_Type::get_integer_types(), true ) ) {
			throw new Schema_Exception(
				sprintf(
					'Cannot apply AUTO_INCREMENT to column "%s" because its type, "%s", is not an integer type.',
					$this->name,
					$this->type
				)
			);
		}
		if ( $this->is_nullable ) {
			throw new Schema_Exception(
				sprintf( 'Cannot apply AUTO_INCREMENT to column "%s" because it has been defined as nullable.', $this->name )
			);
		}
		if ( null !== $this->default ) {
			throw new Schema_Exception(
				sprintf( 'Cannot apply AUTO_INCREMENT to column "%s" because it already has a default value defined.', $this->name )
			);
		}

		$this->auto_increments = true;
		return $this;
	}

	/**
	 * Sets the column as the primary key.
	 *
	 * @return self The current instance for fluent method chaining.
	 *
	 * @throws Schema_Exception When the column is configured as nullable.
	 */
	public function primary(): self {
		if ( $this->is_nullable ) {
			throw new Schema_Exception(
				sprintf(
					'Cannot make column "%s" a PRIMARY KEY because it has been defined ' .
					'as nullable. Primary keys must be NOT NULL.',
					$this->name
				)
			);
		}
		$this->is_primary = true;
		return $this;
	}

	/**
	 * Adds a UNIQUE key on this column.
	 *
	 * @param string|null $key_name Optional custom name for the unique key.
	 * @phpstan-param non-empty-string|null $key_name
	 *
	 * @return self The current instance for fluent method chaining.
	 *
	 * @throws Schema_Exception When empty or invalid name provided.
	 */
	public function unique( ?string $key_name = null ): self {
		if ( null !== $key_name && ! Util::valid_sql_identifier( $key_name ) ) {
			throw new Schema_Exception(
				sprintf(
					'UNIQUE KEY set for column "%s" has invalid name "%s".',
					$this->name,
					$key_name
				)
			);
		}

		$this->is_unique       = true;
		$this->unique_key_name = $key_name;

		return $this;
	}

	/**
	 * Adds a standard/non-unique INDEX (KEY) on this column.
	 *
	 * @param string|null $index_name Optional custom name for the index.
	 * @phpstan-param non-empty-string|null $index_name
	 *
	 * @return self The current instance for fluent method chaining.
	 *
	 * @throws Schema_Exception When empty or invalid name provided.
	 */
	public function index( ?string $index_name = null ): self {
		if ( null !== $index_name && ! Util::valid_sql_identifier( $index_name ) ) {
			throw new Schema_Exception(
				sprintf(
					'INDEX set for column "%s" has invalid name "%s".',
					$this->name,
					$index_name
				)
			);
		}

		$this->has_index  = true;
		$this->index_name = $index_name;

		return $this;
	}

	/**
	 * Adds a FULLTEXT index on this column.
	 *
	 * @param string|null $key_name Optional custom name for the fulltext key.
	 * @phpstan-param non-empty-string|null $key_name
	 *
	 * @return self The current instance for fluent method chaining.
	 *
	 * @throws Schema_Exception When empty or invalid name provided.
	 * @throws Schema_Exception If the column type does not support FULLTEXT indexing (e.g., not a string type).
	 */
	public function fulltext( ?string $key_name = null ): self {
		$allowed_types = Column_Type::get_string_types();

		if ( ! in_array( $this->type, $allowed_types, true ) ) {
			throw new Schema_Exception(
				sprintf(
					'FULLTEXT keys can only be applied to string-based columns (e.g., CHAR, VARCHAR, TEXT).' .
					' Column "%s" has an unsupported type "%s".',
					$this->name,
					$this->type
				)
			);
		}

		if ( null !== $key_name && ! Util::valid_sql_identifier( $key_name ) ) {
			throw new Schema_Exception(
				sprintf(
					'FULLTEXT KEY set for column "%s" has invalid name "%s".',
					$this->name,
					$key_name
				)
			);
		}

		$this->has_fulltext      = true;
		$this->fulltext_key_name = $key_name;
		return $this;
	}

	/**
	 * Adds a SPATIAL index on this column.
	 *
	 * A spatially indexed column must be of a spatial data type and must be defined as NOT NULL.
	 *
	 * @param string|null $key_name Optional custom name for the spatial index.
	 * @phpstan-param non-empty-string|null $key_name
	 *
	 * @return self The current instance for fluent method chaining.
	 *
	 * @throws Schema_Exception When empty or invalid name provided.
	 * @throws Schema_Exception If the column type is not spatial, or if the column is defined as nullable.
	 */
	public function spatial( ?string $key_name = null ): self {
		if ( ! in_array( $this->type, Column_Type::get_spatial_types(), true ) ) {
			throw new Schema_Exception(
				sprintf(
					'SPATIAL keys can only be applied to spatial data types. Column "%s" has an unsupported type "%s".',
					$this->name,
					$this->type
				)
			);
		}

		if ( $this->is_nullable ) {
			throw new Schema_Exception(
				sprintf(
					'Cannot apply a SPATIAL key to column "%s" because it is defined as nullable. Spatially indexed columns must be NOT NULL.',
					$this->name
				)
			);
		}

		if ( null !== $key_name && ! Util::valid_sql_identifier( $key_name ) ) {
			throw new Schema_Exception(
				sprintf(
					'SPATIAL KEY set for column "%s" has invalid name "%s".',
					$this->name,
					$key_name
				)
			);
		}

		$this->has_spatial      = true;
		$this->spatial_key_name = $key_name;
		return $this;
	}

	/**
	 * Adds a comment to the column definition.
	 *
	 * @param string $comment The comment text.
	 * @phpstan-param non-empty-string $comment
	 *
	 * @return self The current instance for fluent method chaining.
	 */
	public function comment( string $comment ): self {
		$this->comment = $comment;
		return $this;
	}

	/**
	 * Adds the `ON UPDATE CURRENT_TIMESTAMP` attribute.
	 *
	 * @return self The current instance for fluent method chaining.
	 *
	 * @throws Schema_Exception If called on a column type other than DATETIME or TIMESTAMP.
	 */
	public function on_update_current_timestamp(): self {
		$allowed_types = [ Column_Type::DATETIME, Column_Type::TIMESTAMP ];
		if ( ! in_array( $this->type, $allowed_types, true ) ) {
			throw new Schema_Exception(
				sprintf(
					'Cannot use ON UPDATE CURRENT_TIMESTAMP on column "%s". This attribute is' .
					' only for DATETIME and TIMESTAMP types, but the column type is "%s".',
					$this->name,
					$this->type
				)
			);
		}
		$this->on_update_current = true;
		return $this;
	}

	/**
	 * Sets a specific character set for this column.
	 *
	 * @param string $charset The character set (e.g., 'utf8mb4').
	 * @phpstan-param non-empty-string $charset
	 *
	 * @return self The current instance for fluent method chaining.
	 *
	 * @throws Schema_Exception If called on a non-string column type.
	 */
	public function charset( string $charset ): self {

		if ( ! $this->type_supports_charset() ) {
			throw new Schema_Exception(
				sprintf(
					'Cannot apply CHARACTER SET to column "%s" because its type, "%s", is not a string type.',
					$this->name,
					$this->type
				)
			);
		}

		$charset = trim( $charset );

		if ( empty( $charset ) ) {
			throw new Schema_Exception(
				sprintf(
					'Empty charset provided for column "%s".',
					$this->name
				)
			);
		}

		$this->charset = $charset;
		return $this;
	}

	/**
	 * Sets a specific collation for this column.
	 *
	 * @param string $collation The collation (e.g., 'utf8mb4_unicode_ci').
	 * @phpstan-param non-empty-string $collation
	 *
	 * @return self The current instance for fluent method chaining.
	 *
	 * @throws Schema_Exception If called on a non-string column type.
	 */
	public function collate( string $collation ): self {

		if ( ! $this->type_supports_charset() ) {
			throw new Schema_Exception(
				sprintf(
					'Cannot apply COLLATION to column "%s" because its type, "%s", is not a string type.',
					$this->name,
					$this->type
				)
			);
		}

		$collation = trim( $collation );

		if ( empty( $collation ) ) {
			throw new Schema_Exception(
				sprintf(
					'Empty Collation provided for column "%s".',
					$this->name
				)
			);
		}

		$this->collation = $collation;
		return $this;
	}

	/**
	 * Generates the full SQL definition for this column.
	 *
	 * As composite primary keys are not used, the PRIMARY KEY clause is
	 * always generated here if the column is marked as primary.
	 *
	 * @return string The SQL fragment for the column.
	 * @phpstan-return non-empty-string
	 */
	public function to_sql(): string {
		$type_with_args = $this->type;
		if ( ! empty( $this->args ) ) {
			$type_with_args .= '(' . implode( ',', $this->args ) . ')';
		}

		$sql_parts = [ sprintf( '`%s` %s', $this->name, $type_with_args ) ];

		if ( $this->is_unsigned ) {
			$sql_parts[] = 'UNSIGNED';
		}
		if ( null !== $this->charset ) {
			$sql_parts[] = "CHARACTER SET {$this->charset}";
		}
		if ( null !== $this->collation ) {
			$sql_parts[] = "COLLATE {$this->collation}";
		}

		$sql_parts[] = $this->is_nullable ? 'NULL' : 'NOT NULL';

		if ( null !== $this->default ) {
			$sql_parts[] = "DEFAULT {$this->default}";
		}
		if ( $this->on_update_current ) {
			$sql_parts[] = 'ON UPDATE CURRENT_TIMESTAMP';
		}
		if ( $this->auto_increments ) {
			$sql_parts[] = 'AUTO_INCREMENT';
		}
		if ( $this->is_primary ) {
			$sql_parts[] = 'PRIMARY KEY';
		}
		if ( null !== $this->comment ) {
			$sql_parts[] = "COMMENT '" . Util::escape_sql( $this->comment ) . "'";
		}

		return implode( ' ', $sql_parts );
	}

	/**
	 * Gets the column name.
	 *
	 * @return string
	 * @phpstan-return non-empty-string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Checks if this column is intended to be a primary key.
	 *
	 * @return bool
	 */
	public function is_primary(): bool {
		return $this->is_primary;
	}

	/**
	 * Checks if this column should have a unique index.
	 *
	 * @return bool
	 */
	public function is_unique(): bool {
		return $this->is_unique;
	}

	/**
	 * Gets the custom name for the unique index, if set.
	 *
	 * @return string|null
	 * @phpstan-return non-empty-string|null
	 */
	public function get_unique_key_name(): ?string {
		return $this->unique_key_name;
	}

	/**
	 * Checks if this column should have a standard index.
	 *
	 * @return bool
	 */
	public function has_index(): bool {
		return $this->has_index;
	}

	/**
	 * Gets the custom name for the standard index, if set.
	 *
	 * @return string|null
	 * @phpstan-return non-empty-string|null
	 */
	public function get_index_name(): ?string {
		return $this->index_name;
	}

	/**
	 * Checks if this column should have a FULLTEXT key.
	 *
	 * @return bool
	 */
	public function has_fulltext(): bool {
		return $this->has_fulltext;
	}

	/**
	 * Gets the custom name for the FULLTEXT key, if set.
	 *
	 * @return string|null
	 * @phpstan-return non-empty-string|null
	 */
	public function get_fulltext_key_name(): ?string {
		return $this->fulltext_key_name;
	}

	/**
	 * Checks if this column should have a SPATIAL key.
	 *
	 * @return bool
	 */
	public function has_spatial(): bool {
		return $this->has_spatial;
	}

	/**
	 * Gets the custom name for the SPATIAL key, if set.
	 *
	 * @return string|null
	 * @phpstan-return non-empty-string|null
	 */
	public function get_spatial_key_name(): ?string {
		return $this->spatial_key_name;
	}

	/**
	 * Checks if this column auto-increments.
	 *
	 * @return bool
	 */
	public function is_auto_increment(): bool {
		return $this->auto_increments;
	}

	/**
	 * Check if column allows NULL values.
	 *
	 * @return bool
	 */
	public function is_nullable(): bool {
		return $this->is_nullable;
	}

	/**
	 * Checks if the column's data type supports character sets and collations.
	 *
	 * @return bool
	 */
	private function type_supports_charset(): bool {
		$string_types = array_merge(
			Column_Type::get_string_types(),
			[ Column_Type::ENUM, Column_Type::SET ]
		);
		return in_array( $this->type, $string_types, true );
	}
}
