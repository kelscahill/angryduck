<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Helpers
 */

namespace RymeraWebCo\WWOF\Helpers;

/**
 * WC Helper class.
 *
 * @since 3.0
 */
class WC {

    /**
     * Get WooCommerce settings used in the app script.
     *
     * @since 3.0
     * @return array[]
     */
    public static function get_script_l10n() {

        $login_url = ( WWLC::is_active()
            ? wwlc_get_url_of_page_option( 'wwlc_general_login_page' )
            : ( get_permalink( wc_get_page_id( 'myaccount' ) )
                ? get_permalink( wc_get_page_id( 'myaccount' ) )
                : wp_login_url() )
        );

        return array(
            /**
             * Filter the login URL used in the app script.
             *
             * @param string $login_url The login URL.
             *
             * @since 3.0
             */
            'loginUrl'   => apply_filters( 'wwof_login_url', $login_url ),
            'wcSettings' => array(
                'cartUrl'            => wc_get_cart_url(),
                'lowStockAmount'     => get_option( 'woocommerce_notify_low_stock_amount' ),
                'stockFormat'        => get_option( 'woocommerce_stock_format' ),
                'priceDisplaySuffix' => get_option( 'woocommerce_price_display_suffix' )
                    ? '<small class="woocommerce-price-suffix">' . get_option( 'woocommerce_price_display_suffix' ) . '</small>'
                    : '',
                /**
                 * Filter the default catalog order by used in the app script.
                 *
                 * @param string $orderBy The default catalog order by.
                 *
                 * @since 3.0
                 */
                'orderBy'            => apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby', 'menu_order' ) ),
                'currency'           => array(
                    'code'              => get_woocommerce_currency(),
                    'symbol'            => get_woocommerce_currency_symbol(),
                    'position'          => get_option( 'woocommerce_currency_pos' ),
                    'decimalSeparator'  => wc_get_price_decimal_separator(),
                    'thousandSeparator' => wc_get_price_thousand_separator(),
                    'numDecimals'       => wc_get_price_decimals(),
                ),
                'taxDisplayMode'     => array(
                    'shop' => get_option( 'woocommerce_tax_display_shop' ),
                    'cart' => get_option( 'woocommerce_tax_display_cart' ),
                ),
            ),
        );
    }

    /**
     * Add tax query.
     *
     * @param array $args      Query args.
     * @param array $tax_query Tax query.
     *
     * @since 3.0
     * @return array|mixed
     */
    public static function add_tax_query( $args, $tax_query ) {

        if ( empty( $args['tax_query'] ) ) {
            $args['tax_query'] = array();
        }

        $args['tax_query'][] = $tax_query;

        return $args['tax_query'];
    }

    /**
     * Add meta query.
     *
     * @param array $args       Query args.
     * @param array $meta_query Meta query.
     *
     * @since 3.0
     * @return array
     */
    public static function add_meta_query( $args, $meta_query ) {

        if ( empty( $args['meta_query'] ) ) {
            $args['meta_query'] = array();
        }

        $args['meta_query'][] = $meta_query;

        return $args['meta_query'];
    }
}
