<?php
/**
 * Utility functions.
 *
 * @package WPTechnix\WP_Tables_Schema
 */

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema;

/**
 * A collection of utility functions.
 *
 * @package WPTechnix\WP_Tables_Schema
 */
final class Util {

	/**
	 * Maximum allowed length for MySQL identifiers.
	 *
	 * @var int
	 * @phpstan-var positive-int
	 */
	public const MAX_IDENTIFIER_LENGTH = 64;

	/**
	 * Private constructor to prevent instantiation.
	 */
	private function __construct() {
	}

	/**
	 * Validates a string as a valid SQL identifier.
	 *
	 * @param mixed $identifier The identifier to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 *
	 * @phpstan-assert-if-true non-empty-string $identifier
	 */
	public static function valid_sql_identifier( mixed $identifier ): bool {
		// Must not be empty.
		if ( ! is_string( $identifier ) || '' === trim( $identifier ) ) {
			return false;
		}

		// Must not exceed maximum length.
		if ( strlen( $identifier ) > self::MAX_IDENTIFIER_LENGTH ) {
			return false;
		}

		// Must contain only alphanumeric characters and underscores.
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $identifier ) ) {
			return false;
		}

		// Must not start with a digit.
		if ( is_numeric( $identifier[0] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Formats a string for use as a SQL literal value.
	 *
	 * @param string $str The raw string to format.
	 *
	 * @return string The escaped string.
	 * @phpstan-return ( $str is non-empty-string ? non-empty-string : string )
	 */
	public static function escape_sql( string $str ): string {
		// Check if WordPress function exists.
		if ( function_exists( 'esc_sql' ) ) {
			$returned = esc_sql( $str );
			/** @phpstan-var non-empty-string $returned */
			return $returned;
		}

		// @codeCoverageIgnore
		return str_replace(
			[ '\\', "\0", "\n", "\r", "'", '"', "\x1a" ],
			[ '\\\\', "\\0", "\\n", "\\r", "\\'", '\\"', '\\Z' ],
			$str
		);
	}

	/**
	 * Generates a conventional SQL index name.
	 *
	 * Pattern: {prefix}_{table}_{columns}
	 * If the name exceeds 64 characters, it's truncated and a hash is appended.
	 *
	 * @param string $table_name The base table name (without prefix).
	 * @param array  $columns    The columns in the index.
	 * @param string $prefix     The index prefix (e.g., 'idx', 'uniq', 'fk').
	 *
	 * @phpstan-param non-empty-string $table_name
	 * @phpstan-param list<non-empty-string> $columns
	 * @phpstan-param non-empty-string $prefix
	 *
	 * @return string The generated index name.
	 *
	 * @phpstan-return non-empty-string
	 */
	public static function generate_sql_index_name( string $table_name, array $columns, string $prefix ): string {
		// Build the ideal name.
		$column_part = implode( '_', $columns );
		$ideal_name  = "{$prefix}_{$table_name}_{$column_part}";

		// If it fits, use it.
		if ( strlen( $ideal_name ) <= self::MAX_IDENTIFIER_LENGTH ) {
			return $ideal_name;
		}

		// Otherwise, truncate and add a hash for uniqueness.
		$hash = substr( md5( $ideal_name ), 0, 8 );

		// Reserve space for underscore and hash (9 characters).
		$max_base_length = self::MAX_IDENTIFIER_LENGTH - 9;

		// Prefer to truncate the column part first.
		$prefix_and_table = "{$prefix}_{$table_name}";

		if ( strlen( $prefix_and_table ) > $max_base_length ) {
			// Even prefix and table are too long, truncate everything.
			return substr( $ideal_name, 0, $max_base_length ) . '_' . $hash;
		}

		// Truncate the column part to fit.
		$remaining_space   = $max_base_length - strlen( $prefix_and_table ) - 1; // -1 for underscore.
		$truncated_columns = substr( $column_part, 0, $remaining_space );

		return "{$prefix}_{$table_name}_{$truncated_columns}_{$hash}";
	}
}
