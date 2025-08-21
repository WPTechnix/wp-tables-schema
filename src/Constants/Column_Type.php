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
	public const BOOLEAN            = 'BOOLEAN';
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
	 * @return string[] An array of all column type constants.
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
		];
	}

	/**
	 * Get all integer column types.
	 *
	 * @return string[] An array of numeric type constants.
	 */
	public static function get_integer_types(): array {
		return [
			self::TINYINT,
			self::BIGINT,
			self::SMALLINT,
			self::INTEGER,
			self::INT,
		];
	}

	/**
	 * Get all double column types.
	 *
	 * @return string[] An array of numeric type constants.
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
	 * @return string[] An array of numeric type constants.
	 */
	public static function get_numeric_types(): array {
		return [ ...self::get_integer_types(), ...self::get_double_types() ];
	}

	/**
	 * Get text-based column types (excluding CHAR and VARCHAR).
	 *
	 * @return string[] An array of text type constants.
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
	 * @return string[] An array of string type constants.
	 */
	public static function get_string_types( bool $include_text_types = true ): array {
		$types = [ self::CHAR, self::VARCHAR ];

		if ( $include_text_types ) {
			$types = [ ...$types, ...self::get_text_types() ];
		}
		return $types;
	}

	/**
	 * Get all date and time related column types.
	 *
	 * @return string[] An array of date and time type constants.
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
	 * @return string[] An array of BLOB type constants.
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
	 * @return string[] An array of binary type constants.
	 */
	public static function get_binary_types( bool $include_blobs = true ): array {
		$types = [ self::BINARY, self::VARBINARY ];

		if ( $include_blobs ) {
			$types = [ ...$types, ...self::get_blob_types() ];
		}

		return $types;
	}

	/**
	 * Get all spatial (GIS) column types.
	 *
	 * @return string[] An array of spatial type constants.
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
