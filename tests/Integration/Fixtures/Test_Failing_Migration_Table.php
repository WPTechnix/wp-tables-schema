<?php

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures;

use Exception;
use WPTechnix\WP_Tables_Schema\Table;
use WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema;

/**
 * Table for testing migration failure and resumption.
 */
class Test_Failing_Migration_Table extends Table {

	/**
	 * The schema version of the table.
	 *
	 * @var int
	 */
	protected int $schema_version = 10003;

	/**
	 * The name of the table.
	 *
	 * @var string
	 */
	protected string $table_name = 'failing';

	/**
	 * The singular name of the table.
	 *
	 * @var string
	 */
	protected string $table_singular_name = 'failing';

	/**
	 * The primary key column of the table.
	 *
	 * @var string
	 */
	protected string $primary_key_column = 'id';

	/**
	 * The foreign key name of the table.
	 *
	 * @var string
	 */
	protected string $foreign_key_name = 'failing_id';

	/**
	 * A flag to simulate a migration failure.
	 *
	 * @var bool
	 */
	private bool $should_fail = true;

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
				return $table;
			}
		);
	}

	/**
	 * Migrate the table to the specified schema version.
	 *
	 * @return bool True if the migration was successful, false otherwise.
	 *
	 * @throws Exception When marked to be fail.
	 */
	protected function migrate_to_10002(): bool {
		if ( $this->should_fail ) {
			// Simulate a failure.
			throw new Exception( 'Simulated migration failure' );
		}
		return $this->add_column( 'status', 'VARCHAR(50)' );
	}

	/**
	 * Migrate the table to the specified schema version.
	 *
	 * @return bool True if the migration was successful, false otherwise.
	 */
	protected function migrate_to_10003(): bool {
		return $this->add_column( 'created_at', 'DATETIME' );
	}

	/**
	 * Fix the issue preventing the migration from succeeding.
	 */
	public function fix_issue(): void {
		$this->should_fail = false;
	}
}
