<?php
/**
 * Utility Functions.
 *
 * This class provides a collection of static utility functions
 * that can be used throughout the package.
 *
 * @package WPTechnix\WP_Tables_Schema
 */

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema;

/**
 * Class Util.
 *
 * A final class containing reusable utility methods for common tasks.
 * All methods are intended to be static.
 */
final class Util {

	/**
	 * Maximum allowed length for MySQL identifiers.
	 *
	 * @var positive-int
	 */
	public const MAX_IDENTIFIER_LENGTH = 64;

	/**
	 * Validates a string as a valid SQL identifier.
	 *
	 * @param mixed $identifier The identifier to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public static function valid_sql_identifier( mixed $identifier ): bool {
		// Must be a non-empty string.
		if ( ! is_string( $identifier ) || '' === trim( $identifier ) ) {
			return false;
		}

		// Must not exceed maximum length.
		if ( self::MAX_IDENTIFIER_LENGTH < strlen( $identifier ) ) {
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
	 */
	public static function escape_sql( string $str ): string {
		// Check if WordPress function exists.
		if ( function_exists( 'esc_sql' ) ) {
			/** @var string $escaped */
			$escaped = esc_sql( $str );
			return $escaped;
		}

		// @codeCoverageIgnore
		return str_replace(
			[ '\\', "\0", "\n", "\r", "'", '"', "\x1a" ],
			[ '\\\\', "\\0", "\\n", "\\r", "\\'", '\\"', '\\Z' ],
			$str
		);
	}

	/**
	 * Generates a conventional SQL constraint name (e.g., for indexes, foreign keys).
	 *
	 * Pattern: {prefix}_{table}_{columns}
	 * If the name exceeds 64 characters, it is truncated and a hash is appended
	 * to ensure uniqueness.
	 *
	 * @param non-empty-string       $table_name The base table name (without prefix).
	 * @param list<non-empty-string> $columns    The columns used in the constraint.
	 * @param non-empty-string       $prefix     The prefix to use (e.g., "idx", "fk").

	 * @return non-empty-string The generated constraint name.
	 */
	public static function generate_identifier_name( string $table_name, array $columns, string $prefix ): string {

		// Build the ideal name.
		$column_part = implode( '_', $columns );
		$ideal_name  = "{$prefix}_{$table_name}_{$column_part}";

		// If it fits, use it.
		if ( self::MAX_IDENTIFIER_LENGTH >= strlen( $ideal_name ) ) {
			return $ideal_name;
		}

		// Otherwise, truncate and add a hash for uniqueness.
		$hash = substr( md5( $ideal_name ), 0, 8 );

		// Reserve space for underscore and hash (9 characters).
		$max_base_length = self::MAX_IDENTIFIER_LENGTH - 9;

		// Prefer to truncate the column part first.
		$prefix_and_table = "{$prefix}_{$table_name}";

		if ( $max_base_length < strlen( $prefix_and_table ) ) {
			// Even prefix and table are too long, truncate everything.
			return substr( $ideal_name, 0, $max_base_length ) . '_' . $hash;
		}

		// Truncate the column part to fit.
		$remaining_space   = $max_base_length - strlen( $prefix_and_table ) - 1; // -1 for underscore.
		$truncated_columns = substr( $column_part, 0, $remaining_space );

		return "{$prefix}_{$table_name}_{$truncated_columns}_{$hash}";
	}
}
