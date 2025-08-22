<?php
/**
 * Tests for the Create_Table_Schema class.
 */

namespace WPTechnix\WP_Tables_Schema\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use WPTechnix\WP_Tables_Schema\Exceptions\Schema_Exception;
use WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema;

/**
 * Create Table Schema Test
 *
 * @covers \WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema
 */
final class Create_Table_Schema_Test extends TestCase {

	/**
	 * Tests that the constructor succeeds with valid table names.
	 *
	 * @test
	 */
	public function test_constructor_succeeds_with_valid_names(): void {
		$schema1 = new Create_Table_Schema( 'my_table' );
		self::assertSame( 'my_table', $schema1->get_table_name() );
		self::assertSame( 'my_table', $schema1->get_short_table_name() );

		$schema2 = new Create_Table_Schema( 'a_very_long_table_name_for_some_reason', 'short_name' );
		self::assertSame( 'a_very_long_table_name_for_some_reason', $schema2->get_table_name() );
		self::assertSame( 'short_name', $schema2->get_short_table_name() );
	}

	/**
	 * Tests that the constructor throws an exception for an invalid table name.
	 *
	 * @test
	 */
	public function test_constructor_throws_on_invalid_table_name(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'The provided table name "invalid-name" is invalid.' );
		new Create_Table_Schema( 'invalid-name' );
	}

	/**
	 * Tests that the constructor throws an exception for an invalid short table name.
	 *
	 * @test
	 */
	public function test_constructor_throws_on_invalid_short_table_name(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'The provided short table name "invalid-name" is invalid.' );
		new Create_Table_Schema( 'valid_name', 'invalid-name' );
	}

	/**
	 * Tests that the id() macro creates a correct primary key column.
	 *
	 * @test
	 */
	public function test_id_macro_creates_correct_primary_key_column(): void {
		$schema = new Create_Table_Schema( 'test_table' );
		$schema->id();
		$sql = $schema->to_sql();

		self::assertStringContainsString( '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $sql );
	}

	/**
	 * Tests that the timestamps() macro adds created_at and updated_at columns.
	 *
	 * @test
	 */
	public function test_timestamps_macro_adds_datetime_columns(): void {
		$schema = new Create_Table_Schema( 'test_table' );
		$schema->id(); // A table needs at least one column.
		$schema->timestamps();
		$sql = $schema->to_sql();

		self::assertStringContainsString( '`created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP', $sql );
		self::assertStringContainsString( '`updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', $sql );
	}

	/**
	 * Tests that the morphs() macro adds polymorphic columns and an index.
	 *
	 * @test
	 */
	public function test_morphs_macro_adds_columns_and_index(): void {
		$schema = new Create_Table_Schema( 'test_table' );
		$schema->id();
		$schema->morphs( 'commentable' );
		$sql = $schema->to_sql();

		self::assertStringContainsString( '`commentable_id` BIGINT UNSIGNED NOT NULL', $sql );
		self::assertStringContainsString( '`commentable_type` VARCHAR(191) NOT NULL', $sql );
		self::assertStringContainsString( 'KEY `idx_test_table_commentable_type_commentable_id` (`commentable_type`, `commentable_id`)', $sql );
	}

	/**
	 * Tests that various table options are correctly reflected in the final SQL.
	 *
	 * @test
	 */
	public function test_table_options_are_reflected_in_sql(): void {
		$schema = new Create_Table_Schema( 'test_table' );
		$schema->id();
		$schema->engine( 'MyISAM' )
			->charset( 'utf8' )
			->collation( 'utf8_general_ci' )
			->comment( 'My Test Table' )
			->auto_increment( 1000 )
			->if_not_exists();

		$sql = $schema->to_sql();

		self::assertStringStartsWith( 'CREATE TABLE IF NOT EXISTS', $sql );
		self::assertStringContainsString( 'ENGINE=MyISAM', $sql );
		self::assertStringContainsString( 'DEFAULT CHARSET=utf8', $sql );
		self::assertStringContainsString( 'COLLATE=utf8_general_ci', $sql );
		self::assertStringContainsString( "COMMENT='My Test Table'", $sql );
		self::assertStringContainsString( 'AUTO_INCREMENT=1000', $sql );
	}

	/**
	 * Tests that column-level index intents are compiled into table indexes.
	 *
	 * @test
	 */
	public function test_compile_translates_column_index_intents_to_table_indexes(): void {
		$schema = new Create_Table_Schema( 'test_table' );
		$schema->id();
		$schema->string( 'email' )->unique(); // Column-level intent.
		$schema->string( 'status' )->index( 'idx_my_status' ); // Column-level intent with name.

		$sql = $schema->to_sql();

		self::assertStringContainsString( 'UNIQUE KEY `uq_test_table_email` (`email`)', $sql );
		self::assertStringContainsString( 'KEY `idx_my_status` (`status`)', $sql );
	}

	/**
	 * Tests that to_sql() throws an exception when the table has no columns.
	 *
	 * @test
	 */
	public function test_to_sql_throws_with_no_columns(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Cannot create a table with no columns.' );
		$schema = new Create_Table_Schema( 'test_table' );
		$schema->to_sql();
	}

	/**
	 * Tests that to_sql() throws if an index references a non-existent column.
	 *
	 * @test
	 */
	public function test_to_sql_throws_if_index_references_non_existent_column(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Index "my_idx" references a non-existent column: "non_existent_col".' );
		$schema = new Create_Table_Schema( 'test_table' );
		$schema->id();
		$schema->add_index( 'non_existent_col', 'my_idx' );
		$schema->to_sql();
	}

	/**
	 * Tests that to_sql() throws if a foreign key references a non-existent local column.
	 *
	 * @test
	 */
	public function test_to_sql_throws_if_foreign_key_references_non_existent_local_column(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Foreign key "my_fk" references a non-existent local column: "non_existent_col".' );
		$schema = new Create_Table_Schema( 'test_table' );
		$schema->id();
		$schema->add_foreign_key( 'non_existent_col', 'my_fk' );
		$schema->to_sql();
	}

	/**
	 * Tests that to_sql() throws an exception with multiple primary keys defined.
	 *
	 * @test
	 */
	public function test_to_sql_throws_with_multiple_primary_keys(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'A table can only have one primary key.' );
		$schema = new Create_Table_Schema( 'test_table' );
		$schema->id();
		$schema->integer( 'another_id' )->primary();
		$schema->to_sql();
	}

	/**
	 * Tests that to_sql() throws an exception with multiple auto-increment columns defined.
	 *
	 * @test
	 */
	public function test_to_sql_throws_with_multiple_auto_increment_columns(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'A table can only have one auto-incrementing column.' );
		$schema = new Create_Table_Schema( 'test_table' );
		$schema->id();
		$schema->integer( 'another_id' )->auto_increment();
		$schema->to_sql();
	}

	/**
	 * Tests that to_sql() throws if an auto-increment column is not a key.
	 *
	 * @test
	 */
	public function test_to_sql_throws_if_auto_increment_is_not_on_key(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'The auto-incrementing column "counter" must be a primary key or the first column of a UNIQUE index.' );
		$schema = new Create_Table_Schema( 'test_table' );
		$schema->integer( 'counter' )->auto_increment(); // Not a key.
		$schema->to_sql();
	}

	/**
	 * Tests that to_sql() throws if an auto-increment column is not the first in a unique key.
	 *
	 * @test
	 */
	public function test_to_sql_throws_if_auto_increment_is_not_first_in_unique_key(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'The auto-incrementing column "counter" must be a primary key or the first column of a UNIQUE index.' );
		$schema = new Create_Table_Schema( 'test_table' );
		$schema->integer( 'counter' )->auto_increment();
		$schema->string( 'type' );
		$schema->add_unique_key( [ 'type', 'counter' ] ); // 'counter' is second.
		$schema->to_sql();
	}

	/**
	 * Tests that to_sql() succeeds if an auto-increment column is a primary key.
	 *
	 * @test
	 */
	public function test_to_sql_succeeds_if_auto_increment_is_on_primary_key(): void {
		$schema = new Create_Table_Schema( 'test_table' );
		$schema->id(); // This is a valid auto-incrementing primary key.
		$sql = $schema->to_sql();
		self::assertIsString( $sql );
		self::assertStringContainsString( 'AUTO_INCREMENT PRIMARY KEY', $sql );
	}

	/**
	 * Tests that to_sql() succeeds if an auto-increment column is the first in a unique key.
	 *
	 * @test
	 */
	public function test_to_sql_succeeds_if_auto_increment_is_on_first_col_of_unique_key(): void {
		$schema = new Create_Table_Schema( 'test_table' );
		$schema->integer( 'counter' )->auto_increment();
		$schema->string( 'type' );
		$schema->add_unique_key( [ 'counter', 'type' ] ); // 'counter' is first.
		$sql = $schema->to_sql();
		self::assertIsString( $sql );
		self::assertStringContainsString( '`counter` INTEGER NOT NULL AUTO_INCREMENT', $sql );
		self::assertStringContainsString( 'UNIQUE KEY `uq_test_table_counter_type` (`counter`, `type`)', $sql );
	}
}
