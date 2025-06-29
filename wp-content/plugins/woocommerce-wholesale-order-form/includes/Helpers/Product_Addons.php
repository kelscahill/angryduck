<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Helpers
 */

namespace RymeraWebCo\WWOF\Helpers;

use WWP_Wholesale_Prices;

/**
 * Product_Addons class.
 *
 * @since 3.0.2
 */
class Product_Addons {

    /**
     * Check if SiteGround Optimizer is active.
     *
     * @since 3.0.2
     * @return bool
     */
    public static function is_active() {
        return is_plugin_active( 'woocommerce-product-addons/woocommerce-product-addons.php' );
    }

	/**
	 * Get product add-ons display html.
	 *
	 * @param WC_Product $product The product object.
     *
     * @since 3.0.2
     * @return string
	 */
    public static function get_product_addons_html( $product ) {
        global $Product_Addon_Display, $wc_wholesale_prices; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
        $GLOBALS['product'] = $product;
        $product_id         = $product->get_id();

        ob_start();
        $Product_Addon_Display->display( $product_id ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
        $product_addons_html = ob_get_clean();

        if ( empty( $product_addons_html ) ) {
            return '';
        }

        $user_wholesale_role = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();

        if ( ! empty( $user_wholesale_role ) ) {
            $price_arr       = WWP_Wholesale_Prices::get_product_wholesale_price_on_shop_v3( $product_id, $user_wholesale_role );
            $wholesale_price = $price_arr['wholesale_price'];

            $html = new \DOMDocument( '1.0', 'UTF-8' );
            $html->loadHTML( $product_addons_html );
            $xpath = new \DOMXPath( $html );
            $price = $xpath->query( "//div[contains(@id, 'product-addons-total')]" );
            if ( $price->length > 0 ) {
                $price = $price->item( 0 );
                $price->setAttribute( 'data-price', $wholesale_price );
                $price->setAttribute( 'data-raw-price', $wholesale_price );
            }
            $product_addons_html = $html->saveHTML();
        }

        return $product_addons_html;
    }
}
