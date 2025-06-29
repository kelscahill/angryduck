<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Helpers
 */

namespace RymeraWebCo\WWOF\Helpers;

use WWP_Helper_Functions;
use WWP_Wholesale_Roles;

/**
 * WWP class.
 *
 * @since 3.0
 */
class WWP {

    /**
     * Cache group for this helper class.
     */
    const CACHE_GROUP = 'wwp';

    /**
     * WWP plugin file basename.
     */
    const WWP_FILE_BASENAME = 'woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php';

    /**
     * Path to WWP main plugin file.
     */
    const WWP_FILE = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . self::WWP_FILE_BASENAME;

    /**
     * Checks if WooCommerce Wholesale Prices Premium plugin is active.
     *
     * @since 3.0
     * @return bool
     */
    public static function is_active() {

        return is_plugin_active( self::WWP_FILE_BASENAME );
    }

    /**
     * Checks if WWP is version 2 or higher.
     *
     * @since 3.0
     *
     * @return bool|null True if WWP is version 2 or higher, false if WWP is version 1, null if WWP is not installed
     * or active.
     */
    public static function is_v2() {

        $wwp_data = self::get_wwp_data();

        if ( ! is_plugin_active( self::WWP_FILE_BASENAME ) || empty( $wwp_data['Version'] ) ) {
            return null;
        }

        return version_compare( $wwp_data['Version'], '2', '>=' );
    }

    /**
     * Get WWP data from its plugin file header.
     *
     * @param bool $force Force the data to be refreshed.
     *
     * @since 3.0
     * @return array|bool WWP data or false if not found.
     */
    public static function get_wwp_data( $force = false ) {

        $wwp_data = wp_cache_get( 'wwp_data', self::CACHE_GROUP );
        if ( false === $wwp_data || $force ) {
            if ( file_exists( self::WWP_FILE ) ) {
                $wwp_data = get_plugin_data( self::WWP_FILE, false, false );

                wp_cache_set( 'wwp_data', $wwp_data, self::CACHE_GROUP, HOUR_IN_SECONDS );
            }
        }

        return $wwp_data;
    }

    /**
     * Get the list of registered wholesale roles.
     *
     * @since 3.0
     * @return array
     */
    public static function get_all_register_wholesale_roles() {

        $all_registered_wholesale_roles = array();
        if ( defined( 'WWP_OPTIONS_REGISTERED_CUSTOM_ROLES' ) ) {
            $all_registered_wholesale_roles = maybe_unserialize( get_option( WWP_OPTIONS_REGISTERED_CUSTOM_ROLES ) );

            if ( ! is_array( $all_registered_wholesale_roles ) ) {
                $all_registered_wholesale_roles = array();
            }
        }

        /**
         * Filter the list of registered wholesale roles.
         *
         * @param array $all_registered_wholesale_roles List of registered wholesale roles.
         *
         * @since 3.0
         */
        return apply_filters( 'wwp_registered_wholesale_roles', $all_registered_wholesale_roles );
    }

    /**
     * Check if current user is a wholesale customer.
     *
     * @since 3.0
     * @return bool
     */
    public static function is_wholesale_customer() {

        $is_wholesale_customer = false;

        if ( ! is_user_logged_in() ) {
            return false;
        }

        $user = wp_get_current_user();

        if ( ! $user instanceof \WP_User ) {
            return false;
        }

        $all_registered_wholesale_roles = self::get_all_register_wholesale_roles();

        if ( empty( $all_registered_wholesale_roles ) ) {
            return false;
        }

        foreach ( $all_registered_wholesale_roles as $role_key => $role ) {
            if ( in_array( $role_key, $user->roles, true ) ) {
                $is_wholesale_customer = true;
                break;
            }
        }

        return $is_wholesale_customer;
    }

    /**
     * Get main wholesale role.
     *
     * @since 3.0
     * @return string|null Main wholesale role or null if not found.
     */
    public static function get_main_wholesale_role() {

        $wholesale_roles = WWP_Wholesale_Roles::getInstance()->getAllRegisteredWholesaleRoles();
        if ( ! empty( $wholesale_roles ) ) {
            foreach ( $wholesale_roles as $wholesale_role => $role_data ) {
                if ( true === $role_data['main'] ) {
                    return $wholesale_role;
                }
            }
        }

        return null;
    }

    /**
     * Get WWP settings used in the app script.
     *
     * @since 3.0
     * @return array[]
     */
    public static function get_script_l10n() {

        return array(
            'wwpSettings' => array(
                'nonce'                             => wp_create_nonce( 'wwp_nonce' ),
                'hidePriceAddToCart'                => get_option( 'wwp_hide_price_add_to_cart', 'no' ),
                'hidePriceAddToCartText'            => get_option( 'wwp_price_and_add_to_cart_replacement_message ', '' ),
                'showWholesalePricesToNonWholesale' => array(
                    'enabled'         => get_option( 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale', 'no' ),
                    'showInWWOF'      => get_option( 'wwp_non_wholesale_show_in_wwof', 'no' ),
                    'wholesaleRoles'  => get_option( 'wwp_non_wholesale_wholesale_role_select2', array() ),
                    'replacementText' => get_option( 'wwp_see_wholesale_prices_replacement_text', '' ),
                ),
            ),
        );
    }

    /**
     * Get formatted price.
     *
     * @param numeric $price Price to format.
     *
     * @since 3.0
     * @return string
     */
    public static function formatted_price( $price ) {

        if ( self::is_active() ) {
            return WWP_Helper_Functions::wwp_formatted_price( $price );
        }

        return wc_price( $price );
    }
}
