<?php
/**
 * Fluent interface for building `CREATE TABLE` SQL statements.
 *
 * @package WPTechnix\WP_Tables_Schema\Schema
 * @author  WPTechnix <developers@wptechnix.com>
 */

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Schema;

use WPTechnix\WP_Tables_Schema\Constants\Column_Type;
use WPTechnix\WP_Tables_Schema\Constants\Index_Type;
use WPTechnix\WP_Tables_Schema\Exceptions\Schema_Exception;
use WPTechnix\WP_Tables_Schema\Util;

/**
 * Fluent interface for building `CREATE TABLE` SQL statements.
 *
 * Basic usage example:
 * ```php
 * try {
 *     $schema = new Create_Table_Schema( 'very_long_table_name', 'short_table_name' );
 *
 *     $schema->id(); // Auto-incrementing BIGINT UNSIGNED 'id' primary key.
 *     $schema->string( 'level', 50 )->index();
 *     $schema->long_text( 'message' );
 *     $schema->string( 'context' )->nullable();
 *     $schema->timestamps(); // Adds `created_at` and `updated_at` DATETIME columns.
 *
 *     // $sql contains the complete, valid CREATE TABLE statement.
 *     $sql = $schema->to_sql();
 *
 * } catch ( Schema_Exception $e ) {
 *     // Handle schema definition errors.
 *     wp_die( $e->getMessage() );
 * }
 * ```
 *
 * @see \WPTechnix\WP_Tables_Schema\Schema\Column_Definition
 * @see \WPTechnix\WP_Tables_Schema\Schema\Index_Definition
 * @see \WPTechnix\WP_Tables_Schema\Schema\Foreign_Key_Definition
 *
 * @phpstan-type Supported_Index_Type = Index_Type::INDEX|Index_Type::UNIQUE|Index_Type::FULLTEXT|Index_Type::SPATIAL
 *
 * @package WPTechnix\WP_Tables_Schema\Schema
 */
final class Create_Table_Schema {

	/**
	 * The full table name
	 *
	 * @var string
	 * @phpstan-var non-empty-string
	 */
	private string $table_name;

	/**
	 * The short table name (Ideal for genearting fk/index names).
	 *
	 * @var string
	 * @phpstan-var non-empty-string
	 */
	private string $short_table_name;

	/**
	 * Column definitions, keyed by column name for efficient lookups.
	 *
	 * @var array
	 * @phpstan-var array<non-empty-string, Column_Definition>
	 */
	private array $columns = [];

	/**
	 * Index definitions, keyed by index name for efficient lookups.
	 *
	 * @var array
	 * @phpstan-var array<non-empty-string, Index_Definition>
	 */
	private array $indexes = [];

	/**
	 * Foreign key definitions, keyed by constraint name for efficient lookups.
	 *
	 * @var array
	 * @phpstan-var array<non-empty-string, Foreign_Key_Definition>
	 */
	private array $foreign_keys = [];

	/**
	 * Storage engine.
	 *
	 * @var string
	 * @phpstan-var non-empty-string
	 */
	private string $engine = 'InnoDB';

	/**
	 * Default character set.
	 *
	 * @var string
	 * @phpstan-var non-empty-string
	 */
	private string $charset = 'utf8mb4';

	/**
	 * Default collation.
	 *
	 * @var string
	 * @phpstan-var non-empty-string
	 */
	private string $collation = 'utf8mb4_unicode_ci';

	/**
	 * Table comment.
	 *
	 * @var string|null
	 * @phpstan-var non-empty-string|null
	 */
	private ?string $comment = null;

	/**
	 * Whether to use IF NOT EXISTS.
	 *
	 * @var bool
	 */
	private bool $if_not_exists = false;

	/**
	 * AUTO_INCREMENT starting value.
	 *
	 * @var int|null
	 * @phpstan-var positive-int|null
	 */
	private ?int $auto_increment_start = null;

	/**
	 * Constructor.
	 *
	 * @param string      $table_name       Full table name.
	 * @param string|null $short_table_name A shorter version for table name. Ideal for generating
	 *                                      names for indexes and foreign keys. (Optional). If not
	 *                                      specified, $table_name will be used.
	 *
	 * @phpstan-param non-empty-string $table_name
	 * @phpstan-param non-empty-string|null $short_table_name
	 *
	 * @throws Schema_Exception When the table name is invalid.
	 */
	public function __construct( string $table_name, ?string $short_table_name = null ) {

		if ( ! Util::valid_sql_identifier( $table_name ) ) {
			throw new Schema_Exception(
				sprintf(
					'The provided table name "%s" is invalid. It must be between 1 and %d characters long' .
					' and contain only alphanumeric characters and underscores.',
					$table_name,
					Util::MAX_IDENTIFIER_LENGTH
				)
			);
		}

		if ( null === $short_table_name ) {
			$short_table_name = $table_name;
		}

		if ( ! Util::valid_sql_identifier( $short_table_name ) ) {
			throw new Schema_Exception(
				sprintf(
					'The provided short table name "%s" is invalid. It must be between 1 and %d characters long' .
					' and contain only alphanumeric characters and underscores.',
					$short_table_name,
					Util::MAX_IDENTIFIER_LENGTH
				)
			);
		}

		$this->table_name       = $table_name;
		$this->short_table_name = $short_table_name;
	}

	/*
	|--------------------------------------------------------------------------
	| Column Type Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Adds a column to the schema and prevents modification after compilation.
	 *
	 * @param Column_Definition $column The column definition.
	 * @return Column_Definition
	 * @throws Schema_Exception If the schema has already been compiled.
	 */
	private function add_column( Column_Definition $column ): Column_Definition {
		$this->columns[ $column->get_name() ] = $column;
		return $column;
	}

	/**
	 * Add a BIGINT column.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function big_integer( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::BIGINT ) );
	}

	/**
	 * Add an INT column.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function integer( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::INTEGER ) );
	}

	/**
	 * Add a MEDIUMINT column.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function medium_integer( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::MEDIUMINT ) );
	}

	/**
	 * Add a SMALLINT column.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function small_integer( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::SMALLINT ) );
	}

	/**
	 * Add a TINYINT column.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function tiny_integer( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::TINYINT ) );
	}

	/**
	 * Add a BOOLEAN column (alias for TINYINT).
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function boolean( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::TINYINT, [ 1 ] ) );
	}

	/**
	 * Add a DECIMAL column for precise numeric values.
	 *
	 * @param string $column_name The column name.
	 * @param int    $precision   Total number of digits (1-65).
	 * @param int    $scale       Digits after decimal point (0-30).
	 *
	 * @phpstan-param non-empty-string $column_name
	 * @phpstan-param int<1, 65> $precision
	 * @phpstan-param int<0, 30> $scale
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function decimal( string $column_name, int $precision = 10, int $scale = 0 ): Column_Definition {
		if ( $precision < 1 || $precision > 65 ) {
			throw new Schema_Exception(
				sprintf( 'DECIMAL precision for column "%s" must be between 1 and 65.', $column_name )
			);
		}

		if ( $scale < 0 || $scale > 30 || $scale > $precision ) {
			throw new Schema_Exception(
				sprintf(
					'DECIMAL scale for column "%s" must be between 0 and 30, and not greater than precision.',
					$column_name
				)
			);
		}

		return $this->add_column( new Column_Definition( $column_name, Column_Type::DECIMAL, [ $precision, $scale ] ) );
	}

	/**
	 * Add a NUMERIC column (standard SQL synonym for DECIMAL).
	 *
	 * @param string $column_name The column name.
	 * @param int    $precision   Total number of digits (1-65).
	 * @param int    $scale       Digits after decimal point (0-30).
	 *
	 * @phpstan-param non-empty-string $column_name
	 * @phpstan-param int<1, 65> $precision
	 * @phpstan-param int<0, 30> $scale
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function numeric( string $column_name, int $precision = 10, int $scale = 0 ): Column_Definition {
		return $this->decimal( $column_name, $precision, $scale );
	}

	/**
	 * Add a DECIMAL(19,4) column, suitable for storing monetary values.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function money( string $column_name ): Column_Definition {
		return $this->decimal( $column_name, 19, 4 );
	}

	/**
	 * Add a FLOAT column.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function float( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::FLOAT ) );
	}

	/**
	 * Add a DOUBLE column.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function double( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::DOUBLE ) );
	}

	/**
	 * Add a VARCHAR column.
	 *
	 * @param string $column_name The column name.
	 * @param int    $length      Maximum length (1-65535).
	 *
	 * @phpstan-param non-empty-string $column_name
	 * @phpstan-param int<1, 65535> $length
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function string( string $column_name, int $length = 191 ): Column_Definition {
		if ( $length < 1 || $length > 65535 ) {
			throw new Schema_Exception(
				sprintf(
					'VARCHAR length must be between 1 and 65535, got %d for column "%s".',
					$length,
					$column_name
				)
			);
		}

		return $this->add_column( new Column_Definition( $column_name, Column_Type::VARCHAR, [ $length ] ) );
	}

	/**
	 * Add a CHAR fixed-length column.
	 *
	 * @param string $column_name The column name.
	 * @param int    $length      Fixed length (1-255).
	 *
	 * @phpstan-param non-empty-string $column_name
	 * @phpstan-param int<1, 255> $length
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function char( string $column_name, int $length = 1 ): Column_Definition {
		if ( $length < 1 || $length > 255 ) {
			throw new Schema_Exception(
				sprintf( 'CHAR length must be between 1 and 255, got %d for column "%s".', $length, $column_name )
			);
		}

		return $this->add_column( new Column_Definition( $column_name, Column_Type::CHAR, [ $length ] ) );
	}

	/**
	 * Add a BINARY column (fixed-length binary string).
	 *
	 * @param string $column_name The column name.
	 * @param int    $length      The fixed length.
	 *
	 * @phpstan-param non-empty-string $column_name
	 * @phpstan-param positive-int $length
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function binary( string $column_name, int $length = 1 ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::BINARY, [ max( 1, $length ) ] ) );
	}

	/**
	 * Add a VARBINARY column (variable-length binary string).
	 *
	 * @param string $column_name The column name.
	 * @param int    $length      The maximum length.
	 *
	 * @phpstan-param non-empty-string $column_name
	 * @phpstan-param positive-int $length
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function var_binary( string $column_name, int $length = 1 ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::VARBINARY, [ max( 1, $length ) ] ) );
	}

	/**
	 * Add a UUID column as CHAR(36).
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function uuid( string $column_name ): Column_Definition {
		return $this->char( $column_name, 36 );
	}

	/**
	 * Add a TEXT column (max ~64 KB).
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function text( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::TEXT ) );
	}

	/**
	 * Add a TINYTEXT column (max 255 bytes).
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function tiny_text( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::TINYTEXT ) );
	}

	/**
	 * Add a MEDIUMTEXT column (max ~16MB).
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function medium_text( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::MEDIUMTEXT ) );
	}

	/**
	 * Add a LONGTEXT column (max ~4GB).
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function long_text( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::LONGTEXT ) );
	}

	/**
	 * Add a BLOB column.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function blob( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::BLOB ) );
	}

	/**
	 * Add a TINYBLOB column.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function tiny_blob( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::TINYBLOB ) );
	}

	/**
	 * Add a MEDIUMBLOB column.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function medium_blob( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::MEDIUMBLOB ) );
	}

	/**
	 * Add a LONGBLOB column.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function long_blob( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::LONGBLOB ) );
	}

	/**
	 * Add a BIT column.
	 *
	 * @param string $column_name The column name.
	 * @param int    $length      The number of bits (1-64).
	 *
	 * @phpstan-param non-empty-string $column_name
	 * @phpstan-param int<1, 64> $length
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function bit( string $column_name, int $length = 1 ): Column_Definition {
		if ( $length < 1 || $length > 64 ) {
			throw new Schema_Exception(
				sprintf( 'Invalid length "%d" for BIT column "%s". Must be between 1 and 64.', $length, $column_name )
			);
		}

		return $this->add_column( new Column_Definition( $column_name, Column_Type::BIT, [ $length ] ) );
	}

	/**
	 * Add a GEOMETRY column for storing any type of spatial data.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function geometry( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::GEOMETRY ) );
	}

	/**
	 * Add a POINT column for storing a single lat/lng coordinate.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function point( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::POINT ) );
	}

	/**
	 * Add a LINESTRING column for storing a series of points (e.g., a route).
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function linestring( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::LINESTRING ) );
	}

	/**
	 * Add a POLYGON column for storing a shape (e.g., a delivery zone).
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function polygon( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::POLYGON ) );
	}

	/**
	 * Add a MULTIPOINT column for storing a collection of points.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function multipoint( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::MULTIPOINT ) );
	}

	/**
	 * Add a MULTILINESTRING column for storing multiple line strings.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function multilinestring( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::MULTILINESTRING ) );
	}

	/**
	 * Add a MULTIPOLYGON column for storing multiple polygons.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function multipolygon( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::MULTIPOLYGON ) );
	}

	/**
	 * Add a GEOMETRYCOLLECTION column for storing a collection of geometries.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function geometrycollection( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::GEOMETRYCOLLECTION ) );
	}

	/**
	 * Add a JSON column.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function json( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::JSON ) );
	}

	/**
	 * Add a DATE column.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function date( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::DATE ) );
	}

	/**
	 * Add a TIME column.
	 *
	 * @param string   $column_name The column name.
	 * @param int|null $precision   Fractional seconds precision (0-6).
	 *
	 * @phpstan-param non-empty-string $column_name
	 * @phpstan-param null|int<0, 6> $precision
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function time( string $column_name, ?int $precision = null ): Column_Definition {
		$args = [];
		if ( null !== $precision ) {
			if ( $precision < 0 || $precision > 6 ) {
				throw new Schema_Exception(
					sprintf( 'Precision must be between 0 and 6, %d was provided for column "%s".', $precision, $column_name )
				);
			}
			$args[] = $precision;
		}

		return $this->add_column( new Column_Definition( $column_name, Column_Type::TIME, $args ) );
	}

	/**
	 * Add a DATETIME column.
	 *
	 * @param string   $column_name The column name.
	 * @param int|null $precision   Fractional seconds precision (0-6).
	 *
	 * @phpstan-param non-empty-string $column_name
	 * @phpstan-param null|int<0, 6> $precision
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function datetime( string $column_name, ?int $precision = null ): Column_Definition {
		$args = [];
		if ( null !== $precision ) {
			if ( $precision < 0 || $precision > 6 ) {
				throw new Schema_Exception(
					sprintf( 'Precision must be between 0 and 6, %d was provided for column "%s".', $precision, $column_name )
				);
			}
			$args[] = $precision;
		}

		return $this->add_column( new Column_Definition( $column_name, Column_Type::DATETIME, $args ) );
	}

	/**
	 * Add a TIMESTAMP column.
	 *
	 * @param string   $column_name The column name.
	 * @param int|null $precision   Fractional seconds precision (0-6).
	 *
	 * @phpstan-param non-empty-string $column_name
	 * @phpstan-param null|int<0, 6> $precision
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function timestamp( string $column_name, ?int $precision = null ): Column_Definition {
		$args = [];
		if ( null !== $precision ) {
			if ( $precision < 0 || $precision > 6 ) {
				throw new Schema_Exception(
					sprintf( 'Precision must be between 0 and 6, %d was provided for column "%s".', $precision, $column_name )
				);
			}
			$args[] = $precision;
		}

		return $this->add_column( new Column_Definition( $column_name, Column_Type::TIMESTAMP, $args ) );
	}

	/**
	 * Add a YEAR column.
	 *
	 * @param string $column_name The column name.
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function year( string $column_name ): Column_Definition {
		return $this->add_column( new Column_Definition( $column_name, Column_Type::YEAR ) );
	}

	/**
	 * Add an ENUM column.
	 *
	 * @param string   $column_name The column name.
	 * @param string[] $values      Allowed values.
	 *
	 * @phpstan-param non-empty-string $column_name
	 * @phpstan-param list<non-empty-string> $values
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function enum( string $column_name, array $values ): Column_Definition {
		if ( empty( $values ) ) {
			throw new Schema_Exception( sprintf( 'ENUM column "%s" must have at least one value.', $column_name ) );
		}

		if ( count( $values ) > 65535 ) {
			throw new Schema_Exception( sprintf( 'ENUM column "%s" has too many values (max 65535).', $column_name ) );
		}

		if ( count( array_unique( $values ) ) !== count( $values ) ) {
			throw new Schema_Exception( sprintf( 'ENUM column "%s" contains duplicate values.', $column_name ) );
		}

		$escaped = [];
		foreach ( $values as $value ) {
			if ( trim( $value ) === '' ) {
				throw new Schema_Exception( sprintf( 'ENUM values for column "%s" must be non-empty strings.', $column_name ) );
			}
			if ( strlen( $value ) > 255 ) {
				throw new Schema_Exception( sprintf( 'ENUM value "%s" for column "%s" is too long (max 255).', $value, $column_name ) );
			}
			$escaped[] = "'" . Util::escape_sql( $value ) . "'";
		}

		return $this->add_column( new Column_Definition( $column_name, Column_Type::ENUM, $escaped ) );
	}

	/**
	 * Add a SET column.
	 *
	 * @param string   $column_name The column name.
	 * @param string[] $values      Allowed values.
	 *
	 * @phpstan-param non-empty-string $column_name
	 * @phpstan-param list<non-empty-string> $values
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function set( string $column_name, array $values ): Column_Definition {
		if ( empty( $values ) ) {
			throw new Schema_Exception( sprintf( 'SET column "%s" must have at least one value.', $column_name ) );
		}

		if ( count( $values ) > 64 ) {
			throw new Schema_Exception( sprintf( 'SET column "%s" has too many values (max 64).', $column_name ) );
		}

		if ( count( array_unique( $values ) ) !== count( $values ) ) {
			throw new Schema_Exception( sprintf( 'SET column "%s" contains duplicate values.', $column_name ) );
		}

		$escaped = [];
		foreach ( $values as $value ) {
			if ( trim( $value ) === '' ) {
				throw new Schema_Exception( sprintf( 'SET members for column "%s" must be non-empty strings.', $column_name ) );
			}
			if ( str_contains( $value, ',' ) ) {
				throw new Schema_Exception( sprintf( 'SET member "%s" for column "%s" cannot contain a comma.', $value, $column_name ) );
			}
			$escaped[] = "'" . Util::escape_sql( $value ) . "'";
		}

		return $this->add_column( new Column_Definition( $column_name, Column_Type::SET, $escaped ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Macro Methods (Common Patterns)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Add an auto-incrementing BIGINT UNSIGNED primary key.
	 *
	 * @param string $column_name The column name (default: 'id').
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function id( string $column_name = 'id' ): Column_Definition {
		return $this->big_integer( $column_name )->unsigned()->auto_increment()->primary();
	}

	/**
	 * Add `created_at` and `updated_at` DATETIME columns.
	 *
	 * @param bool $use_current_timestamp Whether to set CURRENT_TIMESTAMP as default and ON UPDATE.
	 *
	 * @return self
	 *
	 * @throws Schema_Exception If the underlying column definitions fail or schema is compiled.
	 */
	public function timestamps( bool $use_current_timestamp = true ): self {
		if ( $use_current_timestamp ) {
			$this->datetime( 'created_at' )->nullable()->default( 'CURRENT_TIMESTAMP', true );
			$this->datetime( 'updated_at' )->nullable()->default( 'CURRENT_TIMESTAMP', true )->on_update_current_timestamp();
		} else {
			$this->datetime( 'created_at' )->nullable();
			$this->datetime( 'updated_at' )->nullable();
		}
		return $this;
	}

	/**
	 * Add a nullable `deleted_at` DATETIME column for soft deletes.
	 *
	 * @param string $column_name The column name (default: 'deleted_at').
	 *
	 * @phpstan-param non-empty-string $column_name
	 *
	 * @return Column_Definition
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function soft_deletes( string $column_name = 'deleted_at' ): Column_Definition {
		return $this->datetime( $column_name )->nullable();
	}

	/**
	 * Add morphable columns for polymorphic relationships.
	 *
	 * Creates `{name}_id` (BIGINT UNSIGNED) and `{name}_type` (VARCHAR) columns, with a composite index.
	 *
	 * @param string $name The base name (e.g., 'commentable').
	 *
	 * @phpstan-param non-empty-string $name
	 *
	 * @return self
	 *
	 * @throws Schema_Exception On invalid parameters or if schema is already compiled.
	 */
	public function morphs( string $name ): self {
		$this->big_integer( "{$name}_id" )->unsigned();
		$this->string( "{$name}_type" ); // Default length is fine.
		$this->add_index( [ "{$name}_type", "{$name}_id" ] ); // Recommended order for queries.
		return $this;
	}

	/*
	|--------------------------------------------------------------------------
	| Index and Key Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Adds an index definition.
	 *
	 * @param string|string[] $columns Column name(s).
	 * @param string|null     $name    Index name.
	 * @param string          $type    Index type.
	 *
	 * @phpstan-param non-empty-string|list<non-empty-string> $columns
	 * @phpstan-param non-empty-string|null $name
	 * @phpstan-param Supported_Index_Type $type
	 *
	 * @return Index_Definition
	 *
	 * @throws Schema_Exception If schema is compiled or parameters are invalid.
	 */
	private function add_index(
		string|array $columns,
		?string $name = null,
		string $type = Index_Type::INDEX,
	): Index_Definition {

		$columns = (array) $columns;
		$name    = $name ?? Util::generate_identifier_name( $this->short_table_name, $columns, $type );
		$index   = new Index_Definition( $name, $columns, $type );

		$this->indexes[ $index->get_name() ] = $index;

		return $index;
	}

	/**
	 * Add a unique key.
	 *
	 * @param string|string[] $columns Column name(s).
	 * @param string|null     $name    Custom key name.
	 *
	 * @phpstan-param non-empty-string|list<non-empty-string> $columns
	 * @phpstan-param non-empty-string|null $name
	 *
	 * @return Index_Definition
	 *
	 * @throws Schema_Exception When parameters are invalid or schema is compiled.
	 */
	public function add_unique_key( string|array $columns, ?string $name = null ): Index_Definition {
		return $this->add_index( $columns, $name, Index_Type::UNIQUE );
	}

	/**
	 * Add a fulltext key.
	 *
	 * @param string|string[] $columns Column name(s).
	 * @param string|null     $name    Custom key name.
	 *
	 * @phpstan-param non-empty-string|list<non-empty-string> $columns
	 * @phpstan-param non-empty-string|null $name
	 *
	 * @return Index_Definition
	 *
	 * @throws Schema_Exception When parameters are invalid or schema is compiled.
	 */
	public function add_fulltext_key( string|array $columns, ?string $name = null ): Index_Definition {
		return $this->add_index( $columns, $name, Index_Type::FULLTEXT );
	}

	/**
	 * Add a spatial key.
	 *
	 * @param string|string[] $columns Column name(s).
	 * @param string|null     $name    Custom key name.
	 *
	 * @phpstan-param non-empty-string|list<non-empty-string> $columns
	 * @phpstan-param non-empty-string|null $name
	 *
	 * @return Index_Definition
	 *
	 * @throws Schema_Exception When parameters are invalid or schema is compiled.
	 */
	public function add_spatial_key( string|array $columns, ?string $name = null ): Index_Definition {
		return $this->add_index( $columns, $name, Index_Type::SPATIAL );
	}

	/**
	 * Add a foreign key constraint.
	 *
	 * @param string|string[] $columns Local column name(s).
	 * @param string|null     $name    Custom constraint name.
	 *
	 * @phpstan-param non-empty-string|list<non-empty-string> $columns
	 * @phpstan-param non-empty-string|null $name
	 *
	 * @return Foreign_Key_Definition
	 *
	 * @throws Schema_Exception When parameters are invalid or schema is compiled.
	 */
	public function add_foreign_key( string|array $columns, ?string $name = null ): Foreign_Key_Definition {
		$columns_array = (array) $columns;
		$name          = $name ?? Util::generate_identifier_name( $this->short_table_name, $columns_array, 'fk' );
		$foreign_key   = new Foreign_Key_Definition( $name, $columns_array );
		$this->foreign_keys[ $foreign_key->get_name() ] = $foreign_key;
		return $foreign_key;
	}

	/*
	|--------------------------------------------------------------------------
	| Table Options
	|--------------------------------------------------------------------------
	*/

	/**
	 * Set the storage engine.
	 *
	 * @param string $engine Engine name (e.g., 'InnoDB', 'MyISAM').
	 *
	 * @phpstan-param non-empty-string $engine
	 *
	 * @return self
	 */
	public function engine( string $engine ): self {
		$this->engine = $engine;
		return $this;
	}

	/**
	 * Set the default character set.
	 *
	 * @param string $charset Character set name.
	 *
	 * @phpstan-param non-empty-string $charset
	 *
	 * @return self
	 */
	public function charset( string $charset ): self {
		$this->charset = $charset;
		return $this;
	}

	/**
	 * Set the default collation.
	 *
	 * @param string $collation Collation name.
	 *
	 * @phpstan-param non-empty-string $collation
	 *
	 * @return self
	 */
	public function collation( string $collation ): self {
		$this->collation = $collation;
		return $this;
	}

	/**
	 * Set a table comment.
	 *
	 * @param string $comment Comment text.
	 *
	 * @phpstan-param non-empty-string $comment
	 *
	 * @return self
	 */
	public function comment( string $comment ): self {
		$this->comment = $comment;
		return $this;
	}

	/**
	 * Set the AUTO_INCREMENT starting value.
	 *
	 * @param int $value Starting value.
	 *
	 * @phpstan-param positive-int $value
	 *
	 * @return self
	 */
	public function auto_increment( int $value ): self {
		$this->auto_increment_start = max( 1, $value );
		return $this;
	}

	/**
	 * Use IF NOT EXISTS clause.
	 *
	 * @return self
	 */
	public function if_not_exists(): self {
		$this->if_not_exists = true;
		return $this;
	}

	/*
	|--------------------------------------------------------------------------
	| SQL Generation
	|--------------------------------------------------------------------------
	*/

	/**
	 * Generate the CREATE TABLE SQL statement.
	 *
	 * @return string The complete SQL statement.
	 *
	 * @phpstan-return non-empty-string
	 *
	 * @throws Schema_Exception If validation fails.
	 */
	public function to_sql(): string {

		$this->compile();
		$this->validate();

		$create_clause = $this->if_not_exists ? 'CREATE TABLE IF NOT EXISTS' : 'CREATE TABLE';
		$definitions   = [];

		foreach ( $this->columns as $column ) {
			$definitions[] = '  ' . $column->to_sql();
		}

		foreach ( $this->indexes as $index ) {
			$definitions[] = '  ' . $index->to_sql();
		}

		foreach ( $this->foreign_keys as $fk ) {
			$definitions[] = '  ' . $fk->to_sql();
		}

		$options = [
			"ENGINE={$this->engine}",
			"DEFAULT CHARSET={$this->charset}",
			"COLLATE={$this->collation}",
		];

		if ( null !== $this->auto_increment_start ) {
			$options[] = "AUTO_INCREMENT={$this->auto_increment_start}";
		}

		if ( null !== $this->comment ) {
			$options[] = "COMMENT='" . Util::escape_sql( $this->comment ) . "'";
		}

		return sprintf(
			"%s `%s` (\n%s\n) %s;",
			$create_clause,
			$this->table_name,
			implode( ",\n", $definitions ),
			implode( ' ', $options )
		);
	}

	/**
	 * Get the full table name.
	 *
	 * @return string
	 *
	 * @phpstan-return non-empty-string
	 */
	public function get_table_name(): string {
		return $this->table_name;
	}

	/**
	 * Get the short table name.
	 *
	 * @return string
	 *
	 * @phpstan-return non-empty-string
	 */
	public function get_short_table_name(): string {
		return $this->short_table_name;
	}

	/**
	 * Compile column intents into concrete table-level definitions.
	 *
	 * @throws Schema_Exception If schema is already compiled.
	 */
	private function compile(): void {
		foreach ( $this->columns as $column ) {
			$column_name = $column->get_name();

			if ( $column->is_unique() ) {
				$this->add_unique_key( $column_name, $column->get_unique_key_name() );
			}

			if ( $column->has_index() ) {
				$this->add_index( $column_name, $column->get_index_name() );
			}

			if ( $column->has_fulltext() ) {
				$this->add_fulltext_key( $column_name, $column->get_fulltext_key_name() );
			}

			if ( $column->has_spatial() ) {
				$this->add_spatial_key( $column_name, $column->get_spatial_key_name() );
			}
		}
	}

	/**
	 * Validate the final compiled schema for correctness and integrity.
	 *
	 * @throws Schema_Exception If any schema rule is violated.
	 */
	private function validate(): void {
		if ( empty( $this->columns ) ) {
			throw new Schema_Exception( 'Cannot create a table with no columns.' );
		}

		foreach ( $this->indexes as $index ) {
			foreach ( $index->get_columns() as $column_name ) {
				if ( ! isset( $this->columns[ $column_name ] ) ) {
					throw new Schema_Exception(
						sprintf(
							'Index "%s" references a non-existent column: "%s".',
							$index->get_name(),
							$column_name
						)
					);
				}
			}
		}

		foreach ( $this->foreign_keys as $fk ) {
			foreach ( $fk->get_columns() as $column_name ) {
				if ( ! isset( $this->columns[ $column_name ] ) ) {
					throw new Schema_Exception(
						sprintf(
							'Foreign key "%s" references a non-existent local column: "%s".',
							$fk->get_name(),
							$column_name
						)
					);
				}
			}
		}

		$primary_key_column    = null;
		$auto_increment_column = null;

		foreach ( $this->columns as $column ) {
			if ( $column->is_primary() ) {
				if ( null !== $primary_key_column ) {
					throw new Schema_Exception( 'A table can only have one primary key.' );
				}
				$primary_key_column = $column->get_name();
			}

			if ( $column->is_auto_increment() ) {
				if ( null !== $auto_increment_column ) {
					throw new Schema_Exception( 'A table can only have one auto-incrementing column' );
				}
				$auto_increment_column = $column->get_name();
			}
		}

		if ( null !== $auto_increment_column ) {
			if ( $auto_increment_column === $primary_key_column ) {
				return;
			}

			foreach ( $this->indexes as $index ) {
				if ( $index->get_type() === Index_Type::UNIQUE ) {
					$index_columns = $index->get_columns();
					if ( ! empty( $index_columns ) && $index_columns[0] === $auto_increment_column ) {
						return;
					}
				}
			}

			throw new Schema_Exception(
				sprintf(
					'The auto-incrementing column "%s" must be a primary key or the ' .
					'first column of a UNIQUE index.',
					$auto_increment_column
				)
			);
		}
	}
}
