<?php
/**
 * Plugin Name: WooCommerce Shipping
 * Plugin URI: https://woocommerce.com/products/shipping/
 * Description: Save time and money with WooCommerce Shipping. Print discounted shipping labels with just a few clicks from your WooCommerce dashboard.
 * Author: WooCommerce
 * Author URI: https://woocommerce.com/
 * Text Domain: woocommerce-shipping
 * Domain Path: /languages/
 * Version: 1.8.0
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.7
 * Tested up to: 6.8.1
 * WC requires at least: 9.8
 * WC tested up to: 10.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Copyright (c) 2017-2024 Automattic
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package Automattic\WCShipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCSHIPPING_VERSION', '1.8.0' ); // WRCS: DEFINED_VERSION.
define( 'WCSHIPPING_PLUGIN_FILE', __FILE__ );
define( 'WCSHIPPING_PLUGIN_DIR', __DIR__ );
define( 'WCSHIPPING_PLUGIN_DIST_DIR', WCSHIPPING_PLUGIN_DIR . '/dist/' );
define( 'WCSHIPPING_PLUGIN_URL', plugin_dir_url( WCSHIPPING_PLUGIN_FILE ) );
define( 'WCSHIPPING_PLUGIN_DIST_URL', plugin_dir_url( WCSHIPPING_PLUGIN_FILE ) . 'dist/' );
define( 'WCSHIPPING_ASSETS_URL', WCSHIPPING_PLUGIN_URL . 'assets/' );
define( 'WCSHIPPING_STYLESHEETS_URL', WCSHIPPING_ASSETS_URL . 'stylesheets/' );
define( 'WCSHIPPING_JAVASCRIPT_URL', WCSHIPPING_ASSETS_URL . 'javascript/' );
define( 'WCSHIPPING_ASSETS_DIR', WCSHIPPING_PLUGIN_DIR . '/assets/' );
define( 'WCSHIPPING_STYLESHEETS_DIR', WCSHIPPING_ASSETS_DIR . 'stylesheets/' );
define( 'WCSHIPPING_JAVASCRIPT_DIR', WCSHIPPING_ASSETS_URL . 'javascript/' );

// Load autoloader.
require_once __DIR__ . '/src/Autoloader.php';
if ( ! \Automattic\WCShipping\Autoloader::init() ) {
	return;
}

require_once __DIR__ . '/classes/class-wc-connect-extension-compatibility.php';
require_once __DIR__ . '/classes/class-wc-connect-functions.php';
require_once __DIR__ . '/classes/class-wc-connect-jetpack.php';
require_once __DIR__ . '/classes/class-wc-connect-options.php';
require_once __DIR__ . '/classes/class-wc-connect-options.php';
require_once __DIR__ . '/classes/class-wc-connect-package-settings.php';

use Automattic\WCShipping\Loader;

// Check for CI environment variable to trigger test mode.
if ( false !== getenv( 'WOOCOMMERCE_SERVICES_CI_TEST_MODE' ) ) {
	if ( ! defined( 'WOOCOMMERCE_SERVICES_LOCAL_TEST_MODE' ) ) {
		define( 'WOOCOMMERCE_SERVICES_LOCAL_TEST_MODE', true );
	}
	if ( ! defined( 'JETPACK_DEV_DEBUG' ) ) {
		define( 'JETPACK_DEV_DEBUG', true );
	}
}

if ( ! defined( 'WC_UNIT_TESTING' ) ) {
	new Automattic\WCShipping\Loader();
}

register_deactivation_hook( __FILE__, array( Loader::class, 'plugin_deactivation' ) );
register_activation_hook( __FILE__, array( Loader::class, 'plugin_activation' ) );
register_uninstall_hook( __FILE__, array( Loader::class, 'plugin_uninstall' ) );
add_action( 'plugins_loaded', array( Loader::class, 'maybe_plugin_updated' ) );
