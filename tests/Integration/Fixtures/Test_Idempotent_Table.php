<?php

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures;

use WPTechnix\WP_Tables_Schema\Table;
use WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema;
use WPTechnix\WP_Tables_Schema\Constants\Index_Type;

/**
 * Table for testing idempotent operations.
 */
class Test_Idempotent_Table extends Table {

	/**
	 * @var int $schema_version The schema version of the table.
	 */
	protected int $schema_version = 10001;

	/**
	 * @var string $table_name The name of the table.
	 */
	protected string $table_name = 'idempotent';

	/**
	 * @var string $table_singular_name The singular name of the table.
	 */
	protected string $table_singular_name = 'idempotent';

	/**
	 * @var string $table_alias The alias of the table.
	 */
	protected string $table_alias = 'ide';

	/**
	 * @var string $primary_key_column The primary key column of the table.
	 */
	protected string $primary_key_column = 'id';

	/**
	 * @var string $foreign_key_name The foreign key name of the table.
	 */
	protected string $foreign_key_name = 'idempotent_id';

	/**
	 * Migrates the table schema to the specified version.
	 *
	 * @return bool True if successful, false if not.
	 */
	protected function migrate_to_10001(): bool {
		$result = $this->create_table(
			function ( Create_Table_Schema $table ) {
				$table->id();
				$table->string( 'name', 100 );
				$table->string( 'email', 191 );
				return $table;
			}
		);

		if ( ! $result ) {
			return false;
		}

		// Run operations multiple times - should be idempotent.
		for ( $i = 0; $i < 3; $i++ ) {
			$result = $result
				&& $this->add_column( 'status', 'VARCHAR(50) DEFAULT "active"' )
				&& $this->add_index( 'email', Index_Type::UNIQUE, 'uniq_email' )
				&& $this->add_index( 'name', Index_Type::INDEX, 'idx_name' );
		}

		return $result;
	}
}
