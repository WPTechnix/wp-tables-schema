<?php
/**
 * Index Algorithm Constants
 *
 * @package WPTechnix\WP_Tables_Schema\Constants
 */

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Constants;

/**
 * Index Algorithm Constants
 *
 * @package WPTechnix\WP_Tables_Schema\Constants
 */
final class Index_Algorithm {

	public const BTREE = 'BTREE';
	public const HASH  = 'HASH';

	/**
	 * Private constructor to prevent instantiation.
	 */
	private function __construct() {
	}

	/**
	 * Get all index algorithms.
	 *
	 * @return string[]
	 */
	public static function get_all(): array {
		return [
			self::BTREE,
			self::HASH,
		];
	}
}
