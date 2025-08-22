<?php
/**
 * Foreign key actions constants
 *
 * @package WPTechnix\WP_Tables_Schema\Constants
 */

declare(strict_types=1);

namespace WPTechnix\WP_Tables_Schema\Constants;

/**
 * Foreign key actions constants
 */
final class Foreign_Key_Action {

	public const CASCADE   = 'CASCADE';
	public const SET_NULL  = 'SET NULL';
	public const RESTRICT  = 'RESTRICT';
	public const NO_ACTION = 'NO ACTION';

	/**
	 * Private constructor to prevent instantiation.
	 */
	private function __construct() {
	}

	/**
	 * Get all actions.
	 *
	 * @return string[]
	 *
	 * @phpstan-return list<self::*>
	 */
	public static function get_all(): array {
		return [
			self::CASCADE,
			self::SET_NULL,
			self::RESTRICT,
			self::NO_ACTION,
		];
	}
}
