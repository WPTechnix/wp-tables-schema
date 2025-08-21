<?php
/**
 * Tests for the Index_Definition class.
 */

namespace WPTechnix\WP_Tables_Schema\Tests\Schema;

use PHPUnit\Framework\TestCase;
use WPTechnix\WP_Tables_Schema\Constants\Index_Algorithm;
use WPTechnix\WP_Tables_Schema\Constants\Index_Type;
use WPTechnix\WP_Tables_Schema\Exceptions\Schema_Exception;
use WPTechnix\WP_Tables_Schema\Schema\Index_Definition;
use WPTechnix\WP_Tables_Schema\Util;

/**
 * Index Definition Test
 *
 * @covers \WPTechnix\WP_Tables_Schema\Schema\Index_Definition
 */
final class Index_Definition_Test extends TestCase {

	/**
	 * @test
	 * @group constructor
	 */
	public function test_constructor_succeeds_and_getters_work(): void {
		$index = new Index_Definition( 'idx_email', [ 'user_email' ], Index_Type::UNIQUE );

		self::assertSame( 'idx_email', $index->get_name() );
		self::assertSame( [ 'user_email' ], $index->get_columns() );
		self::assertSame( Index_Type::UNIQUE, $index->get_type() );
	}

	/**
	 * @test
	 * @group constructor
	 */
	public function test_constructor_defaults_to_standard_index_type(): void {
		$index = new Index_Definition( 'idx_user_id', [ 'user_id' ] );
		self::assertSame( Index_Type::INDEX, $index->get_type() );
	}

	/**
	 * @param string $invalid_name The invalid index name to test.
	 *
	 * @dataProvider invalid_identifier_provider
	 * @test
	 * @group constructor
	 */
	public function test_constructor_throws_on_invalid_index_name( string $invalid_name ): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage(
			sprintf(
				'The provided index name "%s" is invalid. It must be between 1 and %d characters long',
				$invalid_name,
				Util::MAX_IDENTIFIER_LENGTH
			)
		);
		new Index_Definition( $invalid_name, [ 'column1' ] );
	}

	/**
	 * @test
	 * @group constructor
	 */
	public function test_constructor_throws_on_empty_columns(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Cannot define the index "idx_test" with an empty list of columns.' );
		new Index_Definition( 'idx_test', [] );
	}

	/**
	 * @test
	 * @group constructor
	 */
	public function test_constructor_throws_on_invalid_type(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'The specified index type "INVALID_TYPE" is not valid. Please use one of: "INDEX", "UNIQUE", "FULLTEXT", "SPATIAL".' );
		new Index_Definition( 'idx_test', [ 'column1' ], 'INVALID_TYPE' );
	}

	/**
	 * @param string $invalid_column The invalid column name to test.
	 *
	 * @dataProvider invalid_identifier_provider
	 * @test
	 * @group constructor
	 */
	public function test_constructor_throws_on_invalid_column_name( string $invalid_column ): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( sprintf( 'The column name "%s" provided for index "idx_test" is not a valid SQL identifier.', $invalid_column ) );
		new Index_Definition( 'idx_test', [ 'valid_column', $invalid_column ] );
	}

	/**
	 * [NEW] This test covers the specific `is_string() ? $column : 'NOT_A_STRING'` path.
	 *
	 * @test
	 * @group constructor
	 */
	public function test_constructor_throws_on_non_string_in_columns_array(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'The column name "NOT_A_STRING" provided for index "idx_test" is not a valid SQL identifier.' );
		new Index_Definition( 'idx_test', [ 'valid_column', null ] );
	}

	/**
	 * @test
	 * @group fluent
	 */
	public function test_using_succeeds_with_valid_algorithm(): void {
		$index = new Index_Definition( 'idx_test', [ 'column1' ] );
		$index->using( Index_Algorithm::BTREE );

		self::assertStringContainsString( 'USING BTREE', $index->to_sql() );
	}

	/**
	 * @test
	 * @group fluent
	 */
	public function test_using_throws_on_invalid_algorithm(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'The specified index algorithm "INVALID_ALGO" on index "idx_test" is not valid. Please use one of: "BTREE", "HASH".' );
		$index = new Index_Definition( 'idx_test', [ 'column1' ] );
		$index->using( 'INVALID_ALGO' );
	}

	/**
	 * @test
	 * @group fluent
	 */
	public function test_length_succeeds_with_valid_inputs(): void {
		$index = new Index_Definition( 'idx_test', [ 'varchar_col', 'text_col' ] );
		$index->length( 'varchar_col', 100 );
		$index->length( 'text_col', 50 );

		$expected_sql = 'KEY `idx_test` (`varchar_col`(100), `text_col`(50))';
		self::assertSame( $expected_sql, $index->to_sql() );
	}

	/**
	 * @test
	 * @group fluent
	 */
	public function test_length_throws_on_non_existent_column(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Cannot set a prefix length for column "not_in_index" because it is not part of the index "idx_test".' );
		$index = new Index_Definition( 'idx_test', [ 'column1' ] );
		$index->length( 'not_in_index', 10 );
	}

	/**
	 * @param int $input_length The length value to test.
	 *
	 * @dataProvider zero_and_negative_length_provider
	 * @test
	 * @group fluent
	 */
	public function test_length_coerces_invalid_length_to_one( int $input_length ): void {
		$index = new Index_Definition( 'idx_test', [ 'column1' ] );
		$index->length( 'column1', $input_length );
		self::assertStringContainsString( '(`column1`(1))', $index->to_sql() );
	}

	/**
	 * @param string $type         The index type from Index_Type constants.
	 * @param string $expected_sql The expected SQL keyword string.
	 *
	 * @dataProvider index_type_provider
	 * @test
	 * @group sql
	 */
	public function test_to_sql_generates_correct_syntax_for_all_types( string $type, string $expected_sql ): void {
		$index = new Index_Definition( 'idx_test', [ 'column1' ], $type );
		self::assertStringStartsWith( $expected_sql, $index->to_sql() );
	}

	/**
	 * @test
	 * @group sql
	 */
	public function test_to_sql_for_complex_index(): void {
		$index = new Index_Definition( 'idx_complex', [ 'col_a', 'col_b' ], Index_Type::UNIQUE );
		$index->length( 'col_a', 20 )->using( Index_Algorithm::BTREE );

		$expected_sql = 'UNIQUE KEY `idx_complex` (`col_a`(20), `col_b`) USING BTREE';
		self::assertSame( $expected_sql, $index->to_sql() );
	}

	/**
	 * @return array<string, array<string>>
	 */
	public function invalid_identifier_provider(): array {
		return [
			'with space'          => [ 'invalid name' ],
			'with dash'           => [ 'invalid-name' ],
			'potential injection' => [ "' OR 1=1; --" ],
			'too long'            => [ str_repeat( 'a', Util::MAX_IDENTIFIER_LENGTH + 1 ) ],
		];
	}

	/**
	 * @return array<string, array<int>>
	 */
	public function zero_and_negative_length_provider(): array {
		return [
			'zero'     => [ 0 ],
			'negative' => [ -10 ],
		];
	}

	/**
	 * @return array<string, array<string>>
	 */
	public function index_type_provider(): array {
		return [
			'standard index' => [ Index_Type::INDEX, 'KEY' ],
			'unique index'   => [ Index_Type::UNIQUE, 'UNIQUE KEY' ],
			'fulltext index' => [ Index_Type::FULLTEXT, 'FULLTEXT KEY' ],
			'spatial index'  => [ Index_Type::SPATIAL, 'SPATIAL KEY' ],
		];
	}
}
