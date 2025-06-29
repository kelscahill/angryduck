<?php
/**
 * Plugin Name:          WooCommerce Wholesale Payments
 * Requires Plugins:     woocommerce, woocommerce-wholesale-prices
 * Plugin URI:           https://wholesalesuiteplugin.com/woocommerce-wholesale-payments/
 * Update URI:           wholesalesuiteplugin.com
 * Description:          WooCommerce extension that lets store owners provide payment plans for orders right within their wholesale store.
 * Version:              1.0.4
 * Author:               Rymera Web Co
 * Author URI:           https://rymera.com.au/
 * License: GPL          v2 or later
 * Text Domain:          woocommerce-wholesale-payments
 * Domain Path:          /languages
 * Requires at least:    5.9
 * Tested up to:         6.8
 * WC requires at least: 5.9
 * WC tested up to:      9.9
 *
 * @package  RymeraWebCo\WPay
 * @author   Rymera Web Co <josh@rymera.com.au>
 * @license  GPL v2 or later
 * @link     https://rymera.com.au/
 */

/***************************************************************************
 * Main plugin file
 * **************************************************************************
 *
 * This file is the main entry point for the plugin. It is responsible for
 * loading the plugin's dependencies and initializing the plugin.
 */

/***************************************************************************
 * Ensure that the plugin is not accessed or called directly
 * **************************************************************************
 */

defined( 'ABSPATH' ) || exit;

/***************************************************************************
 * Plugin Constants
 * **************************************************************************
 */

/**
 * Plugin file path.
 */
define( 'WPAY_PLUGIN_FILE', __FILE__ );

/**
 * Plugin version key.
 */
define( 'WPAY_INSTALLED_VERSION', 'wpay_installed_version' );

/**
 * Plugin directory path.
 */
define( 'WPAY_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );

if ( ! defined( 'WPAY_ENABLE_SUBRESOURCE_INTEGRITY_CHECK' ) ) {
    /**
     * Enable subresource integrity check by default.
     */
    define( 'WPAY_ENABLE_SUBRESOURCE_INTEGRITY_CHECK', true );
}

if ( ! defined( 'WWS_SLMW_SERVER_URL' ) ) {
    define( 'WWS_SLMW_SERVER_URL', 'https://wholesalesuiteplugin.com' );
}

/***************************************************************************
 * Ensures get_plugin_data() is available.
 ***************************************************************************
 *
 * Function get_plugin_data() is not available in the front-end but
 * we are calling it there.
 */
if ( ! function_exists( 'get_plugin_data' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

/***************************************************************************
 * Loads plugin text domain.
 * **************************************************************************
 *
 * Loads the plugin text domain for translation.
 */
function wpay_textdomain() {

    load_plugin_textdomain(
        'woocommerce-wholesale-payments',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}
add_action( 'init', 'wpay_textdomain' );

/***************************************************************************
 * Checks required minimum PHP version to run the plugin.
 ***************************************************************************
 *
 * Checks the required minimum PHP version to run the plugin and prints
 * admin notice for admins if the PHP version is not met.
 */
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    /**
     * Admin notice for required PHP version to run the plugin
     */
    function wpay_required_php_version() {

        include WPAY_PLUGIN_DIR_PATH . 'templates/admin/parts/require-php-version.php';
    }

    add_action( 'admin_notices', 'wpay_required_php_version' );
} else {

    /**
     * Load the functions.
     */
    require_once WPAY_PLUGIN_DIR_PATH . 'includes/functions.php';

    /***************************************************************************
     * Loads the plugin.
     ***************************************************************************
     *
     * Here we load the plugin if all checks passed.
     */

    /**
     * Our bootstrap class instance.
     *
     * @var RymeraWebCo\WPay\App $app
     */
    $app = require_once 'bootstrap/App.php';

    $app->boot();
}
