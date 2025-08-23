<?php
/**
 * Abstract Meta Table Class.
 *
 * @package WPTechnix\WP_Tables_Schema
 */

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema;

use wpdb;
use WPTechnix\WP_Tables_Schema\Schema\Create_Table_Schema;
use WPTechnix\WP_Tables_Schema\Interfaces\Table_Interface;
use WPTechnix\WP_Tables_Schema\Constants\Foreign_Key_Action;

/**
 * Abstract Meta Table Class.
 */
abstract class Meta_Table extends Table {

	/**
	 * Schema Version
	 *
	 * @var int
	 * @phpstan-var positive-int
	 */
	protected int $schema_version = 10001;

	/**
	 * Primary key column
	 *
	 * @var string
	 * @phpstan-var non-empty-string
	 */
	protected string $primary_key_column = 'meta_id';

	/**
	 * Foreign column name.
	 *
	 * @var string
	 * @phpstan-var non-empty-string
	 */
	protected string $foreign_column_name;

	/**
	 * The action to perform when parent row deleted having child
	 * rows in meta table.
	 *
	 * @var null|string
	 * @phpstan-var null|Foreign_Key_Action::*
	 */
	protected ?string $on_delete_action = Foreign_Key_Action::RESTRICT;

	/**
	 * Meta Table Constructor.
	 *
	 * @param Table_Interface $parent_table Parent Table.
	 * @param wpdb            $wpdb WP Database object.
	 * @param string|null     $plugin_prefix Plugin Prefix.
	 *
	 * @phpstan-param non-empty-string|null $plugin_prefix
	 */
	public function __construct(
		protected Table_Interface $parent_table,
		wpdb $wpdb,
		?string $plugin_prefix = null
	) {

		$this->table_singular_name = $this->get_table_singular_name() . 'meta';
		$this->table_name          = $this->table_singular_name;

		parent::__construct( $wpdb, $plugin_prefix );

		$this->foreign_column_name = $this->parent_table->get_table_singular_name() . '_id';

		$this->wpdb->{$this->get_table_singular_name() . 'meta'} = $this->get_table_name();
	}

	/**
	 * Create Meta Table.
	 */
	protected function migrate_to_10001(): bool {
		return $this->create_table(
			function ( Create_Table_Schema $schema ) {

				$schema->id( $this->get_primary_key() );

				$schema
				->big_integer( $this->foreign_column_name )
				->unsigned()
				->index( $this->foreign_column_name );

				// Add the standard meta_key and meta_value columns.
				$schema->string( 'meta_key', 191 )->index( 'meta_key' );
				$schema->long_text( 'meta_value' )->nullable();

				// Add a foreign key constraint to the parent table for data integrity.
				$fk = $schema->add_foreign_key( $this->foreign_column_name )
						->references(
							$this->parent_table->get_table_name(),
							$this->parent_table->get_primary_key()
						);

				if ( isset( $this->on_delete_action ) ) {
					$fk->on_delete( $this->on_delete_action );
				}

				return $schema;
			}
		);
	}
}
