<?php
/**
 * Tests for the Column_Definition class.
 *
 * @package WPTechnix\WP_Tables_Schema\Tests\Schema
 */

namespace WPTechnix\WP_Tables_Schema\Tests\Schema;

use PHPUnit\Framework\TestCase;
use WPTechnix\WP_Tables_Schema\Constants\Column_Type;
use WPTechnix\WP_Tables_Schema\Exceptions\Schema_Exception;
use WPTechnix\WP_Tables_Schema\Schema\Column_Definition;
use WPTechnix\WP_Tables_Schema\Util;

/**
 * Column Definition Test
 *
 * @covers \WPTechnix\WP_Tables_Schema\Schema\Column_Definition
 */
final class Column_Definition_Test extends TestCase {

	/**
	 * @test
	 */
	public function test_constructor_succeeds_with_valid_inputs(): void {
		$column = new Column_Definition( 'user_login', Column_Type::VARCHAR, [ 60 ] );
		self::assertSame( 'user_login', $column->get_name() );
		self::assertFalse( $column->is_primary() );
	}

	/**
	 * @param string $invalid_name The invalid column name to test.
	 *
	 * @dataProvider invalid_identifier_provider
	 * @test
	 */
	public function test_constructor_throws_on_invalid_name( string $invalid_name ): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage(
			sprintf(
				'The column name "%s" is invalid. It must be between 1 and %d characters long',
				$invalid_name,
				Util::MAX_IDENTIFIER_LENGTH
			)
		);
		new Column_Definition( $invalid_name, Column_Type::BIGINT );
	}

	/**
	 * @test
	 */
	public function test_constructor_throws_on_invalid_type(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'The specified column type "INVALID_TYPE" is not a valid or supported type.' );
		new Column_Definition( 'col', 'INVALID_TYPE' );
	}

	/**
	 * @param mixed  $value         The default value to set.
	 * @param string $expected_sql  The expected SQL output for the default clause.
	 *
	 * @dataProvider default_value_provider
	 * @test
	 */
	public function test_default_succeeds_with_various_types( mixed $value, string $expected_sql ): void {
		$column = new Column_Definition( 'col', Column_Type::VARCHAR );
		$column->default( $value );
		self::assertStringContainsString( $expected_sql, $column->to_sql() );
	}

	/**
	 * @test
	 */
	public function test_default_handles_null_literals_correctly(): void {
		// Test with the actual null type.
		$column1 = new Column_Definition( 'col', Column_Type::BIGINT );
		$column1->default( null );
		$expected_sql = '`col` BIGINT NULL DEFAULT NULL';
		self::assertSame( $expected_sql, $column1->to_sql() );

		// Test with the string "NULL", case-insensitively.
		$column2 = new Column_Definition( 'col', Column_Type::BIGINT );
		$column2->default( 'nuLL' );
		self::assertSame( $expected_sql, $column2->to_sql() );
	}

	/**
	 * @test
	 */
	public function test_current_timestamp_as_default_succeeds(): void {
		$column = new Column_Definition( 'created_at', Column_Type::DATETIME );
		$column->current_timestamp_as_default();
		self::assertStringContainsString( 'DEFAULT CURRENT_TIMESTAMP', $column->to_sql() );
	}

	/**
	 * @test
	 */
	public function test_default_throws_on_unsupported_type(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Cannot set a default value on column "col" because its type, "TEXT", does not support default values.' );
		$column = new Column_Definition( 'col', Column_Type::TEXT );
		$column->default( 'some text' );
	}

	/**
	 * @test
	 */
	public function test_default_null_throws_on_primary_key(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Cannot set a default value of NULL on column "id" because it is defined as a PRIMARY KEY.' );
		$column = new Column_Definition( 'id', Column_Type::BIGINT );
		$column->primary()->default( null );
	}

	/**
	 * @test
	 */
	public function test_nullable_succeeds(): void {
		$column = new Column_Definition( 'col', Column_Type::BIGINT );
		$column->nullable();
		self::assertStringContainsString( 'NULL', $column->to_sql() );
		self::assertStringNotContainsString( 'NOT NULL', $column->to_sql() );
	}

	/**
	 * @param string $conflicting_method The method that conflicts with nullable().
	 * @param string $expected_message   The expected exception message.
	 *
	 * @dataProvider nullable_conflict_provider
	 * @test
	 */
	public function test_nullable_throws_when_conflicting( string $conflicting_method, string $expected_message ): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( $expected_message );
		$column = new Column_Definition( 'col', Column_Type::BIGINT );
		$column->{$conflicting_method}(); // This will call the methods such as primary(), auto_increment(), etc.
		$column->nullable();              // This call should fail.
	}

	/**
	 * @test
	 */
	public function test_nullable_throws_on_spatial_key(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Cannot make column "col" nullable because it has a SPATIAL key. Spatially indexed columns must be NOT NULL.' );
		$column = new Column_Definition( 'col', Column_Type::GEOMETRY );
		$column->spatial()->nullable();
	}

	/**
	 * @test
	 */
	public function test_primary_throws_when_already_nullable(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Cannot make column "col" a PRIMARY KEY because it has been defined as nullable.' );
		$column = new Column_Definition( 'col', Column_Type::BIGINT );
		$column->nullable()->primary();
	}

	/**
	 * @test
	 */
	public function test_auto_increment_succeeds(): void {
		$column = new Column_Definition( 'id', Column_Type::BIGINT );
		$column->auto_increment();
		self::assertStringContainsString( 'AUTO_INCREMENT', $column->to_sql() );
	}

	/**
	 * @param callable $setup_action      A function to set up the conflicting state.
	 * @param string   $expected_message  The expected exception message.
	 *
	 * @dataProvider auto_increment_conflict_provider
	 * @test
	 */
	public function test_auto_increment_throws_when_conflicting( callable $setup_action, string $expected_message ): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( $expected_message );
		$column = new Column_Definition( 'id', Column_Type::BIGINT );
		$setup_action( $column );
		$column->auto_increment();
	}

	/**
	 * @test
	 */
	public function test_auto_increment_throws_on_non_integer_type(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Cannot apply AUTO_INCREMENT to column "id" because its type, "VARCHAR", is not an integer type.' );
		$column = new Column_Definition( 'id', Column_Type::VARCHAR );
		$column->auto_increment();
	}

	/**
	 * @test
	 */
	public function test_unsigned_succeeds_on_numeric_type(): void {
		$column = new Column_Definition( 'id', Column_Type::BIGINT );
		$column->unsigned();
		self::assertStringContainsString( 'UNSIGNED', $column->to_sql() );
	}

	/**
	 * @test
	 */
	public function test_unsigned_throws_on_non_numeric_type(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Cannot apply UNSIGNED to column "name" because its type, "VARCHAR", is not numeric.' );
		$column = new Column_Definition( 'name', Column_Type::VARCHAR );
		$column->unsigned();
	}

	/**
	 * @test
	 */
	public function test_on_update_current_timestamp_succeeds_on_valid_type(): void {
		$column = new Column_Definition( 'updated_at', Column_Type::DATETIME );
		$column->on_update_current_timestamp();
		self::assertStringContainsString( 'ON UPDATE CURRENT_TIMESTAMP', $column->to_sql() );
	}

	/**
	 * @test
	 */
	public function test_on_update_current_timestamp_throws_on_invalid_type(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Cannot use ON UPDATE CURRENT_TIMESTAMP on column "col". This attribute is only for DATETIME and TIMESTAMP types' );
		$column = new Column_Definition( 'col', Column_Type::BIGINT );
		$column->on_update_current_timestamp();
	}

	/**
	 * @test
	 */
	public function test_charset_and_collate_succeed_on_string_type(): void {
		$column = new Column_Definition( 'col', Column_Type::VARCHAR );
		$column->charset( 'utf8mb4' )->collate( 'utf8mb4_unicode_520_ci' );

		$sql = $column->to_sql();
		self::assertStringContainsString( 'CHARACTER SET utf8mb4', $sql );
		self::assertStringContainsString( 'COLLATE utf8mb4_unicode_520_ci', $sql );
	}

	/**
	 * @test
	 */
	public function test_charset_throws_on_non_string_type(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Cannot apply CHARACTER SET to column "col" because its type, "BIGINT", is not a string type.' );
		( new Column_Definition( 'col', Column_Type::BIGINT ) )->charset( 'utf8mb4' );
	}

	/**
	 * @test
	 */
	public function test_collate_throws_on_non_string_type(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Cannot apply COLLATION to column "col" because its type, "BIGINT", is not a string type.' );
		( new Column_Definition( 'col', Column_Type::BIGINT ) )->collate( 'utf8mb4_unicode_ci' );
	}

	/**
	 * @test
	 */
	public function test_charset_throws_on_empty_string(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Empty charset provided for column "col".' );
		( new Column_Definition( 'col', Column_Type::VARCHAR ) )->charset( ' ' );
	}

	/**
	 * @test
	 */
	public function test_collate_throws_on_empty_string(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Empty Collation provided for column "col".' );
		( new Column_Definition( 'col', Column_Type::VARCHAR ) )->collate( ' ' );
	}

	/**
	 * @test
	 */
	public function test_comment_succeeds(): void {
		$column = new Column_Definition( 'col', Column_Type::VARCHAR );
		$column->comment( 'A test comment' );
		self::assertStringContainsString( "COMMENT 'A test comment'", $column->to_sql() );
	}

	/**
	 * @test
	 */
	public function test_index_methods_set_state_correctly(): void {
		$column = new Column_Definition( 'email', Column_Type::VARCHAR );
		$column->unique( 'uq_email' )->index( 'idx_email' );

		self::assertTrue( $column->is_unique() );
		self::assertSame( 'uq_email', $column->get_unique_key_name() );
		self::assertTrue( $column->has_index() );
		self::assertSame( 'idx_email', $column->get_index_name() );
	}

	/**
	 * @param string $method_name The name of the index method to call.
	 * @param string $key_type    The SQL keyword for the key type.
	 *
	 * @dataProvider index_method_provider
	 * @test
	 */
	public function test_index_methods_throw_for_invalid_key_name( string $method_name, string $key_type ): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( "{$key_type} set for column \"col\" has invalid name \"invalid-key-name\"." );

		$type = in_array( $method_name, [ 'spatial', 'fulltext' ], true ) ? Column_Type::VARCHAR : Column_Type::BIGINT;
		if ( 'spatial' === $method_name ) {
			$type = Column_Type::GEOMETRY;
		}

		( new Column_Definition( 'col', $type ) )->{$method_name}( 'invalid-key-name' );
	}

	/**
	 * @param string $method_name      The specialized index method to call.
	 * @param string $column_type      The invalid column type to test with.
	 * @param string $expected_message The expected exception message.
	 *
	 * @dataProvider specialized_index_provider
	 * @test
	 */
	public function test_specialized_indexes_throw_on_invalid_column_type( string $method_name, string $column_type, string $expected_message ): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( $expected_message );
		$column = new Column_Definition( 'col', $column_type );
		$column->{$method_name}();
	}

	/**
	 * @test
	 */
	public function test_to_sql_with_all_attributes(): void {
		$column = new Column_Definition( 'id', Column_Type::BIGINT, [ 20 ] );
		$column->unsigned()
			->auto_increment()
			->primary()
			->comment( "User's unique ID" );

		$expected = "`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'User\\'s unique ID'";
		self::assertSame( $expected, $column->to_sql() );
	}

	/**
	 * Data provider for default values.
	 */
	public function default_value_provider(): array {
		return [
			'string'        => [ 'hello', "DEFAULT 'hello'" ],
			'integer'       => [ 123, 'DEFAULT 123' ],
			'float'         => [ 1.23, 'DEFAULT 1.23' ],
			'boolean true'  => [ true, 'DEFAULT 1' ],
			'boolean false' => [ false, 'DEFAULT 0' ],
		];
	}

	/**
	 * Data provider for invalid SQL identifiers.
	 */
	public function invalid_identifier_provider(): array {
		return [
			'with space' => [ 'invalid name' ],
			'with dash'  => [ 'invalid-name' ],
			'too long'   => [ str_repeat( 'a', Util::MAX_IDENTIFIER_LENGTH + 1 ) ],
		];
	}

	/**
	 * Data provider for nullable conflicts.
	 */
	public function nullable_conflict_provider(): array {
		return [
			'with primary'        => [ 'primary', 'Cannot make column "col" nullable because it is defined as a PRIMARY KEY.' ],
			'with auto_increment' => [ 'auto_increment', 'Cannot make column "col" nullable because it is defined as AUTO_INCREMENT.' ],
		];
	}

	/**
	 * Data provider for auto_increment conflicts.
	 */
	public function auto_increment_conflict_provider(): array {
		return [
			'with nullable' => [
				fn( $col ) => $col->nullable(),
				'Cannot apply AUTO_INCREMENT to column "id" because it has been defined as nullable.',
			],
			'with default'  => [
				fn( $col ) => $col->default( 0 ),
				'Cannot apply AUTO_INCREMENT to column "id" because it already has a default value defined.',
			],
		];
	}

	/**
	 * Data provider for index methods.
	 */
	public function index_method_provider(): array {
		return [
			'unique'   => [ 'unique', 'UNIQUE KEY' ],
			'index'    => [ 'index', 'INDEX' ],
			'fulltext' => [ 'fulltext', 'FULLTEXT KEY' ],
			'spatial'  => [ 'spatial', 'SPATIAL KEY' ],
		];
	}

	/**
	 * Data provider for specialized index type conflicts.
	 */
	public function specialized_index_provider(): array {
		return [
			'spatial on non-spatial type' => [
				'spatial',
				Column_Type::VARCHAR,
				'SPATIAL keys can only be applied to spatial data types. Column "col" has an unsupported type "VARCHAR".',
			],
			'fulltext on non-string type' => [
				'fulltext',
				Column_Type::BIGINT,
				'FULLTEXT keys can only be applied to string-based columns (e.g., CHAR, VARCHAR, TEXT). Column "col" has an unsupported type "BIGINT".',
			],
		];
	}
}
