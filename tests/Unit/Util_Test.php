<?php
/**
 * Unit tests for Util class.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPTechnix\WP_Tables_Schema\Util;

/**
 * Test class for Util utility functions.
 *
 * @covers \WPTechnix\WP_Tables_Schema\Util
 */
final class Util_Test extends TestCase {

	/**
	 * Test valid SQL identifiers.
	 *
	 * @param string $identifier Valid Identifier to test.
	 *
	 * @dataProvider valid_sql_identifier_provider
	 *
	 * @test
	 */
	public function test_valid_sql_identifier_returns_true_for_valid_identifiers( string $identifier ): void {
		self::assertTrue( Util::valid_sql_identifier( $identifier ) );
	}

	/**
	 * Data provider for valid SQL identifiers.
	 *
	 * @return array<string, array{string}>
	 */
	public static function valid_sql_identifier_provider(): array {
		return [
			'single letter'          => [ 'a' ],
			'single underscore'      => [ '_' ],
			'alphanumeric'           => [ 'user_id' ],
			'all uppercase'          => [ 'USER_ID' ],
			'all lowercase'          => [ 'user_id' ],
			'mixed case'             => [ 'User_Id' ],
			'with numbers'           => [ 'user123' ],
			'starts with underscore' => [ '_private' ],
			'long valid identifier'  => [ 'very_long_table_name_with_many_underscores_and_numbers123' ],
		];
	}

	/**
	 * Test invalid SQL identifiers.
	 *
	 * @param mixed $identifier Invalid Identifier to test.
	 *
	 * @dataProvider invalid_sql_identifier_provider
	 *
	 * @test
	 */
	public function test_valid_sql_identifier_returns_false_for_invalid_identifiers( mixed $identifier ): void {
		self::assertFalse( Util::valid_sql_identifier( $identifier ) );
	}

	/**
	 * Data provider for invalid SQL identifiers.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function invalid_sql_identifier_provider(): array {
		return [
			'null'                      => [ null ],
			'integer'                   => [ 123 ],
			'float'                     => [ 12.3 ],
			'boolean true'              => [ true ],
			'boolean false'             => [ false ],
			'array'                     => [ [ 'test' ] ],
			'object'                    => [ new \stdClass() ],
			'empty string'              => [ '' ],
			'whitespace only'           => [ '   ' ],
			'string with spaces'        => [ 'user id' ],
			'string with hyphens'       => [ 'user-id' ],
			'string with dots'          => [ 'user.id' ],
			'string with special chars' => [ 'user@id' ],
			'starts with number'        => [ '1user' ],
			'exceeds max length'        => [ str_repeat( 'a', 65 ) ],
			'contains newline'          => [ "user\nid" ],
			'contains tab'              => [ "user\tid" ],
			'sql injection attempt'     => [ "user'; DROP TABLE users; --" ],
		];
	}


	/**
	 * Test generate_identifier_name method for names that fit within length limit.
	 *
	 * @param string   $table_name Table name.
	 * @param string[] $columns Columns.
	 * @param string   $prefix Prefix.
	 * @param string   $expected Expected result.
	 *
	 * @dataProvider generate_identifier_name_short_provider
	 *
	 * @test
	 */
	public function test_generate_identifier_name_short_names(
		string $table_name,
		array $columns,
		string $prefix,
		string $expected
	): void {
		$result = Util::generate_identifier_name( $table_name, $columns, $prefix );
		self::assertSame( $expected, $result );
		self::assertLessThanOrEqual( 64, strlen( $result ) );
	}

	/**
	 * Data provider for short identifier names.
	 *
	 * @return array<string, array{string, array<string>, string, string}>
	 */
	public static function generate_identifier_name_short_provider(): array {
		return [
			'simple case'       => [ 'users', [ 'id' ], 'idx', 'idx_users_id' ],
			'multiple columns'  => [ 'users', [ 'first_name', 'last_name' ], 'idx', 'idx_users_first_name_last_name' ],
			'foreign key'       => [ 'posts', [ 'user_id' ], 'fk', 'fk_posts_user_id' ],
			'unique constraint' => [ 'users', [ 'email' ], 'uq', 'uq_users_email' ],
		];
	}

	/**
	 * Test generate_identifier_name method for names that exceed length limit.
	 *
	 * @test
	 */
	public function test_generate_identifier_name_long_names_get_truncated_and_hashed(): void {
		$table_name = 'very_long_table_name_that_exceeds_limits';
		$columns    = [ 'very_long_column_name_one', 'very_long_column_name_two', 'very_long_column_name_three' ];
		$prefix     = 'idx';

		$result = Util::generate_identifier_name( $table_name, $columns, $prefix );

		// Should be exactly 64 characters (max length).
		self::assertSame( 64, strlen( $result ) );

		// Should start with prefix and table name.
		self::assertStringStartsWith( 'idx_very_long_table_name_that_exceeds_limits_', $result );

		// Should end with underscore and 8-character hash.
		self::assertMatchesRegularExpression( '/_[a-f0-9]{8}$/', $result );
	}

	/**
	 * Test generate_identifier_name method when even prefix and table name are too long.
	 *
	 * @test
	 */
	public function test_generate_identifier_name_extremely_long_prefix_and_table(): void {
		$table_name = str_repeat( 'a', 50 );
		$columns    = [ 'column' ];
		$prefix     = str_repeat( 'b', 20 );

		$result = Util::generate_identifier_name( $table_name, $columns, $prefix );

		// Should be exactly 64 characters.
		self::assertSame( 64, strlen( $result ) );

		// Should end with underscore and hash.
		self::assertMatchesRegularExpression( '/_[a-f0-9]{8}$/', $result );
	}

	/**
	 * Test that generated names are consistent (same inputs produce same outputs).
	 *
	 * @test
	 */
	public function test_generate_identifier_name_consistency(): void {
		$table_name = 'test_table';
		$columns    = [ 'col1', 'col2' ];
		$prefix     = 'idx';

		$result1 = Util::generate_identifier_name( $table_name, $columns, $prefix );
		$result2 = Util::generate_identifier_name( $table_name, $columns, $prefix );

		self::assertSame( $result1, $result2 );
	}

	/**
	 * Test that different inputs produce different outputs.
	 *
	 * @test
	 */
	public function test_generate_identifier_name_uniqueness(): void {
		$table_name = 'test_table';
		$columns1   = [ 'col1', 'col2' ];
		$columns2   = [ 'col1', 'col3' ];
		$prefix     = 'idx';

		$result1 = Util::generate_identifier_name( $table_name, $columns1, $prefix );
		$result2 = Util::generate_identifier_name( $table_name, $columns2, $prefix );

		self::assertNotSame( $result1, $result2 );
	}

	/**
	 * Test generate_identifier_name with edge case inputs.
	 *
	 * @param string $table_name Table name.
	 * @param array  $columns Columns.
	 * @param string $prefix Prefix.
	 *
	 * @dataProvider generate_identifier_name_edge_cases_provider
	 *
	 * @test
	 */
	public function test_generate_identifier_name_edge_cases(
		string $table_name,
		array $columns,
		string $prefix
	): void {
		$result = Util::generate_identifier_name( $table_name, $columns, $prefix );

		// Result should always be a non-empty string.
		self::assertIsString( $result );
		self::assertNotEmpty( $result );

		// Should not exceed max length.
		self::assertLessThanOrEqual( 64, strlen( $result ) );

		// Should be a valid SQL identifier.
		self::assertTrue( Util::valid_sql_identifier( $result ) );
	}

	/**
	 * Data provider for edge cases.
	 *
	 * @return array<string, array{string, array<string>, string}>
	 */
	public static function generate_identifier_name_edge_cases_provider(): array {
		return [
			'single character inputs' => [ 'a', [ 'b' ], 'c' ],
			'minimal inputs'          => [ 't', [ 'c' ], 'p' ],
			'long single column'      => [ 'table', [ str_repeat( 'a', 50 ) ], 'idx' ],
		];
	}
}
