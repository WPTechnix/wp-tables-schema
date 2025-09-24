<?php
/**
 * Interface Table_Interface.
 *
 * @package WPTechnix\WP_Tables_Schema\Interfaces
 */

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Interfaces;

/**
 * Interface Table_Interface.
 */
interface Table_Interface {

	/**
	 * Gets the current schema version of the table.
	 *
	 * @return positive-int Schema version number.
	 */
	public function get_schema_version(): int;

	/**
	 * Gets the currently installed version of the table.
	 *
	 * @return positive-int Installed version number.
	 */
	public function get_installed_version(): int;

	/**
	 * Gets the full, sanitized table name with prefixes.
	 *
	 * In a multisite installation, it uses the base network prefix (e.g., `wp_`)
	 * for shared tables; otherwise, it uses the site-specific prefix (e.g., `wp_2_`).
	 *
	 * @param bool $with_wp_prefix Whether to include the global WordPress prefix (e.g., 'wp_').
	 *
	 * @return non-empty-string The sanitized, fully prefixed table name.
	 */
	public function get_table_name( bool $with_wp_prefix = true ): string;

	/**
	 * Gets the singular name, without WordPress or plugin prefixes.
	 *
	 * @return non-empty-string
	 */
	public function get_table_singular_name(): string;

	/**
	 * Gets the primary key column name for the table.
	 *
	 * @return non-empty-string Primary key column name.
	 */
	public function get_primary_key(): string;

	/**
	 * Gets the foreign key name for the table.
	 *
	 * @return non-empty-string Foreign key name.
	 */
	public function get_foreign_key_name(): string;

	/**
	 * Installs the table in the database.
	 */
	public function install(): void;

	/**
	 * Drops the table from the database.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function drop(): bool;
}
