<?php

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures;

use WPTechnix\WP_Tables_Schema\Table;
use WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema;
use WPTechnix\WP_Tables_Schema\Constants\Index_Type;

/**
 * Table for testing index operations.
 */
class Test_Index_Operations_Table extends Table {

	/**
	 * The schema version.
	 *
	 * @var int
	 */
	protected int $schema_version = 10001;

	/**
	 * The full table name.
	 *
	 * @var string
	 */
	protected string $table_name = 'indexes';

	/**
	 * The singular table name.
	 *
	 * @var string
	 */
	protected string $table_singular_name = 'indexes';

	/**
	 * The table alias.
	 *
	 * @var string
	 */
	protected string $table_alias = 'idx';

	/**
	 * The primary key column.
	 *
	 * @var string
	 */
	protected string $primary_key_column = 'id';

	/**
	 * The foreign key name.
	 *
	 * @var string
	 */
	protected string $foreign_key_name = 'index_id';

	/**
	 * Migrates the table schema to the specified version.
	 *
	 * @return bool True if successful, false if not.
	 */
	protected function migrate_to_10001(): bool {
		$result = $this->create_table(
			function ( Create_Table_Schema $table ) {
				$table->id();
				$table->string( 'email', 191 );
				$table->string( 'status', 50 );
				$table->integer( 'priority' );
				$table->text( 'content' );
				$table->datetime( 'created_at' );
				return $table;
			}
		);

		if ( ! $result ) {
			return false;
		}

		// Add various types of indexes.
		return $this->add_index( 'created_at', Index_Type::INDEX, 'idx_created_at' )
					&& $this->add_unique_key( 'email', 'uniq_email' )
					&& $this->add_index( [ 'status', 'priority' ], Index_Type::INDEX, 'idx_status_priority' )
					&& $this->add_index( 'priority', Index_Type::INDEX, 'idx_to_drop' )
					&& $this->add_index( 'content', Index_Type::FULLTEXT, 'ft_content' )
					&& $this->drop_index( 'idx_to_drop' );
	}
}
