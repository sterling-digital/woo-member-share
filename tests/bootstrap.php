<?php
/**
 * PHPUnit Bootstrap for Woo Member Share
 * 
 * @package WooMemberShare
 */

// Define testing environment
define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', __DIR__ . '/../vendor/yoast/phpunit-polyfills');

// Load WordPress test environment
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

// Include WordPress test framework
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    // Load WooCommerce (required dependency)
    if (file_exists(WP_PLUGIN_DIR . '/woocommerce/woocommerce.php')) {
        require_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
    }
    
    // Load WooCommerce Memberships (required dependency)
    if (file_exists(WP_PLUGIN_DIR . '/woocommerce-memberships/woocommerce-memberships.php')) {
        require_once WP_PLUGIN_DIR . '/woocommerce-memberships/woocommerce-memberships.php';
    }
    
    // Load our plugin
    require dirname(dirname(__FILE__)) . '/woo-member-share.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Include our test helper classes
require_once __DIR__ . '/helpers/class-test-helper.php';
