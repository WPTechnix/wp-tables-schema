<?php

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures;

use WPTechnix\WP_Tables_Schema\Table;
use WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema;

/**
 * Table for testing database compatibility methods.
 */
class Test_Compatibility_Table extends Table {

	/**
	 * Schema version of the table.
	 *
	 * @var int
	 */
	protected int $schema_version = 10001;

	/**
	 * Full, sanitized table name with prefixes.
	 *
	 * @var string
	 */
	protected string $table_name = 'compat';

	/**
	 * Table singular name, without WordPress or plugin prefixes.
	 *
	 * @var string
	 */
	protected string $table_singular_name = 'compat';

	/**
	 * Table alias used in queries.
	 *
	 * @var string
	 */
	protected string $table_alias = 'cmp';

	/**
	 * Primary key column name for the table.
	 *
	 * @var string
	 */
	protected string $primary_key_column = 'id';

	/**
	 * Foreign key name for the table.
	 *
	 * @var string
	 */
	protected string $foreign_key_name = 'compat_id';

	/**
	 * Migrate the table to version 10001.
	 *
	 * @return bool True if the migration was successful, false otherwise.
	 */
	protected function migrate_to_10001(): bool {
		return $this->create_table(
			function ( Create_Table_Schema $table ) {
				$table->id();
				$table->string( 'test', 100 );
				return $table;
			}
		);
	}

	/**
	 * Get the version of the database server.
	 *
	 * @return string The version of the database server.
	 */
	public function get_db_version(): string {
		return $this->get_mysql_or_mariadb_version();
	}

	/**
	 * Check if the database server is MariaDB.
	 *
	 * @return bool True if the database server is MariaDB, false otherwise.
	 */
	public function check_is_mariadb(): bool {
		return $this->is_mariadb();
	}

	/**
	 * Check if the version of the database server is at least the specified version.
	 *
	 * @param string $version The version to check.
	 *
	 * @return bool True if the version of the database server is at least the specified version, false otherwise.
	 */
	public function check_mysql_version( string $version ): bool {
		return $this->is_mysql_at_least( $version );
	}

	/**
	 * Check if the version of the database server is at least the specified version.
	 *
	 * @param string $version The version to check.
	 *
	 * @return bool True if the version of the database server is at least the specified version, false otherwise.
	 */
	public function check_mariadb_version( string $version ): bool {
		return $this->is_mariadb_at_Least( $version );
	}
}
