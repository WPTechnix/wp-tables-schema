<?php
/**
 * Defines foreign key constraints for use in CREATE TABLE statements (requires InnoDB).
 *
 * @package WPTechnix\WP_Tables_Schema\Schema
 */

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Schema;

use WPTechnix\WP_Tables_Schema\Constants\Foreign_Key_Action;
use WPTechnix\WP_Tables_Schema\Exceptions\Schema_Exception;
use WPTechnix\WP_Tables_Schema\Util;

/**
 * Encapsulates a foreign key constraint definition within a CREATE TABLE schema (InnoDB only).
 *
 * @package WPTechnix\WP_Tables_Schema\Schema
 */
final class Foreign_Key_Definition {

	/**
	 * The constraint name.
	 *
	 * @var string
	 * @phpstan-var non-empty-string
	 */
	private string $name;

	/**
	 * The local column(s).
	 *
	 * @var array
	 * @phpstan-var list<non-empty-string>
	 */
	private array $columns;

	/**
	 * The referenced table name.
	 *
	 * @var string
	 * @phpstan-var non-empty-string
	 */
	private string $references_table;

	/**
	 * The referenced column(s).
	 *
	 * @var array
	 * @phpstan-var list<non-empty-string>
	 */
	private array $references_columns;

	/**
	 * The action to take on DELETE.
	 *
	 * @var string
	 * @phpstan-var Foreign_Key_Action::*
	 */
	private string $on_delete = Foreign_Key_Action::RESTRICT;

	/**
	 * The action to take on UPDATE.
	 *
	 * @var string
	 * @phpstan-var Foreign_Key_Action::*
	 */
	private string $on_update = Foreign_Key_Action::RESTRICT;

	/**
	 * Constructor.
	 *
	 * @param string       $name    The constraint name.
	 * @param string|array $columns The local column name(s).
	 *
	 * @phpstan-param non-empty-string $name
	 * @phpstan-param non-empty-string|list<non-empty-string> $columns
	 *
	 * @throws Schema_Exception When the constraint name is invalid or columns are empty/invalid.
	 */
	public function __construct( string $name, string|array $columns ) {
		if ( ! Util::valid_sql_identifier( $name ) ) {
			throw new Schema_Exception(
				sprintf(
					'The provided foreign key name "%s" is invalid. It must be between 1 and %d ' .
					'characters long and contain only alphanumeric characters and underscores.',
					$name,
					Util::MAX_IDENTIFIER_LENGTH
				)
			);
		}

		$validated_columns = (array) $columns;

		if ( empty( $validated_columns ) ) {
			throw new Schema_Exception(
				sprintf(
					'Foreign key "%s" must have at least one local column.',
					$name
				)
			);
		}

		foreach ( $validated_columns as $column ) {
			if ( ! Util::valid_sql_identifier( $column ) ) {
				throw new Schema_Exception(
					sprintf(
						'The local column name "%s" for foreign key "%s" is invalid.',
						is_string( $column ) ? $column : 'NOT_A_STRING',
						$name
					)
				);
			}
		}

		$this->name    = $name;
		$this->columns = $validated_columns;
	}

	/**
	 * Sets the referenced table and column(s).
	 *
	 * @param string       $table   The referenced table's complete name (including wpdb prefix).
	 * @param string|array $columns The referenced column name(s). Defaults to 'id'.
	 *
	 * @phpstan-param non-empty-string $table
	 * @phpstan-param non-empty-string|list<non-empty-string> $columns
	 *
	 * @return self The current instance for fluent method chaining.
	 *
	 * @throws Schema_Exception When table or column names are invalid.
	 */
	public function references( string $table, string|array $columns = 'id' ): self {
		if ( ! Util::valid_sql_identifier( $table ) ) {
			throw new Schema_Exception(
				sprintf(
					'The referenced table name "%s" for foreign key "%s" is invalid.',
					$table,
					$this->name
				)
			);
		}

		$validated_columns = (array) $columns;

		if ( empty( $validated_columns ) ) {
			throw new Schema_Exception(
				sprintf(
					'Foreign key "%s" must reference at least one column.',
					$this->name
				)
			);
		}

		foreach ( $validated_columns as $column ) {
			if ( ! Util::valid_sql_identifier( $column ) ) {
				throw new Schema_Exception(
					sprintf(
						'The referenced column name "%s" for foreign key "%s" is invalid.',
						is_string( $column ) ? $column : 'NOT_A_STRING',
						$this->name
					)
				);
			}
		}

		$this->references_table   = $table;
		$this->references_columns = $validated_columns;

		return $this;
	}

	/**
	 * Sets the action for ON DELETE.
	 *
	 * @param string $action The action (e.g., 'CASCADE', 'SET NULL').
	 * @phpstan-param Foreign_Key_Action::* $action
	 *
	 * @return self The current instance for fluent method chaining.
	 *
	 * @throws Schema_Exception When an invalid action is provided.
	 */
	public function on_delete( string $action ): self {
		$action = strtoupper( trim( $action ) );
		if ( ! in_array( $action, Foreign_Key_Action::get_all(), true ) ) {
			throw new Schema_Exception(
				sprintf(
					'Invalid ON DELETE action "%s" set on foreign key "%s". ' .
					'Valid actions are: "%s".',
					$action,
					$this->name,
					implode( '", "', Foreign_Key_Action::get_all() )
				)
			);
		}

		/** @phpstan-var Foreign_Key_Action::* $action */
		$this->on_delete = $action;
		return $this;
	}

	/**
	 * Sets the action for ON UPDATE.
	 *
	 * @param string $action The action (e.g., 'CASCADE', 'SET NULL').
	 * @phpstan-param Foreign_Key_Action::* $action
	 *
	 * @return self The current instance for fluent method chaining.
	 *
	 * @throws Schema_Exception When an invalid action is provided.
	 */
	public function on_update( string $action ): self {
		$action = strtoupper( trim( $action ) );
		if ( ! in_array( $action, Foreign_Key_Action::get_all(), true ) ) {
			throw new Schema_Exception(
				sprintf(
					'Invalid ON UPDATE action "%s" set on foreign key "%s". ' .
					'Valid actions are: "%s".',
					$action,
					$this->name,
					implode( '", "', Foreign_Key_Action::get_all() )
				)
			);
		}

		/** @phpstan-var Foreign_Key_Action::* $action */
		$this->on_update = $action;
		return $this;
	}

	/**
	 * Shorthand for CASCADE on both DELETE and UPDATE.
	 *
	 * @return self The current instance for fluent method chaining.
	 */
	public function cascade(): self {
		$this->on_delete( Foreign_Key_Action::CASCADE );
		$this->on_update( Foreign_Key_Action::CASCADE );
		return $this;
	}

	/**
	 * Shorthand for SET NULL on DELETE.
	 *
	 * Note: The local column(s) must be nullable for this to work.
	 *
	 * @return self The current instance for fluent method chaining.
	 */
	public function nullify_on_delete(): self {
		$this->on_delete( Foreign_Key_Action::SET_NULL );
		return $this;
	}

	/**
	 * Generates the SQL fragment for this foreign key constraint.
	 *
	 * @return string The SQL fragment for the CREATE TABLE statement.
	 * @phpstan-return non-empty-string
	 *
	 * @throws Schema_Exception If the foreign key definition is incomplete or invalid.
	 */
	public function to_sql(): string {
		if ( ! isset( $this->references_table, $this->references_columns ) ) {
			throw new Schema_Exception(
				sprintf(
					'Foreign key "%s" is incomplete. Use the references() method to' .
					' specify the referenced table and column(s).',
					$this->name
				)
			);
		}

		if ( count( $this->columns ) !== count( $this->references_columns ) ) {
			throw new Schema_Exception(
				sprintf(
					'The number of local columns (%d) in foreign key "%s" does not match' .
					' the number of referenced columns (%d).',
					count( $this->columns ),
					$this->name,
					count( $this->references_columns )
				)
			);
		}

		$local_columns_sql = '`' . implode( '`, `', $this->columns ) . '`';
		$ref_columns_sql   = '`' . implode( '`, `', $this->references_columns ) . '`';

		$sql = sprintf(
			'CONSTRAINT `%s` FOREIGN KEY (%s) REFERENCES `%s` (%s)',
			$this->name,
			$local_columns_sql,
			$this->references_table,
			$ref_columns_sql
		);

		if ( Foreign_Key_Action::RESTRICT !== $this->on_delete ) {
			$sql .= " ON DELETE {$this->on_delete}";
		}
		if ( Foreign_Key_Action::RESTRICT !== $this->on_update ) {
			$sql .= " ON UPDATE {$this->on_update}";
		}

		return $sql;
	}

	/**
	 * Gets the local column names.
	 *
	 * @return array The column names.
	 *
	 * @phpstan-return list<non-empty-string>
	 */
	public function get_columns(): array {
		return $this->columns;
	}

	/**
	 * Gets the constraint name.
	 *
	 * @return string The constraint name.
	 *
	 * @phpstan-return non-empty-string
	 */
	public function get_name(): string {
		return $this->name;
	}
}
