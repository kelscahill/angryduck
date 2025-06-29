<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Helpers
 */

namespace RymeraWebCo\WWOF\Helpers;

use WWPP_Helper_Functions;

/**
 * WWPP class.
 *
 * @since 3.0
 */
class WWPP {

    /**
     * Cache group for this helper class.
     */
    const CACHE_GROUP = 'wwpp';

    /**
     * Path to WWPP main plugin file.
     *
     * @since 3.0
     */
    const WWPP_FILE_BASENAME = 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php';

    /**
     * Absolute file path to WWPP main plugin file.
     *
     * @since 3.0
     */
    const WWPP_FILE = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . self::WWPP_FILE_BASENAME;

    /**
     * Get WWPP data from its plugin file header.
     *
     * @param bool $force Force the data to be refreshed.
     *
     * @since 3.0
     * @return array|bool WWPP data or false if not found.
     */
    public static function get_wwpp_data( $force = false ) {

        $wwpp_data = wp_cache_get( 'wwpp_data', self::CACHE_GROUP );
        if ( false === $wwpp_data || $force ) {
            if ( file_exists( self::WWPP_FILE ) ) {
                $wwpp_data = get_plugin_data( self::WWPP_FILE, false, false );

                wp_cache_set( 'wwpp_data', $wwpp_data, self::CACHE_GROUP, HOUR_IN_SECONDS );
            }
        }

        return $wwpp_data;
    }

    /**
     * Checks if WooCommerce Wholesale Prices Premium plugin is active.
     *
     * @since 3.0
     * @return bool
     */
    public static function is_active() {

        return is_plugin_active( self::WWPP_FILE_BASENAME );
    }

    /**
     * Get WWPP settings used in the app script.
     *
     * @since 3.0
     * @return array[]
     */
    public static function get_script_l10n() {

        if ( ! self::is_active() ) {
            return array();
        }

        [
            'min_order_quantity' => $min_order_quantity,
            'min_order_price'    => $min_order_price,
            'min_req_logic'      => $min_req_logic,
        ] = self::get_min_requirement_data( WWOF::get_app_user() );

        return array(
            'wwppSettings' => array(
                'allowAddToCartBelowProductMinimum'     => get_option( 'wwpp_settings_allow_add_to_cart_below_product_minimum', 'no' ),
                'hideQuantityDiscountTable'             => get_option( 'wwpp_settings_hide_quantity_discount_table', 'no' ),
                'onlyShowWsProductsToWsUsers'           => get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', 'no' ),
                'overrideStockDisplayFormat'            => get_option( 'wwpp_settings_override_stock_display_format', '' ),
                'alwaysAllowBackordersToWholesaleUsers' => get_option( 'wwpp_settings_always_allow_backorders_to_wholesale_users', 'no' ),
                'showBackroderNoticeWholesaleUsers'     => get_option( 'wwpp_settings_show_back_order_notice_wholesale_users', 'no' ),
                'wholesaleTax'                          => array(
                    'cart' => self::get_tax_display_cart(),
                    'shop' => self::get_tax_display_shop(),
                ),
                'minRequirementsMessage'                => 'admin' !== WWOF::get_app_user()
                    ? self::get_min_requirement_message( $min_order_quantity, $min_order_price, $min_req_logic )
                    : null,
            ),
        );
    }

    /**
     * Get tax display mode.
     *
     * @param string $wholesale_role Current user's wholesale role.
     *
     * @since 3.0
     * @return string Tax display mode. Either 'incl' or 'excl'.
     */
    public static function get_tax_display_cart( $wholesale_role = null ) {

        global $wc_wholesale_prices;

        if ( empty( $wholesale_role ) ) {
            $wholesale_role = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole()[0] ?? WWOF::get_app_user();
        }
        $mode = ! empty( WC()->cart ) && method_exists( WC()->cart, 'get_tax_price_display_mode' ) ? WC()->cart->get_tax_price_display_mode() : '';
        if ( $wholesale_role && get_option( 'woocommerce_calc_taxes', false ) === 'yes' ) {
            $tax_exempted               = WWPP_Helper_Functions::is_user_wwpp_tax_exempted( get_current_user_id(), $wholesale_role );
            $wholesale_tax_display_cart = get_option( 'wwpp_settings_wholesale_tax_display_cart' ); // Display Prices During Cart and Checkout.
            if ( 'yes' !== $tax_exempted && 'incl' === $wholesale_tax_display_cart ) {
                $mode = 'incl';
            } elseif ( 'yes' === $tax_exempted || 'excl' === $wholesale_tax_display_cart ) {
                $mode = 'excl';
            }
        }

        return $mode;
    }

    /**
     * Get tax display mode for shop.
     *
     * @param string $wholesale_role Current user's wholesale role.
     *
     * @since 3.0
     * @return false|mixed|string|null
     */
    public static function get_tax_display_shop( $wholesale_role = null ) {

        global $wc_wholesale_prices;

        if ( empty( $wholesale_role ) ) {
            $wholesale_role = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole()[0] ?? WWOF::get_app_user();
        }

        $mode = get_option( 'woocommerce_tax_display_shop' );
        if ( $wholesale_role && get_option( 'woocommerce_calc_taxes', false ) === 'yes' ) {
            $tax_exempted               = WWPP_Helper_Functions::is_user_wwpp_tax_exempted( get_current_user_id(), $wholesale_role );
            $wholesale_tax_display_shop = get_option( 'wwpp_settings_incl_excl_tax_on_wholesale_price' );
            if ( 'yes' !== $tax_exempted && 'incl' === $wholesale_tax_display_shop ) {
                $mode = 'incl';
            } elseif ( 'yes' === $tax_exempted || 'excl' === $wholesale_tax_display_shop ) {
                $mode = 'excl';
            }
        }

        return $mode;
    }

    /**
     * Get the minimum order requirement data.
     *
     * @param string $wholesale_role The wholesale role of current login user.
     *
     * @since 1.17
     * @return array
     */
    public static function get_min_requirement_data( $wholesale_role ) {

        $wwpp_order_requirement_mapping = get_option( 'wwpp_option_wholesale_role_order_requirement_mapping' );
        /**
         * Filter the current user ID.
         *
         * @param int $user_id The current user ID.
         *
         * @since 3.0
         */
        $user_id = apply_filters( 'wwof_min_requirement_current_user_id', get_current_user_id() );

        if ( 'yes' === get_user_meta( $user_id, 'wwpp_override_min_order_qty', true ) ) {
            // Use override per user.
            $min_order_quantity = get_user_meta( $user_id, 'wwpp_min_order_qty', true );
            $min_order_price    = '';
            $min_req_logic      = '';

            if ( 'yes' === get_user_meta( $user_id, 'wwpp_override_min_order_price', true ) ) {
                $min_order_price = get_user_meta( $user_id, 'wwpp_min_order_price', true );
                $min_req_logic   = get_user_meta( $user_id, 'wwpp_min_order_logic', true );
            }
        } elseif ( 'yes' === get_option( 'wwpp_settings_override_order_requirement_per_role' ) &&
            isset( $wwpp_order_requirement_mapping[ $wholesale_role ] ) ) {
            // Override per wholesale role option in the general setting.
            $min_order_quantity = $wwpp_order_requirement_mapping[ $wholesale_role ]['minimum_order_quantity'];
            $min_order_price    = $wwpp_order_requirement_mapping[ $wholesale_role ]['minimum_order_subtotal'];
            $min_req_logic      = $wwpp_order_requirement_mapping[ $wholesale_role ]['minimum_order_logic'];
        } else {
            // Use general setting.
            $min_order_quantity = get_option( 'wwpp_settings_minimum_order_quantity' );
            $min_order_price    = get_option( 'wwpp_settings_minimum_order_price' );
            $min_req_logic      = get_option( 'wwpp_settings_minimum_requirements_logic' );
        }

        return array(
            'min_order_quantity' => $min_order_quantity,
            'min_order_price'    => $min_order_price,
            'min_req_logic'      => $min_req_logic,
        );
    }

    /**
     * Get the minimum order requirement message.
     *
     * @param string|number $min_order_quantity Minimum order quantity.
     * @param string|number $min_order_price    Minimum order price.
     * @param string        $min_req_logic      Minimum requirement logic.
     *
     * @since 3.0
     * @return string
     */
    public static function get_min_requirement_message( $min_order_quantity, $min_order_price, $min_req_logic ) {

        $message = '';

        if ( Aelia::is_active() ) {
            /**
             * Filter the minimum order price for Aelia Currency Switcher if it's active.
             *
             * @param string $min_order_price  Minimum order price.
             * @param string $currency         The current currency.
             * @param string $default_currency Default currency.
             */
            $min_order_price = apply_filters( 'wc_aelia_cs_convert', $min_order_price, get_option( 'woocommerce_currency' ), get_woocommerce_currency() );
        }

        if ( WOOCS::is_active() && ! empty( $GLOBALS['WOOCS'] ) && method_exists( $GLOBALS['WOOCS'], 'woocs_exchange_value' ) ) {
            $min_order_price = $GLOBALS['WOOCS']->woocs_exchange_value( $min_order_price );
        }

        if ( ! empty( $min_order_quantity ) && ! empty( $min_order_price ) && ! empty( $min_req_logic ) ) {
            if ( 'or' === $min_req_logic ) {
                $message = sprintf(/* translators: %1$s = opening <strong> tag; %2$s = closing </strong> tag; %3$s = minimum order quantity; %4$s = formatted price */
                    __(
                        '%1$sNOTE:%2$s A minimum order quantity of %1$s%3$s%2$s or minimum order subtotal of %1$s%5$s%2$s is required to activate wholesale pricing in the cart.',
                        'woocommerce-wholesale-order-form'
                    ),
                    '<strong>',
                    '</strong>',
                    $min_order_quantity,
                    $min_req_logic,
                    WWP::formatted_price( $min_order_price )
                );
            } else {
                $message = sprintf(/* translators: %1$s = opening <strong> tag; %2$s = closing </strong> tag; %3$s = minimum order quantity; %4$s = formatted price */
                    __(
                        '%1$sNOTE:%2$s A minimum order quantity of %1$s%3$s%2$s and minimum order subtotal of %1$s%5$s%2$s is required to activate wholesale pricing in the cart.',
                        'woocommerce-wholesale-order-form'
                    ),
                    '<strong>',
                    '</strong>',
                    $min_order_quantity,
                    $min_req_logic,
                    WWP::formatted_price( $min_order_price )
                );
            }
        } elseif ( ! empty( $min_order_quantity ) ) {
            $message = sprintf(/* translators: %1$s = opening <strong> tag; %2$s = closing </strong> tag; %3$s = minimum order quantity */
                __(
                    '%1$sNOTE:%2$s A minimum order quantity of %1$s%3$s%2$s is required to activate wholesale pricing in the cart.',
                    'woocommerce-wholesale-order-form'
                ),
                '<strong>',
                '</strong>',
                $min_order_quantity
            );
        } elseif ( ! empty( $min_order_price ) ) {
            $message = sprintf(/* translators: %1$s = opening <strong> tag; %2$s = closing </strong> tag; %3$s = formatted price */
                __(
                    '%1$sNOTE:%2$s A minimum order subtotal of %1$s%3$s%2$s is required to activate wholesale pricing in the cart.',
                    'woocommerce-wholesale-order-form'
                ),
                '<strong>',
                '</strong>',
                WWP::formatted_price( $min_order_price )
            );
        }

        /**
         * Filter the minimum order requirement message.
         *
         * @param string        $message            The minimum order requirement message.
         * @param string|number $min_order_quantity Minimum order quantity.
         * @param string|number $min_order_price    Minimum order price.
         * @param string        $min_req_logic      Minimum requirement logic.
         *
         * @since 3.0
         */
        return apply_filters(
            'wwof_wholesale_order_requirements_message',
            $message,
            $min_order_quantity,
            $min_order_price,
            $min_req_logic
        );
    }
}
