<?php

$_tests_lib_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-suite/wordpress-tests-lib';

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
// We are using vendor folder but vendor-prefixed which doesn't include the PHPUnit Polyfills.

if (!file_exists("{$_tests_lib_dir}/includes/functions.php")) {
    echo "Could not find {$_tests_lib_dir}/includes/functions.php, have you run bin/install-wp-tests ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit(1);
}

// Give access to tests_add_filter() function.
require_once "{$_tests_lib_dir}/includes/functions.php";

tests_add_filter('muplugins_loaded', function () {
    require dirname(__FILE__, 2) . '/vendor/autoload.php';
});

// Start up the WP testing environment.
require "{$_tests_lib_dir}/includes/bootstrap.php";
