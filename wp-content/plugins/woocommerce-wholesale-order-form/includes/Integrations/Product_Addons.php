<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Integrations
 */

namespace RymeraWebCo\WWOF\Integrations;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Helpers\Product_Addons as PAO_Helper;
use WC_Product_Addons_Helper;

/**
 * Product_Addons class.
 *
 * @since 3.0.2
 */
class Product_Addons extends Abstract_Class {

    /**
     * Add product addons data to the REST API response.
     *
     * @param WP_REST_Response $response The response object.
     * @param WC_Data          $wc_data  The WC_Data object.
     * @param WP_REST_Request  $request  Request used to generate the response.
     *
     * @since 3.0.2
     * @return mixed
     */
    public function rest_product_addons_data_object( $response, $wc_data, $request ) {

        $product_id     = $wc_data->get_id();
        $product_addons = WC_Product_Addons_Helper::get_product_addons( $product_id );

        if ( is_array( $product_addons ) && count( $product_addons ) > 0 ) {
            $response->data['wwof_product_addons']['data'] = $product_addons;
            $response->data['wwof_product_addons']['html'] = PAO_Helper::get_product_addons_html( $wc_data );

            if ( 'variable' === $wc_data->get_type() ) {

                $variations_data = $wc_data->get_available_variations();
                foreach ( $variations_data as $key => $variation ) {
                    if ( isset( $variations_data[ $key ]['wholesale_price'] ) ) {
                        $variations_data[ $key ]['display_price'] = $variations_data[ $key ]['wholesale_price'];
                    }
                }

                $response->data['wwof_product_addons']['variations_data'] = $variations_data;
            }
        }

        return $response;
    }

    /**
     * Add additional localization properties for WooCommerce Product Addons.
     *
     * @param array $defaults The default localization properties.
     *
     * @since 3.0.2
     * @return array
     */
    public function wwof_app_common_l10n( $defaults ) {
        array_push( $defaults['thirdPartyPlugins'], 'woocommerce-product-addons' );
        array_push( $defaults['_fields'], 'wwof_product_addons' );
        return $defaults;
    }

    /**
     * Load WooCommerce Product Addons scripts & styles.
     *
     * @since 3.0.2
     */
    public function load_product_addons_scripts() {
        wp_enqueue_style( 'woocommerce-addons-css', WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/css/frontend/frontend.css', array( 'dashicons' ), WC_PRODUCT_ADDONS_VERSION );
        wp_enqueue_script( 'jquery-tiptip', WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip.min.js', array( 'jquery' ), WC_VERSION, true );
	}

    /**
     * Load WooCommerce Product Addons scripts & styles on admin form editor.
     *
     * @since 3.0.2
     */
    public function load_product_addons_admin_scripts() {
        global $Product_Addon_Display; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
        wp_register_script( 'wc-add-to-cart-variation', WC()->plugin_url() . '/assets/js/frontend/add-to-cart-variation' . $suffix . '.js', array( 'jquery' ), WC_VERSION ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter

        $Product_Addon_Display->addon_scripts(); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

        self::load_product_addons_scripts();
	}

    /**
     * Run the WooCommerce Product Addons integration hooks
     */
    public function run() {

        if ( ! PAO_Helper::is_active() ) {
            return;
        }

        add_filter( 'wwof_wc_rest_prepare_product_object', array( $this, 'rest_product_addons_data_object' ), 10, 3 );
        add_filter( 'wwof_order_form_app_common_l10n_defaults', array( $this, 'wwof_app_common_l10n' ), 10 );
        add_action( 'wwof_enqueue_wwof_product_listing_sc_script', array( $this, 'load_product_addons_scripts' ), 10 );
        add_action( 'wwof_enqueue_admin_order_forms_sc_script', array( $this, 'load_product_addons_admin_scripts' ), 10 );
    }
}
