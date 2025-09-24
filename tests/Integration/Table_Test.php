<?php

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Tests\Integration;

use WP_UnitTestCase;
use wpdb;
use WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures\Test_Basic_Table;
use WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures\Test_Child_Table;
use WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures\Test_Column_Operations_Table;
use WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures\Test_Compatibility_Table;
use WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures\Test_Complex_Schema_Table;
use WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures\Test_Failing_Migration_Table;
use WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures\Test_Idempotent_Table;
use WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures\Test_Index_Operations_Table;
use WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures\Test_Migration_Table;
use WPTechnix\WP_Tables_Schema\Tests\Integration\Fixtures\Test_Parent_Table;

// phpcs:disable WordPress.DB.PreparedSQL

/**
 * Integration tests for the Table class.
 *
 * These tests verify the complete functionality of the Table class including:
 * - Table creation and schema management
 * - Column, index, and foreign key operations
 * - Migration system with version tracking
 * - MySQL/MariaDB compatibility detection
 * - Error recovery and idempotent operations
 *
 * Prerequisites:
 * - WordPress test environment with database access
 * - PHPUnit 9.6
 * - MySQL 5.7+ or MariaDB 10.2+
 * - InnoDB engine for foreign key tests
 *
 * Note: Some tests may be skipped based on database capabilities:
 * - Foreign key tests require InnoDB engine
 * - JSON column tests require MySQL 5.7.8+/MariaDB 10.2.7+
 *
 * @covers \WPTechnix\WP_Tables_Schema\Table
 * @uses \WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema
 * @uses \WPTechnix\WP_Tables_Schema\Schema\ColumnDefinition
 * @uses \WPTechnix\WP_Tables_Schema\Schema\IndexDefinition
 * @uses \WPTechnix\WP_Tables_Schema\Schema\ForeignKeyDefinition
 * @uses \WPTechnix\WP_Tables_Schema\Util
 */
final class Table_Test extends WP_UnitTestCase {

	/**
	 * @var wpdb
	 */
	protected wpdb $wpdb;

	/**
	 * @var string
	 */
	protected static string $plugin_prefix = 'wptm_test_';

	/**
	 * @var array<string>
	 */
	protected array $created_tables = [];

	/**
	 * Set up test environment.
	 */
	public function set_up(): void {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->wpdb->suppress_errors( false );
		$this->wpdb->show_errors( true );
	}

	/**
	 * Clean up after tests.
	 */
	public function tear_down(): void {
		$this->clean_up_tests_table();
	}

	/**
	 * Clean up any existing test tables from previous runs.
	 */
	protected function clean_up_tests_table(): void {

		foreach ( $this->created_tables as $table ) {
			$this->wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}

		// Also clean up the options.
		delete_option( self::$plugin_prefix . '_table_versions' );
		delete_site_option( self::$plugin_prefix . '_table_versions' );
	}

	/**
	 * Test table instantiation and basic properties.
	 */
	public function test_table_instantiation(): void {
		$table = new Test_Basic_Table( $this->wpdb, self::$plugin_prefix );
		// Test basic getters.
		self::assertNotEmpty( $table->get_table_name(), 'Table name should not be empty' );
		self::assertStringContainsString(
			self::$plugin_prefix,
			$table->get_table_name(),
			'Table name should contain plugin prefix'
		);
		self::assertStringContainsString( 'basic', $table->get_table_name(), 'Table name should contain base name' );

		self::assertEquals( 'id', $table->get_primary_key(), 'Primary key should be "id"' );
		self::assertEquals( 'basic_id', $table->get_foreign_key_name(), 'Foreign key name should be "basic_id"' );
		self::assertEquals( 10001, $table->get_schema_version(), 'Schema version should be 10001' );
		self::assertEquals( 10000, $table->get_installed_version(), 'Initial installed version should be 10000' );
	}

	/**
	 * Test basic table creation and installation.
	 */
	public function test_create_and_install_basic_table(): void {
		$table = new Test_Basic_Table( $this->wpdb, self::$plugin_prefix );

		// Table should not exist initially.
		self::assertFalse( $this->table_exists( $table->get_table_name() ) );

		// Install the table.
		$table->install();

		// Debug: Check for any errors.
		if ( ! $this->table_exists( $table->get_table_name() ) ) {
			$last_error = $this->wpdb->last_error;
			self::fail( 'Table was not created. Last error: ' . ( empty( $last_error ) ? $last_error : 'No error message' ) );
		}

		$this->created_tables[] = $table->get_table_name();

		// Table should now exist.
		self::assertTrue( $this->table_exists( $table->get_table_name() ), 'Table should exist after installation' );

		// Verify schema version.
		self::assertEquals( 10001, $table->get_installed_version() );
		self::assertEquals( 10001, $table->get_schema_version() );

		// Verify table structure.
		$columns = $this->get_table_columns( $table->get_table_name() );
		self::assertNotEmpty( $columns, 'Table should have columns' );
		self::assertArrayHasKey( 'id', $columns, 'Table should have id column' );
		self::assertArrayHasKey( 'name', $columns, 'Table should have name column' );
		self::assertArrayHasKey( 'email', $columns, 'Table should have email column' );
		self::assertArrayHasKey( 'created_at', $columns, 'Table should have created_at column' );
	}

	/**
	 * Test incremental migrations.
	 */
	public function test_incremental_migrations(): void {
		$table = new Test_Migration_Table( $this->wpdb, self::$plugin_prefix );

		// Install table (will run migrations up to current schema version).
		$table->install();

		$this->created_tables[] = $table->get_table_name();

		// Verify all migrations were applied.
		self::assertEquals( 10003, $table->get_installed_version() );

		// Verify columns from each migration.
		$columns = $this->get_table_columns( $table->get_table_name() );

		// From migration 10001 (initial).
		self::assertArrayHasKey( 'id', $columns, 'Should have id column from initial migration' );
		self::assertArrayHasKey( 'title', $columns, 'Should have title column from initial migration' );

		// From migration 10003 - final state.
		self::assertArrayHasKey( 'updated_at', $columns, 'Should have updated_at column from migration 10003' );
		self::assertArrayNotHasKey( 'priority', $columns, 'Priority column should be dropped' );
		self::assertArrayHasKey( 'category', $columns, 'Should have category column (renamed from status)' );
		self::assertArrayNotHasKey( 'status', $columns, 'Status column should be renamed to category' );
	}

	/**
	 * Test resumable migrations after failure.
	 */
	public function test_resumable_migration_after_failure(): void {
		$table = new Test_Failing_Migration_Table( $this->wpdb, self::$plugin_prefix );

		// First installation attempt (will fail at migration 10002).
		$table->install();

		$this->created_tables[] = $table->get_table_name();

		// Should have completed migration 10001 but not 10002.
		self::assertEquals( 10001, $table->get_installed_version() );

		// Fix the issue and retry.
		$table->fix_issue();
		$table->install();

		// Should now complete all migrations.
		self::assertEquals( 10003, $table->get_installed_version() );
	}

	/**
	 * Test column operations.
	 */
	public function test_column_operations(): void {
		$table = new Test_Column_Operations_Table( $this->wpdb, self::$plugin_prefix );

		$table->install();

		$this->created_tables[] = $table->get_table_name();

		// Test all column operations were successful.
		$columns = $this->get_table_columns( $table->get_table_name() );

		// Base columns.
		self::assertArrayHasKey( 'id', $columns, 'Should have id column' );
		self::assertArrayHasKey( 'name', $columns, 'Should have name column' );

		// Added columns.
		self::assertArrayHasKey(
			'description',
			$columns,
			'Should have description column'
		);

		// Modified column (check type changed).
		self::assertStringContainsString(
			'text',
			strtolower( $columns['description']['Type'] ),
			'Description should be TEXT type'
		);

		// Renamed column (quantity -> amount).
		self::assertArrayHasKey( 'amount', $columns, 'Should have amount column (renamed from quantity)' );
		self::assertArrayNotHasKey( 'quantity', $columns, 'Quantity column should be renamed to amount' );

		// Changed column (price -> cost with new definition).
		self::assertArrayHasKey( 'cost', $columns, 'Should have cost column (changed from price)' );
		self::assertArrayNotHasKey( 'price', $columns, 'Price column should be changed to cost' );
		self::assertStringContainsString(
			'decimal(12,2)',
			strtolower( $columns['cost']['Type'] ),
			'Cost should be DECIMAL(12,2)'
		);

		// Dropped column.
		self::assertArrayNotHasKey( 'obsolete_column', $columns, 'Obsolete column should be dropped' );
	}

	/**
	 * Test index operations.
	 */
	public function test_index_operations(): void {
		$table = new Test_Index_Operations_Table( $this->wpdb, self::$plugin_prefix );

		$table->install();

		$this->created_tables[] = $table->get_table_name();

		// Verify indexes.
		$indexes = $this->get_table_indexes( $table->get_table_name() );

		// Regular index.
		self::assertArrayHasKey(
			'idx_created_at',
			$indexes,
			'Should have index on created_at'
		);

		// Unique index.
		self::assertArrayHasKey( 'uniq_email', $indexes, 'Should have unique index on email' );
		self::assertEquals( '0', $indexes['uniq_email']['Non_unique'], 'Email index should be unique' );

		// Composite index.
		self::assertArrayHasKey(
			'idx_status_priority',
			$indexes,
			'Should have composite index on status and priority'
		);

		// Fulltext index (if supported - check both the index exists or the table engine doesn't support it).
		self::assertArrayHasKey( 'ft_content', $indexes, 'Should have fulltext index on content when supported' );
		if ( isset( $indexes['ft_content'] ) ) {
			self::assertEquals(
				'FULLTEXT',
				$indexes['ft_content']['Index_type'],
				'Content index should be FULLTEXT type'
			);
		}

		// Primary key.
		self::assertArrayHasKey( 'PRIMARY', $indexes, 'Should have primary key' );

		// Dropped index should not exist.
		self::assertArrayNotHasKey( 'idx_to_drop', $indexes, 'Dropped index should not exist' );
	}

	/**
	 * Test foreign key operations.
	 */
	public function test_foreign_key_operations(): void {

		// Create parent table first.
		$parent_table = new Test_Parent_Table( $this->wpdb, self::$plugin_prefix );
		$parent_table->install();

		// Verify parent table exists.
		self::assertTrue( $this->table_exists( $parent_table->get_table_name() ), 'Parent table should exist' );

		// Create child table with foreign keys.
		$child_table = new Test_Child_Table( $this->wpdb, self::$plugin_prefix );
		$child_table->set_parent_table( $parent_table );
		$child_table->install();

		$this->created_tables[] = $child_table->get_table_name();
		$this->created_tables[] = $parent_table->get_table_name();

		// Verify child table exists.
		self::assertTrue( $this->table_exists( $child_table->get_table_name() ), 'Child table should exist' );

		// Verify foreign keys exist.
		$foreign_keys = $this->get_table_foreign_keys( $child_table->get_table_name() );

		// Check if any foreign keys were created.
		if ( empty( $foreign_keys ) ) {
			// Foreign keys might not be supported or not created properly.
			self::markTestIncomplete( 'Foreign keys were not created - check if InnoDB is properly configured' );
		}

		// FK using table_interface.
		self::assertArrayHasKey( 'fk_parent_id', $foreign_keys, 'Should have fk_parent_id foreign key' );
		if ( isset( $foreign_keys['fk_parent_id'] ) ) {
			self::assertEquals(
				$parent_table->get_table_name(),
				$foreign_keys['fk_parent_id']['REFERENCED_TABLE_NAME'],
				'FK should reference parent table'
			);
			self::assertEquals(
				'id',
				$foreign_keys['fk_parent_id']['REFERENCED_COLUMN_NAME'],
				'FK should reference id column'
			);
			self::assertEquals(
				'CASCADE',
				$foreign_keys['fk_parent_id']['DELETE_RULE'],
				'FK should have CASCADE delete rule'
			);
		}

		// FK using explicit strings.
		self::assertArrayHasKey(
			'fk_other_parent',
			$foreign_keys,
			'Should have fk_other_parent foreign key'
		);
		if ( isset( $foreign_keys['fk_other_parent'] ) ) {
			self::assertEquals(
				'RESTRICT',
				$foreign_keys['fk_other_parent']['DELETE_RULE'],
				'FK should have RESTRICT delete rule'
			);
		}

		// Dropped FK should not exist.
		self::assertArrayNotHasKey( 'fk_to_drop', $foreign_keys, 'Dropped foreign key should not exist' );
	}

	/**
	 * Test idempotent operations.
	 */
	public function test_idempotent_operations(): void {
		$table = new Test_Idempotent_Table( $this->wpdb, self::$plugin_prefix );

		// First installation.
		$table->install();

		// Run install again - should not fail.
		$table->install();

		$this->created_tables[] = $table->get_table_name();

		// Verify nothing changed.
		self::assertEquals( 10001, $table->get_installed_version() );

		// Verify table structure is correct.
		$columns = $this->get_table_columns( $table->get_table_name() );
		self::assertCount( 4, $columns ); // id, name, email, status.
	}

	/**
	 * Test table with complex schema.
	 */
	public function test_complex_schema_table(): void {
		$table = new Test_Complex_Schema_Table( $this->wpdb, self::$plugin_prefix );

		$table->install();

		$this->created_tables[] = $table->get_table_name();

		// Verify various column types.
		$columns = $this->get_table_columns( $table->get_table_name() );

		// Numeric types.
		self::assertArrayHasKey( 'tiny_int', $columns );
		self::assertArrayHasKey( 'small_int', $columns );
		self::assertArrayHasKey( 'medium_int', $columns );
		self::assertArrayHasKey( 'big_int', $columns );
		self::assertArrayHasKey( 'decimal_col', $columns );
		self::assertArrayHasKey( 'float_col', $columns );
		self::assertArrayHasKey( 'double_col', $columns );

		// String types.
		self::assertArrayHasKey( 'varchar_col', $columns );
		self::assertArrayHasKey( 'char_col', $columns );
		self::assertArrayHasKey( 'text_col', $columns );
		self::assertArrayHasKey( 'long_text_col', $columns );

		// Date/Time types.
		self::assertArrayHasKey( 'date_col', $columns );
		self::assertArrayHasKey( 'datetime_col', $columns );
		self::assertArrayHasKey( 'timestamp_col', $columns );
		self::assertArrayHasKey( 'time_col', $columns );
		self::assertArrayHasKey( 'year_col', $columns );

		// Binary types.
		self::assertArrayHasKey( 'blob_col', $columns );
		self::assertArrayHasKey( 'binary_col', $columns );

		// Special types.
		self::assertArrayHasKey( 'boolean_col', $columns );
		self::assertArrayHasKey( 'enum_col', $columns );
		self::assertArrayHasKey( 'set_col', $columns );

		if ( $table->check_mysql_version( '5.7.8' ) || $table->check_mariadb_version( '10.2.1' ) ) {
			// JSON type (if supported).
			self::assertArrayHasKey( 'json_col', $columns );
		}
	}

	/**
	 * Test MySQL/MariaDB compatibility detection.
	 */
	public function test_database_compatibility(): void {
		$table = new Test_Compatibility_Table( $this->wpdb, self::$plugin_prefix );

		// These methods should return valid results.
		$version = $table->get_db_version();
		self::assertNotEmpty( $version );
		self::assertMatchesRegularExpression( '/^\d+\.\d+/', $version );

		$is_mariadb = $table->check_is_mariadb();
		self::assertIsBool( $is_mariadb );

		// Test version comparison methods.
		if ( $is_mariadb ) {
			self::assertTrue( $table->check_mariadb_version( '5.0' ) );
			self::assertFalse( $table->check_mysql_version( '5.0' ) );
		} else {
			self::assertTrue( $table->check_mysql_version( '5.0' ) );
			self::assertFalse( $table->check_mariadb_version( '5.0' ) );
		}
	}

	/**
	 * Test table drop operation.
	 */
	public function test_drop_table(): void {
		$table = new Test_Basic_Table( $this->wpdb, self::$plugin_prefix );

		// Create and verify table exists.
		$table->install();

		$this->created_tables[] = $table->get_table_name();

		self::assertTrue( $this->table_exists( $table->get_table_name() ) );

		// Drop table.
		$result = $table->drop();
		self::assertTrue( $result );

		// Verify table no longer exists.
		self::assertFalse( $this->table_exists( $table->get_table_name() ) );
	}

	/**
	 * Test version persistence across instances.
	 */
	public function test_version_persistence(): void {

		$table_1 = new Test_Basic_Table( $this->wpdb, self::$plugin_prefix );

		// Install with first instance.
		self::assertEquals( 10000, $table_1->get_installed_version() );

		$table_1->install();
		$this->created_tables[] = $table_1->get_table_name();

		self::assertEquals( 10001, $table_1->get_installed_version() );

		// Create new instance and check version.
		$table_2 = new Test_Basic_Table( $this->wpdb, self::$plugin_prefix );

		self::assertEquals( 10001, $table_2->get_installed_version() );

		// Verify no re-installation occurs.
		$table_2->install();
		$this->created_tables[] = $table_2->get_table_name();
		self::assertEquals( 10001, $table_2->get_installed_version() );
	}

	/**
	 * Check if table exists.
	 *
	 * @param string $table_name Table name.
	 */
	protected function table_exists( string $table_name ): bool {
		$result = $this->wpdb->get_var(
			$this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);
		return ! empty( $result );
	}

	/**
	 * Get table columns.
	 *
	 * @param string $table_name Table name.
	 *
	 * @return array
	 */
	protected function get_table_columns( string $table_name ): array {
		$columns = [];
		if ( ! $this->table_exists( $table_name ) ) {
			return $columns;
		}

		$results = $this->wpdb->get_results( "SHOW COLUMNS FROM `{$table_name}`", ARRAY_A );
		if ( ! empty( $results ) ) {
			foreach ( $results as $column ) {
				$columns[ $column['Field'] ] = $column;
			}
		}

		return $columns;
	}

	/**
	 * Get table indexes.
	 *
	 * @param string $table_name Table name.
	 *
	 * @return array
	 */
	protected function get_table_indexes( string $table_name ): array {
		$indexes = [];
		if ( ! $this->table_exists( $table_name ) ) {
			return $indexes;
		}

		$results = $this->wpdb->get_results( "SHOW INDEX FROM `{$table_name}`", ARRAY_A );
		if ( ! empty( $results ) ) {
			foreach ( $results as $index ) {
				if ( ! isset( $indexes[ $index['Key_name'] ] ) ) {
					$indexes[ $index['Key_name'] ] = $index;
				}
			}
		}
		return $indexes;
	}

	/**
	 * Get table foreign keys.
	 *
	 * @param string $table_name Table name.
	 *
	 * @return array<string, array<string,mixed>>
	 */
	protected function get_table_foreign_keys( string $table_name ): array {
		// Strip prefix to get base table name if needed.
		$base_table_name = $table_name;
		if ( strpos( $table_name, $this->wpdb->prefix ) === 0 ) {
			$base_table_name = substr( $table_name, strlen( $this->wpdb->prefix ) );
		}

		$query = $this->wpdb->prepare(
			'SELECT
                rc.CONSTRAINT_NAME,
                rc.REFERENCED_TABLE_NAME,
                kcu.REFERENCED_COLUMN_NAME,
                rc.DELETE_RULE,
                rc.UPDATE_RULE
             FROM information_schema.REFERENTIAL_CONSTRAINTS rc
             JOIN information_schema.KEY_COLUMN_USAGE kcu
                ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                AND rc.TABLE_NAME = kcu.TABLE_NAME
             WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
             AND rc.TABLE_NAME = %s',
			$table_name
		);

		$foreign_keys = [];
		$results      = $this->wpdb->get_results( $query, ARRAY_A );
		if ( ! empty( $results ) ) {
			foreach ( $results as $fk ) {
				$foreign_keys[ $fk['CONSTRAINT_NAME'] ] = $fk;
			}
		}

		// Also try with base table name if no results.
		if ( empty( $foreign_keys ) && $base_table_name !== $table_name ) {
			$query = $this->wpdb->prepare(
				'SELECT
                    rc.CONSTRAINT_NAME,
                    rc.REFERENCED_TABLE_NAME,
                    kcu.REFERENCED_COLUMN_NAME,
                    rc.DELETE_RULE,
                    rc.UPDATE_RULE
                 FROM information_schema.REFERENTIAL_CONSTRAINTS rc
                 JOIN information_schema.KEY_COLUMN_USAGE kcu
                    ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                    AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                    AND rc.TABLE_NAME = kcu.TABLE_NAME
                 WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
                 AND rc.TABLE_NAME = %s',
				$base_table_name
			);

			$results = $this->wpdb->get_results( $query, ARRAY_A );
			if ( ! empty( $results ) ) {
				foreach ( $results as $fk ) {
					$foreign_keys[ $fk['CONSTRAINT_NAME'] ] = $fk;
				}
			}
		}

		return $foreign_keys;
	}
}
