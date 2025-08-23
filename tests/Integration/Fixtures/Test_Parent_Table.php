<?php

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures;

use WPTechnix\WP_Tables_Schema\Table;
use WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema;

/**
 * Parent table for foreign key testing.
 */
class Test_Parent_Table extends Table {

	/**
	 * Current schema version.
	 *
	 * @var int
	 */
	protected int $schema_version = 10001;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected string $table_name = 'parent';

	/**
	 * Singular table name for human-readable output.
	 *
	 * @var string
	 */
	protected string $table_singular_name = 'parent';

	/**
	 * Table alias.
	 *
	 * @var string
	 */
	protected string $table_alias = 'par';

	/**
	 * Primary key column.
	 *
	 * @var string
	 */
	protected string $primary_key_column = 'id';

	/**
	 * Foreign key name.
	 *
	 * @var string
	 */
	protected string $foreign_key_name = 'parent_id';

	/**
	 * Migrate the table to version 10001.
	 *
	 * @return bool True if the migration was successful, false otherwise.
	 */
	protected function migrate_to_10001(): bool {
		return $this->create_table(
			function ( Create_Table_Schema $table ) {
				$table->id();
				$table->string( 'name', 100 );
				// Ensure InnoDB for foreign key references.
				$table->engine( 'InnoDB' );
				return $table;
			}
		);
	}
}
