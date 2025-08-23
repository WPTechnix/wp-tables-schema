<?php

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures;

use WPTechnix\WP_Tables_Schema\Table;
use WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema;

/**
 * Table for testing incremental migrations.
 */
class Test_Migration_Table extends Table {
	/**
	 * Current schema version.
	 *
	 * @var int
	 */
	protected int $schema_version = 10003;

	/**
	 * Name of the table.
	 *
	 * @var string
	 */
	protected string $table_name = 'migration';

	/**
	 * Singular name of the table.
	 *
	 * @var string
	 */
	protected string $table_singular_name = 'migration';

	/**
	 * Alias for the table.
	 *
	 * @var string
	 */
	protected string $table_alias = 'mig';

	/**
	 * Name of the primary key column.
	 *
	 * @var string
	 */
	protected string $primary_key_column = 'id';

	/**
	 * Name of the foreign key to this table.
	 *
	 * @var string
	 */
	protected string $foreign_key_name = 'migration_id';

	/**
	 * Migrate the table to version 10001.
	 *
	 * @return bool True if the migration was successful, false otherwise.
	 */
	protected function migrate_to_10001(): bool {
		return $this->create_table(
			function ( Create_Table_Schema $table ) {
				$table->id();
				$table->string( 'title', 200 );
				return $table;
			}
		);
	}

	/**
	 * Migrate the table to version 10002.
	 *
	 * @return bool True if the migration was successful, false otherwise.
	 */
	protected function migrate_to_10002(): bool {
		return $this->add_column( 'status', 'VARCHAR(50) DEFAULT "pending"' )
				&& $this->add_column( 'priority', 'INT DEFAULT 0' );
	}

	/**
	 * Migrate the table to version 10003.
	 *
	 * @return bool True if the migration was successful, false otherwise.
	 */
	protected function migrate_to_10003(): bool {
		return $this->add_column( 'updated_at', 'DATETIME NULL' )
				&& $this->drop_column( 'priority' )
				&& $this->rename_column( 'status', 'category' );
	}
}
