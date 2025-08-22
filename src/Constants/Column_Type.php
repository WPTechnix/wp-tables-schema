<?php
/**
 * Column Type Constants
 *
 * @package WPTechnix\WP_Tables_Schema\Constants
 */

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Constants;

/**
 * Column Type Constants
 *
 * @package WPTechnix\WP_Tables_Schema\Constants
 *
 * @phpstan-type Column_Type_Integer self::TINYINT | self::SMALLINT | self::MEDIUMINT | self::INT | self::INTEGER | self::BIGINT
 * @phpstan-type Column_Type_Double self::DECIMAL | self::NUMERIC | self::FLOAT | self::DOUBLE
 * @phpstan-type Column_Type_Numeric Column_Type_Integer | Column_Type_Double
 *
 * @phpstan-type Column_Type_Text self::TINYTEXT | self::TEXT | self::MEDIUMTEXT | self::LONGTEXT
 * @phpstan-type Column_Type_Basic_String self::CHAR | self::VARCHAR
 * @phpstan-type Column_Type_String Column_Type_Basic_String | Column_Type_Text
 *
 * @phpstan-type Column_Type_Date self::DATE | self::TIME | self::DATETIME | self::TIMESTAMP | self::YEAR
 *
 * @phpstan-type Column_Type_Blob self::TINYBLOB | self::BLOB | self::MEDIUMBLOB | self::LONGBLOB
 * @phpstan-type Column_Type_Binary_String self::BINARY | self::VARBINARY
 * @phpstan-type Column_Type_Binary Column_Type_Binary_String | Column_Type_Blob
 *
 * @phpstan-type Column_Type_Spatial self::GEOMETRY | self::POINT | self::LINESTRING | self::POLYGON | self::MULTIPOINT | self::MULTILINESTRING | self::MULTIPOLYGON | self::GEOMETRYCOLLECTION
 */
final class Column_Type {

	public const TINYINT            = 'TINYINT';
	public const SMALLINT           = 'SMALLINT';
	public const MEDIUMINT          = 'MEDIUMINT';
	public const INT                = 'INT';
	public const INTEGER            = 'INTEGER';
	public const BIGINT             = 'BIGINT';
	public const DECIMAL            = 'DECIMAL';
	public const NUMERIC            = 'NUMERIC';
	public const FLOAT              = 'FLOAT';
	public const DOUBLE             = 'DOUBLE';
	public const BIT                = 'BIT';
	public const CHAR               = 'CHAR';
	public const VARCHAR            = 'VARCHAR';
	public const TINYTEXT           = 'TINYTEXT';
	public const TEXT               = 'TEXT';
	public const MEDIUMTEXT         = 'MEDIUMTEXT';
	public const LONGTEXT           = 'LONGTEXT';
	public const ENUM               = 'ENUM';
	public const SET                = 'SET';
	public const BINARY             = 'BINARY';
	public const VARBINARY          = 'VARBINARY';
	public const TINYBLOB           = 'TINYBLOB';
	public const BLOB               = 'BLOB';
	public const MEDIUMBLOB         = 'MEDIUMBLOB';
	public const LONGBLOB           = 'LONGBLOB';
	public const DATE               = 'DATE';
	public const TIME               = 'TIME';
	public const DATETIME           = 'DATETIME';
	public const TIMESTAMP          = 'TIMESTAMP';
	public const YEAR               = 'YEAR';
	public const GEOMETRY           = 'GEOMETRY';
	public const POINT              = 'POINT';
	public const LINESTRING         = 'LINESTRING';
	public const POLYGON            = 'POLYGON';
	public const MULTIPOINT         = 'MULTIPOINT';
	public const MULTILINESTRING    = 'MULTILINESTRING';
	public const MULTIPOLYGON       = 'MULTIPOLYGON';
	public const GEOMETRYCOLLECTION = 'GEOMETRYCOLLECTION';
	public const JSON               = 'JSON';

	/**
	 * Private constructor to prevent instantiation.
	 */
	private function __construct() {
	}

	/**
	 * Get all defined column types.
	 *
	 * @return list<string> An array of all column type constants.
	 *
	 * @phpstan-return list<self::*>
	 */
	public static function get_all(): array {
		return [
			...self::get_numeric_types(),
			...self::get_string_types(),
			...self::get_date_types(),
			...self::get_binary_types(),
			...self::get_spatial_types(),
			self::JSON,
			self::ENUM,
			self::SET,
			self::BIT,
		];
	}

	/**
	 * Get all integer column types.
	 *
	 * @return list<string> An array of integer type constants.
	 * @phpstan-return list<Column_Type_Integer>
	 */
	public static function get_integer_types(): array {
		return [
			self::TINYINT,
			self::BIGINT,
			self::SMALLINT,
			self::INTEGER,
			self::MEDIUMINT,
			self::INT,
		];
	}

	/**
	 * Get all double/floating-point column types.
	 *
	 * @return list<string> An array of double type constants.
	 * @phpstan-return list<Column_Type_Double>
	 */
	public static function get_double_types(): array {
		return [
			self::FLOAT,
			self::DOUBLE,
			self::DECIMAL,
			self::NUMERIC,
		];
	}

	/**
	 * Get all numeric column types.
	 *
	 * @return list<string> An array of numeric type constants.
	 * @phpstan-return list<Column_Type_Numeric>
	 */
	public static function get_numeric_types(): array {
		return [ ...self::get_integer_types(), ...self::get_double_types() ];
	}

	/**
	 * Get text-based column types (excluding CHAR and VARCHAR).
	 *
	 * @return list<string> An array of text type constants.
	 * @phpstan-return list<Column_Type_Text>
	 */
	public static function get_text_types(): array {
		return [
			self::TINYTEXT,
			self::TEXT,
			self::MEDIUMTEXT,
			self::LONGTEXT,
		];
	}

	/**
	 * Get all string-based column types.
	 *
	 * @param bool $include_text_types Whether to include text types (TINYTEXT, TEXT, etc.). Default true.
	 *
	 * @return list<string> An array of string type constants.
	 * @phpstan-return ($include_text_types is true ? list<Column_Type_String> : list<Column_Type_Basic_String>)
	 */
	public static function get_string_types( bool $include_text_types = true ): array {
		return [
			self::CHAR,
			self::VARCHAR,
			...( $include_text_types ? self::get_text_types() : [] ),
		];
	}

	/**
	 * Get all date and time related column types.
	 *
	 * @return list<string> An array of date and time type constants.
	 * @phpstan-return list<Column_Type_Date>
	 */
	public static function get_date_types(): array {
		return [
			self::DATE,
			self::TIME,
			self::DATETIME,
			self::TIMESTAMP,
			self::YEAR,
		];
	}

	/**
	 * Get all BLOB (Binary Large Object) column types.
	 *
	 * @return list<string> An array of BLOB type constants.
	 * @phpstan-return list<Column_Type_Blob>
	 */
	public static function get_blob_types(): array {
		return [
			self::TINYBLOB,
			self::BLOB,
			self::MEDIUMBLOB,
			self::LONGBLOB,
		];
	}

	/**
	 * Get all binary string column types.
	 *
	 * @param bool $include_blobs Whether to include BLOB types. Default true.
	 * @return list<string> An array of binary type constants.
	 * @phpstan-return ($include_blobs is true ? list<Column_Type_Binary> : list<Column_Type_Binary_String>)
	 */
	public static function get_binary_types( bool $include_blobs = true ): array {
		return [
			self::BINARY,
			self::VARBINARY,
			...( $include_blobs ? self::get_blob_types() : [] ),
		];
	}

	/**
	 * Get all spatial (GIS) column types.
	 *
	 * @return list<string> An array of spatial type constants.
	 * @phpstan-return list<Column_Type_Spatial>
	 */
	public static function get_spatial_types(): array {
		return [
			self::GEOMETRY,
			self::POINT,
			self::LINESTRING,
			self::POLYGON,
			self::MULTIPOINT,
			self::MULTILINESTRING,
			self::MULTIPOLYGON,
			self::GEOMETRYCOLLECTION,
		];
	}
}
