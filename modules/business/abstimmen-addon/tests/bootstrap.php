<?php
/**
 * PHPUnit Bootstrap File
 *
 * @package DGPTM_Abstimmen\Tests
 */

// WordPress Test Library Path
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php\n";
	echo "Please set WP_TESTS_DIR environment variable or install wordpress-tests-lib\n";
	exit( 1 );
}

// Give access to tests_add_filter()
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin for tests
 */
function _manually_load_plugin() {
	// Load required dependencies first
	if ( ! defined( 'DGPTM_ABSTIMMEN_PATH' ) ) {
		define( 'DGPTM_ABSTIMMEN_PATH', dirname( __DIR__ ) . '/' );
	}
	if ( ! defined( 'DGPTM_ABSTIMMEN_URL' ) ) {
		define( 'DGPTM_ABSTIMMEN_URL', 'http://example.org/wp-content/plugins/abstimmen-addon/' );
	}
	if ( ! defined( 'DGPTM_ABSTIMMEN_VERSION' ) ) {
		define( 'DGPTM_ABSTIMMEN_VERSION', '4.0.0' );
	}

	// Load plugin
	require dirname( __DIR__ ) . '/abstimmen-addon.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WordPress testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Load test helpers
require_once __DIR__ . '/helpers/TestHelpers.php';
