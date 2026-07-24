<?php
/**
 * WWPP integration with Elementor / Elementor Pro.
 *
 * @package RymeraWebCo\WWPP
 * @since   2.1.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Elementor' ) ) {

    /**
     * Model that houses the logic of integrating with the 'Elementor' / 'Elementor Pro' plugin.
     *
     * Elementor Pro's "Menu Cart" widget renders the slide-out cart panel (the "SideCart"). Its
     * contents are produced by `woocommerce_mini_cart()` and refreshed on the frontend through
     * WooCommerce's cart fragments (`?wc-ajax=get_refreshed_fragments`) and Elementor's own
     * `elementor_menu_cart_fragments` request.
     *
     * WWP keeps the mini-cart in sync with wholesale pricing by recalculating cart totals on the
     * `woocommerce_before_mini_cart` action. Elementor Pro, however, ships its own mini-cart
     * template override that fires `woocommerce_before_mini_cart_contents` and never
     * `woocommerce_before_mini_cart`, so WWP's recalculation is bypassed. Because cart totals are
     * served from the session cache during a fragments request, `woocommerce_before_calculate_totals`
     * does not fire either, and the wholesale price is never re-applied to the re-hydrated cart item
     * product objects. The Menu Cart template then reads `WC()->cart->get_product_price()`, which
     * returns the retail (catalog) price, so wholesale customers see retail per-item prices in the
     * SideCart even though the cached subtotal is correctly discounted.
     *
     * This integration recalculates cart totals on `woocommerce_before_mini_cart_contents` — the
     * action Elementor's template does fire — which re-applies wholesale pricing (via WWP's
     * `woocommerce_before_calculate_totals` handler) before the Menu Cart template renders the line
     * items. The recalculation runs at most once per request and only for wholesale customers, so
     * retail customers and guests are unaffected.
     *
     * @since 2.1.1
     */
    class WWPP_Elementor {
        /*
        |--------------------------------------------------------------------------
        | Class Properties
        |--------------------------------------------------------------------------
        */

        /**
         * Property that holds the single main instance of WWPP_Elementor.
         *
         * @since 2.1.1
         * @access private
         * @var WWPP_Elementor
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since 2.1.1
         * @access private
         * @var WWPP_Wholesale_Roles
         */
        private $_wwpp_wholesale_roles;

        /**
         * Flag to ensure the mini-cart recalculation only runs once per request.
         *
         * @since 2.1.1
         * @access private
         * @var bool
         */
        private $_recalculated_for_mini_cart = false;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
        */

        /**
         * WWPP_Elementor constructor.
         *
         * @since 2.1.1
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Elementor model.
         */
        public function __construct( $dependencies = array() ) {
            $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
        }

        /**
         * Ensure that only one instance of WWPP_Elementor is loaded or can be loaded (Singleton Pattern).
         *
         * @since 2.1.1
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Elementor model.
         * @return WWPP_Elementor
         */
        public static function instance( $dependencies = array() ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Get current user wholesale role.
         *
         * @since 2.1.1
         * @access private
         *
         * @return string User role string or empty string.
         */
        private function _get_current_user_wholesale_role() {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            $wholesale_role = ( is_array( $user_wholesale_role ) && ! empty( $user_wholesale_role ) ) ? $user_wholesale_role[0] : '';

            return apply_filters( 'wwpp_get_current_wholesale_role', $wholesale_role );
        }

        /**
         * Recalculate cart totals before the Elementor Menu Cart (SideCart) contents are rendered.
         *
         * Elementor's mini-cart template fires `woocommerce_before_mini_cart_contents` (instead of the
         * `woocommerce_before_mini_cart` action WWP recalculates on), so wholesale pricing is never
         * re-applied to the cart item product objects for that template. Recalculating totals here
         * runs WWP's `woocommerce_before_calculate_totals` handler, which re-applies the wholesale
         * price, before the Menu Cart template reads `WC()->cart->get_product_price()`.
         *
         * Runs at most once per request and only for wholesale customers, so retail customers and
         * guests are unaffected.
         *
         * @since 2.1.1
         * @access public
         *
         * @return void
         */
        public function recalculate_wholesale_prices_for_mini_cart() {

            if ( $this->_recalculated_for_mini_cart ) {
                return;
            }

            // Bail if invoked from within the totals calculation chain to avoid re-entrant recalculation.
            if (
                doing_action( 'woocommerce_before_calculate_totals' ) ||
                doing_action( 'woocommerce_after_calculate_totals' ) ||
                doing_action( 'woocommerce_calculate_totals' )
            ) {
                return;
            }

            // Only act on the Elementor mini-cart template override. When it is off, a standard
            // WooCommerce mini-cart renders and WWP already recalculates wholesale pricing on its
            // woocommerce_before_mini_cart hook, so recalculating here would be redundant.
            if ( 'yes' !== get_option( 'elementor_use_mini_cart_template', 'no' ) ) {
                return;
            }

            if ( empty( $this->_get_current_user_wholesale_role() ) ) {
                return;
            }

            if ( ! ( WC()->cart instanceof WC_Cart ) || WC()->cart->is_empty() ) {
                return;
            }

            $this->_recalculated_for_mini_cart = true;

            WC()->cart->calculate_totals();
        }

        /**
         * Execute model.
         *
         * @since 2.1.1
         * @access public
         *
         * @return void
         */
        public function run() {

            if ( ! WWP_Helper_Functions::is_plugin_active( 'elementor-pro/elementor-pro.php' ) ) {
                return;
            }

            // Re-apply wholesale pricing before the Menu Cart (SideCart) contents render.
            add_action( 'woocommerce_before_mini_cart_contents', array( $this, 'recalculate_wholesale_prices_for_mini_cart' ), 5 );
        }
    }
}
