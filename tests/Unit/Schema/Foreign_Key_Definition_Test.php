<?php
/**
 * Foreign Key Definition Test
 */

namespace WPTechnix\WP_Tables_Schema\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use WPTechnix\WP_Tables_Schema\Constants\Foreign_Key_Action;
use WPTechnix\WP_Tables_Schema\Exceptions\Schema_Exception;
use WPTechnix\WP_Tables_Schema\Schema\Foreign_Key_Definition;
use WPTechnix\WP_Tables_Schema\Util;

/**
 * Foreign Key Definition Test
 *
 * @covers \WPTechnix\WP_Tables_Schema\Schema\Foreign_Key_Definition
 */
final class Foreign_Key_Definition_Test extends TestCase {

	/**
	 * Tests that the constructor succeeds with both string and array column definitions.
	 *
	 * @test
	 */
	public function test_constructor_succeeds_with_string_and_array_columns(): void {
		$fk_string = new Foreign_Key_Definition( 'fk_user_id', 'user_id' );
		self::assertSame( 'fk_user_id', $fk_string->get_name() );
		self::assertSame( [ 'user_id' ], $fk_string->get_columns() );

		$fk_array = new Foreign_Key_Definition( 'fk_order_product', [ 'order_id', 'product_id' ] );
		self::assertSame( 'fk_order_product', $fk_array->get_name() );
		self::assertSame( [ 'order_id', 'product_id' ], $fk_array->get_columns() );
	}

	/**
	 * Tests that the constructor throws an exception for an invalid constraint name.
	 *
	 * @param string $invalid_name The invalid name to test.
	 *
	 * @dataProvider invalid_identifier_provider
	 * @test
	 */
	public function test_constructor_throws_on_invalid_constraint_name( string $invalid_name ): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage(
			sprintf(
				'The provided foreign key name "%s" is invalid. It must be between 1 and %d',
				$invalid_name,
				Util::MAX_IDENTIFIER_LENGTH
			)
		);
		new Foreign_Key_Definition( $invalid_name, 'user_id' );
	}

	/**
	 * Tests that the constructor throws an exception for an empty columns array.
	 *
	 * @test
	 */
	public function test_constructor_throws_on_empty_columns_array(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Foreign key "fk_test" must have at least one local column.' );
		new Foreign_Key_Definition( 'fk_test', [] );
	}

	/**
	 * Tests that the constructor throws an exception for an invalid column name.
	 *
	 * @param string $invalid_column Invalid column name.
	 *
	 * @dataProvider invalid_identifier_provider
	 * @test
	 */
	public function test_constructor_throws_on_invalid_column_name( string $invalid_column ): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( sprintf( 'The local column name "%s" for foreign key "fk_test" is invalid.', $invalid_column ) );
		new Foreign_Key_Definition( 'fk_test', [ 'valid_column', $invalid_column ] );
	}

	/**
	 * Tests that the constructor throws an exception for a non-string value in the columns array.
	 *
	 * This test covers the specific `is_string() ? $column : 'NOT_A_STRING'` path.
	 *
	 * @test
	 */
	public function test_constructor_throws_on_non_string_in_columns_array(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'The local column name "NOT_A_STRING" for foreign key "fk_test" is invalid.' );
		new Foreign_Key_Definition( 'fk_test', [ 'valid_column', null ] );
	}

	/**
	 * Tests that the references() method succeeds and defaults to the 'id' column.
	 *
	 * @test
	 */
	public function test_references_succeeds_and_defaults_to_id_column(): void {
		$fk_explicit = new Foreign_Key_Definition( 'fk_post_author', 'author_id' );
		$fk_explicit->references( 'users', 'ID' );
		$expected_sql_explicit = 'CONSTRAINT `fk_post_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`ID`)';
		self::assertSame( $expected_sql_explicit, $fk_explicit->to_sql() );

		$fk_default = new Foreign_Key_Definition( 'fk_post_author', 'author_id' );
		$fk_default->references( 'users' );
		$expected_sql_default = 'CONSTRAINT `fk_post_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`)';
		self::assertSame( $expected_sql_default, $fk_default->to_sql() );
	}

	/**
	 * Tests that references() throws an exception for an invalid table name.
	 *
	 * @test
	 */
	public function test_references_throws_on_invalid_table_name(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'The referenced table name "invalid table" for foreign key "fk_test" is invalid.' );
		$fk = new Foreign_Key_Definition( 'fk_test', 'col1' );
		$fk->references( 'invalid table', 'col2' );
	}

	/**
	 * Tests that references() throws an exception for an invalid column name.
	 *
	 * @test
	 */
	public function test_references_throws_on_invalid_column_name(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'The referenced column name "invalid-column" for foreign key "fk_test" is invalid.' );
		$fk = new Foreign_Key_Definition( 'fk_test', 'col1' );
		$fk->references( 'valid_table', 'invalid-column' );
	}

	/**
	 * Tests that references() throws an exception for an empty referenced columns array.
	 *
	 * This test covers the case where an empty array is explicitly passed to references().
	 *
	 * @test
	 */
	public function test_references_throws_for_empty_columns_array(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Foreign key "fk_test" must reference at least one column.' );
		$fk = new Foreign_Key_Definition( 'fk_test', 'col1' );
		$fk->references( 'valid_table', [] );
	}

	/**
	 * Tests that the on_delete() and on_update() methods succeed.
	 *
	 * @test
	 */
	public function test_on_delete_and_on_update_succeed(): void {
		$fk = new Foreign_Key_Definition( 'fk_user_id', 'user_id' );
		$fk->references( 'users', 'id' )
			->on_delete( Foreign_Key_Action::CASCADE )
			->on_update( Foreign_Key_Action::SET_NULL );

		$expected_sql = 'CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL';
		self::assertSame( $expected_sql, $fk->to_sql() );
	}

	/**
	 * Tests that on_delete() throws an exception for an invalid action.
	 *
	 * @test
	 */
	public function test_on_delete_throws_on_invalid_action(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Invalid ON DELETE action "DROP" set on foreign key "fk_test".' );
		$fk = new Foreign_Key_Definition( 'fk_test', 'col1' );
		$fk->on_delete( 'DROP' );
	}

	/**
	 * Tests that on_update() throws an exception for an invalid action.
	 *
	 * @test
	 */
	public function test_on_update_throws_on_invalid_action(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Invalid ON UPDATE action "DROP" set on foreign key "fk_test".' );
		$fk = new Foreign_Key_Definition( 'fk_test', 'col1' );
		$fk->on_update( 'DROP' );
	}

	/**
	 * Tests that the shorthand action methods succeed.
	 *
	 * @test
	 */
	public function test_shorthand_methods_succeed(): void {
		$fk_cascade = new Foreign_Key_Definition( 'fk_post_meta', 'post_id' );
		$fk_cascade->references( 'posts', 'id' )->cascade();
		$expected_cascade = 'CONSTRAINT `fk_post_meta` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE';
		self::assertSame( $expected_cascade, $fk_cascade->to_sql() );

		$fk_nullify = new Foreign_Key_Definition( 'fk_comment_author', 'user_id' );
		$fk_nullify->references( 'users', 'id' )->nullify_on_delete();
		$expected_nullify = 'CONSTRAINT `fk_comment_author` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL';
		self::assertSame( $expected_nullify, $fk_nullify->to_sql() );
	}

	/**
	 * Tests that to_sql() succeeds for a composite foreign key.
	 *
	 * @test
	 */
	public function test_to_sql_succeeds_for_composite_key(): void {
		$fk = new Foreign_Key_Definition( 'fk_order_items', [ 'order_id', 'product_id' ] );
		$fk->references( 'order_details', [ 'order_id', 'product_id' ] )
			->on_delete( Foreign_Key_Action::CASCADE );

		$expected_sql = 'CONSTRAINT `fk_order_items` FOREIGN KEY (`order_id`, `product_id`) REFERENCES `order_details` (`order_id`, `product_id`) ON DELETE CASCADE';
		self::assertSame( $expected_sql, $fk->to_sql() );
	}

	/**
	 * Tests that to_sql() omits the RESTRICT action by default.
	 *
	 * @test
	 */
	public function test_to_sql_omits_restrict_action_by_default(): void {
		$fk = new Foreign_Key_Definition( 'fk_simple', 'col1' );
		$fk->references( 'ref_table', 'ref_col1' );

		$expected_sql = 'CONSTRAINT `fk_simple` FOREIGN KEY (`col1`) REFERENCES `ref_table` (`ref_col1`)';
		self::assertSame( $expected_sql, $fk->to_sql() );
		self::assertStringNotContainsString( 'ON DELETE', $fk->to_sql() );
		self::assertStringNotContainsString( 'ON UPDATE', $fk->to_sql() );
	}

	/**
	 * Tests that to_sql() throws an exception if the definition is incomplete.
	 *
	 * @test
	 */
	public function test_to_sql_throws_if_not_fully_configured(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'Foreign key "fk_incomplete" is incomplete. Use the references() method' );
		$fk = new Foreign_Key_Definition( 'fk_incomplete', 'col1' );
		$fk->to_sql();
	}

	/**
	 * Tests that to_sql() throws an exception when local and referenced column counts mismatch.
	 *
	 * @test
	 */
	public function test_to_sql_throws_on_column_count_mismatch(): void {
		$this->expectException( Schema_Exception::class );
		$this->expectExceptionMessage( 'The number of local columns (2) in foreign key "fk_mismatch" does not match the number of referenced columns (1).' );
		$fk = new Foreign_Key_Definition( 'fk_mismatch', [ 'col1', 'col2' ] );
		$fk->references( 'ref_table', 'ref_col1' );
		$fk->to_sql();
	}

	/**
	 * Data provider for invalid SQL identifiers.
	 *
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
}
