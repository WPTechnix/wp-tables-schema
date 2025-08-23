<?php

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures;

use WPTechnix\WP_Tables_Schema\Table;
use WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema;

/**
 * Table with complex schema for testing various column types.
 */
class Test_Complex_Schema_Table extends Table {

	/**
	 * @var int The schema version of this table.
	 */
	protected int $schema_version = 10001;

	/**
	 * @var string The name of the table in the database.
	 */
	protected string $table_name = 'complex';

	/**
	 * @var string The singular name of the table.
	 */
	protected string $table_singular_name = 'complex';

	/**
	 * @var string The alias used for this table in the database.
	 */
	protected string $table_alias = 'com';
	/**
	 * @var string The primary key column of the table.
	 */
	protected string $primary_key_column = 'id';
	/**
	 * @var string The foreign key name used for this table.
	 */
	protected string $foreign_key_name = 'complex_id';

	/**
	 * Migrates the table to the specified schema version.
	 *
	 * @return bool True if the migration was successful, false otherwise.
	 */
	protected function migrate_to_10001(): bool {
		return $this->create_table(
			function ( Create_Table_Schema $table ) {
				$table->id();
				// Numeric types.
				$table->tiny_integer( 'tiny_int' );
				$table->small_integer( 'small_int' );
				$table->medium_integer( 'medium_int' );
				$table->big_integer( 'big_int' );
				$table->decimal( 'decimal_col', 10, 2 );
				$table->float( 'float_col' );
				$table->double( 'double_col' );

				// String types.
				$table->string( 'varchar_col', 255 );
				$table->char( 'char_col', 10 );
				$table->text( 'text_col' );
				$table->long_text( 'long_text_col' );

				// Date/Time types.
				$table->date( 'date_col' );
				$table->datetime( 'datetime_col' );
				$table->timestamp( 'timestamp_col' );
				$table->time( 'time_col' );
				$table->year( 'year_col' );

				// Binary types.
				$table->blob( 'blob_col' );
				$table->binary( 'binary_col', 16 );

				// Special types.
				$table->boolean( 'boolean_col' );
				$table->enum( 'enum_col', [ 'option1', 'option2', 'option3' ] );
				$table->set( 'set_col', [ 'tag1', 'tag2', 'tag3' ] );

				// JSON type (conditional).
				if ( $this->is_mysql_at_least( '5.7.8' ) || $this->is_mariadb_at_least( '10.2.1' ) ) {
					$table->json( 'json_col' );
				}

				return $table;
			}
		);
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
