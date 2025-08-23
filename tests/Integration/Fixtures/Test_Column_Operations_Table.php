<?php

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures;

use WPTechnix\WP_Tables_Schema\Table;
use WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema;

/**
 * Table for testing column operations.
 */
class Test_Column_Operations_Table extends Table {

	/**
	 * Table schema version.
	 *
	 * @var int
	 */
	protected int $schema_version = 10001;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected string $table_name = 'columns';

	/**
	 * Table singular name.
	 *
	 * @var string
	 */
	protected string $table_singular_name = 'columns';

	/**
	 * Table alias.
	 *
	 * @var string
	 */
	protected string $table_alias = 'col';

	/**
	 * Primary key column name.
	 *
	 * @var string
	 */
	protected string $primary_key_column = 'id';

	/**
	 * Foreign key column name.
	 *
	 * @var string
	 */
	protected string $foreign_key_name = 'column_id';

	/**
	 * Migrate the table schema to the specified version.
	 *
	 * @return bool True if successful, false if not.
	 */
	protected function migrate_to_10001(): bool {
		$result = $this->create_table(
			function ( Create_Table_Schema $table ) {
				$table->id();
				$table->string( 'name', 100 );
				$table->string( 'obsolete_column', 50 );
				return $table;
			}
		);

		if ( ! $result ) {
			return false;
		}

		// Test various column operations.
		return $this->add_column( 'description', 'VARCHAR(255)' )
			&& $this->add_column( 'price', 'DECIMAL(10,2)', 'name' )
			&& $this->add_column( 'quantity', 'INT DEFAULT 0' )
			&& $this->modify_column( 'description', 'TEXT' )
			&& $this->rename_column( 'quantity', 'amount' )
			&& $this->change_column( 'price', 'cost', 'DECIMAL(12,2) DEFAULT 0.00' )
			&& $this->drop_column( 'obsolete_column' );
	}
}
