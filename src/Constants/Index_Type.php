<?php
/**
 * Index Type Constants
 *
 * @package WPTechnix\WP_Tables_Schema\Constants
 * @author  WPTechnix <developers@wptechnix.com>
 */

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Constants;

/**
 * Index Type Constants
 *
 * @package WPTechnix\WP_Tables_Schema\Constants
 *
 * @phpstan-type Index_Types_Excluding_Primary Index_Type::INDEX | Index_Type::UNIQUE | Index_Type::FULLTEXT | Index_Type::SPATIAL
 */
final class Index_Type {

	public const INDEX    = 'INDEX';
	public const PRIMARY  = 'PRIMARY';
	public const UNIQUE   = 'UNIQUE';
	public const FULLTEXT = 'FULLTEXT';
	public const SPATIAL  = 'SPATIAL';

	/**
	 * Private constructor to prevent instantiation.
	 */
	private function __construct() {
	}

	/**
	 * Retrieves a list of all defined index types.
	 *
	 * @param bool $include_primary If true, the PRIMARY type will be included in the list.
	 *
	 * @return array A list of index type constants.
	 *
	 * @phpstan-return ( $include_primary is true ? list<self::*> : list<Index_Types_Excluding_Primary> )
	 */
	public static function get_all( bool $include_primary = true ): array {
		return [
			self::INDEX,
			self::UNIQUE,
			self::FULLTEXT,
			self::SPATIAL,
			...( $include_primary ? [ self::PRIMARY ] : [] ),
		];
	}
}
