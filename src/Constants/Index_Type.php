<?php
/**
 * Index Type Constants.
 *
 * @package WPTechnix\WP_Tables_Schema\Constants
 */

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Constants;

/**
 * Index Type Constants.
 *
 * @phpstan-type Index_Types_Excluding_Primary Index_Type::UNIQUE | Index_Type::INDEX | Index_Type::FULLTEXT | Index_Type::SPATIAL
 * @psalm-type Index_Types_Excluding_Primary Index_Type::UNIQUE | Index_Type::INDEX | Index_Type::FULLTEXT | Index_Type::SPATIAL
 */
final class Index_Type {

	public const PRIMARY  = 'PRIMARY';
	public const UNIQUE   = 'UNIQUE';
	public const INDEX    = 'INDEX';
	public const FULLTEXT = 'FULLTEXT';
	public const SPATIAL  = 'SPATIAL';

	/**
	 * Retrieves a list of all defined index types.
	 *
	 * @param bool $include_primary If true, the PRIMARY type will be included in the list.
	 *
	 * @return array A list of index type constants.
	 *
	 * @phpstan-return ( $include_primary is true ? list<self::*> : list<Index_Types_Excluding_Primary> )
	 * @psalm-return ( $include_primary is true ? list<self::*> : list<Index_Types_Excluding_Primary> )
	 */
	public static function get_all( bool $include_primary = true ): array {
		return [
			...( $include_primary ? [ self::PRIMARY ] : [] ),
			self::UNIQUE,
			self::INDEX,
			self::FULLTEXT,
			self::SPATIAL,
		];
	}
}
