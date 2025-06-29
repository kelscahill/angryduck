<?php if (!defined('ABSPATH')) {
    exit;
}
// Exit if accessed directly

if (!class_exists('WWPP_Tax')) {

    /**
     * Model that houses the logic of taxing for wholesale users.
     *
     * @since 1.12.8
     */
    class WWPP_Tax {

        /*
        |--------------------------------------------------------------------------
        | Class Properties
        |--------------------------------------------------------------------------
         */

        /**
         * Property that holds the single main instance of WWPP_Tax.
         *
         * @since 1.12.8
         * @access private
         * @var WWPP_Tax
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since 1.12.8
         * @access private
         * @var WWPP_Wholesale_Roles
         */
        private $_wwpp_wholesale_roles;

        /**
         * Model that handles the checking if a wholesale user meets the requirements of having wholesale price.
         *
         * @since 1.12.8
         * @access private
         * @var WWPP_Wholesale_Price_Requirement
         */
        private $_wwpp_wholesale_price_requirement;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWPP_Tax constructor.
         *
         * @since 1.12.8
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Tax model.
         */
        public function __construct($dependencies) {

            $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
            $this->_wwpp_wholesale_price_requirement = $dependencies['WWPP_Wholesale_Price_Requirement'];

        }

        /**
         * Ensure that only one instance of WWPP_Tax is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.12.8
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Tax model.
         * @return WWPP_Tax
         */
        public static function instance($dependencies) {

            if (!self::$_instance instanceof self) {
                self::$_instance = new self($dependencies);
            }

            return self::$_instance;

        }

        /**
         * Get curent user wholesale role.
         *
         * @since 1.12.8
         * @access private
         *
         * @return string User role string or empty string.
         */
        private function _get_current_user_wholesale_role() {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            return (is_array($user_wholesale_role) && !empty($user_wholesale_role)) ? $user_wholesale_role[0] : '';

        }

        /**
         * Apply proper tax classes for both wholesale and non wholesale users.
         *
         * @since 1.16.0
         * @access public
         *
         * @param string           $tax_class  Tax class applied for this specific product or shipping cost depending on where it is called.
         * @param WC_Product|null $product     WC_Product instance or null depending on the filter this callback is triggered.
         * @return string Filtered tax class.
         */
        public function apply_proper_tax_classes($tax_class, $product = null) {

            $user_wholesale_role = $this->_get_current_user_wholesale_role();

            $wholesale_role_tax_class_mapping = get_option(WWPP_OPTION_WHOLESALE_ROLE_TAX_CLASS_OPTIONS_MAPPING, array());
            if (!is_array($wholesale_role_tax_class_mapping)) {
                $wholesale_role_tax_class_mapping = array();
            }

            if (!empty($user_wholesale_role) && !empty($wholesale_role_tax_class_mapping) && array_key_exists($user_wholesale_role, $wholesale_role_tax_class_mapping)) {

                $filtered_tax_class = $wholesale_role_tax_class_mapping[$user_wholesale_role]['tax-class'];
                if (!empty($filtered_tax_class)) {
                    return $filtered_tax_class;
                }

            } elseif (empty($user_wholesale_role) && !empty($wholesale_role_tax_class_mapping) && get_option('wwpp_settings_mapped_tax_classes_for_wholesale_users_only') === 'yes') {

                $forbidden_tax_classes = array();
                foreach ($wholesale_role_tax_class_mapping as $wholesale_role_key => $mapping) {
                    $forbidden_tax_classes[] = $mapping['tax-class'];
                }

                if (in_array($tax_class, $forbidden_tax_classes)) {
                    return '';
                }
                // Empty means Standard Rate

            }

            return $tax_class;

        }

        /**
         * Apply tax exemption to wholesale users.
         * Merged function of 'apply_tax_exemptions_to_wholesale_users_no_integration' and 'apply_tax_exemptions_to_wholesale_users_with_integration'.
         * Previously the integration and non-integration function was separated, but have moved the integration logic on the `is_user_wwpp_tax_exempted` helper function.
         *
         * @since 1.30
         * @access public
         */
        public function apply_tax_exemptions_to_wholesale_users() {

            $user_wholesale_role = $this->_get_current_user_wholesale_role();

            if ( ! empty( $user_wholesale_role ) ) {

                $wwpp_tax_exempted = WWPP_Helper_Functions::is_user_wwpp_tax_exempted( get_current_user_id(), $user_wholesale_role );

                if ( 'yes' === $wwpp_tax_exempted ) {
                    WC()->customer->set_is_vat_exempt( true );
                } else {
                    WC()->customer->set_is_vat_exempt( false );
                }
            }

        }

        /**
         * Integrate tax to product price on shop pages ( Either include or exclude ).
         * Wholesale user role tax exemptions only apply on cart and checkout pages.
         * It don't apply on shop pages.
         * So even if wholesale user is tax exempted, if setting on the backend to display product price on the shop to
         * include taxes. Then prices will include tax on the shop page even for a tax exempted wholesale user.
         * After he/she adds that product to the cart and go to the cart, that's where tax exemption applies.
         *
         * @since 1.0.0
         * @since 1.12.5 The above comment ain't true anymore, nah uh. Now if a wholesale user is tax exempted, it will always see tax exempted price/wholesale price no matter what, no matter where.
         * @since 1.12.8 Refactor code base, move to its model.
         * @since 1.16.0 Now we are attaching to the 'wwp_pass_wholesale_price_through_taxing' hook.
         * @access public
         *
         * @param float $wholesale_price     Wholesale price.
         * @param int   $product_id          Product Id.
         * @param array $user_wholesale_role User wholesale roles.
         * @return float Modified wholesale price.
         */
        public function integrate_tax_to_wholesale_price_on_shop($wholesale_price, $product_id, $user_wholesale_role) {

            if (!empty($wholesale_price) && !empty($user_wholesale_role) && get_option('woocommerce_calc_taxes', false) === 'yes') {

                $product = wc_get_product($product_id);
                $tax_exempted = WWPP_Helper_Functions::is_user_wwpp_tax_exempted(get_current_user_id(), $user_wholesale_role[0]);

                if ($tax_exempted === 'yes') {

                    // Wholesale user is tax exempted so no matter what, the user will always see tax exempted prices
                    $wholesale_price = WWP_Helper_Functions::wwp_get_price_excluding_tax($product, array('qty' => 1, 'price' => $wholesale_price));

                } else {

                    $wholesale_tax_display_shop = get_option('wwpp_settings_incl_excl_tax_on_wholesale_price', false);
                    $woocommerce_tax_display_shop = get_option('woocommerce_tax_display_shop', false);

                    if ($wholesale_tax_display_shop === 'incl') {
                        $wholesale_price = WWP_Helper_Functions::wwp_get_price_including_tax($product, array('qty' => 1, 'price' => $wholesale_price));
                    } elseif ($wholesale_tax_display_shop === 'excl') {
                        $wholesale_price = WWP_Helper_Functions::wwp_get_price_excluding_tax($product, array('qty' => 1, 'price' => $wholesale_price));
                    } elseif (empty($wholesale_tax_display_shop)) {

                        if ($woocommerce_tax_display_shop === 'incl') {
                            $wholesale_price = WWP_Helper_Functions::wwp_get_price_including_tax($product, array('qty' => 1, 'price' => $wholesale_price));
                        } else {
                            $wholesale_price = WWP_Helper_Functions::wwp_get_price_excluding_tax($product, array('qty' => 1, 'price' => $wholesale_price));
                        }

                    }

                }

            }

            return $wholesale_price;

        }

        /**
         * Override "woocommerce_tax_display_cart" option for wholesale users.
         *
         * @since 1.5.0
         * @since 1.12.5
         * This used to be named 'wholesaleTaxDisplayCart' now its renamed as 'wholesale_tax_display'.
         * This uses the technique of overriding the options in wordpress via the 'option_' . option name technique.
         * So we are overriding the option inside the code that saves it itself of wordpress.
         * Usually this is not an ideal way to filter option values because this is tooooooooooooo early.
         * But in this current case, it fits the bill.
         * @since 1.12.8 Moved to its own model.
         * @access public
         * @see wholesale_tax_display_shop
         *
         * @param string $option_value Option value.
         * @return string Filtered option value.
         */
        public function wholesale_tax_display_cart($option_value) {

            $user_wholesale_role = $this->_get_current_user_wholesale_role();

            if (!empty($user_wholesale_role) || (defined('DOING_AJAX') && DOING_AJAX && !empty($user_wholesale_role))) {

                if (get_option('woocommerce_calc_taxes', false) === 'yes') {

                    $tax_exempted = WWPP_Helper_Functions::is_user_wwpp_tax_exempted(get_current_user_id(), $user_wholesale_role);
                    $wholesale_tax_display_cart = get_option('wwpp_settings_wholesale_tax_display_cart'); // Display Prices During Cart and Checkout

                    if ($tax_exempted !== 'yes' && $wholesale_tax_display_cart === 'incl') {
                        $option_value = 'incl';
                    } elseif ($tax_exempted === 'yes' || $wholesale_tax_display_cart === 'excl') {
                        $option_value = 'excl';
                    }

                }

            }

            return $option_value;

        }

        /**
         * Override "woocommerce_tax_display_shop" option for wholesale users.
         *
         * @since 1.12.5
         * @since 1.12.8 Move to its model.
         * @access public
         * @see wholesale_tax_display_cart
         *
         * @param string $option_value Option value.
         * @return string Filtered option value.
         */
        public function wholesale_tax_display_shop($option_value) {

            $user_wholesale_role = $this->_get_current_user_wholesale_role();

            if (!empty($user_wholesale_role) || (defined('DOING_AJAX') && DOING_AJAX && !empty($user_wholesale_role))) {

                if (get_option('woocommerce_calc_taxes', false) === 'yes') {

                    $tax_exempted = WWPP_Helper_Functions::is_user_wwpp_tax_exempted(get_current_user_id(), $user_wholesale_role);
                    $wholesale_tax_display_shop = get_option('wwpp_settings_incl_excl_tax_on_wholesale_price'); // Display Prices in the Shop

                    if ($tax_exempted !== 'yes' && $wholesale_tax_display_shop === 'incl') {
                        $option_value = 'incl';
                    } elseif ($tax_exempted === 'yes' || $wholesale_tax_display_shop === 'excl') {
                        $option_value = 'excl';
                    }

                }

            }

            return $option_value;

        }

        /**
         * On tax settings update, delete product transients so that taxing is properly applied in the frontend. WWPP-454
         *
         * @since 1.22
         * @access public
         */
        public function clear_product_transient_on_tax_settings_save() {

            if (function_exists('wc_delete_product_transients')) {
                wc_delete_product_transients();
            }

        }

        /*
        |--------------------------------------------------------------------------
        | Execute Model
        |--------------------------------------------------------------------------
         */

        /**
         * Execute model.
         *
         * @since 1.12.8
         * @access public
         */
        public function run() {

            // Apply proper tax classes
            add_filter('woocommerce_product_get_tax_class', array($this, 'apply_proper_tax_classes'), 10, 2);
            add_filter('woocommerce_product_variation_get_tax_class', array($this, 'apply_proper_tax_classes'), 10, 2);
            add_filter('option_woocommerce_shipping_tax_class', array($this, 'apply_proper_tax_classes'), 10, 1);

            // Apply tax exemptions to wholesale user on cart and checkout.
            add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_tax_exemptions_to_wholesale_users' ), 10 );
            add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'apply_tax_exemptions_to_wholesale_users' ), 10 );

            /**
             * Integrate tax to wholesale price on shop pages.
             *
             * Note: No need to integrate tax to wholesale price on cart and checkout page, because WC already takes care of this
             * because of the way we apply wholesale pricing. Since we used the "woocommerce_before_calculate_totals" action
             * hook, we changed the product price (apply wholesale pricing) before any WC calculations (including taxing) is done.
             * So by the time we finished applying our wholesale pricing,
             * WC will apply its own calculations stuff above our wholesale price (including taxing).
             */
            add_filter('wwp_pass_wholesale_price_through_taxing', array($this, 'integrate_tax_to_wholesale_price_on_shop'), 10, 3);

            // Override "woocommerce_tax_display_cart" option for wholesale users.
            add_filter('option_woocommerce_tax_display_cart', array($this, 'wholesale_tax_display_cart'), 10, 1);

            // Override "woocommerce_tax_display_shop" option for wholesale users.
            add_filter('option_woocommerce_tax_display_shop', array($this, 'wholesale_tax_display_shop'), 10, 1);

            // Delete product transients on tax settings save.
            add_action('woocommerce_settings_save_tax', array($this, 'clear_product_transient_on_tax_settings_save'), 10, 1);

        }

    }

}
