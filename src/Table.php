<?php
/**
 * Abstract Database Table Schema Manager for WordPress.
 *
 * This file defines the core abstract `Table` class, which provides a comprehensive
 * and secure foundation for managing custom database tables within a WordPress
 * environment. It is designed to be extended by concrete table classes, offering
 * a robust framework for schema definition, version-controlled migrations, and
 * safe database interactions.
 *
 * @package WPTechnix\WP_Tables_Schema
 */

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema;

use Closure;
use LogicException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;
use wpdb;
use WPTechnix\WP_Tables_Schema\Constants\Foreign_Key_Action;
use WPTechnix\WP_Tables_Schema\Constants\Index_Type;
use WPTechnix\WP_Tables_Schema\Exceptions\Schema_Exception;
use WPTechnix\WP_Tables_Schema\Interfaces\Table_Interface;
use WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema;

// phpcs:disable WordPress.DB.PreparedSQL -- SQL is safely constructed. Table and column names are dynamic but rigorously sanitized, which cannot be prepared by wpdb.

/**
 * Abstract Database Table Class.
 *
 * This class provides a robust and secure foundation for creating, migrating, and managing
 * database custom tables within a WordPress environment. It features a unified, high-compatibility
 * API for all index and column manipulations.
 *
 * Requires PHP: 8.0+
 * Requires MySQL: 5.6+ or MariaDB 10.1+
 *
 * @package WPTechnix\WP_Tables_Schema
 *
 * @psalm-import-type Index_Types_Excluding_Primary from Index_Type
 * @phpstan-import-type Index_Types_Excluding_Primary from Index_Type
 * @phpstan-type IndexCacheRow array{Non_unique: '0'|'1', Key_name: non-empty-string, Index_type: string}
 * @psalm-type IndexCacheRow array{Non_unique: '0'|'1', Key_name: non-empty-string, Index_type: string}
 */
abstract class Table implements Table_Interface, LoggerAwareInterface {

	use LoggerAwareTrait;

	/**
	 * The base version number for a table that has not been installed yet.
	 *
	 * @var int
	 */
	private const BASE_VERSION = 10000;

	/**
	 * The target schema version for this table, as defined by the current plugin code.
	 *
	 * @var positive-int
	 */
	protected int $schema_version = self::BASE_VERSION;

	/**
	 * The table name, without WordPress or plugin prefixes.
	 *
	 * @var non-empty-string
	 */
	protected string $table_name;

	/**
	 * The singular name of table, without WordPress or plugin prefixes.
	 *
	 * @var non-empty-string
	 */
	protected string $table_singular_name;

	/**
	 * The primary key column for this table.
	 *
	 * @var non-empty-string
	 */
	protected string $primary_key_column = 'id';

	/**
	 * The name of this table's primary key when used as a foreign key in other tables.
	 *
	 * @var non-empty-string
	 */
	protected string $foreign_key_name;

	/**
	 * Determines if the table is shared across all sites in a multisite installation.
	 *
	 * @var bool
	 */
	protected bool $multisite_shared = false;

	/**
	 * The WordPress option name that stores all table schema versions for this plugin.
	 *
	 * @var non-empty-string
	 */
	protected string $table_versions_option_name;

	/**
	 * A local cache for the array of table versions stored in the database.
	 *
	 * @var null|array<class-string<self>, positive-int>
	 */
	protected ?array $table_versions = null;

	/**
	 * The cached MySQL/MariaDB version number.
	 *
	 * @var non-empty-string|null
	 */
	private static ?string $mysql_server_version = null;

	/**
	 * The cached check for whether the database is MariaDB.
	 *
	 * @var bool|null
	 */
	private static ?bool $is_mariadb_installation = null;

	/**
	 * The version number being installed.
	 *
	 * @var int<0, max>
	 */
	protected int $version_being_installed = 0;

	/**
	 * The plugin prefix.
	 *
	 * @var string
	 */
	protected string $plugin_prefix = '';

	/**
	 * A cache for foreign key existence checks.
	 *
	 * @var array<non-empty-string, bool>
	 */
	protected array $fk_exists_cached = [];

	/**
	 * A cache for index existence checks.
	 *
	 * @var array<non-empty-string, IndexCacheRow|false>
	 */
	protected array $index_cached = [];

	/**
	 * A cache for column existence checks.
	 *
	 * @var array<non-empty-string, bool>
	 */
	protected array $column_cached = [];

	/*
	|--------------------------------------------------------------------------
	| Constructor
	|--------------------------------------------------------------------------
	*/

	/**
	 * Table constructor.
	 *
	 * @param wpdb                  $wpdb          The WordPress database object.
	 * @param non-empty-string|null $plugin_prefix The plugin prefix.
	 *
	 * @throws LogicException When required properties are not set.
	 *
	 * @throws Schema_Exception When defined property identifiers are invalid.
	 */
	public function __construct( protected wpdb $wpdb, ?string $plugin_prefix = null ) {

		if ( null !== $plugin_prefix ) {
			$this->plugin_prefix = $plugin_prefix;
		}

		// Verify that required properties are declared in the child class.
		$required_properties = [ 'table_name', 'table_singular_name', 'primary_key_column', 'foreign_key_name', 'plugin_prefix' ];
		foreach ( $required_properties as $prop ) {
			$value = $this->{$prop} ?? null;
			if ( ! isset( $value ) || '' === $value ) {
				throw new LogicException( static::class . " must declare the \${$prop} property." );
			}
		}

		// Validate all property-based identifiers once on instantiation.
		$this->validate_property_identifiers();

		$this->table_versions_option_name               = $this->plugin_prefix . '_table_versions';
		$this->wpdb->{$this->get_table_singular_name()} = $this->get_table_name();
	}

	/**
	 * Validates that all properties intended to be SQL identifiers are valid.
	 *
	 * @throws Schema_Exception If any property is an invalid identifier.
	 */
	private function validate_property_identifiers(): void {
		$properties_to_validate = [
			'table_name'          => $this->table_name,
			'table_singular_name' => $this->table_singular_name,
			'primary_key_column'  => $this->primary_key_column,
			'foreign_key_name'    => $this->foreign_key_name,
			'plugin_prefix'       => $this->plugin_prefix,
		];

		foreach ( $properties_to_validate as $name => $value ) {
			if ( ! Util::valid_sql_identifier( $value ) ) {
				throw new Schema_Exception(
					sprintf( 'The property $%s with value "%s" is not a valid SQL identifier.', $name, $value )
				);
			}
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Public API Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	final public function get_schema_version(): int {
		return $this->schema_version;
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	final public function get_installed_version(): int {
		$this->retrieve_table_versions();

		return max( self::BASE_VERSION, $this->table_versions[ static::class ] ?? self::BASE_VERSION );
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	final public function get_table_name( bool $with_wp_prefix = true ): string {
		$base_name = $this->plugin_prefix . $this->table_name;
		if ( $with_wp_prefix ) {
			$prefix    = $this->is_multisite_shared() ? $this->wpdb->base_prefix : $this->wpdb->prefix;
			$base_name = $prefix . $base_name;
		}

		return $base_name;
	}

	/**
	 * {@inheritDoc}
	 */
	#[\Override]
	public function get_table_singular_name(): string {
		return $this->plugin_prefix . $this->table_singular_name;
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	final public function get_primary_key(): string {
		return $this->primary_key_column;
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	final public function get_foreign_key_name(): string {
		return $this->foreign_key_name;
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	final public function install(): void {
		$installed_version = $this->get_installed_version();
		$target_version    = $this->get_schema_version();

		if ( ! $this->table_exists() && self::BASE_VERSION < $installed_version ) {
			$this->logger?->warning(
				'The database table does not exist but is marked as installed.',
				[
					'table'             => $this->get_table_name(),
					'installed_version' => $installed_version,
				]
			);
		}

		if ( $target_version <= $installed_version ) {
			return; // Already up-to-date.
		}

		try {
			for ( $version_to_migrate = $installed_version + 1; $target_version >= $version_to_migrate; $version_to_migrate++ ) {
				$method_name = 'migrate_to_' . $version_to_migrate;
				if ( method_exists( $this, $method_name ) ) {
					$this->version_being_installed = $version_to_migrate;
					if ( false === $this->{$method_name}() ) {
						// Allow migrations to gracefully stop the process.
						return;
					}
					$this->update_current_db_version( $version_to_migrate );
				}
			}
		} catch ( Throwable $e ) {
			$this->logger?->critical(
				'A critical error occurred during database migration. The process has been stopped.',
				[
					'table'             => $this->get_table_name(),
					'failed_at_version' => $this->version_being_installed,
					'error'             => $e->getMessage(),
					'trace'             => $e->getTraceAsString(),
				]
			);
		} finally {
			// Cleanup state regardless of success or failure.
			$this->version_being_installed = 0;
			$this->fk_exists_cached        = [];
			$this->index_cached            = [];
			$this->column_cached           = [];
		}
	}

	/**
	 * {@inheritdoc}
	 */
	#[\Override]
	final public function drop(): bool {
		$table_name = $this->get_table_name();

		return false !== $this->wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`;" );
	}

	/**
	 * Creates the table using a fluent schema builder.
	 *
	 * @param (Closure(Create_Table_Schema): (Create_Table_Schema|non-empty-string)) $closure The closure that builds the table.
	 *
	 * @return bool
	 *
	 * @throws Schema_Exception If the schema definition is invalid.
	 */
	final protected function create_table( Closure $closure ): bool {
		$table_name = $this->get_table_name();
		// Use the unprefixed name for generating constraints.
		$short_name = $this->get_table_name( false );
		$schema     = new Create_Table_Schema( $table_name, $short_name );
		$schema->if_not_exists();

		// Set charset and collation from wpdb.
		if ( '' !== trim( $this->wpdb->charset ) ) {
			/** @var non-empty-string $charset */
			$charset = $this->wpdb->charset;
			$schema->charset( $charset );
		}
		if ( '' !== trim( $this->wpdb->collate ) ) {
			/** @var non-empty-string $collate */
			$collate = $this->wpdb->collate;
			$schema->collation( $collate );
		}

		$result    = $closure( $schema );
		$sql       = $result instanceof Create_Table_Schema ? $result->to_sql() : $result;
		$succeeded = false !== $this->wpdb->query( $sql );

		if ( ! $succeeded ) {
			$this->logger?->error(
				'Failed to create table.',
				[
					'table'      => $table_name,
					'wpdb_error' => $this->wpdb->last_error,
				]
			);
		}

		return $succeeded;
	}

	/*
	|--------------------------------------------------------------------------
	| Core Table & Migration Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Checks if the table is shared across all sites in a multisite installation.
	 *
	 * @return bool
	 */
	final protected function is_multisite_shared(): bool {
		return is_multisite() && $this->multisite_shared;
	}

	/**
	 * Gets the current site ID.
	 *
	 * @return positive-int
	 */
	final protected function get_current_site_id(): int {
		return max( 1, ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 ) );
	}

	/**
	 * Checks if the database table exists.
	 *
	 * @return bool
	 */
	final protected function table_exists(): bool {
		$table_name = $this->get_table_name();
		$result     = $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		return null !== $result && '' !== trim( $result );
	}

	/**
	 * Gets the database character set and collation string for use in SQL.
	 *
	 * @return string
	 */
	final protected function get_db_charset_collate(): string {
		return $this->wpdb->get_charset_collate();
	}

	/**
	 * Updates the stored database version for this specific table.
	 *
	 * @param positive-int $version The new version number to store.
	 */
	final protected function update_current_db_version( int $version ): void {
		$this->retrieve_table_versions(); // Ensure current versions are loaded.
		$this->table_versions[ static::class ] = $version;

		$option_function = $this->is_multisite_shared() ? 'update_site_option' : 'update_option';
		$option_function( $this->table_versions_option_name, $this->table_versions );
	}

	/**
	 * Retrieves the stored database table versions.
	 */
	final protected function retrieve_table_versions(): void {
		if ( ! isset( $this->table_versions ) ) {
			$option_value   = $this->is_multisite_shared() ? get_site_option( $this->table_versions_option_name, [] ) : get_option( $this->table_versions_option_name, [] );
			$table_versions = is_array( $option_value ) ? $option_value : [];
			/** @var array<class-string<self>, positive-int> $table_versions */
			$this->table_versions = $table_versions;
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Column Management Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Checks if a specific column exists in this table.
	 *
	 * @param non-empty-string $column_name The name of the column to check.
	 *
	 * @return bool
	 *
	 * @throws Schema_Exception If the column name is an invalid identifier.
	 */
	final protected function column_exists( string $column_name ): bool {
		if ( ! Util::valid_sql_identifier( $column_name ) ) {
			throw new Schema_Exception( sprintf( 'The column name "%s" is not a valid SQL identifier.', $column_name ) );
		}

		if ( isset( $this->column_cached[ $column_name ] ) ) {
			return $this->column_cached[ $column_name ];
		}
		$table_name = $this->get_table_name();
		// @phpstan-ignore argument.type
		$result                              = $this->wpdb->get_var( $this->wpdb->prepare( "SHOW COLUMNS FROM `{$table_name}` LIKE %s", $column_name ) );
		$exists                              = null !== $result && '' !== trim( $result );
		$this->column_cached[ $column_name ] = $exists;

		return $exists;
	}

	/**
	 * Adds a new column to the table.
	 *
	 * @param non-empty-string      $column_name       The name of the column to add.
	 * @param non-empty-string      $column_definition The SQL definition of the column (e.g., 'VARCHAR(191) NOT NULL').
	 * @param non-empty-string|null $after_column      Optional. The name of the column after which to add the new column.
	 *
	 * @return bool
	 *
	 * @throws Schema_Exception If any identifier is invalid.
	 */
	final protected function add_column( string $column_name, string $column_definition, ?string $after_column = null ): bool {
		if ( ! Util::valid_sql_identifier( $column_name ) ) {
			throw new Schema_Exception( sprintf( 'The column name "%s" is not a valid SQL identifier.', $column_name ) );
		}
		if ( $this->column_exists( $column_name ) ) {
			return true; // Idempotent: already exists.
		}

		$table_name = $this->get_table_name();
		$sql        = "ALTER TABLE `{$table_name}` ADD COLUMN `{$column_name}` {$column_definition}";
		if ( null !== $after_column ) {
			if ( ! Util::valid_sql_identifier( $after_column ) ) {
				throw new Schema_Exception( sprintf( 'The "after" column name "%s" is not a valid SQL identifier.', $after_column ) );
			}
			$sql .= " AFTER `{$after_column}`";
		}

		$has_added = false !== $this->wpdb->query( $sql );
		if ( $has_added ) {
			$this->column_cached[ $column_name ] = true;
		}

		return $has_added;
	}

	/**
	 * Drops a column from the table.
	 *
	 * @param non-empty-string $column_name The name of the column to drop.
	 *
	 * @return bool
	 *
	 * @throws Schema_Exception If the column name is an invalid identifier.
	 */
	final protected function drop_column( string $column_name ): bool {
		if ( ! Util::valid_sql_identifier( $column_name ) ) {
			throw new Schema_Exception( sprintf( 'The column name "%s" is not a valid SQL identifier.', $column_name ) );
		}
		if ( ! $this->column_exists( $column_name ) ) {
			return true; // Idempotent: does not exist.
		}

		$table_name  = $this->get_table_name();
		$has_dropped = false !== $this->wpdb->query( "ALTER TABLE `{$table_name}` DROP COLUMN `{$column_name}`" );
		if ( $has_dropped ) {
			$this->column_cached[ $column_name ] = false;
		}

		return $has_dropped;
	}

	/**
	 * Modifies an existing column's definition.
	 *
	 * @param non-empty-string $column_name            The name of the column to modify.
	 * @param non-empty-string $new_column_definition The new SQL definition for the column.
	 *
	 * @return bool
	 *
	 * @throws Schema_Exception If the column name is an invalid identifier.
	 */
	final protected function modify_column( string $column_name, string $new_column_definition ): bool {
		if ( ! Util::valid_sql_identifier( $column_name ) ) {
			throw new Schema_Exception( sprintf( 'The column name "%s" is not a valid SQL identifier.', $column_name ) );
		}
		if ( ! $this->column_exists( $column_name ) ) {
			return false; // Cannot modify a non-existent column.
		}

		$table_name   = $this->get_table_name();
		$query        = "ALTER TABLE `{$table_name}` MODIFY COLUMN `{$column_name}` {$new_column_definition}";
		$has_modified = false !== $this->wpdb->query( $query );
		if ( $has_modified ) {
			$this->column_cached[ $column_name ] = true;
		}

		return $has_modified;
	}

	/**
	 * Renames a column and simultaneously changes its definition.
	 *
	 * @param non-empty-string $old_column_name       The current name of the column.
	 * @param non-empty-string $new_column_name       The new name for the column.
	 * @param non-empty-string $new_column_definition The new SQL definition for the renamed column.
	 *
	 * @return bool True on success, false on failure.
	 *
	 * @throws Schema_Exception When old or new name is in invalid format.
	 */
	final protected function change_column(
		string $old_column_name,
		string $new_column_name,
		string $new_column_definition
	): bool {

		if ( ! Util::valid_sql_identifier( $old_column_name ) ) {
			throw new Schema_Exception(
				sprintf(
					'Old column name "%s" is not a valid SQL identifier.',
					$old_column_name
				)
			);
		}
		if ( ! Util::valid_sql_identifier( $new_column_name ) ) {
			throw new Schema_Exception(
				sprintf(
					'New column name "%s" is not a valid SQL identifier.',
					$new_column_name
				)
			);
		}

		if ( ! $this->column_exists( $old_column_name ) ) {
			return false;
		}

		if ( $old_column_name !== $new_column_name && $this->column_exists( $new_column_name ) ) {
			return false;
		}

		$table_name  = $this->get_table_name();
		$query       = "ALTER TABLE `$table_name` CHANGE COLUMN `$old_column_name` `$new_column_name` $new_column_definition";
		$has_changed = false !== $this->wpdb->query( $query );
		if ( $has_changed ) {
			// Update the cache to reflect the new column name.
			$this->column_cached[ $old_column_name ] = false;
			$this->column_cached[ $new_column_name ] = true;
		}

		return $has_changed;
	}

	/**
	 * Safely renames an existing column without altering its definition.
	 *
	 * @param non-empty-string $old_column_name The current name of the column.
	 * @param non-empty-string $new_column_name The new name for the column.
	 *
	 * @return bool True on success, false on failure.
	 *
	 * @throws Schema_Exception When old or new name are same or they are invalid SQL identifiers.
	 */
	final protected function rename_column( string $old_column_name, string $new_column_name ): bool {

		if ( ! Util::valid_sql_identifier( $old_column_name ) ) {
			throw new Schema_Exception(
				sprintf(
					'Old column name "%s" is not a valid SQL identifier.',
					$old_column_name
				)
			);
		}

		if ( ! Util::valid_sql_identifier( $new_column_name ) ) {
			throw new Schema_Exception(
				sprintf(
					'New column name "%s" is not a valid SQL identifier.',
					$new_column_name
				)
			);
		}

		if ( $old_column_name === $new_column_name ) {
			throw new Schema_Exception(
				sprintf(
					'Old column name "%s" and new column name "%s" cannot be same.',
					$old_column_name,
					$new_column_name
				)
			);
		}

		$table_name = $this->get_table_name();

		if ( ! $this->column_exists( $old_column_name ) ) {
			$this->logger?->warning(
				'Failed to rename column: old column does not exist.',
				[
					'table'           => $table_name,
					'old_column'      => $old_column_name,
					'new_column'      => $new_column_name,
					'when_installing' => $this->version_being_installed,
					'site_id'         => $this->get_current_site_id(),
				]
			);

			return false;
		}

		if ( $this->column_exists( $new_column_name ) ) {
			$this->logger?->warning(
				'Failed to rename column: new column name already exists.',
				[
					'table'           => $table_name,
					'old_column'      => $old_column_name,
					'new_column'      => $new_column_name,
					'when_installing' => $this->version_being_installed,
					'site_id'         => $this->get_current_site_id(),
				]
			);

			return false;
		}

		// Use modern syntax if available for performance and safety.
		if ( $this->is_mysql_at_least( '8.0.3' ) || $this->is_mariadb_at_least( '10.5.3' ) ) {
			$query       = "ALTER TABLE `$table_name` RENAME COLUMN `$old_column_name` TO `$new_column_name`";
			$has_renamed = false !== $this->wpdb->query( $query );
			if ( $has_renamed ) {
				// Update the cache to reflect the new column name.
				$this->column_cached[ $old_column_name ] = false;
				$this->column_cached[ $new_column_name ] = true;
			}

			return $has_renamed;
		}

		// Fallback for older MySQL/MariaDB versions.
		$definition = $this->get_column_definition_for_change( $old_column_name );
		if ( '' === $definition ) {
			$this->logger?->error(
				'Failed to rename column: could not retrieve column definition for fallback.',
				[
					'table'           => $table_name,
					'column'          => $old_column_name,
					'when_installing' => $this->version_being_installed,
					'site_id'         => $this->get_current_site_id(),
				]
			);

			return false;
		}

		return $this->change_column( $old_column_name, $new_column_name, $definition );
	}

	/**
	 * Fetches the full SQL definition of a column for use in a `CHANGE COLUMN` statement.
	 *
	 * @param non-empty-string $column_name The name of the column.
	 *
	 * @return string
	 */
	private function get_column_definition_for_change( string $column_name ): string {
		$table_name = $this->get_table_name();
		$row        = $this->wpdb->get_row( "SHOW CREATE TABLE `{$table_name}`", 'ARRAY_A' );
		if ( ! is_array( $row ) || ! isset( $row['Create Table'] ) || ! is_string( $row['Create Table'] ) || '' === trim( $row['Create Table'] ) ) {
			return '';
		}

		$create_table_sql = $row['Create Table'];
		$lines            = explode( "\n", $create_table_sql );
		foreach ( $lines as $line ) {
			if ( str_starts_with( trim( $line ), "`{$column_name}`" ) ) {
				return rtrim( trim( substr( trim( $line ), strlen( $column_name ) + 2 ) ), ',' );
			}
		}

		return '';
	}

	/*
	|--------------------------------------------------------------------------
	| Index Management Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Checks if an index exists on the table by its name only, regardless of type.
	 *
	 * @param non-empty-string $index_name The name of the index to check.
	 *
	 * @return bool
	 *
	 * @throws Schema_Exception If the index name is an invalid identifier.
	 */
	final protected function index_exists( string $index_name ): bool {
		if ( ! Util::valid_sql_identifier( $index_name ) ) {
			throw new Schema_Exception( sprintf( 'The index name "%s" is not a valid SQL identifier.', $index_name ) );
		}
		if ( isset( $this->index_cached[ $index_name ] ) ) {
			return ! ( false === $this->index_cached[ $index_name ] );
		}

		$table_name = $this->get_table_name();
		// @phpstan-ignore argument.type
		$query  = $this->wpdb->prepare( "SHOW INDEX FROM `{$table_name}` WHERE Key_name = %s", $index_name );
		$result = $this->wpdb->get_row( $query, 'ARRAY_A' );

		if ( ! is_array( $result ) ) {
			$this->index_cached[ $index_name ] = false;

			return false;
		}

		if (
			! isset( $result['Non_unique'] ) || ! is_string( $result['Non_unique'] ) ||
			! isset( $result['Key_name'] ) || ! is_string( $result['Key_name'] ) || '' === $result['Key_name'] ||
			! isset( $result['Index_type'] ) || ! is_string( $result['Index_type'] ) || '' === $result['Index_type']
		) {
			return false;
		}

		$index_info = [
			'Non_unique' => '1' === $result['Non_unique'] ? '1' : '0',
			'Key_name'   => $result['Key_name'],
			'Index_type' => $result['Index_type'],
		];

		$this->index_cached[ $index_name ] = $index_info;

		return true;
	}

	/**
	 * Checks if an index of a specific type exists on the table. This is the core checking method.
	 *
	 * @param non-empty-string $index_name The name of the index to check.
	 * @param string           $index_type The type of index, other then primary.
	 *
	 * @phpstan-param Index_Types_Excluding_Primary $index_type
	 * @psalm-param Index_Types_Excluding_Primary $index_type
	 *
	 * @return bool True if an index of the specified name and type exists, false otherwise.
	 *
	 * @throws Schema_Exception If index name is invalid SQL identifier.
	 */
	final protected function index_exists_by_type(
		string $index_name,
		string $index_type = Index_Type::INDEX
	): bool {
		// 1. Ensure the cache is populated by calling index_exists first.
		if ( ! $this->index_exists( $index_name ) ) {
			return false;
		}

		// 2. Now, reliably get the details from the cache.
		$index_info = $this->index_cached[ $index_name ];

		if ( false === $index_info ) {
			return false; // Unlikely.
		}

		// 3. Perform the logic check on the cached data.
		return match ( $index_type ) {
			Index_Type::UNIQUE   => '0' === $index_info['Non_unique'] && 'PRIMARY' !== $index_info['Key_name'],
			Index_Type::FULLTEXT => 'FULLTEXT' === $index_info['Index_type'],
			Index_Type::SPATIAL  => 'SPATIAL' === $index_info['Index_type'],
			default              => '0' !== $index_info['Non_unique'] && 'BTREE' === $index_info['Index_type'],
		};
	}

	/**
	 * Adds an index of a specific type to the table.
	 *
	 * @param non-empty-string|list<non-empty-string> $columns    Column name or an array of column names.
	 * @param string                                  $index_type The type of index, using one of the `Index_Type::*` constants.
	 * @param non-empty-string|null                   $index_name Name of the index (optional for autogenerated name).
	 *
	 * @phpstan-param Index_Type::* $index_type
	 * @psalm-param Index_Type::* $index_type
	 *
	 * @return bool
	 *
	 * @throws Schema_Exception If index or column names are invalid SQL identifiers.
	 */
	final protected function add_index(
		string|array $columns,
		string $index_type = Index_Type::INDEX,
		?string $index_name = null
	): bool {
		$columns_array = (array) $columns;
		foreach ( $columns_array as $column ) {
			if ( ! Util::valid_sql_identifier( $column ) ) {
				throw new Schema_Exception( sprintf( 'The column name "%s" in index definition is invalid.', $column ) );
			}
		}

		$table_name = $this->get_table_name();
		if ( null === $index_name ) {
			$index_type_prefix = match ( $index_type ) {
				Index_Type::UNIQUE   => 'uq',
				Index_Type::FULLTEXT => 'ft',
				Index_Type::SPATIAL  => 'sp',
				default              => 'idx',
			};
			$index_name = Util::generate_identifier_name( $this->get_table_name( false ), $columns_array, $index_type_prefix );
		}

		if ( $this->index_exists( $index_name ) ) {
			return true; // Idempotent.
		}

		$column_list   = '`' . implode( '`, `', $columns_array ) . '`';
		$type_keyword  = Index_Type::INDEX === $index_type ? '' : $index_type;
		$sql_statement = "ALTER TABLE `{$table_name}` ADD {$type_keyword} INDEX `{$index_name}` ({$column_list})";

		$has_added = false !== $this->wpdb->query( $sql_statement );
		if ( $has_added ) {
			$index_info = [
				'Non_unique' => Index_Type::UNIQUE === $index_type ? '0' : '1',
				'Key_name'   => $index_name,
				'Index_type' => in_array( $index_type, [ Index_Type::INDEX, Index_Type::UNIQUE ], true ) ? 'BTREE' : $index_type,
			];

			$this->index_cached[ $index_name ] = $index_info;
		}

		return $has_added;
	}

	/**
	 * Drops any named index from the table.
	 *
	 * @param non-empty-string $index_name The name of the index to drop.
	 *
	 * @return bool
	 *
	 * @throws Schema_Exception If the index name is an invalid identifier.
	 */
	final protected function drop_index( string $index_name ): bool {
		if ( ! Util::valid_sql_identifier( $index_name ) ) {
			throw new Schema_Exception( sprintf( 'The index name "%s" is not a valid SQL identifier.', $index_name ) );
		}
		if ( 'PRIMARY' === strtoupper( $index_name ) ) {
			return $this->drop_primary_key();
		}

		if ( ! $this->index_exists( $index_name ) ) {
			return true; // Idempotent.
		}

		$table_name  = $this->get_table_name();
		$query       = "ALTER TABLE `{$table_name}` DROP INDEX `{$index_name}`";
		$has_dropped = false !== $this->wpdb->query( $query );
		if ( $has_dropped ) {
			$this->index_cached[ $index_name ] = false;
		}

		return $has_dropped;
	}

	/**
	 * Adds a PRIMARY KEY to the table.
	 *
	 * @param non-empty-string|list<non-empty-string> $columns Column name or an array of column names.
	 *
	 * @return bool
	 *
	 * @throws Schema_Exception If any column name is invalid.
	 */
	final protected function add_primary_key( string|array $columns ): bool {
		if ( $this->index_exists( 'PRIMARY' ) ) {
			return true;
		}

		$columns_array = (array) $columns;
		foreach ( $columns_array as $column ) {
			if ( ! Util::valid_sql_identifier( $column ) ) {
				throw new Schema_Exception( sprintf( 'The column name "%s" in PRIMARY KEY definition is invalid.', $column ) );
			}
		}

		$table_name  = $this->get_table_name();
		$column_list = '`' . implode( '`, `', $columns_array ) . '`';
		$query       = "ALTER TABLE `{$table_name}` ADD PRIMARY KEY ({$column_list})";
		$has_added   = false !== $this->wpdb->query( $query );
		if ( $has_added ) {
			$this->index_cached['PRIMARY'] = [
				'Non_unique' => '0',
				'Key_name'   => 'PRIMARY',
				'Index_type' => 'BTREE',
			];
		}

		return $has_added;
	}

	/**
	 * Drops the PRIMARY KEY from the table.
	 *
	 * @return bool
	 * @noinspection PhpDocMissingThrowsInspection
	 */
	final protected function drop_primary_key(): bool {
		/** @noinspection PhpUnhandledExceptionInspection */
		if ( ! $this->index_exists( 'PRIMARY' ) ) {
			return true;
		}
		$table_name  = $this->get_table_name();
		$has_dropped = false !== $this->wpdb->query( "ALTER TABLE `{$table_name}` DROP PRIMARY KEY" );
		if ( $has_dropped ) {
			$this->index_cached['PRIMARY'] = false;
		}

		return $has_dropped;
	}

	/**
	 * A convenience wrapper to check if a UNIQUE index exists.
	 *
	 * @param non-empty-string $index_name The name of the unique index.
	 *
	 * @return bool True if the unique index exists, false otherwise.
	 *
	 * @throws Schema_Exception If index name is invalid SQL identifier.
	 */
	final protected function unique_key_exists( string $index_name ): bool {
		return $this->index_exists_by_type( $index_name, Index_Type::UNIQUE );
	}

	/**
	 * A convenience wrapper to add a UNIQUE index.
	 *
	 * @param non-empty-string|list<non-empty-string> $columns    Column name or an array of column names.
	 * @param non-empty-string|null                   $index_name Name of the unique index (optional for autogenerated name).
	 *
	 * @return bool True on success or if the index already existed, false on failure.
	 *
	 * @throws Schema_Exception If index or column names are invalid SQL identifiers.
	 */
	final protected function add_unique_key( string|array $columns, ?string $index_name = null ): bool {
		return $this->add_index( $columns, Index_Type::UNIQUE, $index_name );
	}

	/*
	|--------------------------------------------------------------------------
	| Foreign Key Management Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Checks if a foreign key constraint with a specific name exists on this table.
	 *
	 * @param non-empty-string $fk_name The name of the foreign key constraint to check.
	 *
	 * @return bool
	 *
	 * @throws Schema_Exception If the foreign key name is an invalid identifier.
	 */
	final protected function foreign_key_exists( string $fk_name ): bool {
		if ( ! Util::valid_sql_identifier( $fk_name ) ) {
			throw new Schema_Exception( sprintf( 'The foreign key name "%s" is not a valid SQL identifier.', $fk_name ) );
		}
		if ( isset( $this->fk_exists_cached[ $fk_name ] ) ) {
			return $this->fk_exists_cached[ $fk_name ];
		}

		$query = $this->wpdb->prepare(
			'SELECT COUNT(*) FROM `information_schema`.`REFERENTIAL_CONSTRAINTS`
				WHERE `CONSTRAINT_SCHEMA` = DATABASE()
				AND `TABLE_NAME` = %s
				AND `CONSTRAINT_NAME` = %s',
			[ $this->get_table_name(), $fk_name ]
		);

		$count = $this->wpdb->get_var( $query );

		$this->fk_exists_cached[ $fk_name ] = null !== $count && '' !== trim( $count ) && '0' !== $count;

		return $this->fk_exists_cached[ $fk_name ];
	}

	/**
	 * Adds a foreign key by referencing another table object using conventions.
	 *
	 * @param Table_Interface       $referenced_table The Table object for the table being referenced.
	 * @param string                $on_delete        Optional. The action to take on DELETE. Defaults to 'RESTRICT'.
	 * @param string                $on_update        Optional. The action to take on UPDATE. Defaults to 'RESTRICT'.
	 * @param non-empty-string|null $constraint_name  Optional. The name for the foreign key constraint.
	 *
	 * @phpstan-param Foreign_Key_Action::* $on_delete
	 * @psalm-param Foreign_Key_Action::* $on_delete
	 * @phpstan-param Foreign_Key_Action::* $on_update
	 * @psalm-param Foreign_Key_Action::* $on_update
	 *
	 * @return bool
	 *
	 * @throws Schema_Exception If any identifier is invalid.
	 */
	final protected function add_foreign_key_by_reference(
		Table_Interface $referenced_table,
		string $on_delete = Foreign_Key_Action::RESTRICT,
		string $on_update = Foreign_Key_Action::RESTRICT,
		?string $constraint_name = null
	): bool {
		return $this->add_foreign_key(
			$referenced_table->get_foreign_key_name(),
			$referenced_table->get_table_name(),
			$referenced_table->get_primary_key(),
			$constraint_name,
			$on_delete,
			$on_update
		);
	}

	/**
	 * Adds a foreign key constraint to this table using explicit string identifiers.
	 *
	 * @param non-empty-string      $column_name               The name of the column in this table.
	 * @param non-empty-string      $referenced_table_name     The full name of the table being referenced.
	 * @param non-empty-string      $referenced_column_name    The name of the column in the referenced table.
	 * @param non-empty-string|null $constraint_name           Optional. The name for the foreign key constraint.
	 * @param string                $on_delete                 Optional. The action to take on DELETE.
	 * @param string                $on_update                 Optional. The action to take on UPDATE.
	 *
	 * @phpstan-param Foreign_Key_Action::* $on_delete
	 * @psalm-param Foreign_Key_Action::* $on_delete
	 * @phpstan-param Foreign_Key_Action::* $on_update
	 * @psalm-param Foreign_Key_Action::* $on_update
	 *
	 * @return bool
	 *
	 * @throws Schema_Exception If any identifier or action is invalid.
	 */
	final protected function add_foreign_key(
		string $column_name,
		string $referenced_table_name,
		string $referenced_column_name,
		?string $constraint_name = null,
		string $on_delete = Foreign_Key_Action::RESTRICT,
		string $on_update = Foreign_Key_Action::RESTRICT
	): bool {
		// 1. Validate all identifiers and actions.
		if ( ! Util::valid_sql_identifier( $column_name ) ) {
			throw new Schema_Exception( sprintf( 'The local column name "%s" is invalid.', $column_name ) );
		}
		if ( ! Util::valid_sql_identifier( $referenced_table_name ) ) {
			throw new Schema_Exception( sprintf( 'The referenced table name "%s" is invalid.', $referenced_table_name ) );
		}
		if ( ! Util::valid_sql_identifier( $referenced_column_name ) ) {
			throw new Schema_Exception( sprintf( 'The referenced column name "%s" is invalid.', $referenced_column_name ) );
		}

		$constraint_name = $constraint_name ?? Util::generate_identifier_name( $this->get_table_name( false ), [ $column_name ], 'fk' );

		$valid_actions = Foreign_Key_Action::get_all();
		if ( ! in_array( $on_delete, $valid_actions, true ) || ! in_array( $on_update, $valid_actions, true ) ) {
			throw new Schema_Exception( 'Invalid ON DELETE or ON UPDATE action provided for foreign key.' );
		}

		// 2. Make the operation idempotent.
		if ( $this->foreign_key_exists( $constraint_name ) ) {
			return true;
		}

		// 3. Construct and execute the SQL query.
		$sql = "ALTER TABLE `{$this->get_table_name()}`
				ADD CONSTRAINT `{$constraint_name}`
				FOREIGN KEY (`{$column_name}`)
				REFERENCES `{$referenced_table_name}` (`{$referenced_column_name}`)
				ON DELETE {$on_delete}
				ON UPDATE {$on_update}";

		$has_added = false !== $this->wpdb->query( $sql );
		if ( $has_added ) {
			$this->fk_exists_cached[ $constraint_name ] = true;
		}

		return $has_added;
	}

	/**
	 * Drops a foreign key constraint from this table.
	 *
	 * @param non-empty-string $fk_name The name of the foreign key constraint to drop.
	 *
	 * @return bool
	 *
	 * @throws Schema_Exception If the foreign key name is an invalid identifier.
	 */
	final protected function drop_foreign_key( string $fk_name ): bool {
		if ( ! Util::valid_sql_identifier( $fk_name ) ) {
			throw new Schema_Exception( sprintf( 'The foreign key name "%s" is not a valid SQL identifier.', $fk_name ) );
		}
		if ( ! $this->foreign_key_exists( $fk_name ) ) {
			return true; // Idempotent.
		}

		$table_name  = $this->get_table_name();
		$query       = "ALTER TABLE `{$table_name}` DROP FOREIGN KEY `{$fk_name}`";
		$has_dropped = false !== $this->wpdb->query( $query );
		if ( $has_dropped ) {
			$this->fk_exists_cached[ $fk_name ] = false;
		}

		return $has_dropped;
	}

	/*
	|--------------------------------------------------------------------------
	| MySQL & MariaDB Compatibility Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Check if the current database server is MariaDB.
	 *
	 * @return bool
	 */
	final protected function is_mariadb(): bool {
		if ( ! isset( self::$is_mariadb_installation ) ) {
			$info_string                   = strtolower( (string) $this->wpdb->get_var( 'SELECT @@version_comment' ) );
			self::$is_mariadb_installation = str_contains( $info_string, 'mariadb' );
		}

		return self::$is_mariadb_installation;
	}

	/**
	 * Get the MySQL-compatible server version number.
	 *
	 * @return non-empty-string
	 */
	final protected function get_mysql_or_mariadb_version(): string {
		if ( ! isset( self::$mysql_server_version ) ) {
			$version_string = (string) $this->wpdb->get_var( 'SELECT @@version' );
			$version_string = (string) preg_replace( '/[^0-9.].*/', '', $version_string );
			/** @var non-empty-string $version_string */
			self::$mysql_server_version = $version_string;
		}

		return self::$mysql_server_version;
	}

	/**
	 * Check if the current MySQL server version is at least the specified version.
	 *
	 * @param non-empty-string $version The minimum version number to check.
	 *
	 * @return bool
	 */
	final protected function is_mysql_at_least( string $version ): bool {
		return ! $this->is_mariadb() && 0 <= version_compare( $this->get_mysql_or_mariadb_version(), $version );
	}

	/**
	 * Check if the current MariaDB server version is at least the specified version.
	 *
	 * @param non-empty-string $version The minimum version number to check.
	 *
	 * @return bool
	 */
	final protected function is_mariadb_at_least( string $version ): bool {
		return $this->is_mariadb() && 0 <= version_compare( $this->get_mysql_or_mariadb_version(), $version );
	}
}
