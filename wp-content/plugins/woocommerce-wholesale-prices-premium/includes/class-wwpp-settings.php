<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Settings' ) ) {

    /**
     * Model that houses extended settings options for WWP.
     *
     * @since 1.0.0
     * @since 1.12.8 Refactored codebase. Move settings related code inside here.
     */
    class WWPP_Settings {

        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWPP_Settings.
         *
         * @since  1.12.8
         * @access private
         * @var WWPP_Settings
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since  1.12.8
         * @access private
         * @var WWPP_Wholesale_Roles
         */
        private $_wwpp_wholesale_roles;

        /**
         * Property that holds all registered wholesale roles.
         *
         * @since  1.16.0
         * @access public
         * @var array
         */
        private $_all_wholesale_roles;

        /**
         * Class Methods
         */

        /**
         * WWPP_Tax constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Tax model.
         *
         * @since  1.12.8
         * @access public
         */
        public function __construct( $dependencies ) {

            $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];

            $this->_all_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
        }

        /**
         * Ensure that only one instance of WWPP_Settings is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Settings model.
         *
         * @since  1.12.8
         * @access public
         *
         * @return WWPP_Settings
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Premium plugin settings sections.
         *
         * @param array $sections Array of settings sections.
         *
         * @since  1.12.8 Refactor codebase.
         * @access public
         *
         * @since  1.0.0
         * @return array Filtered array of settings sections.
         */
        public function plugin_settings_sections( $sections ) {

            $sections['wwpp_setting_price_section']           = __( 'Price', 'woocommerce-wholesale-prices-premium' );
            $sections['wwpp_setting_tax_section']             = __( 'Tax', 'woocommerce-wholesale-prices-premium' );
            $sections['wwpp_setting_shipping_section']        = __( 'Shipping', 'woocommerce-wholesale-prices-premium' );
            $sections['wwpp_setting_discount_section']        = __( 'Discount', 'woocommerce-wholesale-prices-premium' );
            $sections['wwpp_setting_payment_gateway_section'] = __( 'Payment Gateway', 'woocommerce-wholesale-prices-premium' );
            $sections['wwpp_setting_cache_section']           = __( 'Cache', 'woocommerce-wholesale-prices-premium' );
            $sections['wwpp_setting_help_section']            = __( 'Help', 'woocommerce-wholesale-prices-premium' );

            if ( ! WWPP_Helper_Functions::is_wwof_active() || ! WWPP_Helper_Functions::is_wwlc_active() ) {
                $sections['wwpp_setting_upgrade_section'] = __( 'Upgrade', 'woocommerce-wholesale-prices-premium' );
            }

            return $sections;
        }

        /**
         * Premium plugin settings section contents.
         *
         * @param array  $settings        Array of settings.
         * @param string $current_section Id of current settings section.
         *
         * @since  1.0.0
         * @since  1.12.8 Refactor codebase.
         * @access public
         *
         * @return array Filtered array of settings.
         */
        public function plugin_settings_section_content( $settings, $current_section ) {

            if ( '' === $current_section ) {

                // General Settings Section.
                $settings = apply_filters( 'wwpp_settings_general_section_settings', $this->_get_general_section_settings(), $settings );

            } elseif ( 'wwpp_setting_price_section' === $current_section ) {

                // Price Settings Section.
                $wwpp_price_settings = apply_filters( 'wwpp_settings_price_section_settings', $this->_get_price_section_settings(), $settings );
                array_splice( $settings, 3, 0, $wwpp_price_settings );

            } elseif ( 'wwpp_setting_tax_section' === $current_section ) {

                // Tax Settings Section.
                $wwpp_tax_settings = apply_filters( 'wwpp_settings_tax_section_settings', $this->_get_tax_section_settings(), $settings );
                $settings          = array_merge( $settings, $wwpp_tax_settings );

            } elseif ( 'wwpp_setting_shipping_section' === $current_section ) {

                // Shipping Settings Section.
                $wwpp_shipping_settings = apply_filters( 'wwpp_settings_shipping_section_settings', $this->_get_shipping_section_settings(), $settings );
                $settings               = array_merge( $settings, $wwpp_shipping_settings );

            } elseif ( 'wwpp_setting_discount_section' === $current_section ) {

                // Discount Settings Section.
                $wwpp_discount_settings = apply_filters( 'wwpp_settings_discount_section_settings', $this->_get_discount_section_settings(), $settings );
                $settings               = array_merge( $settings, $wwpp_discount_settings );

            } elseif ( 'wwpp_setting_payment_gateway_section' === $current_section ) {

                // Payment Gateway Settings Section.
                $wwpp_payment_gateway_settings = apply_filters( 'wwpp_settings_payment_gateway_section_settings', $this->_get_payment_gateway_section_settings(), $settings );
                $settings                      = array_merge( $settings, $wwpp_payment_gateway_settings );

            } elseif ( 'wwpp_setting_cache_section' === $current_section ) {

                // Cache Settings Section.
                $wwpp_cache_settings = apply_filters( 'wwpp_settings_cache_section_settings', $this->_get_cache_section_settings(), $settings );
                $settings            = array_merge( $settings, $wwpp_cache_settings );

            } elseif ( 'wwpp_setting_help_section' === $current_section ) {

                // Help Settings Section.
                $wwpp_help_settings = apply_filters( 'wwpp_settings_help_section_settings', $this->_get_help_section_settings(), $settings );
                $settings           = array_merge( $settings, $wwpp_help_settings );

            } elseif ( 'wwpp_setting_upgrade_section' === $current_section ) {

                // Upgrade Settings Section.
                $wwpp_upgrade_settings = apply_filters( 'wwpp_settings_upgrade_section_settings', $this->_get_upgrade_section_settings() );
                $settings              = array_merge( $settings, $wwpp_upgrade_settings );

            } elseif ( 'wwpp_license_section' === $current_section ) {

                $wwpp_license_section = apply_filters( 'wwpp_license_section', $this->_get_license_section_settings() );
                $settings             = array_merge( $settings, $wwpp_license_section );

            }

            return apply_filters( 'wwpp_settings_section_content', $settings, $current_section );
        }

        /**
         * Filter wwpp_editor custom settings field so it gets stored properly after sanitizing.
         *
         * @param array $settings Array of settings.
         *
         * @since 1.12.8 Refactor codebase.
         * @since 1.23.2 Clear cache if caching is re-enabled to get updated products in the listings.
         *
         * @since 1.7.4
         */
        public function save_editor_custom_field_type( $settings ) { // phpcs:ignore
            if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-settings' ) ) {
                if ( isset( $_POST['wwpp_editor'] ) && ! empty( $_POST['wwpp_editor'] ) ) {
                    foreach ( $_POST['wwpp_editor'] as $index => $content ) {
                        $_POST[ $index ] = htmlentities( wpautop( $content ) );
                    }
                }

                // Clear cache first to make sure product listings are up to date.
                if ( isset( $_GET['tab'] ) && isset( $_GET['section'] ) && 'wwp_settings' === $_GET['tab'] ) {

                    global $wc_wholesale_prices_premium;

                    // If "Enable product ID caching" is enabled.
                    if ( 'wwpp_setting_cache_section' === $_GET['section'] && isset( $_POST['wwpp_enable_product_cache'] ) ) {
                        $wc_wholesale_prices_premium->wwpp_cache->clear_product_transients_cache();
                    }

                    // If General tab is updated. Need to clear cache when "Only Show Wholesale Products To Wholesale Users" is updated.
                    if ( '' === $_GET['section'] ) {
                        $wc_wholesale_prices_premium->wwpp_cache->clear_product_transients_cache();
                    }
                }
            }
        }

        /**
         * General settings section options.
         *
         * @since  1.0.0
         * @since  1.12.8 Refactor codebase.
         * @access public
         *
         * @return array Array of premium options for the plugin's general settings section.
         */
        private function _get_general_section_settings() {

            return array(

                array(
                    'name' => __( 'Order Requirements', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    // translators: %s: link to kb article.
                    'desc' => sprintf( __( 'These settings describe the Minimum Order Requirements to ensure that your wholesale customers will not activate wholesale pricing if they don’t meet the requirements yet. Until they meet the requirements, they will see regular prices in the cart along with a notice to tell them how much they need to add before they will activate wholesale pricing. <a href="%s" target="_blank">Read more about how this works here</a>.', 'woocommerce-wholesale-prices-premium' ), 'https://wholesalesuiteplugin.com/kb/why-does-the-cart-show-retail-prices-until-the-minimums-are-met/?utm_source=Prices%20Premium%20Plugin&utm_medium=Settings&utm_campaign=Minimum%Order%20Setting%20' ),
                    'id'   => 'wwpp_general_order_requirements_section_title',
                ),

                array(
                    'name'              => __( 'Default Minimum Order Quantity', 'woocommerce-wholesale-prices-premium' ),
                    'type'              => 'number',
                    'custom_attributes' => array(
                        'min' => 0,
                    ),
                    'desc'              => __( 'Define a minimum number of items that wholesale customers must reach in the cart before they activate wholesale pricing.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip'          => __( 'Leave as 0 or blank to disable this setting.', 'woocommerce-wholesale-prices-premium' ),
                    'default'           => 0,
                    'id'                => 'wwpp_settings_minimum_order_quantity',
                ),

                array(
                    'name'              => __( 'Default Minimum Order Subtotal', 'woocommerce-wholesale-prices-premium' ),
                    'type'              => 'number',
                    'custom_attributes' => array(
                        'min'  => 0,
                        'step' => 'any',
                    ),
                    'desc'              => __( 'Define a minimum subtotal that wholesale customers must reach before they activate wholesale pricing for items in their cart.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip'          => __( 'Leave as 0 or blank to disable this setting.', 'woocommerce-wholesale-prices-premium' ),
                    'default'           => 0,
                    'id'                => 'wwpp_settings_minimum_order_price',
                ),

                array(
                    'name'     => __( 'Minimum Order Requirement Satisfaction', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'radio',
                    'desc'     => __( 'Should the customer satisfy both or just one of the minimum order rules?', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'If one of the above default minimum order settings is disabled, this setting will be ignored.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_minimum_requirements_logic',
                    'options'  => array(
                        'and' => __( 'Require Quantity AND Subtotal', 'woocommerce-wholesale-prices-premium' ),
                        'or'  => __( 'Require Quantity OR Subtotal', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'default'  => 'and',
                ),

                array(
                    'name'     => __( 'Wholesale Role Specific Minimum Requirements', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'Override the default minimum order rules per wholesale role. This lets you apply different minimum order requirements based on the customer’s user role.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'You only need to define a mapping for the roles you wish to override, all other roles will use the default minimum order requirements above.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_override_order_requirement_per_role',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_general_order_requirements_section_sectionend',
                ),

                array(
                    'name' => __( 'Wholesale Products', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => __( 'These settings generally affect the way wholesale products are shown and priced.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_general_wholesale_products_section_title',
                ),

                array(
                    'name'     => __( 'Only Show Wholesale Products To Wholesale Customers', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'Products without wholesale pricing will be hidden from wholesale customers.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'This setting only affects wholesale customers.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_only_show_wholesale_products_to_wholesale_users',
                ),

                array(
                    'name'     => __( 'Multiple Category Discount Priority', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'select',
                    'desc'     => __( 'When a product belongs to two categories that both have discounts, which category discount should it get?', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'This only applies when the wholesale price is being derived from the category level % discount.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_multiple_category_wholesale_discount_logic',
                    'options'  => array(
                        'highest' => __( 'Highest Discount Available', 'woocommerce-wholesale-prices-premium' ),
                        'lowest'  => __( 'Lowest Discount Available', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'default'  => 'lowest',
                ),

                array(
                    'name'     => __( 'Hide Quantity Discount Tables', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'Even if a product has a quantity based discount, do not show the quantity discount table.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'By default, a table describing the quantity based discounts is shown to wholesale customers.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_hide_quantity_discount_table',
                ),

                array(
                    'name'     => __( 'Hide Category Product Count', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'On the shop page and product category archives, do not show the product count in the category name. This can speed up performance on those pages.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'Determining the product count is an expensive operation due to having to take product visibility into account, hiding the count stops the system from calculating it.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_hide_product_categories_product_count',
                ),

                array(
                    'name' => __( 'Enforce Min/Step On Cart', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'checkbox',
                    'desc' => __( 'Ensure that cart item quantity boxes on the cart page also respect the minimum and step settings of the product.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_settings_enforce_product_min_step_on_cart_page',
                ),

                array(
                    'name'    => __( 'Wholesale Stock Display Format', 'woocommerce-wholesale-prices-premium' ),
                    'type'    => 'select',
                    'desc'    => __( 'Override the stock display format for wholesale users.', 'woocommerce-wholesale-prices-premium' ),
                    'id'      => 'wwpp_settings_override_stock_display_format',
                    'options' => array(
                        ''           => __( '--Use woocommerce default--', 'woocommerce-wholesale-prices-premium' ),
                        'amount'     => __( 'Always show quantity remaining in stock e.g. "12 in stock"', 'woocommerce-wholesale-prices-premium' ),
                        'low_amount' => __( 'Only show quantity remaining in stock when low e.g. "Only 2 left in stock"', 'woocommerce-wholesale-prices-premium' ),
                        'no_amount'  => __( 'Never show quantity remaining in stock', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'default' => '',
                ),

                array(
                    'name' => __( 'Prevent Stock Reduction', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'checkbox',
                    'desc' => __( 'Don’t reduce stock count on products for wholesale orders.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_settings_prevent_stock_reduction',
                ),

                array(
                    'name' => __( 'Allow Add To Cart Below Product Minimum', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'checkbox',
                    'desc' => __( 'Lets customers add quantity lower than the specified minimum amount.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_settings_allow_add_to_cart_below_product_minimum',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_general_wholesale_products_section_sectionend',
                ),

                array(
                    'name' => __( 'Wholesale Customer Capabilities', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => __( 'These settings describe some of the additional capabilities of your wholesale customers.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_general_wholesale_customer_capabities_section_title',
                ),

                array(
                    'name'     => __( 'Disable Coupons For Wholesale Users', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'Globally turn off coupons functionality for customers with a wholesale user role.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'This applies to all customers with a wholesale role.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_disable_coupons_for_wholesale_users',
                    'class'    => 'wwpp_settings_disable_coupons_for_wholesale_users',
                ),

                array(
                    'name'     => __( 'Always Allow Backorders', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'Even if backorders are disallowed on the product, still allow wholesale customers to make a backorder.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'This overrides the defined backorder behavior for the product for all customers with a wholesale role.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_always_allow_backorders_to_wholesale_users',
                ),

                array(
                    'name'     => __( 'Show Backorders Notice When Allowed', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'If backorders are allowed for wholesale customers (as per Always Allow Backorders setting), notify the customer that the product is "Available on backorder".', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'Shows the standard "Available on backorder" notice from WooCommerce. This setting is ignored when "Always Allow Backorders" is off.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_show_back_order_notice_wholesale_users',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_general_wholesale_customer_capabities_section_sectionend',
                ),

                array(
                    'name' => __( 'Misc', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => __( 'These settings handle other miscellaneous parts of the way your wholesale system works.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_general_misc_section_title',
                ),

                array(
                    'name'     => __( 'Wholesale Order Received Thank You Message', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'wwpp_editor',
                    'desc'     => __( 'Show wholesale customers a thank you message with important information on the Order Received screen.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'This message is only shown on the Order Received screen.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_thankyou_message',
                    'css'      => 'min-width: 400px; min-height: 100px;',
                ),

                array(
                    'name'     => __( 'Wholesale Order Received Thank You Message Position', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'select',
                    'desc'     => __( 'Choose to whether to replace the standard thank you message from WooCommerce, prepend or append the message on the Order Received screen.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'The standard WooCommerce thank you message is "Thank you. Your order has been received."', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_thankyou_message_position',
                    'options'  => array(
                        'replace' => __( 'Replace', 'woocommerce-wholesale-prices-premium' ),
                        'append'  => __( 'Append', 'woocommerce-wholesale-prices-premium' ),
                        'prepend' => __( 'Prepend', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'default'  => 'replace',
                ),

                array(
                    'name'     => __( 'Clear Cart On Login', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'Clears the wholesale customer’s shopping after they log in to ensure a fresh session.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'This can help if you are having issues with old orders being retained in the cart.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_clear_cart_on_login',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_general_misc_section_sectionend',
                ),

            );
        }

        /**
         * Price settings section options.
         *
         * @since  1.14.0
         * @access public
         *
         * @return array Array of premium options for the plugin's price settings section.
         */
        private function _get_price_section_settings() {

            return array(

                array(
                    'name'     => __( 'Show Wholesale Saving Amount', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'If checked, displays the saving amount and percentage of retail price on the shop page, single product page, cart page, checkout page, order page and email invoice.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => '',
                    'id'       => 'wwpp_settings_show_saving_amount',
                    'class'    => 'wwpp_settings_show_saving_amount',
                ),

                array(
                    'name' => __( 'Always Use Regular Price', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'checkbox',
                    'desc' => __( 'When calculating the wholesale price by using a percentage (global discount %, category based %, or quantity based %) always ensure the Regular Price is used and ignore the Sale Price if present.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc',
                ),

                array(
                    'name'     => __( 'Variable product price display', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'select',
                    'desc'     => __( 'Specify the format in which variable product prices are displayed. Only for wholesale customers.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => true,
                    'id'       => 'wwpp_settings_variable_product_price_display',
                    'options'  => array(
                        'price-range' => __( 'Price Range', 'woocommerce-wholesale-prices-premium' ),
                        'minimum'     => __( 'Minimum Price', 'woocommerce-wholesale-prices-premium' ),
                        'maximum'     => __( 'Maximum Price', 'woocommerce-wholesale-prices-premium' ),
                    ),
                ),

                array(
                    'name' => __( 'Hide wholesale price on admin product listing', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'checkbox',
                    'desc' => __( 'If checked, hides wholesale price per wholesale role on the product listing on the admin page.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_hide_wholesale_price_on_product_listing',
                ),

            );
        }

        /**
         * Tax settings section options.
         *
         * @since  1.4.2
         * @since  1.12.8 Refactor codebase.
         * @access public
         *
         * @return array Array of premium options for the plugin's tax settings section.
         */
        private function _get_tax_section_settings() {

            return array(

                array(
                    'name' => __( 'Tax Options', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => '',
                    'id'   => 'wwpp_settings_tax_section_title',
                ),

                array(
                    'name'     => __( 'Tax Exemption', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'Do not apply tax to all wholesale roles', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'Removes tax for all wholesale roles. All wholesale prices will display excluding tax throughout the store, cart and checkout. The display settings below will be ignored.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_tax_exempt_wholesale_users',
                ),

                array(
                    'name'     => __( 'Display Prices in the Shop', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'select',
                    'desc'     => __( 'Choose how wholesale roles see all prices throughout your shop pages.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'Note: If the option above of "Tax Exempting" wholesale users is enabled, then wholesale prices on shop pages will not include tax regardless the value of this option.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_incl_excl_tax_on_wholesale_price',
                    'options'  => array(
                        ''     => __( '--Use woocommerce default--', 'woocommerce-wholesale-prices-premium' ),
                        'incl' => __( 'Including tax', 'woocommerce-wholesale-prices-premium' ),
                        'excl' => __( 'Excluding tax', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'default'  => '',
                ),

                array(
                    'name'     => __( 'Display Prices During Cart and Checkout', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'select',
                    'desc'     => __( 'Choose how wholesale roles see all prices on the cart and checkout pages.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'Note: If the option above of "Tax Exempting" wholesale users is enabled, then wholesale prices on cart and checkout page will not include tax regardless the value of this option.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_wholesale_tax_display_cart',
                    'options'  => array(
                        ''     => __( '--Use woocommerce default--', 'woocommerce-wholesale-prices-premium' ),
                        'incl' => __( 'Including tax', 'woocommerce-wholesale-prices-premium' ),
                        'excl' => __( 'Excluding tax', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'default'  => '',
                ),

                array(
                    'name'     => __( 'Override Regular Price Suffix', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'text',
                    'desc'     => __( 'Override the price suffix on regular prices for wholesale users.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'Make this blank to use the default price suffix. You can also use prices substituted here using one of the following {price_including_tax} and {price_excluding_tax}.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_override_price_suffix_regular_price',
                ),

                array(
                    'name'     => __( 'Wholesale Price Suffix', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'text',
                    'desc'     => __( 'Set a specific price suffix specifically for wholesale prices.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'Make this blank to use the default price suffix. You can also use prices substituted here using one of the following {price_including_tax} and {price_excluding_tax}.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_override_price_suffix',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_tax_divider1_sectionend',
                ),

                array(
                    'name' => __( 'Wholesale Role / Tax Exemption Mapping', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => __( 'Specify tax exemption per wholesale role. Overrides general <b>"Tax Exemption"</b> option above.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_settings_wholesale_role_tax_exemption_mapping_section_title',
                ),

                array(
                    'name' => '',
                    'type' => 'wholesale_role_tax_options_mapping_controls',
                    'desc' => '',
                    'id'   => 'wwpp_settings_shipping_section_shipping_controls',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_tax_divider2_sectionend',
                ),

                array(
                    'name' => __( 'Wholesale Role / Tax Class Mapping', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => __( 'Specify tax classes per wholesale role.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_settings_wholesale_role_tax_class_mapping_section_title',
                ),

                array(
                    'name' => __( 'Wholesale Only Tax Classes', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'checkbox',
                    'desc' => __( 'Hide the mapped tax classes from non-wholesale customers. Non-wholesale customers will no longer be able to see the tax classes you have mapped below. Warning: If a product uses one of the mapped tax classes, customers whose roles are not included on the mapping below (including guest users) will be taxed using the standard tax class.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_settings_mapped_tax_classes_for_wholesale_users_only',
                ),

                array(
                    'name' => '',
                    'type' => 'wholesale_role_tax_class_options_mapping_controls',
                    'desc' => '',
                    'id'   => 'wwpp_wholesale_role_tax_class_options_mapping',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_tax_sectionend',
                ),

            );
        }

        /**
         * Shipping settings section options.
         *
         * @since  1.0.3
         * @since  1.12.8 Refactor codebase.
         * @access public
         *
         * @return array Array of premium options for the plugin's shipping settings section.
         */
        private function _get_shipping_section_settings() {

            return array(

                array(
                    'name' => __( 'Shipping Options', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => '',
                    'id'   => 'wwpp_settings_shipping_section_title',
                ),

                array(
                    'name'     => __( 'Force Free Shipping', 'woocommerce-wholesale-prices-premium' ),
                    'type'     => 'checkbox',
                    'desc'     => __( 'Forces all wholesale roles to use free shipping. All other shipping methods will be removed.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'Note: If a wholesale role has ANY mappings in the table below, free shipping will not be forced.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_wholesale_users_use_free_shipping',
                ),

                array(
                    'name' => __( 'Free Shipping Label', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'text',
                    'desc' => __( 'If <b>"Force Free Shipping"</b> is enabled, a dynamically created free shipping method is created and used by force. The label for this defaults to <b>"Free Shipping"</b> but you can override that here.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_dynamic_free_shipping_title',
                ),

                array(
                    'name' => __( 'Wholesale Only Shipping Methods', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'checkbox',
                    'desc' => __( 'Hide the mapped shipping methods from non-wholesale customers. Regular customers will no longer be able to see the shipping methods you have mapped below.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_settings_mapped_methods_for_wholesale_users_only',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_shipping_divider1_sectionend',
                ),

                array(
                    'name' => __( 'Wholesale Role/Shipping Method Mapping', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => __(
                        'Map the shipping methods you wish to restrict wholesale customers to use within a shipping zone.<br/><br/>
                                     <b>1.</b> Select the wholesale role you wish to restrict<br/>
                                     <b>2.</b> Choose the shipping zone you want this to apply to<br/>
                                     <b>3.</b> Finally, choose the shipping method in that shipping zone that you wish to restrict the selected wholesale role to.<br/><br/>
                                     You can repeat this process to map multiple shipping methods per zone & multiple zones per role.
                                     <h2>Non-Zoned Shipping Methods</h2>
                                     <p>Non-Zoned shipping methods covers third party shipping methods extensions that register their shipping methods globally meaning they appear to the user always and do not take the shipping zone into account at all.<br/><br/></p>
                                     <p>To map these non-zoned methods, please select the <b>"Use Non-Zoned Shipping Methods"</b> checkbox and select the method from the list.</p>',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    'id'   => 'wwpp_settings_wholesale_shipping_section_title',
                ),

                array(
                    'name' => '',
                    'type' => 'shipping_controls',
                    'desc' => '',
                    'id'   => 'wwpp_settings_shipping_section_shipping_controls',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_shipping_sectionend',
                ),

            );
        }

        /**
         * Discount settings section options.
         *
         * @since  1.2.0
         * @since  1.12.8 Refactor codebase.
         * @access public
         *
         * @return array Array of premium options for the plugin's discount settings section.
         */
        private function _get_discount_section_settings() {

            return array(

                array(
                    'name' => __( 'General Discount Options', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => __( 'This is where you set <b>"general discount"</b> for each wholesale role that will be applied to those users<br/>if a product they wish to purchase has no wholesale price set and no wholesale discount set at the product category level.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_settings_discount_section_title',
                ),

                array(
                    'name' => '',
                    'type' => 'discount_controls',
                    'desc' => '',
                    'id'   => 'wwpp_settings_discount_section_discount_controls',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_general_discount_section_sectionend',
                ),

                array(
                    'name' => __( 'General Quantity Based Discounts', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => __( 'Give an additional quantity based discount when using the global General Discount for that wholesale role.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_settings_qty_discount_section_title',
                ),

                array(
                    'name' => __( 'Enable General Quantity Based Discounts', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'checkbox',
                    'desc' => __( 'Turns the general quantity based discount system on/off. Mappings below will be disregarded if this option is unchecked.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'enable_wholesale_role_cart_quantity_based_wholesale_discount',
                ),

                array(
                    'name' => __( 'Apply Discounts Based On Individual Product Quantities?', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'checkbox',
                    'desc' => __( 'By default, the general quantity based discounts system will use the total quantity of all items in the cart. This option changes this to apply quantity based discounts based on the quantity of individual products in the cart.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'enable_wholesale_role_cart_quantity_based_wholesale_discount_mode_2',
                ),

                array(
                    'name' => '',
                    'type' => 'general_cart_qty_based_discount_controls',
                    'desc' => '',
                    'id'   => 'wwpp_settings_discount_section_qty_discount_controls',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_qty_based_discount_section_sectionend',
                ),

                array(
                    'name' => __( 'Cart Subtotal Price Discounts', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => __(
                        'Optionally give an additional discount for specific wholesale roles based on the cart subtotal price (excluding taxes & shipping). Discounts are shown in the totals table and are applied to the entire order.<br/><br/>
                                Simply select a wholesale user role, the subtotal price at which the additional discount should start to apply to their order, and the discount amount to give either as a fixed amount or a percentage of the cart subtotal.<br/><br/>
                                You can tier additional discounts by adding extra subtotal price breakpoints. Only one discount will apply.',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    'id'   => 'wwpp_settings_total_prie_based_discount_title',
                ),

                array(
                    'name' => __( 'Only Apply Discounts If Minimum Order Requirements Met', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'checkbox',
                    'desc' => __( 'When enabled, prevents the customer from getting the below additional discounts if they haven’t met the minimum order requirements.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'enable_wholesale_role_cart_only_apply_discount_if_min_order_req_met',
                ),

                array(
                    'name' => '',
                    'type' => 'cart_subtotal_price_based_discount_controls',
                    'desc' => '',
                    'id'   => 'wwpp_settings_cart_subtotal_price_based_discount_controls',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_cart_total_price_discount_sectionend',
                ),

            );
        }

        /**
         * Payment gateway surcharge settings section options.
         *
         * @since  1.3.0
         * @since  1.12.8 Refactor codebase.
         * @access public
         *
         * @return array Array of premium options for the plugin's payment gateway settings section.
         */
        private function _get_payment_gateway_section_settings() {

            return array(

                array(
                    'name' => __( 'Payment Gateway Options', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => '',
                    'id'   => 'wwpp_settings_payment_gateway_section_title',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_payment_gateway_first_sectionend',
                ),

                array(
                    'name' => __( 'Wholesale Role / Payment Gateway', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => __( 'You can specify what payment gateways are available per wholesale role (Note that payment gateway need not be enabled)', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_settings_payment_gateway_surcharge_section_title',
                ),

                array(
                    'name' => '',
                    'type' => 'wholesale_role_payment_gateway_controls',
                    'desc' => '',
                    'id'   => 'wwpp_settings_payment_gateway_wholesale_role_mapping',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_payment_gateway_section_sectionend',
                ),

                array(
                    'name' => __( 'Wholesale Role / Payment Gateway Surcharge', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => __( 'You can specify extra cost per payment gateway per wholesale role', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_settings_payment_gateway_surcharge_section_title',
                ),

                array(
                    'name' => '',
                    'type' => 'payment_gateway_surcharge_controls',
                    'desc' => '',
                    'id'   => 'wwpp_settings_payment_gateway_section_surcharge',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_payment_gateway_sectionend',
                ),

            );
        }

        /**
         * Cache settings section options.
         *
         * @since  1.6.0]
         * @access public
         *
         * @return array Array of premium options for the plugin's cache settings section.
         */
        private function _get_cache_section_settings() {

            return array(

                array(
                    'name' => __( 'Cache Options', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => '',
                    'id'   => 'wwpp_settings_cache_section_title',
                ),

                array(
                    'name' => __( 'Enable wholesale price caching', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'checkbox',
                    'desc' => __( 'When enabled, products with wholesale price will be cached to improve loading time when the product has multiple tier of wholesale prices.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_enable_wholesale_price_cache',
                ),

                array(
                    'name' => __( 'Enable variable product price range caching', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'checkbox',
                    'desc' => __( 'When enabled, wholesale price ranges for variable products will be cached by the system. This speeds up the loading of your category pages and single product pages.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_enable_var_prod_price_range_caching',
                ),

                array(
                    'name' => __( 'Clear all variable product price range and wholesale price cache', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'wwpp_clear_var_prod_price_range_caching',
                    'desc' => __( 'Clear all variable product price range and wholesale price cache.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_clear_var_prod_price_range_caching',
                ),

                array(
                    'name' => __( 'Enable product ID caching', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'checkbox',
                    'desc' => __( 'When enabled, product IDs will be cached for visibility purposes for each user role to reduce the load time for large product catalogs.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_enable_product_cache',
                ),

                array(
                    'name' => '',
                    'type' => 'wwpp_clear_product_caching',
                    'desc' => '',
                    'id'   => 'wwpp_clear_product_caching',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_cache_sectionend',
                ),

            );
        }

        /**
         * Help settings section options.
         *
         * @since  1.3.0
         * @since  1.12.8 Refactor codebase.
         * @access public
         *
         * @return array Array of premium options for the plugin's help settings section.
         */
        private function _get_help_section_settings() {

            return array(

                array(
                    'name' => __( 'Help Options', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => '',
                    'id'   => 'wwpp_settings_help_section_title',
                ),

                array(
                    'name' => '',
                    'type' => 'help_resources_controls',
                    'desc' => '',
                    'id'   => 'wwpp_settings_help_resources',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_help_devider1',
                ),

                array(
                    'name' => __( 'Debug Tools', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'title',
                    'desc' => '',
                    'id'   => 'wwpp_settings_help_debug_tools_title',
                ),

                array(
                    'name' => '',
                    'type' => 'clear_unused_product_meta_button',
                    'desc' => '',
                    'id'   => 'wwpp_settings_clear_unused_product_meta_button',
                ),

                array(
                    'name' => '',
                    'type' => 'initialize_product_visibility_meta_button',
                    'desc' => '',
                    'id'   => 'wwpp_settings_initialize_product_visibility_meta_button',
                ),

                array(
                    'name' => __( 'Clean up plugin options on un-installation', 'woocommerce-wholesale-prices-premium' ),
                    'type' => 'checkbox',
                    'desc' => __( 'If checked, removes all plugin options when this plugin is uninstalled. <b>Warning:</b> This process is irreversible.', 'woocommerce-wholesale-prices-premium' ),
                    'id'   => 'wwpp_settings_help_clean_plugin_options_on_uninstall',
                ),

                array(
                    'name' => '',
                    'type' => 'force_fetch_update_data_button',
                    'desc' => '',
                    'id'   => 'wwpp_settings_force_fetch_update_data_button',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_help_sectionend',
                ),

            );
        }

        /**
         * Upgrade settings section.
         *
         * @since  1.24
         * @access public
         *
         * @return array Array of premium options for the plugin's help settings section.
         */
        private function _get_upgrade_section_settings() {

            if ( WWPP_Helper_Functions::is_wwof_active() && WWPP_Helper_Functions::is_wwlc_active() ) {
                return array();
            }

            return array(

                array(
                    'name' => '',
                    'type' => 'title',
                    'desc' => '',
                    'id'   => 'wwpp_settings_upgrade_section_title',
                ),

                array(
                    'name' => '',
                    'type' => 'wwpp_upgrade_content',
                    'desc' => '',
                    'id'   => 'wwpp_settings_upgrade_content',
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_upgrade_sectionend',
                ),

            );
        }

        /**
         * WWS License options
         *
         * @since  1.30.3.1
         * @access private
         *
         * @return array
         */
        private function _get_license_section_settings() {

            return array(

                array(
                    'name'  => '',
                    'title' => '',
                    'desc'  => '',
                    'id'    => 'wwpp_settings_license_section_title',
                ),
                array(
                    'name' => '',
                    'type' => 'license_upgrade_content',
                    'desc' => '',
                    'id'   => 'wwpp_settings_license_upgrade_content',
                ),
                array(
                    'type' => 'sectionend',
                    'id'   => 'wwpp_settings_license_sectionend',
                ),

            );
        }

        /**
         * Render WWS License upgrade content
         *
         * @since  1.30.3.1
         * @access public
         */
        public function render_license_upgrade_content() {

            if ( isset( $_GET['section'] ) && 'wwpp_license_section' === $_GET['section'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $license_settings_url = is_multisite() ? network_admin_url( 'admin.php?page=wws-ms-license-settings' ) : admin_url( 'admin.php?page=wws-license-settings' );
                wp_safe_redirect( $license_settings_url );
                exit;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Custom Settings Fields
        |--------------------------------------------------------------------------
         */

        /**
         * Wholesale role shipping options mapping.
         * WooCommerce > Settings > Wholesale Prices > Shipping > Wholesale Role/Shipping Method Mapping
         *
         * @since  1.0.3
         * @since  1.12.8 Refactor codebase.
         * @access public
         */
        public function render_plugin_settings_custom_field_shipping_controls() {

            if ( version_compare( WC()->version, '2.6.0', '<' ) ) {

                $all_wholesale_roles      = $this->wwppGetAllRegisteredWholesaleRoles( null, false );
                $wc_shipping_methods      = WC_Shipping::instance()->load_shipping_methods();
                $saved_mapping            = get_option( WWPP_OPTION_WHOLESALE_ROLE_SHIPPING_METHOD_MAPPING, array() );
                $table_rate_shipping_type = $this->checkTable_rate_shipping_type();

                if ( ! is_array( $all_wholesale_roles ) ) {
                    $all_wholesale_roles = array();
                }

                if ( ! is_array( $wc_shipping_methods ) ) {
                    $wc_shipping_methods = array();
                }

                if ( ! is_array( $saved_mapping ) ) {
                    $saved_mapping = array();
                }

                if ( 'code_canyon' === $table_rate_shipping_type ) {
                    $cc_shipping_zones = get_option( 'be_woocommerce_shipping_zones', array() );
                } elseif ( 'mango_hour' === $table_rate_shipping_type ) {

                    $mh_shipping_zones    = get_option( 'mh_wc_table_rate_plus_zones', array() );
                    $mh_shipping_services = get_option( 'mh_wc_table_rate_plus_services', array() );

                }

                // Legacy shipping functionality.
                require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-shipping-controls-custom-field.php';

            } else {

                // New shipping functionality ( WC 2.6.0 ).
                $all_wholesale_roles    = $this->_all_wholesale_roles;
                $wc_shipping_zones      = WC_Shipping_Zones::get_zones();
                $wc_default_zone        = WC_Shipping_Zones::get_zone( 0 );
                $wholesale_zone_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_SHIPPING_ZONE_METHOD_MAPPING, array() );

                $non_zoned_shipping_methods = array();
                $wc_shipping_methods        = WC()->shipping->load_shipping_methods();

                foreach ( $wc_shipping_methods as $shipping_method ) {
                    if ( ! $shipping_method->supports( 'shipping-zones' ) && 'yes' === $shipping_method->enabled ) {
                        $non_zoned_shipping_methods[ $shipping_method->id ] = $shipping_method;
                    }
                }

                require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-shipping-controls-custom-field-wc-2.6.php';

            }
        }

        /**
         * Wholesale role general wholesale discount options mapping.
         * WooCommerce > Settings > Wholesale Prices > Discount > General Discount Options
         *
         * @since  1.2.0
         * @since  1.12.8 Refactor codebase.
         * @access public
         */
        public function render_plugin_settings_custom_field_discount_controls() {

            $all_wholesale_roles = $this->_all_wholesale_roles;

            $saved_general_discount = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, array() );
            if ( ! is_array( $saved_general_discount ) ) {
                $saved_general_discount = array();
            }

            require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-discount-controls-custom-field.php';
        }

        /**
         * Wholesale role per cart qty wholesale discount options mapping.
         * WooCommerce > Settings > Wholesale Prices > Discount > General Quantity Based Discounts
         *
         * @since  1.16.0
         * @access public
         */
        public function render_plugin_settings_custom_field_general_cart_qty_based_discount_controls() {

            $all_wholesale_roles       = $this->_all_wholesale_roles;
            $cart_qty_discount_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, array() );
            if ( ! is_array( $cart_qty_discount_mapping ) ) {
                $cart_qty_discount_mapping = array();
            }

            ?>

            <tr valign="top" id="wholesale-role-cart-qty-based-wholesale-discount-container">
                <th colspan="2" scope="row" class="titledesc">

                    <?php require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-general-cart-qty-based-discount-controls-custom-field.php'; ?>

                </th>
            </tr>

            <?php
        }

        /**
         * Wholesale role payment gateway surcharge options mapping.
         * WooCommerce > Settings > Wholesale Prices > Payment Gateway > Wholesale Role / Payment Gateway Surcharge
         *
         * @since  1.3.0
         * @since  1.12.8 Refactor codebase.
         * @access public
         */
        public function render_plugin_settings_custom_field_payment_gateway_surcharge_controls() {

            $all_wholesale_roles = $this->_all_wholesale_roles;

            $payment_gateway_surcharge = get_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING, array() );
            if ( ! is_array( $payment_gateway_surcharge ) ) {
                $payment_gateway_surcharge = array();
            }

            $available_gateways = WC()->payment_gateways->payment_gateways();
            if ( ! is_array( $available_gateways ) ) {
                $available_gateways = array();
            }

            $surcharge_types = array(
                'fixed_price' => __( 'Fixed Price', 'woocommerce-wholesale-prices-premium' ),
                'percentage'  => __( 'Percentage', 'woocommerce-wholesale-prices-premium' ),
            );
            ?>

            <tr valign="top">
                <th colspan="2" scope="row" class="titledesc">

                    <?php require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-payment-gateway-surcharge-controls-custom-field.php'; ?>

                    <style>
                    p.submit {
                        display : none !important;
                    }
                    </style>

                </th>
            </tr>

            <?php
        }

        /**
         * Wholesale role payment gateway options mapping.
         * WooCommerce > Settings > Wholesale Prices > Payment Gateway > Wholesale Role / Payment Gateway
         *
         * @since  1.3.0
         * @since  1.12.8 Refactor codebase.
         * @access public
         */
        public function render_plugin_settings_custom_field_wholesale_role_payment_gateway_controls() {

            $all_wholesale_roles = $this->_all_wholesale_roles;

            $wholesale_role_payment_gateway_papping = get_option( WWPP_OPTION_WHOLESALE_ROLE_PAYMENT_GATEWAY_MAPPING, array() );
            if ( ! is_array( $wholesale_role_payment_gateway_papping ) ) {
                $wholesale_role_payment_gateway_papping = array();
            }

            $available_gateways = WC()->payment_gateways->payment_gateways();
            if ( ! is_array( $available_gateways ) ) {
                $available_gateways = array();
            }

            require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-wholesale-role-payment-gateway-controls-custom-field.php';
        }

        /**
         * Plugin knowledge base custom control.
         * WooCommerce > Settings > Wholesale Prices > Help > Knowledge Base
         *
         * @since  1.4.1
         * @since  1.12.8 Refactor codebase.
         * @access public
         */
        public function render_plugin_settings_custom_field_help_resources_controls() {

            require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-help-resources-controls-custom-field.php';
        }

        /**
         * Wholesale role tax exemption mapping.
         * WooCommerce > Settings > Wholesale Prices > Tax > Wholesale Role / Tax Exemption Mapping.
         *
         * @since  1.5.0
         * @since  1.12.8 Refactor codebase.
         * @access public
         */
        public function render_plugin_settings_custom_field_wholesale_role_tax_options_mapping_controls() {

            $all_wholesale_roles = $this->_all_wholesale_roles;

            $wholesale_role_tax_options = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_OPTION_MAPPING, array() );
            if ( ! is_array( $wholesale_role_tax_options ) ) {
                $wholesale_role_tax_options = array();
            }

            require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-wholesale-role-tax-options-mapping-controls-custom-field.php';
        }

        /**
         * Wholesale role tax class options mapping.
         *
         * WooCommerce > Settings > Wholesale Prices > Tax > Wholesale Role / Tax Class Mapping.
         *
         * @since  1.16.0
         * @access public
         */
        public function render_plugin_settings_custom_field_wholesale_role_tax_class_options_mapping_controls() {

            $wc_tax_classes = WC_Tax::get_tax_classes();
            if ( ! is_array( $wc_tax_classes ) ) {
                $wc_tax_classes = array();
            }

            $all_wholesale_roles   = $this->_all_wholesale_roles;
            $processed_tax_classes = array();

            foreach ( $wc_tax_classes as $tax_class ) {
                $processed_tax_classes[ sanitize_title( $tax_class ) ] = $tax_class;
            }

            $wholesale_role_tax_class_options = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_CLASS_OPTIONS_MAPPING, array() );
            if ( ! is_array( $wholesale_role_tax_class_options ) ) {
                $wholesale_role_tax_class_options = array();
            }

            ?>

            <tr valign="top">
                <th colspan="2" scope="row" class="titledesc">
                    <div id="wholesale-role-tax-class-options">

                        <?php require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-wholesale-role-tax-class-options-mapping-controls-custom-field.php'; ?>

                    </div>
                </th>
            </tr>

            <?php
        }

        /**
         * Product Visibility Meta.
         * WooCommerce > Settings > Wholesale Prices > Help > Clear Unused Product Meta
         *
         * @since  1.23.9
         * @access public
         */
        public function render_plugin_settings_custom_field_clear_unused_product_meta_button() {

            require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-clear-unused-product-meta-button-custom-field.php';
        }

        /**
         * Product Visibility Meta.
         * WooCommerce > Settings > Wholesale Prices > Help > Product Visibility Meta
         *
         * @since  1.5.2
         * @since  1.12.8 Refactor codebase.
         * @access public
         */
        public function render_plugin_settings_custom_field_initialize_product_visibility_meta_button() {

            require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-initialize-product-visibility-meta-button-custom-field.php';
        }

        /**
         * Clear Update Data.
         * WooCommerce > Settings > Wholesale Prices > Help > Clear Update Data
         *
         * @since  1.26.5
         * @access public
         */
        public function render_plugin_settings_custom_field_force_fetch_update_data_button() {

            require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-force-fetch-update-data-button-custom-field.php';
        }

        /**
         * Render wwpp editor custom field.
         *
         * @param array $data Custom field data.
         *
         * @since  1.12.8 Refactor codebase.
         * @access public
         *
         * @since  1.7.4
         */
        public function render_plugin_settings_custom_field_wwpp_editor( $data ) { // phpcs:ignore
            require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-editor.php';
        }

        /**
         * Render wwpp clear variable product price range cache.
         *
         * @since  1.16.0
         * @access public
         */
        public function render_plugin_settings_custom_field_wwpp_clear_var_prod_price_range_caching() {

            require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-clear-var-prod-price-range-caching.php';
        }

        /**
         * Render wwpp clear variable product price range cache.
         *
         * @since  1.23.2
         * @access public
         */
        public function render_plugin_settings_custom_field_wwpp_clear_product_caching() {

            require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-clear-product-caching.php';
        }

        /**
         * Render wwpp upgrade tab content.
         *
         * @since  1.24
         * @access public
         */
        public function render_wwpp_upgrade_content() {

            require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-upgrade-content.php';
        }

        /**
         * Remove WC Params on Payment Gateway tab.
         *
         * @param array $tabs Array of settings tabs.
         *
         * @since  1.24.6
         * @access public
         *
         * @return array
         */
        public function remove_settings_params( $tabs ) {

            // phpcs:disable WordPress.Security.NonceVerification.Recommended
            if ( isset( $_GET['tab'] ) && 'wwp_settings' === $_GET['tab'] &&
                isset( $_GET['section'] ) && 'wwpp_setting_payment_gateway_section' === $_GET['section'] ) {
                global $wp_scripts;

                if ( isset( $wp_scripts->registered['woocommerce_settings'] ) ) {
                    $wc_settings = $wp_scripts->registered['woocommerce_settings'];
                    if ( $wc_settings->extra && isset( $wc_settings->extra['data'] ) ) {
                        $wp_scripts->registered['woocommerce_settings']->extra['data'] = 'var woocommerce_settings_params = {}';
                    }
                }
            }

            // phpcs:enable WordPress.Security.NonceVerification.Recommended
            return $tabs;
        }

        /**
         * Wholesale role general wholesale discount options mapping.
         * WooCommerce > Settings > Wholesale Prices > Discount > General Discount Options
         *
         * @since  1.2.0
         * @since  1.12.8 Refactor codebase.
         * @access public
         */
        public function render_cart_subtotal_price_based_discount_controls() {

            $all_wholesale_roles                        = $this->_all_wholesale_roles;
            $cart_subtotal_price_based_discount_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_SUBTOTAL_PRICE_BASED_DISCOUNT_MAPPING, array() );

            require_once WWPP_VIEWS_PATH . 'plugin-settings-custom-fields/view-wwpp-cart-subtotal-price-based-discount.php';
        }

        /**
         * Save subfields settings, useful if you have sub fields in your settings fields.
         * Add then save based on $current_section of the subfields settings location so we can save values on subfields.
         *
         * @param array $settings        Current Settings.
         * @param array $current_section Current Section.
         *
         * @since 1.29
         */
        public function save_sub_fields( $settings, $current_section ) {

            // WWPP Prices Settings.
            if ( 'wwpp_setting_price_section' === $current_section ) {

                // For Show Wholesale Saving Amount sub fields.
                $wwpp_show_wholesale_saving_amount_settings = array(

                    array(
                        'type' => 'checkbox',
                        'id'   => 'wwpp_settings_show_saving_amount_page_shop',
                    ),

                    array(
                        'type' => 'checkbox',
                        'id'   => 'wwpp_settings_show_saving_amount_page_single_product',
                    ),

                    array(
                        'type' => 'checkbox',
                        'id'   => 'wwpp_settings_show_saving_amount_page_cart',
                    ),

                    array(
                        'type' => 'checkbox',
                        'id'   => 'wwpp_settings_show_saving_amount_page_invoice',
                    ),

                    array(
                        'type' => 'text',
                        'id'   => 'wwpp_settings_show_saving_amount_text',
                    ),
                );

                WC_Admin_Settings::save_fields( $wwpp_show_wholesale_saving_amount_settings );
            }
        }

        /**
         * Execute model.
         *
         * @since  1.12.8
         * @access public
         */
        public function run() {
            // Extend WWP to include WWPP settings.
            add_filter( 'wwp_filter_settings_sections', array( $this, 'plugin_settings_sections' ), 10, 1 );
            add_filter( 'wwp_settings_section_content', array( $this, 'plugin_settings_section_content' ), 10, 2 );
            add_action( 'wwp_before_save_settings', array( $this, 'save_editor_custom_field_type' ), 10, 1 );
            add_action( 'wwp_before_save_settings', array( $this, 'save_sub_fields' ), 20, 2 );

            // General Tab.
            add_action(
                'woocommerce_admin_field_wwpp_editor',
                array(
                    $this,
                    'render_plugin_settings_custom_field_wwpp_editor',
                ),
                10,
                1
            );

            // Tax Tab.
            add_action(
                'woocommerce_admin_field_wholesale_role_tax_options_mapping_controls',
                array(
                    $this,
                    'render_plugin_settings_custom_field_wholesale_role_tax_options_mapping_controls',
                ),
                10
            );
            add_action(
                'woocommerce_admin_field_wholesale_role_tax_class_options_mapping_controls',
                array(
                    $this,
                    'render_plugin_settings_custom_field_wholesale_role_tax_class_options_mapping_controls',
                ),
                10
            );

            // Shipping Tab.
            add_action(
                'woocommerce_admin_field_shipping_controls',
                array(
                    $this,
                    'render_plugin_settings_custom_field_shipping_controls',
                ),
                10
            );

            // Discount Tab.
            add_action(
                'woocommerce_admin_field_discount_controls',
                array(
                    $this,
                    'render_plugin_settings_custom_field_discount_controls',
                ),
                10
            );
            add_action(
                'woocommerce_admin_field_general_cart_qty_based_discount_controls',
                array(
                    $this,
                    'render_plugin_settings_custom_field_general_cart_qty_based_discount_controls',
                )
            );
            add_action(
                'woocommerce_admin_field_cart_subtotal_price_based_discount_controls',
                array(
                    $this,
                    'render_cart_subtotal_price_based_discount_controls',
                )
            );

            // Payment Gateway Tab.
            add_action(
                'woocommerce_admin_field_payment_gateway_surcharge_controls',
                array(
                    $this,
                    'render_plugin_settings_custom_field_payment_gateway_surcharge_controls',
                ),
                10
            );
            add_action(
                'woocommerce_admin_field_wholesale_role_payment_gateway_controls',
                array(
                    $this,
                    'render_plugin_settings_custom_field_wholesale_role_payment_gateway_controls',
                ),
                10
            );

            // Cache Tab.
            add_action(
                'woocommerce_admin_field_wwpp_clear_var_prod_price_range_caching',
                array(
                    $this,
                    'render_plugin_settings_custom_field_wwpp_clear_var_prod_price_range_caching',
                ),
                10
            );
            add_action(
                'woocommerce_admin_field_wwpp_clear_product_caching',
                array(
                    $this,
                    'render_plugin_settings_custom_field_wwpp_clear_product_caching',
                ),
                10
            );

            // Help Tab.
            add_action(
                'woocommerce_admin_field_help_resources_controls',
                array(
                    $this,
                    'render_plugin_settings_custom_field_help_resources_controls',
                ),
                10
            );

            // Debug Tab.
            add_action(
                'woocommerce_admin_field_clear_unused_product_meta_button',
                array(
                    $this,
                    'render_plugin_settings_custom_field_clear_unused_product_meta_button',
                ),
                10
            );
            add_action(
                'woocommerce_admin_field_initialize_product_visibility_meta_button',
                array(
                    $this,
                    'render_plugin_settings_custom_field_initialize_product_visibility_meta_button',
                ),
                10
            );

            // License Tab.
            add_action(
                'woocommerce_admin_field_license_upgrade_content',
                array(
                    $this,
                    'render_license_upgrade_content',
                ),
                10
            );

            if ( is_main_site() ) {
                add_action(
                    'woocommerce_admin_field_force_fetch_update_data_button',
                    array(
                        $this,
                        'render_plugin_settings_custom_field_force_fetch_update_data_button',
                    ),
                    10
                );
            }

            // Upgrade Tab.
            add_action( 'woocommerce_admin_field_wwpp_upgrade_content', array( $this, 'render_wwpp_upgrade_content' ) );

            // Remove wc params on wwlc setting that has no save changes button.
            add_filter( 'woocommerce_settings_tabs_array', array( $this, 'remove_settings_params' ), 10, 1 );
        }
    }
}
