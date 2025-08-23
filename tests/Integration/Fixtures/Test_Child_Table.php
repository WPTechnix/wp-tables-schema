<?php

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures;

use WPTechnix\WP_Tables_Schema\Table;
use WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema;
use WPTechnix\WP_Tables_Schema\Interfaces\Table_Interface;

/**
 * Child table for foreign key testing.
 */
class Test_Child_Table extends Table {

	/**
	 * Schema version.
	 *
	 * @var int
	 */
	protected int $schema_version = 10001;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected string $table_name = 'child';

	/**
	 * Table singular name.
	 *
	 * @var string
	 */
	protected string $table_singular_name = 'child';

	/**
	 * Table alias.
	 *
	 * @var string
	 */
	protected string $table_alias = 'chi';

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
	protected string $foreign_key_name = 'child_id';

	/**
	 * Parent table.
	 *
	 * @var ?Table_Interface
	 */
	private ?Table_Interface $parent_table = null;

	/**
	 * Set parent table.
	 *
	 * @param Table_Interface $table Parent table.
	 */
	public function set_parent_table( Table_Interface $table ): void {
		$this->parent_table = $table;
	}

	/**
	 * Migrate to schema version 10001.
	 *
	 * @return bool True if migration was successful, false otherwise.
	 */
	protected function migrate_to_10001(): bool {
		$result = $this->create_table(
			function ( Create_Table_Schema $table ) {
				$table->id();
				$table->big_integer( 'parent_id' )->unsigned();
				$table->big_integer( 'other_parent_id' )->unsigned()->nullable();
				$table->string( 'name', 100 );
				// Ensure InnoDB for foreign keys.
				$table->engine( 'InnoDB' );
				return $table;
			}
		);

		if ( empty( $result ) || empty( $this->parent_table ) ) {
			return false;
		}

		// Add foreign keys using different methods.
		return $this->add_foreign_key_by_reference( $this->parent_table, 'CASCADE', 'CASCADE', 'fk_parent_id' )
					&& $this->add_foreign_key(
						'other_parent_id',
						$this->parent_table->get_table_name(),
						'id',
						'fk_other_parent',
						'RESTRICT',
						'RESTRICT'
					)
					&& $this->add_foreign_key( 'parent_id', $this->parent_table->get_table_name(), 'id', 'fk_to_drop', 'RESTRICT', 'RESTRICT' )
					&& $this->drop_foreign_key( 'fk_to_drop' );
	}
}
