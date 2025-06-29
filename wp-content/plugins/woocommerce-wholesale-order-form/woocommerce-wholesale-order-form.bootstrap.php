<?php
/**
 * Plugin Name:          WooCommerce Wholesale Order Form
 * Requires Plugins:     woocommerce
 * Plugin URI:           https://wholesalesuiteplugin.com/
 * Update URI:           wholesalesuiteplugin.com
 * Description:          WooCommerce Extension to Provide Wholesale Product Listing Functionality
 * Author:               Rymera Web Co
 * Version:              3.0.6.1
 * Author URI:           https://rymera.com.au/
 * License: GPL          v2 or later
 * Text Domain:          woocommerce-wholesale-order-form
 * Domain Path:          /languages
 * Requires at least:    5.9
 * Tested up to:         6.7
 * WC requires at least: 4.0
 * WC tested up to:      9.8
 *
 * @package  RymeraWebCo\WWOF
 * @author   Rymera Web Co <josh@rymera.com.au>
 * @license  GPL v2 or later
 * @link     https://rymera.com.au/
 */

/***************************************************************************
 * Main plugin file
 * *************************************************************************
 *
 * This file is the main entry point for the plugin. It is responsible for
 * loading the plugin's dependencies and initializing the plugin.
 */

/***************************************************************************
 * Ensure that the plugin is not accessed or called directly
 * *************************************************************************
 *
 * Bail early if the file is accessed directly or if the WordPress constant
 * ABSPATH is not defined.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Plugin file path.
 *
 * @since 3.0
 */
define( 'WWOF_PLUGIN_FILE', __FILE__ );

/**
 * Plugin directory path.
 *
 * @since 3.0
 */
define( 'WWOF_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 *
 * @since 3.0
 */
define( 'WWOF_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'WWOF_ENABLE_SUBRESOURCE_INTEGRITY_CHECK' ) ) {
    define( 'WWOF_ENABLE_SUBRESOURCE_INTEGRITY_CHECK', false );
}

/***************************************************************************
 * Plugin constants
 * *************************************************************************
 *
 * Define additional plugin constants.
 */
require_once WWOF_PLUGIN_DIR_PATH . 'constants.php';

/***************************************************************************
 * Ensures get_plugin_data() is available.
 ***************************************************************************
 *
 * Function get_plugin_data() is not available in the front-end but, we are
 * calling it there.
 */
if ( ! function_exists( 'get_plugin_data' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

/**
 * Load plugin textdomain.
 */
function wwof_textdomain() {

    load_plugin_textdomain(
        'woocommerce-wholesale-order-form',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}

add_action( 'init', 'wwof_textdomain' );

/***************************************************************************
 * Checks required minimum PHP version to run the plugin.
 ***************************************************************************
 *
 * Checks the required minimum PHP version to run the plugin and
 * prints admin notice for admins if the PHP version is not met.
 */
if ( version_compare( PHP_VERSION, '7.3', '<' ) ) {
    /**
     * Admin notice for required PHP version to run the plugin
     *
     * @since 3.0
     */
    function wwof_required_php_version() {

        ?>
        <div class="error" id="message">
            <p>
                <?php
                printf( /* translators: %s: current server PHP version */
                    esc_html__(
                        'WooCommerce Wholesale Order Form plugin requires at least PHP 7.3 to work properly. Your server is currently using PHP %s.',
                        'woocommerce-wholesale-order-form'
                    ),
                    PHP_VERSION
                );
                ?>
            </p>
        </div>
        <?php
    }

    add_action( 'admin_notices', 'wwof_required_php_version' );
} else {

    /***************************************************************************
     * Here we load the plugin if all checks passed
     ***************************************************************************
     *
     * Our bootstrap class instance.
     *
     * @var RymeraWebCo\WWOF\App $app
     */
    $app = require_once 'bootstrap/App.php';

    $app->boot();
}
