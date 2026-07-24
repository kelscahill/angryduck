<?php
/**
 * Integration instance objects.
 *
 * This file is required on `setup_theme` (see bootstrap/App.php) so every
 * dependency check below runs after all plugins have loaded. Each third-party
 * integration is only instantiated when its dependency is active, avoiding the
 * per-request cost of autoloading and running integration classes for plugins
 * that aren't installed. WC and WWP are hard dependencies, and WWPP is part of
 * the core Wholesale Suite stack, so those stay unconditional. See #990.
 *
 * @package RymeraWebCo\WWOF
 */

use RymeraWebCo\WWOF\Helpers\Aelia as Aelia_Helper;
use RymeraWebCo\WWOF\Helpers\Product_Addons as Product_Addons_Helper;
use RymeraWebCo\WWOF\Helpers\Product_Bundles as Product_Bundles_Helper;
use RymeraWebCo\WWOF\Helpers\Site_Ground_Optimizer as Site_Ground_Optimizer_Helper;
use RymeraWebCo\WWOF\Helpers\WP_Rocket as WP_Rocket_Helper;
use RymeraWebCo\WWOF\Helpers\WPML as WPML_Helper;
use RymeraWebCo\WWOF\Integrations\Aelia;
use RymeraWebCo\WWOF\Integrations\FunnelKit_Cart;
use RymeraWebCo\WWOF\Integrations\Site_Ground_Optimizer;
use RymeraWebCo\WWOF\Integrations\WC;
use RymeraWebCo\WWOF\Integrations\WP_Rocket;
use RymeraWebCo\WWOF\Integrations\WPML;
use RymeraWebCo\WWOF\Integrations\WWP;
use RymeraWebCo\WWOF\Integrations\WWPP;
use RymeraWebCo\WWOF\Integrations\Product_Addons;
use RymeraWebCo\WWOF\Integrations\Product_Bundles;
use RymeraWebCo\WWOF\Integrations\SaveTo_Wishlist;

defined( 'ABSPATH' ) || exit;

// Always-on integrations: WC and WWP are required dependencies, WWPP is part of
// the core Wholesale Suite stack and self-guards cheaply in its own run().
$integrations = array(
    new WC(),
    new WWP(),
    new WWPP(),
);

// Third-party integrations: instantiate only when the dependency is detected as
// active, using the same is_active() guard each integration's run() already
// applies. FunnelKit Cart has no helper, so mirror the class_exists() check
// from its run() flow.
if ( WPML_Helper::is_active() ) {
    $integrations[] = new WPML();
}

if ( Aelia_Helper::is_active() ) {
    $integrations[] = new Aelia();
}

if ( Site_Ground_Optimizer_Helper::is_active() ) {
    $integrations[] = new Site_Ground_Optimizer();
}

if ( WP_Rocket_Helper::is_active() ) {
    $integrations[] = new WP_Rocket();
}

if ( Product_Addons_Helper::is_active() ) {
    $integrations[] = new Product_Addons();
}

if ( Product_Bundles_Helper::is_active() ) {
    $integrations[] = new Product_Bundles();
}

if ( class_exists( \FKCart\Includes\Front::class ) ) {
    $integrations[] = new FunnelKit_Cart();
}

// SaveTo Wishlist Lite owns the button, assets and AJAX; Pro requires Lite. No
// helper exists, so mirror the defined( 'STWLITE_VERSION' ) guard from
// SaveTo_Wishlist::run().
if ( defined( 'STWLITE_VERSION' ) ) {
    $integrations[] = new SaveTo_Wishlist();
}

return $integrations;
