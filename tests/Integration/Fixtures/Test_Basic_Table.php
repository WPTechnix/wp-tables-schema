<?php

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures;

use WPTechnix\WP_Tables_Schema\Table;
use WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema;

/**
 * Basic table for testing simple operations.
 */
class Test_Basic_Table extends Table {

	/**
	 * The current schema version of the table.
	 *
	 * @var int
	 */
	protected int $schema_version = 10001;

	/**
	 * The name of the table.
	 *
	 * @var string
	 */
	protected string $table_name = 'basic';

	/**
	 * The singular name of the table.
	 *
	 * @var string
	 */
	protected string $table_singular_name = 'basic';

	/**
	 * The name of the primary key column.
	 *
	 * @var string
	 */
	protected string $primary_key_column = 'id';

	/**
	 * The name of the foreign key column.
	 *
	 * @var string
	 */
	protected string $foreign_key_name = 'basic_id';

	/**
	 * Migrate the table to the specified schema version.
	 *
	 * @return bool True if the migration was successful, false otherwise.
	 */
	protected function migrate_to_10001(): bool {
		return $this->create_table(
			function ( Create_Table_Schema $table ) {
				$table->id();
				$table->string( 'name', 100 );
				$table->string( 'email', 191 )->unique();
				$table->datetime( 'created_at' )->nullable();
				return $table;
			}
		);
	}
}
