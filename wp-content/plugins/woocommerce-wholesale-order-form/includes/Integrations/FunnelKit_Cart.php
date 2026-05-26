<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Integrations
 */

namespace RymeraWebCo\WWOF\Integrations;

use FKCart\Includes\Data as FKCart_Data;
use FKCart\Includes\Front as FKCart_Front;
use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Helpers\WWOF;

/**
 * FunnelKit Cart integration class.
 *
 * @since 3.0.8
 */
class FunnelKit_Cart extends Abstract_Class {

    /**
     * Check if "Auto Open Side Cart" setting is enabled in FunnelKit Cart.
     *
     * @since 3.0.8
     * @return bool
     */
    private function is_auto_open_enabled() {

        if ( ! class_exists( FKCart_Data::class ) ) {
            return false;
        }

        $auto_open_cart = FKCart_Data::get_value( 'enable_auto_open_cart' );

        return wc_string_to_bool( $auto_open_cart );
    }

    /**
     * Enqueue FunnelKit Cart integration script.
     *
     * Hooks into WWOF script enqueue to enqueue the integration script.
     *
     * @since 3.0.8
     * @return void
     */
    public function enqueue_fkcart_script() {

        if ( ! $this->is_auto_open_enabled() ) {
            return;
        }

        $script_url  = WWOF_PLUGIN_DIR_URL . 'static/js/wwof-funnelkit-cart.js';
        $script_path = WWOF_PLUGIN_DIR_PATH . 'static/js/wwof-funnelkit-cart.js';
        $version     = file_exists( $script_path ) ? filemtime( $script_path ) : WWOF::get_plugin_data()['Version'];

        wp_enqueue_script(
            'wwof-funnelkit-cart',
            $script_url,
            array(),
            $version,
            true
        );
    }

    /**
     * Hook into WooCommerce add to cart action to trigger FunnelKit Cart.
     *
     * This ensures that even if WWOF uses the standard WooCommerce add to cart
     * mechanism, the FunnelKit Cart will still open.
     *
     * @param string $cart_item_key The cart item key.
     * @param int    $product_id    The product ID.
     * @param int    $quantity      The quantity added.
     * @param int    $variation_id  The variation ID (if applicable).
     *
     * @since 3.0.8
     * @return void
     */
    public function add_to_cart_trigger( $cart_item_key, $product_id, $quantity, $variation_id = 0 ) {

        // Check if auto open is enabled.
        if ( ! $this->is_auto_open_enabled() ) {
            return;
        }

        // Check if this is coming from WWOF by checking the REST API endpoint.
        $is_wwof_request = false;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Flag check only, not processing data.
        if ( isset( $_POST['wwof_add_to_cart'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['wwof_add_to_cart'] ) ) ) {
            $is_wwof_request = true;
        }

        if ( ! $is_wwof_request && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
            if ( strpos( $request_uri, 'wc/store/cart/add-item' ) !== false || strpos( $request_uri, 'wc/store/v1/batch' ) !== false ) {
                $is_wwof_request = true;
            }
        }

        // If it's a WWOF request, ensure FunnelKit Cart trigger is set.
        if ( $is_wwof_request && class_exists( FKCart_Front::class ) ) {
            $front_instance = FKCart_Front::get_instance();
            if ( method_exists( $front_instance, 'add_to_cart_trigger' ) ) {
                $front_instance->add_to_cart_trigger();
            }
        }
    }

    /**
     * Register FunnelKit Cart integration hooks.
     *
     * Called on 'init' hook to ensure all dependencies are loaded.
     *
     * @since 3.0.8
     * @return void
     */
    public function funnel_cart_hooks() {

        // Check if FunnelKit Cart is active.
        if ( ! class_exists( FKCart_Front::class ) ) {
            return;
        }

        // Check if auto open is enabled.
        if ( ! $this->is_auto_open_enabled() ) {
            return;
        }

        // Hook into WWOF script enqueue to add footer script.
        add_action( 'wwof_enqueue_wwof_product_listing_sc_script', array( $this, 'enqueue_fkcart_script' ), 10 );

        // Hook into WooCommerce add to cart action.
        add_action( 'woocommerce_add_to_cart', array( $this, 'add_to_cart_trigger' ), 10, 4 );
    }

    /**
     * Run the FunnelKit Cart integration hooks.
     *
     * @since 3.0.8
     * @return void
     */
    public function run() {
        add_action( 'init', array( $this, 'funnel_cart_hooks' ) );
    }
}
