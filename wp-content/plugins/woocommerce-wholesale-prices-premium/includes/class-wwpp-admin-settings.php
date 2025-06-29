<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Admin_Settings' ) ) {
    /**
     * Model that houses the logic of WooCommerce Wholesale Prices Premium Settings page.
     *
     * @since 1.0.0
     */
    class WWPP_Admin_Settings {

        /**
         * Property that holds the single main instance of WWPP_Dashboard.
         *
         * @since  2.0
         * @access private
         * @var WWPP_Dashboard
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to wholesale license manager.
         *
         * @since  2.0.3
         * @access private
         * @var WWPP_WWS_License_Manager
         */
        private $_wwpp_wws_license_manager;

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since  2.0
         * @access private
         * @var WWP_Wholesale_Roles
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
         * WWPP_Admin_Settings constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Admin_Settings model.
         *
         * @since  2.0
         * @access public
         */
        public function __construct( $dependencies ) {

            $this->_wwpp_wws_license_manager = $dependencies['WWPP_WWS_License_Manager'];
            $this->_wwpp_wholesale_roles     = $dependencies['WWPP_Wholesale_Roles'];
            $this->_all_wholesale_roles      = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
        }

        /**
         * Ensure that only one instance of WWPP_Admin_Settings is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Admin_Settings model.
         *
         * @since  2.0
         * @access public
         *
         * @return WWPP_Admin_Settings
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Get the value of a setting.
         *
         * @param array $tabs Settings tabs.
         *
         * @since 2.0
         * @since 2.0.3 Added check if the license is active.
         * @access public
         *
         * @return array
         */
        public function admin_settings_tabs( $tabs ) {

            $tabs['wholesale_prices']['child']['general'] = array(
                'sort'  => 1,
                'key'   => 'general',
                'label' => __( 'General', 'woocommerce-wholesale-prices-premium' ),
            );

            $order_requirements_dec = sprintf(
            // translators: %1$s link to premium add-on, %2$s </a> tag, %3$s link to wholesale suite bundle.
                __(
                    'These settings describe the Minimum Order Requirements to ensure that your wholesale customers will not activate wholesale pricing if they don’t meet the requirements yet. Until they meet the requirements, they will see regular prices in the cart along with a notice to tell them how much they need to add before they will activate wholesale pricing. %1$sRead more about how this works here%2$s.',
                    'woocommerce-wholesale-prices-premium'
                ),
                '<a target="_blank" href="https://wholesalesuiteplugin.com/kb/why-does-the-cart-show-retail-prices-until-the-minimums-are-met/?utm_source=Prices%20Premium%20Plugin&utm_medium=Settings&utm_campaign=Minimum%Order%20Setting%20"> ',
                '</a>'
            );

            // General Options.
            $tabs['wholesale_prices']['child']['general']['sections'] = array(
                'order_requirements'     => array(
                    'label' => __( 'Order Requirements', 'woocommerce-wholesale-prices-premium' ),
                    'desc'  => $order_requirements_dec,
                ),
                'wholesale_products'     => array(
                    'label' => __( 'Wholesale Products', 'woocommerce-wholesale-prices-premium' ),
                    'desc'  => __( 'These settings generally affect the way wholesale products are shown and priced.', 'woocommerce-wholesale-prices-premium' ),
                ),
                'wholesale_customer_cap' => array(
                    'label' => __( 'Wholesale Customer Capabilities', 'woocommerce-wholesale-prices-premium' ),
                    'desc'  => __( 'These settings describe some of the additional capabilities of your wholesale customers.', 'woocommerce-wholesale-prices-premium' ),
                ),
                'wholesale_misc'         => array(
                    'label' => __( 'Misc', 'woocommerce-wholesale-prices-premium' ),
                    'desc'  => __( 'These settings handle other miscellaneous parts of the way your wholesale system works.', 'woocommerce-wholesale-prices-premium' ),
                ),
            );

            // Prices Options - Section.
            $wwp_wwlc_is_active = WWPP_Helper_Functions::is_wwlc_active();

            $tabs['wholesale_prices']['child']['price']['sort']     = 2;
            $tabs['wholesale_prices']['child']['price']['sections'] = array(
                'price_options'         => array(
                    'label' => __( 'Price Options', 'woocommerce-wholesale-prices-premium' ),
                    'desc'  => '',
                ),
                'box_for_non_wholesale' => array(
                    'label'             => __( 'Show Wholesale Prices Box For Non Wholesale Customers', 'woocommerce-wholesale-prices-premium' ),
                    'desc'              => '',
                    'show_lead_upgrade' => ( ! $wwp_wwlc_is_active ) ? true : false,
                ),
            );

            // Tax Options - Section.
            $tax_exp_mapping_dec = sprintf(
            // translators: %1$s <b> tag, %2$s </b> tag, %3$s link to premium add-on, %4$s </a> tag, %5$s link to bundle.
                __(
                    'Specify tax exemption per wholesale role. Overrides general <b>Tax Exemption</b> option above.',
                    'woocommerce-wholesale-prices-premium'
                ),
            );

            $tabs['wholesale_prices']['child']['tax']['sort']     = 3;
            $tabs['wholesale_prices']['child']['tax']['sections'] = array(
                'tax_options'     => array(
                    'label' => __( 'Tax Options', 'woocommerce-wholesale-prices-premium' ),
                    'desc'  => '',
                ),
                'tax_exp_mapping' => array(
                    'label' => __( 'Wholesale Role / Tax Exemption Mapping', 'woocommerce-wholesale-prices-premium' ),
                    'desc'  => $tax_exp_mapping_dec,
                ),
                'tax_cls_mapping' => array(
                    'label' => __( 'Wholesale Role / Tax Class Mapping', 'woocommerce-wholesale-prices-premium' ),
                    'desc'  => __( 'Specify tax classes per wholesale role.', 'woocommerce-wholesale-prices-premium' ),
                ),
            );

            // Shipping Options - Section.
            $shipping_method_mapping_dec   = sprintf(
                __(
                    'Wholesale Role/Shipping Method Mapping<br><ol><li>Select the wholesale role you wish to restrict</li><li>Choose the shipping zone you want this to apply to</li><li>Finally, choose the shipping method in that shipping zone that you wish to restrict the selected wholesale role to.</li></ol><br>You can repeat this process to map multiple shipping methods per zone & multiple zones per role.',
                    'woocommerce-wholesale-prices-premium'
                ),
            );
            $shipping_nonezone_mapping_dec = sprintf(
                __(
                    'Non-Zoned shipping methods covers third party shipping methods extensions that register their shipping methods globally meaning they appear to the user always and do not take the shipping zone into account at all.<br><br>To map these non-zoned methods, please select the “Use Non-Zoned Shipping Methods” checkbox and select the method from the list.',
                    'woocommerce-wholesale-prices-premium'
                ),
            );

            $tabs['wholesale_prices']['child']['shipping'] = array(
                'sort'     => 4,
                'key'      => 'shipping',
                'label'    => __( 'Shipping', 'woocommerce-wholesale-prices-premium' ),
                'sections' => array(
                    'shipping_options'          => array(
                        'label' => __( 'Shipping Options', 'woocommerce-wholesale-prices-premium' ),
                        'desc'  => '',
                    ),
                    'shipping_method_mapping'   => array(
                        'label' => __( 'Wholesale Role / Shipping Method Mapping', 'woocommerce-wholesale-prices-premium' ),
                        'desc'  => $shipping_method_mapping_dec,
                    ),
                    'shipping_nonezone_mapping' => array(
                        'label' => __( 'Non-Zoned Shipping Methods', 'woocommerce-wholesale-prices-premium' ),
                        'desc'  => $shipping_nonezone_mapping_dec,
                    ),
                ),
            );

            // Discount Options - Section.
            $general_discount_desc    = sprintf(
                __(
                    'This is where you set <b>“general discount”</b> for each wholesale role that will be applied to those users<br>if a product they wish to purchase has no wholesale price set and no wholesale discount set at the product category level.',
                    'woocommerce-wholesale-prices-premium'
                ),
            );
            $cart_price_discount_desc = sprintf(
                __(
                    'Optionally give an additional discount for specific wholesale roles based on the cart subtotal price (excluding taxes & shipping). Discounts are shown in the totals table and are applied to the entire order.<br><br>Simply select a wholesale user role, the subtotal price at which the additional discount should start to apply to their order, and the discount amount to give either as a fixed amount or a percentage of the cart subtotal.<br><br>You can tier additional discounts by adding extra subtotal price breakpoints. Only one discount will apply.',
                    'woocommerce-wholesale-prices-premium'
                ),
            );

            $tabs['wholesale_prices']['child']['discount'] = array(
                'sort'     => 5,
                'key'      => 'discount',
                'label'    => __( 'Discount', 'woocommerce-wholesale-prices-premium' ),
                'sections' => array(
                    'general_options'            => array(
                        'label' => __( 'General Discount Options', 'woocommerce-wholesale-prices-premium' ),
                        'desc'  => $general_discount_desc,
                    ),
                    'general_quantity_discounts' => array(
                        'label' => __( 'General Quantity Based Discounts', 'woocommerce-wholesale-prices-premium' ),
                        'desc'  => __( 'Give an additional quantity based discount when using the global General Discount for that wholesale role.', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'cart_price_discounts'       => array(
                        'label' => __( 'Cart Subtotal Price Discounts', 'woocommerce-wholesale-prices-premium' ),
                        'desc'  => $cart_price_discount_desc,
                    ),
                ),
            );

            $tabs['wholesale_prices']['child']['gateway'] = array(
                'sort'     => 6,
                'key'      => 'gateway',
                'label'    => __( 'Payment Gateway', 'woocommerce-wholesale-prices-premium' ),
                'sections' => array(
                    'gateway_options'           => array(
                        'label' => __( 'Wholesale Role / Payment Gateway', 'woocommerce-wholesale-prices-premium' ),
                        'desc'  => __( 'You can specify what payment gateways are available per wholesale role (Note that payment gateway need not be enabled)', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'gateway_surcharge_options' => array(
                        'label' => __( 'Wholesale Role / Payment Gateway Surcharge', 'woocommerce-wholesale-prices-premium' ),
                        'desc'  => __( 'You can specify extra cost per payment gateway per wholesale role', 'woocommerce-wholesale-prices-premium' ),
                    ),
                ),
            );

            $tabs['wholesale_prices']['child']['cache'] = array(
                'sort'     => 7,
                'key'      => 'cache',
                'label'    => __( 'Cache', 'woocommerce-wholesale-prices-premium' ),
                'sections' => array(
                    'cache_options' => array(
                        'label' => __( 'Cache Options', 'woocommerce-wholesale-prices-premium' ),
                        'desc'  => '',
                    ),
                ),
            );

            $help_options_dec = sprintf(
            // translators: %1$s link to premium add-on, %2$s </a> tag.
                __(
                    'Looking for documentation? Please see our growing %1$sKnowledge Base%2$s.',
                    'woocommerce-wholesale-prices-premium'
                ),
                '<a target="_blank" href="https://wholesalesuiteplugin.com/knowledge-base/?utm_source=wwp&utm_medium=kb&utm_campaign=helppagekblink"> ',
                '</a>',
            );
            $tabs['wholesale_prices']['child']['help'] = array(
                'sort'     => 8,
                'key'      => 'help',
                'label'    => __( 'Help', 'woocommerce-wholesale-prices-premium' ),
                'sections' => array(
                    'shipping_options' => array(
                        'label' => __( 'Help Options', 'woocommerce-wholesale-prices-premium' ),
                        'desc'  => $help_options_dec,
                    ),
                    'debug_tools'      => array(
                        'label' => __( 'Debug Tools', 'woocommerce-wholesale-prices-premium' ),
                        'desc'  => '',
                    ),
                ),
            );

            $tabs['wholesale_prices']['child']['upgrade'] = array(
                'sort'            => 10,
                'key'             => 'upgrade',
                'label'           => __( 'Upgrade', 'woocommerce-wholesale-prices-premium' ),
                'sections'        => array(
                    'upgrade_options'  => array(
                        'label' => '',
                        'desc'  => '',
                    ),
                    'upgrade_options2' => array(
                        'label' => '',
                        'desc'  => '',
                    ),
                ),
                'with_background' => true,
                'no_save'         => true,
            );

            $tabs['wholesale_prices']['child']['license'] = array(
                'sort'     => 9,
                'key'      => 'license',
                'label'    => __( 'License', 'woocommerce-wholesale-prices-premium' ),
                'link'     => is_multisite() ? network_admin_url( 'admin.php?page=wws-ms-license-settings' ) : admin_url( 'admin.php?page=wws-license-settings' ),
                'external' => false,
            );

            // Remove the upgrade tab settings.
            $wwp_wwpp_is_active = WWP_Helper_Functions::is_wwpp_active();
            $wwp_wwof_is_active = WWP_Helper_Functions::is_wwof_active();

            if ( $wwp_wwpp_is_active && $wwp_wwof_is_active && $wwp_wwlc_is_active ) {
                unset( $tabs['wholesale_prices']['child']['upgrade'] );
            }

            // Check if license is active.
            $is_show_interstitial = $this->_wwpp_wws_license_manager->show_license_interstitial();
            if ( $is_show_interstitial ) {
                $license_btn_label    = __( 'Enter License Now', 'woocommerce-wholesale-prices-premium' );
                $license_link         = admin_url( 'admin.php?page=wws-license-settings&tab=wwpp' );
                $license_action_label = __( 'Don’t have a license yet? Purchase here.', 'woocommerce-wholesale-prices-premium' );
                $license_action_link  = esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwpp', 'upsell', 'upgradepagewwpplearnmore' ) );

                $status_text = sprintf(
                    /* translators: %1$s and %2$s opening and closing strong tags respectively. */
                    __( '%1$sUrgent!%2$s Your Wholesale Prices Premium license is missing!', 'woocommerce-wholesale-prices-premium' ),
                    '<span style="color: #a00;">',
                    '</span>'
                );
                if ( 'expired' === $is_show_interstitial ) {
                    $license_btn_label    = __( 'Renew License', 'woocommerce-wholesale-prices-premium' );
                    $license_link         = esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwpp', 'upsell', 'upgradepagewwpplearnmore' ) );
                    $license_action_label = __( 'Enter a new license', 'woocommerce-wholesale-prices-premium' );
                    $license_action_link  = admin_url( 'admin.php?page=wws-license-settings&tab=wwpp' );
                    $status_text          = sprintf(
                        /* translators: %1$s and %2$s opening and closing strong tags respectively. */
                        __( '%1$sUrgent!%2$s Your Wholesale Prices Premium license has expired!', 'woocommerce-wholesale-prices-premium' ),
                        '<span style="color: #a00;">',
                        '</span>'
                    );
                } elseif ( 'disabled' === $is_show_interstitial ) {
                    $license_btn_label    = __( 'Repurchase New License', 'woocommerce-wholesale-prices-premium' );
                    $license_link         = esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwpp', 'upsell', 'upgradepagewwpplearnmore' ) );
                    $license_action_label = __( 'Enter a new license', 'woocommerce-wholesale-prices-premium' );
                    $license_action_link  = admin_url( 'admin.php?page=wws-license-settings&tab=wwpp' );
                    $status_text          = sprintf(
                        /* translators: %1$s and %2$s opening and closing strong tags respectively. */
                        __( '%1$sUrgent!%2$s Your Wholesale Prices Premium license is disabled!', 'woocommerce-wholesale-prices-premium' ),
                        '<span style="color: #a00;">',
                        '</span>'
                    );
                }

                $tabs['wholesale_prices']['interstitial']         = true;
                $tabs['wholesale_prices']['interstitial_details'] = array(
                    'title'                => $status_text,
                    'description'          => __( 'Without an active license, your website front end will still continue but premium functionality has been disabled until a valid license is entered.', 'woocommerce-wholesale-prices-premium' ),
                    'license_btn_label'    => $license_btn_label,
                    'license_btn_link'     => $license_link,
                    'license_action_label' => $license_action_label,
                    'license_action_link'  => $license_action_link,
                );
            }

            return $tabs;
        }

        /**
         * Filter General tab controls.
         *
         * @param array $controls General tab controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function admin_settings_general_controls( $controls ) {

            // General Requirements Options - Section.
            $settings_minimum_order_quantity              = get_option( 'wwpp_settings_minimum_order_quantity' );
            $settings_minimum_order_price                 = get_option( 'wwpp_settings_minimum_order_price' );
            $settings_minimum_requirements_logic          = get_option( 'wwpp_settings_minimum_requirements_logic' );
            $settings_override_order_requirement_per_role = get_option( 'wwpp_settings_override_order_requirement_per_role' );
            $dependencies_override_order_display          = ( 'yes' === $settings_override_order_requirement_per_role ) ? false : true;

            $wholesale_role_order_requirement_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_ORDER_REQUIREMENT_MAPPING );
            if ( ! is_array( $wholesale_role_order_requirement_mapping ) ) {
                $wholesale_role_order_requirement_mapping = array();
            }

            $filtered_wholesale_role_order_requirement_mapping = array();
            if ( ! empty( $wholesale_role_order_requirement_mapping ) ) {
                $i = 0;
                foreach ( $wholesale_role_order_requirement_mapping as $role => $mapping ) {
                    if ( array_key_exists( $role, $this->_all_wholesale_roles ) ) {
                        $filtered_wholesale_role_order_requirement_mapping[] = array(
                            'key'                      => $i,
                            'wholesale_role'           => $role,
                            'wholesale_role_text'      => $this->_all_wholesale_roles[ $role ]['roleName'],
                            'minimum_order_quantity'   => $mapping['minimum_order_quantity'],
                            'minimum_order_subtotal'   => $mapping['minimum_order_subtotal'],
                            'minimum_order_logic'      => $mapping['minimum_order_logic'],
                            'minimum_order_logic_text' => 'and' === $mapping['minimum_order_logic'] ? 'AND' : 'OR',
                        );

                        ++$i;
                    }
                }
            }

            $controls['order_requirements'] = array(
                array(
                    'type'        => 'text',
                    'label'       => __( 'Default Minimum Order Quantity', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_minimum_order_quantity',
                    'default'     => ( $settings_minimum_order_quantity ) ? $settings_minimum_order_quantity : 0,
                    'description' => __( 'Define a minimum number of items that wholesale customers must reach in the cart before they activate wholesale pricing.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip'    => __( 'Leave as 0 or blank to disable this setting.', 'woocommerce-wholesale-prices-premium' ),
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Default Minimum Order Subtotal', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_minimum_order_price',
                    'default'     => ( $settings_minimum_order_price ) ? $settings_minimum_order_price : 0,
                    'description' => __( 'Define a minimum subtotal that wholesale customers must reach before they activate wholesale pricing for items in their cart.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip'    => __( 'Leave as 0 or blank to disable this setting.', 'woocommerce-wholesale-prices-premium' ),
                ),
                array(
                    'type'     => 'radio',
                    'label'    => __( 'Should the customer satisfy both or just one of the minimum order rules?', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_minimum_requirements_logic',
                    'options'  => array(
                        'and' => __( 'Require Quantity AND Subtotal', 'woocommerce-wholesale-prices-premium' ),
                        'or'  => __( 'Require Quantity OR Subtotal', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'default'  => $settings_minimum_requirements_logic,
                    'desc_tip' => __( 'If one of the above default minimum order settings is disabled, this setting will be ignored.', 'woocommerce-wholesale-prices-premium' ),
                ),
                array(
                    'type'        => 'switch',
                    'label'       => __( 'Wholesale Role Specific Minimum Requirements', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_override_order_requirement_per_role',
                    'description' => __( 'Override the default minimum order rules per wholesale role. This lets you apply different minimum order requirements based on the customer’s user role. You only need to define a mapping for the roles you wish to override, all other roles will use the default minimum order requirements above.', 'woocommerce-wholesale-prices-premium' ),
                    'options'     => array(
                        'yes' => __( 'Enabled', 'woocommerce-wholesale-prices-premium' ),
                        'no'  => __( 'Disabled', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'default'     => $settings_override_order_requirement_per_role,
                ),
                array(
                    'type'         => 'group',
                    'label'        => __( 'Wholesale Role Override', 'woocommerce-wholesale-prices-premium' ),
                    'id'           => 'wwpp_group_wholesale_override_mapping',
                    'classes'      => 'group-table-data margin-top',
                    'group_action' => 'group_wholesale_specific_override_mapping_save',
                    'hide'         => $dependencies_override_order_display,
                    'condition'    => array(
                        array(
                            'key'   => 'wwpp_settings_override_order_requirement_per_role',
                            'value' => 'yes',
                        ),
                    ),
                    'fields'       => array(
                        array(
                            'type'     => 'select',
                            'label'    => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'wholesale_role',
                            'label_id' => 'wholesale_role_text',
                            'default'  => '',
                            'options'  => $this->all_roles(),
                        ),
                        array(
                            'type'  => 'number',
                            'label' => __( 'Minimum Order Quantity:', 'woocommerce-wholesale-prices-premium' ),
                            'id'    => 'minimum_order_quantity',
                        ),
                        array(
                            'type'  => 'number',
                            'label' => __( 'Minimum Sub-total Amount ($):', 'woocommerce-wholesale-prices-premium' ),
                            'id'    => 'minimum_order_subtotal',
                        ),
                        array(
                            'type'     => 'select',
                            'label'    => __( 'Minimum Order Logic:', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'minimum_order_logic',
                            'label_id' => 'minimum_order_logic_text',
                            'default'  => 'and',
                            'options'  => array(
                                'and' => __( 'AND', 'woocommerce-wholesale-prices-premium' ),
                                'or'  => __( 'OR', 'woocommerce-wholesale-prices-premium' ),
                            ),
                        ),
                    ),
                    'table'        => array(
                        'paginated'     => true,
                        'editable'      => true,
                        'can_delete'    => true,
                        'edit_action'   => 'group_wholesale_specific_override_mapping_edit',
                        'delete_action' => 'group_wholesale_specific_override_mapping_delete',
                        'fields'        => array(
                            array(
                                'title'     => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'wholesale_role_text',
                                'field'     => 'wholesale_role',
                                'key'       => 'wholesale_role_text',
                            ),
                            array(
                                'title'     => __( 'Minimum Order Quantity', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'minimum_order_quantity',
                                'field'     => 'minimum_order_quantity',
                                'key'       => 'minimum_order_quantity',
                            ),
                            array(
                                'title'     => __( 'Minimum Order Logic', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'minimum_order_logic_text',
                                'field'     => 'minimum_order_logic',
                                'key'       => 'minimum_order_logic_text',
                            ),
                            array(
                                'title'     => __( 'Minimum Sub-total Amount ($)', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'minimum_order_subtotal',
                                'field'     => 'minimum_order_subtotal',
                                'key'       => 'minimum_order_subtotal',
                            ),
                            array(
                                'title'     => __( 'Action', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'operation',
                                'field'     => 'operation',
                                'key'       => 'operation',
                            ),
                        ),
                        'data'          => $filtered_wholesale_role_order_requirement_mapping,
                    ),
                    'button_label' => __( 'Add Mapping', 'woocommerce-wholesale-prices-premium' ),
                ),
            );

            // General Products Options - Section.
            $settings_only_show_wholesale_products_to_wholesale_users = get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users' );
            $settings_multiple_category_wholesale_discount_logic      = get_option( 'wwpp_settings_multiple_category_wholesale_discount_logic' );
            $settings_hide_quantity_discount_table                    = get_option( 'wwpp_settings_hide_quantity_discount_table' );
            $settings_hide_product_categories_product_count           = get_option( 'wwpp_settings_hide_product_categories_product_count' );
            $settings_enforce_product_min_step_on_cart_page           = get_option( 'wwpp_settings_enforce_product_min_step_on_cart_page' );
            $settings_override_stock_display_format                   = get_option( 'wwpp_settings_override_stock_display_format' );
            $settings_prevent_stock_reduction                         = get_option( 'wwpp_settings_prevent_stock_reduction' );
            $settings_allow_add_to_cart_below_product_minimum         = get_option( 'wwpp_settings_allow_add_to_cart_below_product_minimum' );

            $controls['wholesale_products'] = array(
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Only Show Wholesale Products To Wholesale Customers', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_only_show_wholesale_products_to_wholesale_users',
                    'input_label' => __( 'Products without wholesale pricing will be hidden from wholesale customers.', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'description' => __( 'This setting only affects wholesale customers.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $settings_only_show_wholesale_products_to_wholesale_users,
                ),
                array(
                    'type'        => 'select',
                    'label'       => __( 'Multiple Category Discount Priority', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_multiple_category_wholesale_discount_logic',
                    'multiple'    => false,
                    'description' => __( 'When a product belongs to two categories that both have discounts, which category discount should it get?', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip'    => __( 'This only applies when the wholesale price is being derived from the category level % discount.', 'woocommerce-wholesale-prices-premium' ),
                    'options'     => array(
                        'highest' => __( 'Highest Discount Available', 'woocommerce-wholesale-prices-premium' ),
                        'lowest'  => __( 'Lowest Discount Available', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'default'     => ! empty( $settings_multiple_category_wholesale_discount_logic ) ? $settings_multiple_category_wholesale_discount_logic : 'lowest',
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Hide Quantity Discount Tables', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_hide_quantity_discount_table',
                    'input_label' => __( 'Even if a product has a quantity based discount, do not show the quantity discount table.', 'woocommerce-wholesale-prices-premium' ),
                    'description' => __( 'By default, a table describing the quantity based discounts is shown to wholesale customers.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $settings_hide_quantity_discount_table,
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Hide Category Product Count', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_hide_product_categories_product_count',
                    'input_label' => __( 'On the shop page and product category archives, do not show the product count in the category name. This can speed up performance on those pages.', 'woocommerce-wholesale-prices-premium' ),
                    'description' => __( 'Determining the product count is an expensive operation due to having to take product visibility into account, hiding the count stops the system from calculating it.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $settings_hide_product_categories_product_count,
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Enforce Min/Step On Cart', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_enforce_product_min_step_on_cart_page',
                    'input_label' => __( 'Ensure that cart item quantity boxes on the cart page also respect the minimum and step settings of the product.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $settings_enforce_product_min_step_on_cart_page,
                ),
                array(
                    'type'        => 'select',
                    'label'       => __( 'Wholesale Stock Display Format', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_override_stock_display_format',
                    'description' => __( 'Override the stock display format for wholesale users.', 'woocommerce-wholesale-prices-premium' ),
                    'options'     => array(
                        ''           => __( '--Use woocommerce default--', 'woocommerce-wholesale-prices-premium' ),
                        'amount'     => __( 'Always show quantity remaining in stock e.g. "12 in stock"', 'woocommerce-wholesale-prices-premium' ),
                        'low_amount' => __( 'Only show quantity remaining in stock when low e.g. "Only 2 left in stock"', 'woocommerce-wholesale-prices-premium' ),
                        'no_amount'  => __( 'Never show quantity remaining in stock', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'default'     => ( $settings_override_stock_display_format ) ? $settings_override_stock_display_format : '',
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Prevent Stock Reduction', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_prevent_stock_reduction',
                    'input_label' => __( 'Don\'t reduce stock count on products for wholesale orders.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $settings_prevent_stock_reduction,
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Allow Add To Cart Below Product Minimum', 'woocommerce-wholesale-prices-premium' ),
                    'input_label' => __( 'Lets customers add quantity lower than the specified minimum amount.', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_allow_add_to_cart_below_product_minimum',
                    'default'     => $settings_allow_add_to_cart_below_product_minimum,
                ),
            );

            // General Capabilities Options - Section.
            $disable_coupons_for_wholesale_users                 = get_option( 'wwpp_settings_disable_coupons_for_wholesale_users' );
            $settings_always_allow_backorders_to_wholesale_users = get_option( 'wwpp_settings_always_allow_backorders_to_wholesale_users' );
            $settings_show_back_order_notice_wholesale_users     = get_option( 'wwpp_settings_show_back_order_notice_wholesale_users' );

            $controls['wholesale_customer_cap'] = array(
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Disable Coupons For Wholesale Users', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_disable_coupons_for_wholesale_users',
                    'input_label' => __( 'Globally turn off coupons functionality for customers with a wholesale user role.', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'description' => __( 'This applies to all customers with a wholesale role.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $disable_coupons_for_wholesale_users,
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Always Allow Backorders', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_always_allow_backorders_to_wholesale_users',
                    'input_label' => __( 'Even if backorders are disallowed on the product, still allow wholesale customers to make a backorder.', 'woocommerce-wholesale-prices-premium' ),
                    'description' => __( 'This overrides the defined backorder behavior for the product for all customers with a wholesale role.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $settings_always_allow_backorders_to_wholesale_users,
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Show Backorders Notice When Allowed', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_show_back_order_notice_wholesale_users',
                    'input_label' => __( 'If backorders are allowed for wholesale customers (as per Always Allow Backorders setting), notify the customer that the product is "Available on backorder".', 'woocommerce-wholesale-prices-premium' ),
                    'description' => __( 'Shows the standard "Available on backorder" notice from WooCommerce. This setting is ignored when "Always Allow Backorders" is off.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $settings_show_back_order_notice_wholesale_users,
                ),
            );

            // General Misc Options - Section.
            $settings_thankyou_message          = get_option( 'wwpp_settings_thankyou_message' );
            $settings_thankyou_message_position = get_option( 'wwpp_settings_thankyou_message_position' );
            $settings_clear_cart_on_login       = get_option( 'wwpp_settings_clear_cart_on_login' );

            $controls['wholesale_misc'] = array(
                array(
                    'type'        => 'textarea',
                    'label'       => __( 'Wholesale Order Received Thank You Message', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_thankyou_message',
                    'editor'      => true,
                    'description' => __( 'Show wholesale customers a thank you message with important information on the Order Received screen.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip'    => __( 'This message is only shown on the Order Received screen.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $settings_thankyou_message,
                ),
                array(
                    'type'        => 'select',
                    'label'       => __( 'Wholesale Order Received Thank You Message Position', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_thankyou_message_position',
                    'description' => __( 'Choose to whether to replace the standard thank you message from WooCommerce, prepend or append the message on the Order Received screen.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip'    => __( 'The standard WooCommerce thank you message is "Thank you. Your order has been received."', 'woocommerce-wholesale-prices-premium' ),
                    'options'     => array(
                        'replace' => __( 'Replace', 'woocommerce-wholesale-prices-premium' ),
                        'append'  => __( 'Append', 'woocommerce-wholesale-prices-premium' ),
                        'prepend' => __( 'Prepend', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'default'     => ( $settings_thankyou_message_position ) ? $settings_thankyou_message_position : 'replace',
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Clear Cart On Login', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_clear_cart_on_login',
                    'input_label' => __( 'Clears the wholesale customer\'s shopping after they log in to ensure a fresh session.', 'woocommerce-wholesale-prices-premium' ),
                    'description' => __( 'This can help if you are having issues with old orders being retained in the cart.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $settings_clear_cart_on_login,
                ),
            );

            return $controls;
        }

        /**
         * Filter Prices tab controls.
         *
         * @param array $controls Prices tab controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function admin_settings_prices_controls( $controls ) {

            // Price options - Section.
            $settings_wholesale_price_title_text             = get_option( 'wwpp_settings_wholesale_price_title_text' );
            $settings_hide_original_price                    = get_option( 'wwpp_settings_hide_original_price' );
            $settings_show_saving_amount                     = get_option( 'wwpp_settings_show_saving_amount' );
            $dependencies_savings_display                    = ( 'yes' === $settings_show_saving_amount ) ? false : true;
            $settings_show_saving_amount_page_shop           = get_option( 'wwpp_settings_show_saving_amount_page_shop' );
            $settings_show_saving_amount_page_single_product = get_option( 'wwpp_settings_show_saving_amount_page_single_product' );
            $settings_show_saving_amount_page_cart           = get_option( 'wwpp_settings_show_saving_amount_page_cart' );
            $settings_show_saving_amount_page_invoice        = get_option( 'wwpp_settings_show_saving_amount_page_invoice' );
            $settings_show_saving_amount_text                = get_option( 'wwpp_settings_show_saving_amount_text' );
            $settings_explicitly_dummy                       = get_option( 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc' );
            $wholesale_price_on_product_listing              = get_option( 'wwpp_hide_wholesale_price_on_product_listing' );
            $hide_price_add_to_cart                          = get_option( 'wwp_hide_price_add_to_cart' );
            $price_and_add_to_cart_replacement_message       = get_option( 'wwp_price_and_add_to_cart_replacement_message' );
            $settings_variable_product_price_display         = get_option( 'wwpp_settings_variable_product_price_display' );

            $wwpp_savings_description = sprintf(
            // translators: %1$s link to premium add-on, %2$s </p> tag.
                __(
                    '%1$sWhat Pages to Show the Saving Text%2$s',
                    'woocommerce-wholesale-prices-premium'
                ),
                '<strong> ',
                '</strong>',
            );

            $wwpp_list_tags_description = sprintf(
            // translators: %1$s link to premium add-on, %2$s </p> tag.
                __(
                    '<b>List of Tags Available</b><br> <ul><li><code>{saved_amount}</code> : Show saved amount</li><li><code>{saved_percentage}</code> : Show saved percentage</li></ul>',
                    'woocommerce-wholesale-prices-premium'
                ),
            );

            $controls['price_options'] = array(
                array(
                    'type'        => 'text',
                    'label'       => __( 'Wholesale Price Text', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_wholesale_price_title_text',
                    'default'     => $settings_wholesale_price_title_text,
                    'description' => __( 'The text shown immediately before the wholesale price. Default is "Wholesale Price: "', 'woocommerce-wholesale-prices-premium' ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Hide Retail Price', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_hide_original_price',
                    'input_label' => __( 'Hide retail price instead of showing a crossed out price if a wholesale price is present.', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'default'     => $settings_hide_original_price,
                ),
                array(
                    'type'        => 'switch',
                    'label'       => __( 'Show Wholesale Saving Amount', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_show_saving_amount',
                    'description' => __( 'If enable, displays the saving amount and percentage of retail price on the shop page, single product page, cart page, checkout page, order page, and email invoice.', 'woocommerce-wholesale-prices-premium' ),
                    'options'     => array(
                        'yes' => __( 'Enabled', 'woocommerce-wholesale-prices-premium' ),
                        'no'  => __( 'Disabled', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'default'     => $settings_show_saving_amount,
                ),
                array(
                    'type'        => 'copy',
                    'label'       => '',
                    'description' => $wwpp_savings_description,
                    'id'          => 'wwpp_pages_to_show_saving_text',
                    'hide'        => $dependencies_savings_display,
                    'condition'   => array(
                        array(
                            'key'   => 'wwpp_settings_show_saving_amount',
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => '',
                    'id'          => 'wwpp_settings_show_saving_amount_page_shop',
                    'input_label' => __( 'Shop Page', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'hide'        => $dependencies_savings_display,
                    'default'     => $settings_show_saving_amount_page_shop,
                    'condition'   => array(
                        array(
                            'key'   => 'wwpp_settings_show_saving_amount',
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => '',
                    'id'          => 'wwpp_settings_show_saving_amount_page_single_product',
                    'input_label' => __( 'Single Product Page', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'hide'        => $dependencies_savings_display,
                    'default'     => $settings_show_saving_amount_page_single_product,
                    'condition'   => array(
                        array(
                            'key'   => 'wwpp_settings_show_saving_amount',
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => '',
                    'id'          => 'wwpp_settings_show_saving_amount_page_cart',
                    'input_label' => __( 'Cart/Checkout/Order Page', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'hide'        => $dependencies_savings_display,
                    'default'     => $settings_show_saving_amount_page_cart,
                    'condition'   => array(
                        array(
                            'key'   => 'wwpp_settings_show_saving_amount',
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => '',
                    'id'          => 'wwpp_settings_show_saving_amount_page_invoice',
                    'input_label' => __( 'Email Invoice', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'hide'        => $dependencies_savings_display,
                    'default'     => $settings_show_saving_amount_page_invoice,
                    'condition'   => array(
                        array(
                            'key'   => 'wwpp_settings_show_saving_amount',
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'        => 'copy',
                    'label'       => '',
                    'description' => $wwpp_list_tags_description,
                    'id'          => 'wwpp_list_of_tags',
                    'hide'        => $dependencies_savings_display,
                    'condition'   => array(
                        array(
                            'key'   => 'wwpp_settings_show_saving_amount',
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'      => 'text',
                    'label'     => __( 'Wholesale Saving Amount Text', 'woocommerce-wholesale-prices-premium' ),
                    'id'        => 'wwpp_settings_show_saving_amount_text',
                    'default'   => $settings_show_saving_amount_text,
                    'desc_tip'  => __( 'The text to be shown on the defined pages. Default is \'You are saving {saved_amount} ({saved_percentage}) off RRP on this order\'', 'woocommerce-wholesale-prices-premium' ),
                    'hide'      => $dependencies_savings_display,
                    'condition' => array(
                        array(
                            'key'   => 'wwpp_settings_show_saving_amount',
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Always Use Regular Price', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_explicitly_use_product_regular_price_on_discount_calc',
                    'input_label' => __( 'When calculating the wholesale price by using a percentage (global discount %, category based %, or quantity based %) always ensure the Regular Price is used and ignore the Sale Price if present.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip'    => __( 'This feature is used to handle Sale Price and the percentage discount will refer to the Sale Price if it\'s available. If you enable this option, it\'ll always refer to the regular price despite the availability of the Sale Price.', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'default'     => $settings_explicitly_dummy,
                ),
                array(
                    'type'     => 'select',
                    'label'    => __( 'Variable Product Price Display', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip' => __( 'Specify the format in which variable product prices are displayed. Only for wholesale customers.', 'woocommerce-wholesale-prices-premium' ),
                    'id'       => 'wwpp_settings_variable_product_price_display',
                    'classes'  => 'wwpp_settings_variable_product_price_display',
                    'options'  => array(
                        'price-range' => __( 'Price Range', 'woocommerce-wholesale-prices-premium' ),
                        'minimum'     => __( 'Minimum Price', 'woocommerce-wholesale-prices-premium' ),
                        'maximum'     => __( 'Maximum Price', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'default'  => ( $settings_variable_product_price_display ) ? $settings_variable_product_price_display : 'price-range',
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Hide Wholesale Price on Admin Product Listing', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_hide_wholesale_price_on_product_listing',
                    'input_label' => __( 'If checked, hides wholesale price per wholesale role on the product listing on the admin page.', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'default'     => $wholesale_price_on_product_listing,
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Hide Price and Add to Cart button', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwp_hide_price_add_to_cart',
                    'input_label' => __( 'If checked, hides price and add to cart button for visitors.', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'default'     => $hide_price_add_to_cart,
                ),
                array(
                    'type'        => 'textarea',
                    'label'       => __( 'Price and Add to Cart Replacement Message', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwp_price_and_add_to_cart_replacement_message',
                    'description' => __( 'This message is only shown if <b>Hide Price and Add to Cart button</b> is enabled. "Login to see prices" is the default message.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $price_and_add_to_cart_replacement_message,
                    'editor'      => true,
                ),
            );

            // Price box for non wholesale customers - Section.
            $wwp_wwof_is_active = WWP_Helper_Functions::is_wwof_active();
            $wwp_wwlc_is_active = WWP_Helper_Functions::is_wwlc_active();

            $prices_settings_show_wholesale_prices_to_non_wholesale = get_option( 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale' );
            $dependencies_display                                   = ( 'yes' === $prices_settings_show_wholesale_prices_to_non_wholesale ) ? false : true;
            $wwp_non_wholesale_show_in_shop                         = get_option( 'wwp_non_wholesale_show_in_shop' );
            $wwp_non_wholesale_show_in_products                     = get_option( 'wwp_non_wholesale_show_in_products' );
            $wwp_non_wholesale_show_in_wwof                         = get_option( 'wwp_non_wholesale_show_in_wwof' );
            $wwp_see_wholesale_prices_replacement_text              = get_option( 'wwp_see_wholesale_prices_replacement_text' );
            $wwp_non_wholesale_wholesale_role_select2               = get_option( 'wwp_non_wholesale_wholesale_role_select2' );
            $wwp_non_wholesale_wholesale_role_select2               = ( ! empty( $wwp_non_wholesale_wholesale_role_select2 ) ) ? $wwp_non_wholesale_wholesale_role_select2 : 'wholesale_customer';
            $wwp_price_settings_register_text                       = get_option( 'wwp_price_settings_register_text' );
            $wwp_price_settings_register_text                       = ( $wwp_wwlc_is_active ) ? $wwp_price_settings_register_text : __( 'Click here to register as a wholesale customer', 'woocommerce-wholesale-prices-premium' );

            $wwof_description = '';
            if ( ! $wwp_wwof_is_active ) {
                $wwof_description = sprintf(
                // translators: %1$s link to premium add-on, %2$s </a> tag.
                    __(
                        'To use this option, you must have %1$s<b>WooCommerce Wholesale Order Form</b>%2$s plugin installed and activated.',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    '<a target="_blank" href="https://wholesalesuiteplugin.com/woocommerce-wholesale-order-form/?utm_source=wwp&utm_medium=upsell&utm_campaign=upgradepagewwoflearnmore"> ',
                    '</a>',
                );
            }

            $controls['box_for_non_wholesale'] = array(
                array(
                    'type'        => 'switch',
                    'label'       => __( 'Show Wholesale Price to non-wholesale users', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                    'description' => __( 'If enable, displays the wholesale price on the front-end to entice non-wholesale customers to register as wholesale customers. This is only shown for guest, customers, administrator, and shop managers.', 'woocommerce-wholesale-prices-premium' ),
                    'options'     => array(
                        'yes' => __( 'Enabled', 'woocommerce-wholesale-prices-premium' ),
                        'no'  => __( 'Disabled', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'default'     => $prices_settings_show_wholesale_prices_to_non_wholesale,
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Locations', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwp_non_wholesale_show_in_shop',
                    'input_label' => __( 'Shop Archives', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'default'     => $wwp_non_wholesale_show_in_shop,
                    'hide'        => $dependencies_display,
                    'condition'   => array(
                        array(
                            'key'   => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => '',
                    'id'          => 'wwp_non_wholesale_show_in_products',
                    'input_label' => __( 'Single Product', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'default'     => $wwp_non_wholesale_show_in_products,
                    'hide'        => $dependencies_display,
                    'condition'   => array(
                        array(
                            'key'   => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => '',
                    'id'          => 'wwp_non_wholesale_show_in_wwof',
                    'input_label' => __( 'Wholesale Order Form', 'woocommerce-wholesale-prices-premium' ),
                    'description' => $wwof_description,
                    'multiple'    => false,
                    'disabled'    => ( ! $wwp_wwof_is_active ) ? true : false,
                    'default'     => $wwp_non_wholesale_show_in_wwof,
                    'hide'        => $dependencies_display,
                    'condition'   => array(
                        array(
                            'key'   => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'      => 'text',
                    'label'     => __( 'Click to See Wholesale Prices Text', 'woocommerce-wholesale-prices-premium' ),
                    'id'        => 'wwp_see_wholesale_prices_replacement_text',
                    'default'   => $wwp_see_wholesale_prices_replacement_text,
                    'desc_tip'  => __( 'The "Click to See Wholesale Prices Text" seen in the frontpage.', 'woocommerce-wholesale-prices-premium' ),
                    'hide'      => $dependencies_display,
                    'condition' => array(
                        array(
                            'key'   => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'      => 'select',
                    'label'     => __( 'Wholesale Roles(s)', 'woocommerce-wholesale-prices-premium' ),
                    'id'        => 'wwp_non_wholesale_wholesale_role_select2',
                    'default'   => $wwp_non_wholesale_wholesale_role_select2,
                    'options'   => $this->all_roles(),
                    'desc_tip'  => __( 'The selected wholesale roles and pricing that should show to non-wholesale customers on the front end.', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'  => true,
                    'hide'      => $dependencies_display,
                    'condition' => array(
                        array(
                            'key'   => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                            'value' => 'yes',
                        ),
                    ),
                ),
                array(
                    'type'              => 'text',
                    'label'             => __( 'Register Text', 'woocommerce-wholesale-prices-premium' ),
                    'id'                => 'wwp_price_settings_register_text',
                    'default'           => $wwp_price_settings_register_text,
                    'desc_tip'          => __( 'This text is linked to the defined registration page in WooCommerce Wholesale Lead Capture settings.', 'woocommerce-wholesale-prices-premium' ),
                    'hide'              => $dependencies_display,
                    'condition'         => array(
                        array(
                            'key'   => 'wwp_prices_settings_show_wholesale_prices_to_non_wholesale',
                            'value' => 'yes',
                        ),
                    ),
                    'disabled'          => ( ! $wwp_wwlc_is_active ) ? true : false,
                    'show_lead_upgrade' => ( ! $wwp_wwlc_is_active ) ? true : false,
                ),
            );

            return $controls;
        }

        /**
         * Filter Tax tab controls.
         *
         * @param array $controls Tax tab controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function admin_settings_tax_controls( $controls ) {

            // Tax options - Section.
            $settings_tax_exempt_wholesale_users          = get_option( 'wwpp_settings_tax_exempt_wholesale_users' );
            $settings_incl_excl_tax_on_wholesale_price    = get_option( 'wwpp_settings_incl_excl_tax_on_wholesale_price' );
            $settings_incl_excl_tax_on_wholesale_price    = ! empty( $settings_incl_excl_tax_on_wholesale_price ) ? $settings_incl_excl_tax_on_wholesale_price : '';
            $settings_wholesale_tax_display_cart          = get_option( 'wwpp_settings_wholesale_tax_display_cart' );
            $settings_wholesale_tax_display_cart          = ! empty( $settings_wholesale_tax_display_cart ) ? $settings_wholesale_tax_display_cart : '';
            $settings_override_price_suffix_regular_price = get_option( 'wwpp_settings_override_price_suffix_regular_price' );
            $settings_override_price_suffix_regular_price = ! empty( $settings_override_price_suffix_regular_price ) ? $settings_override_price_suffix_regular_price : '';
            $settings_override_price_suffix               = get_option( 'wwpp_settings_override_price_suffix' );
            $settings_override_price_suffix               = ! empty( $settings_override_price_suffix ) ? $settings_override_price_suffix : '';

            $controls['tax_options'] = array(
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Tax Exemption', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_tax_exempt_wholesale_users',
                    'input_label' => __( 'Do not apply tax to all wholesale roles', 'woocommerce-wholesale-prices-premium' ),
                    'description' => __( 'Removes tax for all wholesale roles. All wholesale prices will display excluding tax throughout the store, cart and checkout. The display settings below will be ignored.', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'default'     => $settings_tax_exempt_wholesale_users,
                ),
                array(
                    'type'        => 'select',
                    'label'       => __( 'Display Prices in the Shop', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_incl_excl_tax_on_wholesale_price',
                    'options'     => array(
                        ''     => __( '--Use woocommerce default--', 'woocommerce-wholesale-prices-premium' ),
                        'incl' => __( 'Including tax', 'woocommerce-wholesale-prices-premium' ),
                        'excl' => __( 'Excluding tax', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'description' => __( 'Choose how wholesale roles see all prices throughout your shop pages.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip'    => __( 'Note: If the option above of "Tax Exempting" wholesale users is enabled, then wholesale prices on shop pages will not include tax regardless the value of this option.', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'default'     => $settings_incl_excl_tax_on_wholesale_price,
                ),
                array(
                    'type'        => 'select',
                    'label'       => __( 'Display Prices During Cart and Checkout', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_wholesale_tax_display_cart',
                    'options'     => array(
                        ''     => __( '--Use woocommerce default--', 'woocommerce-wholesale-prices-premium' ),
                        'incl' => __( 'Including tax', 'woocommerce-wholesale-prices-premium' ),
                        'excl' => __( 'Excluding tax', 'woocommerce-wholesale-prices-premium' ),
                    ),
                    'description' => __( 'Choose how wholesale roles see all prices on the cart and checkout pages.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip'    => __( 'Note: If the option above of "Tax Exempting" wholesale users is enabled, then wholesale prices on cart and checkout page will not include tax regardless the value of this option.', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'default'     => $settings_wholesale_tax_display_cart,
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Override Regular Price Suffix', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_override_price_suffix_regular_price',
                    'description' => __( 'Override the price suffix on regular prices for wholesale users.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip'    => __( 'Make this blank to use the default price suffix. You can also use prices substituted here using one of the following {price_including_tax} and {price_excluding_tax}.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $settings_override_price_suffix_regular_price,
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Wholesale Price Suffix', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_override_price_suffix',
                    'description' => __( 'Set a specific price suffix specifically for wholesale prices.', 'woocommerce-wholesale-prices-premium' ),
                    'desc_tip'    => __( 'Make this blank to use the default price suffix. You can also use prices substituted here using one of the following {price_including_tax} and {price_excluding_tax}.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $settings_override_price_suffix,
                ),
            );

            // Tax Exemption Mapping options - Section.
            $group_tax_exp_data         = array();
            $wholesale_role_tax_options = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_OPTION_MAPPING, array() );
            if ( ! empty( $wholesale_role_tax_options ) ) {
                $i = 0;
                foreach ( $wholesale_role_tax_options as $tax_option => $tax_option_label ) {
                    $group_tax_exp_data[] = array(
                        'key'                 => $i,
                        'wholesale_role'      => $tax_option,
                        'wholesale_role_name' => $this->_all_wholesale_roles[ $tax_option ]['roleName'],
                        'tax_exempted'        => $tax_option_label['tax_exempted'],
                        'tax_exempted_name'   => ( 'yes' === $tax_option_label['tax_exempted'] ) ? __( 'Yes', 'woocommerce-wholesale-prices-premium' ) : __( 'No', 'woocommerce-wholesale-prices-premium' ),
                    );

                    ++$i;
                }
            }

            $controls['tax_exp_mapping'] = array(
                array(
                    'type'         => 'group',
                    'label'        => __( 'Tax EXP Mapping', 'woocommerce-wholesale-prices-premium' ),
                    'id'           => 'wwpp_group_tax_exp_mapping',
                    'classes'      => 'group-table-data margin-top',
                    'group_action' => 'group_tax_exp_mapping_save',
                    'fields'       => array(
                        array(
                            'type'     => 'select',
                            'label'    => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'wholesale_role',
                            'label_id' => 'wholesale_role_name',
                            'default'  => '',
                            'options'  => $this->all_roles(),
                        ),
                        array(
                            'type'     => 'select',
                            'label'    => __( 'Tax Exempted?', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'tax_exempted',
                            'label_id' => 'tax_exempted_name',
                            'default'  => 'yes',
                            'options'  => array(
                                'yes' => __( 'Yes', 'woocommerce-wholesale-prices-premium' ),
                                'no'  => __( 'No', 'woocommerce-wholesale-prices-premium' ),
                            ),
                        ),
                    ),
                    'table'        => array(
                        'paginated'     => true,
                        'editable'      => true,
                        'can_delete'    => true,
                        'edit_action'   => 'group_tax_exp_mapping_edit',
                        'delete_action' => 'group_tax_exp_mapping_delete',
                        'fields'        => array(
                            array(
                                'title'     => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'wholesale_role_name',
                                'field'     => 'wholesale_role',
                                'key'       => 'wholesale_role_name',
                            ),
                            array(
                                'title'     => __( 'Tax Exempted', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'tax_exempted_name',
                                'field'     => 'tax_exempted',
                                'key'       => 'tax_exempted_name',
                            ),
                            array(
                                'title'     => __( 'Action', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'operation',
                                'field'     => 'operation',
                                'key'       => 'operation',
                            ),
                        ),
                        'data'          => $group_tax_exp_data,
                    ),
                    'button_label' => __( 'Add Mapping', 'woocommerce-wholesale-prices-premium' ),
                ),
            );

            // Tax Class Mapping options - Section.
            $settings_mapped_tax_classes_for_wholesale_users_only = get_option( 'wwpp_settings_mapped_tax_classes_for_wholesale_users_only' );
            $wc_tax_classes                                       = WC_Tax::get_tax_classes();
            if ( ! is_array( $wc_tax_classes ) ) {
                $wc_tax_classes = array();
            }
            $processed_tax_classes = array();

            foreach ( $wc_tax_classes as $tax_class ) {
                $processed_tax_classes[ sanitize_title( $tax_class ) ] = $tax_class;
            }

            $group_tax_cls_data               = array();
            $wholesale_role_tax_class_options = get_option( WWPP_OPTION_WHOLESALE_ROLE_TAX_CLASS_OPTIONS_MAPPING, array() );
            if ( ! empty( $wholesale_role_tax_class_options ) ) {
                $i = 0;
                foreach ( $wholesale_role_tax_class_options as $wholesale_role_key => $mapping ) {
                    $group_tax_cls_data[] = array(
                        'key'                 => $i,
                        'wholesale-role-key'  => $wholesale_role_key,
                        'wholesale-role-name' => $this->_all_wholesale_roles[ $wholesale_role_key ]['roleName'],
                        'tax-class'           => $mapping['tax-class'],
                        'tax-class-name'      => $mapping['tax-class-name'],
                    );

                    ++$i;
                }
            }

            $controls['tax_cls_mapping'] = array(
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Wholesale Only Tax Classes', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_mapped_tax_classes_for_wholesale_users_only',
                    'input_label' => __( 'Hide the mapped tax classes from non-wholesale customers. Non-wholesale customers will no longer be able to see the tax classes you have mapped below. Warning: If a product uses one of the mapped tax classes, customers whose roles are not included on the mapping below (including guest users) will be taxed using the standard tax class.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $settings_mapped_tax_classes_for_wholesale_users_only,
                ),
                array(
                    'type'         => 'group',
                    'label'        => __( 'Tax CLS Mapping', 'woocommerce-wholesale-prices-premium' ),
                    'id'           => 'wwpp_group_tax_cls_mapping',
                    'classes'      => 'group-table-data margin-top',
                    'group_action' => 'group_tax_cls_mapping_save',
                    'fields'       => array(
                        array(
                            'type'     => 'select',
                            'label'    => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'wholesale-role-key',
                            'label_id' => 'wholesale-role-name',
                            'default'  => '',
                            'options'  => $this->all_roles(),
                        ),
                        array(
                            'type'     => 'select',
                            'label'    => __( 'Tax Class', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'tax-class',
                            'label_id' => 'tax-class-name',
                            'default'  => '',
                            'options'  => $processed_tax_classes,
                        ),
                    ),
                    'table'        => array(
                        'paginated'     => true,
                        'editable'      => true,
                        'can_delete'    => true,
                        'edit_action'   => 'group_tax_cls_mapping_edit',
                        'delete_action' => 'group_tax_cls_mapping_delete',
                        'fields'        => array(
                            array(
                                'title'     => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'wholesale-role-name',
                                'field'     => 'wholesale-role-key',
                                'key'       => 'wholesale-role-name',
                            ),
                            array(
                                'title'     => __( 'Tax Class', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'tax-class-name',
                                'field'     => 'tax-class',
                                'key'       => 'tax-class-name',
                            ),
                            array(
                                'title'     => __( 'Action', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'operation',
                                'field'     => 'operation',
                                'key'       => 'operation',
                            ),
                        ),
                        'data'          => $group_tax_cls_data,
                    ),
                    'button_label' => __( 'Add Mapping', 'woocommerce-wholesale-prices-premium' ),
                ),
            );

            return $controls;
        }

        /**
         * Filter additional controls.
         *
         * @param array $controls Additional controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function admin_setting_default_controls( $controls ) {

            // Shipping tab controls.
            $controls['wholesale_prices']['shipping'] = $this->shipping_tab_controls();
            // Discount tab controls.
            $controls['wholesale_prices']['discount'] = $this->discount_tab_controls();
            // Gateway tab controls.
            $controls['wholesale_prices']['gateway'] = $this->gateway_tab_controls();
            // Cache tab controls.
            $controls['wholesale_prices']['cache'] = $this->cache_tab_controls();

            return $controls;
        }

        /**
         * Shipping tab controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function shipping_tab_controls() {

            $shipping_controls = array();

            $settings_wholesale_users_use_free_shipping       = get_option( 'wwpp_settings_wholesale_users_use_free_shipping' );
            $dynamic_free_shipping_title                      = get_option( 'wwpp_dynamic_free_shipping_title' );
            $dynamic_free_shipping_title                      = ( $dynamic_free_shipping_title ) ? $dynamic_free_shipping_title : __( 'Free Shipping', 'woocommerce-wholesale-prices-premium' );
            $settings_mapped_methods_for_wholesale_users_only = get_option( 'wwpp_settings_mapped_methods_for_wholesale_users_only' );

            $shipping_controls['shipping_options'] = array(
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Force Free Shipping', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_wholesale_users_use_free_shipping',
                    'input_label' => __( 'Forces all wholesale roles to use free shipping. All other shipping methods will be removed.', 'woocommerce-wholesale-prices-premium' ),
                    'description' => __( 'Note: If a wholesale role has ANY mappings in the table below, free shipping will not be forced.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $settings_wholesale_users_use_free_shipping,
                ),
                array(
                    'type'        => 'text',
                    'label'       => __( 'Free Shipping Label', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_dynamic_free_shipping_title',
                    'description' => __( 'If <b>"Force Free Shipping"</b> is enabled, a dynamically created free shipping method is created and used by force. The label for this defaults to <b>"Free Shipping"</b> but you can override that here.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $dynamic_free_shipping_title,
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Wholesale Only Shipping Methods', 'woocommerce-wholesale-prices-premium' ),
                    'input_label' => __( 'Hide the mapped shipping methods from non-wholesale customers. Regular customers will no longer be able to see the shipping methods you have mapped below.', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_mapped_methods_for_wholesale_users_only',
                    'default'     => $settings_mapped_methods_for_wholesale_users_only,
                ),
            );

            // Shipping Mapping options - Section.
            $group_non_zoning_methods   = array();
            $wc_shipping_zones          = WC_Shipping_Zones::get_zones();
            $wc_default_zone            = WC_Shipping_Zones::get_zone( 0 );
            $wc_zones                   = array();
            $zoned_shipping_methods     = array();
            $non_zoned_shipping_methods = array();
            $wc_shipping_methods        = WC()->shipping->load_shipping_methods();
            $wholesale_zone_mapping     = get_option( WWPP_OPTION_WHOLESALE_ROLE_SHIPPING_ZONE_METHOD_MAPPING, array() );

            // Get shipping zones.
            $wc_zones[ $wc_default_zone->get_id() ] = $wc_default_zone->get_zone_name();
            $wc_default_zone_options                = array();
            foreach ( $wc_default_zone->get_shipping_methods() as $sm ) {
                $wc_default_zone_options[ $sm->instance_id ] = $sm->title;
            }
            $zoned_shipping_methods[ $wc_default_zone->get_id() ] = $wc_default_zone_options;

            // Get zoned shipping methods.
            foreach ( $wc_shipping_zones as $zone ) {
                $wc_zones[ $zone['zone_id'] ] = $zone['zone_name'];
                $wc_zone_options              = array();
                if ( ! empty( $zone['shipping_methods'] ) ) {

                    // Add default to zoned shipping methods.
                    $wc_zone_options[''] = __( 'Select Shipping Zone Methods', 'woocommerce-wholesale-prices-premium' );

                    foreach ( $zone['shipping_methods'] as $shipping_method ) {
                        $wc_zone_options[ $shipping_method->instance_id ] = $shipping_method->title;
                    }
                }
                $zoned_shipping_methods[ $zone['zone_id'] ] = $wc_zone_options;
            }

            // Add default to zone.
            $wc_zones[''] = __( 'Select Shipping Zone', 'woocommerce-wholesale-prices-premium' );

            // Get non-zoned shipping methods.
            foreach ( $wc_shipping_methods as $shipping_method ) {
                if ( ! $shipping_method->supports( 'shipping-zones' ) && 'yes' === $shipping_method->enabled ) {
                    $non_zoned_shipping_methods[ $shipping_method->id ] = $shipping_method->method_title;
                }
            }

            // Add default non-zoned shipping methods.
            $non_zoned_shipping_methods[''] = __( 'Select Non-Zoned Shipping Zone Methods', 'woocommerce-wholesale-prices-premium' );

            // Loop and map data.
            if ( ! empty( $wholesale_zone_mapping ) ) {

                foreach ( $wholesale_zone_mapping as $idx => $mapping ) {
                    // Wholesale role text.
                    /* translators: %1$s wholesale role name */
                    $wholesale_role_text = sprintf( __( '%1$s role does not exist anymore', 'woocommerce-wholesale-prices-premium' ), $mapping['wholesale_role'] );
                    if ( array_key_exists( $mapping['wholesale_role'], $this->_all_wholesale_roles ) ) {
                        $wholesale_role_text = $this->_all_wholesale_roles[ $mapping['wholesale_role'] ]['roleName'];
                    }

                    // Shipping zone and method text.
                    $shipping_zone_text   = '';
                    $shipping_method_text = '';

                    if ( 'yes' === $mapping['use_non_zoned_shipping_method'] ) {
                        if ( array_key_exists( $mapping['non_zoned_shipping_method'], $non_zoned_shipping_methods ) ) {
                            $shipping_method_text = $non_zoned_shipping_methods[ $mapping['non_zoned_shipping_method'] ];
                        } else {
                            /* translators: %1$s non-zoned shipping method id */
                            $shipping_method_text = sprintf( __( 'Non-zoned shipping method with id of %1$s does not exist anymore', 'woocommerce-wholesale-prices-premium' ), $mapping['non_zoned_shipping_method'] );
                        }
                    } else {
                        $map_shipping_zone    = WC_Shipping_Zones::get_zone( (int) $mapping['shipping_zone'] );
                        $map_shipping_methods = array();

                        if ( $map_shipping_zone && $map_shipping_zone instanceof WC_Shipping_Zone ) {
                            $shipping_zone_text   = $map_shipping_zone->get_zone_name();
                            $map_shipping_methods = $map_shipping_zone->get_shipping_methods();
                        } else {
                            /* translators: %1$s shipping zone id */
                            $shipping_zone_text = sprintf( __( 'Shipping zone with id of %1$s does not exist anymore', 'woocommerce-wholesale-prices-premium' ), $mapping['shipping_zone'] );
                        }

                        // Shipping method text.
                        if ( array_key_exists( $mapping['shipping_method'], $map_shipping_methods ) ) {
                            $map_shipping_method  = $map_shipping_methods[ $mapping['shipping_method'] ];
                            $shipping_method_text = $map_shipping_method->title;
                        } else {
                            /* translators: %1$s shipping method instance id */
                            $shipping_method_text = sprintf( __( 'Shipping method with instance id of %1$s does not exist anymore', 'woocommerce-wholesale-prices-premium' ), $mapping['shipping_method'] );
                        }
                    }

                    $non_zoned = array(
                        'yes' => __( 'Yes', 'woocommerce-wholesale-prices-premium' ),
                        'no'  => __( 'No', 'woocommerce-wholesale-prices-premium' ),
                    );

                    $use_non_zoned_shipping_method = ! empty( $mapping['use_non_zoned_shipping_method'] ) ? $mapping['use_non_zoned_shipping_method'] : 'no';

                    $group_non_zoning_methods[] = array(
                        'key'                            => $idx,
                        'wholesale_role'                 => $mapping['wholesale_role'],
                        'wholesale_role_text'            => $wholesale_role_text,
                        'use_non_zoned_shipping_method'  => $use_non_zoned_shipping_method,
                        'use_non_zoned_shipping_method_text' => $non_zoned[ $use_non_zoned_shipping_method ],
                        'shipping_zone'                  => ! empty( $mapping['shipping_zone'] ) ? $mapping['shipping_zone'] : '',
                        'shipping_zone_text'             => $shipping_zone_text,
                        'shipping_method'                => ! empty( $mapping['shipping_method'] ) ? $mapping['shipping_method'] : '',
                        'shipping_method_text'           => $shipping_method_text,
                        'non_zoned_shipping_method'      => ! empty( $mapping['non_zoned_shipping_method'] ) ? $mapping['non_zoned_shipping_method'] : '',
                        'non_zoned_shipping_method_text' => $shipping_method_text,
                    );
                }
            }

            $shipping_controls['shipping_nonezone_mapping'] = array(
                array(
                    'type'         => 'group_conditional',
                    'label'        => __( 'Shipping Non-Zoned Mapping', 'woocommerce-wholesale-prices-premium' ),
                    'id'           => 'wwpp_group_shipping_nonzoned_mapping',
                    'classes'      => 'group-table-data margin-top',
                    'group_action' => 'group_shipping_nonzoned_mapping_save',
                    'fields'       => array(
                        array(
                            'type'     => 'select',
                            'label'    => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'wholesale_role',
                            'label_id' => 'wholesale_role_text',
                            'default'  => '',
                            'options'  => $this->all_roles(),
                        ),
                        array(
                            'type'           => 'select',
                            'label'          => __( 'Use Non-Zoned Shipping Methods', 'woocommerce-wholesale-prices-premium' ),
                            'id'             => 'use_non_zoned_shipping_method',
                            'label_id'       => 'use_non_zoned_shipping_method_text',
                            'default'        => 'no',
                            'with_condition' => true,
                            'options'        => array(
                                'yes' => __( 'Yes', 'woocommerce-wholesale-prices-premium' ),
                                'no'  => __( 'No', 'woocommerce-wholesale-prices-premium' ),
                            ),
                        ),
                        array(
                            'type'      => 'select',
                            'label'     => __( 'Shipping Zones', 'woocommerce-wholesale-prices-premium' ),
                            'id'        => 'shipping_zone',
                            'label_id'  => 'shipping_zone_text',
                            'options'   => $wc_zones,
                            'dependent' => true,
                            'default'   => '',
                            'condition' => array(
                                array(
                                    'key'   => 'use_non_zoned_shipping_method',
                                    'value' => array(
                                        'no',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'              => 'select',
                            'label'             => __( 'Shipping Zone Methods', 'woocommerce-wholesale-prices-premium' ),
                            'id'                => 'shipping_method',
                            'label_id'          => 'shipping_method_text',
                            'dependent_option'  => 'shipping_zone',
                            'dependent_default' => '',
                            'default'           => '',
                            'options'           => $zoned_shipping_methods,
                            'condition'         => array(
                                array(
                                    'key'   => 'use_non_zoned_shipping_method',
                                    'value' => array(
                                        'no',
                                    ),
                                ),
                            ),
                        ),
                        array(
                            'type'                  => 'select',
                            'label'                 => __( 'Non-Zoned Shipping Zone Methods', 'woocommerce-wholesale-prices-premium' ),
                            'id'                    => 'non_zoned_shipping_method',
                            'label_id'              => 'non_zoned_shipping_method_text',
                            'options'               => $non_zoned_shipping_methods,
                            'column_dependent'      => 'shipping_method',
                            'column_dependent_text' => 'shipping_method_text',
                            'hide'                  => true,
                            'condition'             => array(
                                array(
                                    'key'   => 'use_non_zoned_shipping_method',
                                    'value' => array(
                                        'yes',
                                    ),
                                ),
                            ),
                        ),
                    ),
                    'table'        => array(
                        'paginated'     => true,
                        'editable'      => true,
                        'can_delete'    => true,
                        'inline_edit'   => false,
                        'edit_action'   => 'group_shipping_nonzoned_mapping_edit',
                        'delete_action' => 'group_shipping_nonzoned_mapping_delete',
                        'fields'        => array(
                            array(
                                'title'     => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'wholesale_role_text',
                                'field'     => 'wholesale_role',
                                'key'       => 'wholesale_role_text',
                            ),
                            array(
                                'title'     => __( 'Shipping Zone', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'shipping_zone_text',
                                'field'     => 'shipping_zone',
                                'key'       => 'shipping_zone_text',
                            ),
                            array(
                                'title'     => __( 'Shipping Method', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'shipping_method_text',
                                'field'     => 'shipping_method',
                                'key'       => 'shipping_method_text',
                            ),
                            array(
                                'title'     => __( 'Use Non-Zoned?', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'use_non_zoned_shipping_method_text',
                                'field'     => 'use_non_zoned_shipping_method',
                                'key'       => 'use_non_zoned_shipping_method_text',
                            ),
                            array(
                                'title'     => __( 'Action', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'operation',
                                'field'     => 'operation',
                                'key'       => 'operation',
                            ),
                        ),
                        'data'          => $group_non_zoning_methods,
                    ),
                    'button_label' => __( 'Add Mapping', 'woocommerce-wholesale-prices-premium' ),
                ),
            );

            return $shipping_controls;
        }

        /**
         * Discount tab controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function discount_tab_controls() {

            $discount_controls = array();

            // General Discount Mapping options - Section.
            $saved_general_discounts = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, array() );
            if ( ! is_array( $saved_general_discounts ) ) {
                $saved_general_discounts = array();
            }

            $group_general_discounts = array();
            if ( ! empty( $saved_general_discounts ) ) {
                $i = 0;
                foreach ( $saved_general_discounts as $role => $discount ) {
                    /* translators: %1$s wholesale role name */
                    $wholesale_role_name = sprintf( __( '%1$s role does not exist anymore', 'woocommerce-wholesale-prices-premium' ), $role );
                    if ( isset( $this->_all_wholesale_roles[ $role ]['roleName'] ) ) {
                        $wholesale_role_name = $this->_all_wholesale_roles[ $role ]['roleName'];
                    }

                    $group_general_discounts[] = array(
                        'key'                 => $i,
                        'wholesale_role'      => $role,
                        'wholesale_role_name' => $wholesale_role_name,
                        'general_discount'    => $discount,
                    );
                    ++$i;
                }
            }

            $discount_controls['general_options'] = array(
                array(
                    'type'         => 'group',
                    'label'        => __( 'General Discount', 'woocommerce-wholesale-prices-premium' ),
                    'id'           => 'wwpp_group_general_discount_mapping',
                    'classes'      => 'group-table-data margin-top',
                    'group_action' => 'group_general_discount_mapping_save',
                    'fields'       => array(
                        array(
                            'type'     => 'select',
                            'label'    => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'wholesale_role',
                            'label_id' => 'wholesale_role_name',
                            'default'  => '',
                            'options'  => $this->all_roles(),
                        ),
                        array(
                            'type'        => 'number',
                            'label'       => __( 'Percent Discount', 'woocommerce-wholesale-prices-premium' ),
                            'id'          => 'general_discount',
                            'description' => __( 'General discount for products purchase by this wholesale role. In percent (%), Ex. 3 percent then input 3, 30 percent then input 30, 0.3 percent then input 0.3.', 'woocommerce-wholesale-prices-premium' ),
                        ),
                    ),
                    'table'        => array(
                        'paginated'     => true,
                        'editable'      => true,
                        'can_delete'    => true,
                        'edit_action'   => 'group_general_discount_mapping_edit',
                        'delete_action' => 'group_general_discount_mapping_delete',
                        'fields'        => array(
                            array(
                                'title'     => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'wholesale_role_name',
                                'field'     => 'wholesale_role',
                                'key'       => 'wholesale_role_name',
                            ),
                            array(
                                'title'     => __( 'General Discount', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'general_discount',
                                'field'     => 'general_discount',
                                'key'       => 'general_discount',
                                'suffix'    => '%',
                            ),
                            array(
                                'title'     => __( 'Action', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'operation',
                                'field'     => 'operation',
                                'key'       => 'operation',
                            ),
                        ),
                        'data'          => $group_general_discounts,
                    ),
                    'button_label' => __( 'Add Mapping', 'woocommerce-wholesale-prices-premium' ),
                ),
            );

            // Quantity Discount Mapping options - Section.
            $quantity_based_wholesale_discount        = get_option( 'enable_wholesale_role_cart_quantity_based_wholesale_discount' );
            $quantity_based_wholesale_discount_mode_2 = get_option( 'enable_wholesale_role_cart_quantity_based_wholesale_discount_mode_2' );

            $cart_qty_discount_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_QTY_BASED_DISCOUNT_MAPPING, array() );
            if ( ! is_array( $cart_qty_discount_mapping ) ) {
                $cart_qty_discount_mapping = array();
            }

            $group_general_quantity_discounts = array();
            if ( ! empty( $cart_qty_discount_mapping ) ) {
                foreach ( $cart_qty_discount_mapping as $index => $mapping ) {
                    /* translators: %1$s wholesale role name */
                    $wholesale_role_name = sprintf( __( '%1$s role does not exist anymore', 'woocommerce-wholesale-prices-premium' ), $mapping['wholesale_role'] );
                    if ( isset( $this->_all_wholesale_roles[ $mapping['wholesale_role'] ]['roleName'] ) ) {
                        $wholesale_role_name = $this->_all_wholesale_roles[ $mapping['wholesale_role'] ]['roleName'];
                    }

                    $group_general_quantity_discounts[] = array(
                        'key'                 => $index,
                        'wholesale_role'      => $mapping['wholesale_role'],
                        'wholesale_role_name' => $wholesale_role_name,
                        'start_qty'           => $mapping['start_qty'],
                        'end_qty'             => isset( $mapping['end_qty'] ) ? $mapping['end_qty'] : '',
                        'percent_discount'    => $mapping['percent_discount'],
                    );
                }
            }

            $discount_controls['general_quantity_discounts'] = array(
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Enable General Quantity Based Discounts', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'enable_wholesale_role_cart_quantity_based_wholesale_discount',
                    'input_label' => __( 'Turns the general quantity based discount system on/off. Mappings below will be disregarded if this option is unchecked.', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'default'     => $quantity_based_wholesale_discount,
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Apply Discounts Based On Individual Product Quantities?', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'enable_wholesale_role_cart_quantity_based_wholesale_discount_mode_2',
                    'input_label' => __( 'By default, the general quantity based discounts system will use the total quantity of all items in the cart. This option changes this to apply quantity based discounts based on the quantity of individual products in the cart.', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'default'     => $quantity_based_wholesale_discount_mode_2,
                ),
                array(
                    'type'         => 'group',
                    'label'        => __( 'General Quantity Discount', 'woocommerce-wholesale-prices-premium' ),
                    'id'           => 'wwpp_group_general_quantity_discount_mapping',
                    'classes'      => 'group-table-data margin-top',
                    'group_action' => 'group_general_quantity_mapping_save',
                    'fields'       => array(
                        array(
                            'type'     => 'select',
                            'label'    => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'wholesale_role',
                            'label_id' => 'wholesale_role_name',
                            'default'  => '',
                            'options'  => $this->all_roles(),
                            'desc_tip' => __( 'Select wholesale role to which rule applies.', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        array(
                            'type'     => 'number',
                            'label'    => __( 'Starting Qty', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'start_qty',
                            'desc_tip' => __( 'Minimum order quantity required for this rule. Must be a number.', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        array(
                            'type'     => 'number',
                            'label'    => __( 'Ending Qty', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'end_qty',
                            'desc_tip' => __( 'Maximum order quantity required for this rule. Must be a number. Leave this blank for no maximum quantity.', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        array(
                            'type'        => 'number',
                            'label'       => __( 'Percent Discount', 'woocommerce-wholesale-prices-premium' ),
                            'id'          => 'percent_discount',
                            'description' => __( 'New percentage amount off the retail price. Provide value in percent (%), Ex. 3 percent then input 3, 30 percent then input 30, 0.3 percent then input 0.3.', 'woocommerce-wholesale-prices-premium' ),
                            'desc_tip'    => __( 'The new % value off the regular price. This will be the discount value used for quantities within the given range.' ),
                        ),
                    ),
                    'table'        => array(
                        'paginated'     => true,
                        'editable'      => true,
                        'can_delete'    => true,
                        'edit_action'   => 'group_general_quantity_mapping_edit',
                        'delete_action' => 'group_general_quantity_mapping_delete',
                        'fields'        => array(
                            array(
                                'title'     => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'wholesale_role_name',
                                'field'     => 'wholesale_role',
                                'key'       => 'wholesale_role_name',
                            ),
                            array(
                                'title'     => __( 'Starting Qty', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'start_qty',
                                'field'     => 'start_qty',
                                'key'       => 'start_qty',
                            ),
                            array(
                                'title'     => __( 'Ending Qty', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'end_qty',
                                'field'     => 'end_qty',
                                'key'       => 'end_qty',
                            ),
                            array(
                                'title'     => __( 'Percent Discount', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'percent_discount',
                                'field'     => 'percent_discount',
                                'key'       => 'percent_discount',
                                'suffix'    => '%',
                            ),
                            array(
                                'title'     => __( 'Action', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'operation',
                                'field'     => 'operation',
                                'key'       => 'operation',
                            ),
                        ),
                        'data'          => $group_general_quantity_discounts,
                    ),
                    'button_label' => __( 'Add Mapping', 'woocommerce-wholesale-prices-premium' ),
                ),
            );

            // Cart Price Discount Mapping options - Section.
            $apply_discount_if_min_order_req_met = get_option( 'enable_wholesale_role_cart_only_apply_discount_if_min_order_req_met' );
            $types                               = array(
                'percent-discount' => __( 'Percent Discount', 'woocommerce-wholesale-prices-premium' ),
                'fixed-discount'   => __( 'Fixed Discount', 'woocommerce-wholesale-prices-premium' ),
            );

            $cart_subtotal_price_based_discount_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_CART_SUBTOTAL_PRICE_BASED_DISCOUNT_MAPPING, array() );
            if ( ! is_array( $cart_subtotal_price_based_discount_mapping ) ) {
                $cart_subtotal_price_based_discount_mapping = array();
            }

            $group_general_cart_price_discounts = array();
            if ( ! empty( $cart_subtotal_price_based_discount_mapping ) ) {
                foreach ( $cart_subtotal_price_based_discount_mapping as $index => $mapping ) {
                    /* translators: %1$s wholesale role name */
                    $wholesale_role_name = sprintf( __( '%1$s role does not exist anymore', 'woocommerce-wholesale-prices-premium' ), $mapping['wholesale_role'] );
                    if ( isset( $this->_all_wholesale_roles[ $mapping['wholesale_role'] ]['roleName'] ) ) {
                        $wholesale_role_name = $this->_all_wholesale_roles[ $mapping['wholesale_role'] ]['roleName'];
                    }

                    $group_general_cart_price_discounts[] = array(
                        'key'                 => $index,
                        'wholesale_role'      => $mapping['wholesale_role'],
                        'wholesale_role_name' => $wholesale_role_name,
                        'subtotal_price'      => $mapping['subtotal_price'],
                        'discount_type'       => $mapping['discount_type'],
                        'discount_type_text'  => $types[ $mapping['discount_type'] ],
                        'discount_amount'     => $mapping['discount_amount'],
                        'discount_title'      => $mapping['discount_title'],
                    );
                }
            }

            $discount_controls['cart_price_discounts'] = array(
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Only Apply Discounts If Minimum Order Requirements Met', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'enable_wholesale_role_cart_only_apply_discount_if_min_order_req_met',
                    'input_label' => __( 'When enabled, prevents the customer from getting the below additional discounts if they haven’t met the minimum order requirements.', 'woocommerce-wholesale-prices-premium' ),
                    'multiple'    => false,
                    'default'     => $apply_discount_if_min_order_req_met,
                ),
                array(
                    'type'         => 'group',
                    'label'        => __( 'General Cart Price Discount', 'woocommerce-wholesale-prices-premium' ),
                    'id'           => 'wwpp_group_general_cart_price_discount_mapping',
                    'classes'      => 'group-table-data margin-top',
                    'group_action' => 'group_general_cart_price_discount_mapping_save',
                    'fields'       => array(
                        array(
                            'type'     => 'select',
                            'label'    => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'wholesale_role',
                            'label_id' => 'wholesale_role_name',
                            'default'  => '',
                            'options'  => $this->all_roles(),
                            'desc_tip' => __( 'Select wholesale role to which rule applies.', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        array(
                            'type'     => 'number',
                            'label'    => __( 'Subtotal Price', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'subtotal_price',
                            'desc_tip' => __( 'The cart subtotal price that the discount will start applying at (excluding taxes and shipping). Must be a number.', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        array(
                            'type'     => 'select',
                            'label'    => __( 'Discount Type', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'discount_type',
                            'label_id' => 'discount_type_text',
                            'default'  => '',
                            'options'  => $types,
                            'desc_tip' => __( 'The type of discount which the price is calculated.', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        array(
                            'type'        => 'number',
                            'label'       => __( 'Discount Amount', 'woocommerce-wholesale-prices-premium' ),
                            'id'          => 'discount_amount',
                            'desc_tip'    => __( 'Maximum order quantity required for this rule. Must be a number. Leave this blank for no maximum quantity.', 'woocommerce-wholesale-prices-premium' ),
                            'description' => __( 'Discount amount off the cart subtotal price. If discount type is percentage (%), Ex. 3 percent then input 3, 30 percent then input 30, 0.3 percent then input 0.3.', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        array(
                            'type'     => 'text',
                            'label'    => __( 'Discount Title', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'discount_title',
                            'desc_tip' => __( 'A short title to show the user for this discount. Shown on the totals table.' ),
                        ),
                    ),
                    'table'        => array(
                        'paginated'     => true,
                        'editable'      => true,
                        'can_delete'    => true,
                        'edit_action'   => 'group_general_cart_price_discount_mapping_edit',
                        'delete_action' => 'group_general_cart_price_discount_mapping_delete',
                        'fields'        => array(
                            array(
                                'title'     => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'wholesale_role_name',
                                'field'     => 'wholesale_role',
                                'key'       => 'wholesale_role_name',
                            ),
                            array(
                                'title'     => __( 'Subtotal Price', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'subtotal_price',
                                'field'     => 'subtotal_price',
                                'key'       => 'subtotal_price',
                            ),
                            array(
                                'title'     => __( 'Discount Type', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'discount_type_text',
                                'field'     => 'discount_type',
                                'key'       => 'discount_type_text',
                            ),
                            array(
                                'title'     => __( 'Discount Amount', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'discount_amount',
                                'field'     => 'discount_amount',
                                'key'       => 'discount_amount',
                            ),
                            array(
                                'title'     => __( 'Discount Title', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'discount_title',
                                'field'     => 'discount_title',
                                'key'       => 'discount_title',
                            ),
                            array(
                                'title'     => __( 'Action', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'operation',
                                'field'     => 'operation',
                                'key'       => 'operation',
                            ),
                        ),
                        'data'          => $group_general_cart_price_discounts,
                    ),
                    'button_label' => __( 'Add Mapping', 'woocommerce-wholesale-prices-premium' ),
                ),
            );

            return $discount_controls;
        }

        /**
         * Gateway tab controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function gateway_tab_controls() {

            $gateway_controls = array();

            $available_gateways         = WC()->payment_gateways->payment_gateways();
            $available_payment_gateways = array();

            if ( is_array( $available_gateways ) && ! empty( $available_gateways ) ) {
                foreach ( $available_gateways as $gateway_key => $gateway ) {
                    $gateway_title = '';
                    if ( ! empty( $gateway->title ) && $gateway->title !== $gateway->method_title ) {
                        $gateway_title = $gateway->method_title . ' | ' . $gateway->title;
                    } else {
                        $gateway_title = $gateway->method_title;
                    }

                    $available_payment_gateways[ $gateway_key ] = $gateway_title;
                }
            }

            // Add wholesale role payment gateway mapping.
            $wholesale_role_payment_gateway_papping = get_option( WWPP_OPTION_WHOLESALE_ROLE_PAYMENT_GATEWAY_MAPPING, array() );
            if ( ! is_array( $wholesale_role_payment_gateway_papping ) ) {
                $wholesale_role_payment_gateway_papping = array();
            }

            $group_gateway_options = array();
            if ( ! empty( $wholesale_role_payment_gateway_papping ) ) {
                $i = 0;
                foreach ( $wholesale_role_payment_gateway_papping as $role => $payment_gateways ) {
                    /* translators: %1$s wholesale role name */
                    $wholesale_role_name = sprintf( __( '%1$s role does not exist anymore', 'woocommerce-wholesale-prices-premium' ), $role );
                    if ( isset( $this->_all_wholesale_roles[ $role ]['roleName'] ) ) {
                        $wholesale_role_name = $this->_all_wholesale_roles[ $role ]['roleName'];
                    }

                    $gateway_titles = array();
                    $gateway_ids    = array();

                    foreach ( $payment_gateways as $payment_gateway ) {
                        $gateway_titles[] = $available_payment_gateways[ $payment_gateway['id'] ];
                        $gateway_ids[]    = $payment_gateway['id'];
                    }

                    $group_gateway_options[] = array(
                        'key'                   => $i,
                        'wholesale_role'        => $role,
                        'wholesale_role_name'   => $wholesale_role_name,
                        'payment_gateways'      => $gateway_ids,
                        'payment_gateways_text' => $gateway_titles,
                    );
                    ++$i;
                }
            }

            $install_activate_text = __( 'Install & activate now >', 'woocommerce-wholesale-prices-premium' );
            $install_activate_url  = esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-payments', 'wwpp', 'protip', 'wpaygatewayprotip' ) );

            $gateway_controls['gateway_options'] = array(
                array(
                    'type'         => 'group',
                    'label'        => __( 'Wholesale Payment Gateway', 'woocommerce-wholesale-prices-premium' ),
                    'id'           => 'wwpp_group_payment_gateway_mapping',
                    'classes'      => 'group-table-data margin-top',
                    'group_action' => 'group_payment_gateway_mapping_save',
                    'fields'       => array(
                        array(
                            'type'     => 'select',
                            'label'    => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'wholesale_role',
                            'label_id' => 'wholesale_role_name',
                            'default'  => '',
                            'options'  => $this->all_roles(),
                        ),
                        array(
                            'type'        => 'select',
                            'label'       => __( 'Payment Gateways', 'woocommerce-wholesale-prices-premium' ),
                            'id'          => 'payment_gateways',
                            'label_id'    => 'payment_gateways_text',
                            'default'     => array(),
                            'multiple'    => true,
                            'options'     => $available_payment_gateways,
                            'description' => sprintf(
                                /* translators: %1$s <br>, %2$s open span, %3$s close span */
                                __(
                                    'You can add multiple payment gateways to a single role mapping.%1$s %2$sPro Tip: You can add NET 30/45/60 invoices for your wholesale customers using the Wholesale Payment plugin. %3$s',
                                    'woocommerce-wholesale-prices-premium'
                                ),
                                '<br>',
                                '<span class="wwpp-recommendation-notice"><span class="dashicons dashicons-info-outline"></span>&nbsp;',
                                '<a href="' . $install_activate_url . '" target="_blank">' . $install_activate_text . '</a></span>'
                            ),
                        ),
                    ),
                    'table'        => array(
                        'paginated'     => true,
                        'editable'      => true,
                        'can_delete'    => true,
                        'edit_action'   => 'group_payment_gateway_mapping_edit',
                        'delete_action' => 'group_payment_gateway_mapping_delete',
                        'fields'        => array(
                            array(
                                'title'     => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'wholesale_role_name',
                                'field'     => 'wholesale_role',
                                'key'       => 'wholesale_role_name',
                            ),
                            array(
                                'title'     => __( 'Payment Gateways', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'payment_gateways_text',
                                'field'     => 'payment_gateways',
                                'key'       => 'payment_gateways_text',
                            ),
                            array(
                                'title'     => __( 'Action', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'operation',
                                'field'     => 'operation',
                                'key'       => 'operation',
                            ),
                        ),
                        'data'          => $group_gateway_options,
                    ),
                    'button_label' => __( 'Add Mapping', 'woocommerce-wholesale-prices-premium' ),
                ),
            );

            // Add payment gateway default.
            $available_payment_gateways[''] = __( 'Choose Payment Gateway', 'woocommerce-wholesale-prices-premium' );

            // Add gateway surcharge mapping.
            $surcharge_types = array(
                ''            => __( 'Select Surcharge Type', 'woocommerce-wholesale-prices-premium' ),
                'fixed_price' => __( 'Fixed Price', 'woocommerce-wholesale-prices-premium' ),
                'percentage'  => __( 'Percentage', 'woocommerce-wholesale-prices-premium' ),
            );
            $taxable_types   = array(
                ''    => __( 'Select an option', 'woocommerce-wholesale-prices-premium' ),
                'yes' => __( 'Yes', 'woocommerce-wholesale-prices-premium' ),
                'no'  => __( 'No', 'woocommerce-wholesale-prices-premium' ),
            );

            $payment_gateway_surcharge = get_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING, array() );
            if ( ! is_array( $payment_gateway_surcharge ) ) {
                $payment_gateway_surcharge = array();
            }

            $group_gateway_surcharges = array();
            if ( ! empty( $payment_gateway_surcharge ) ) {
                foreach ( $payment_gateway_surcharge as $idx => $surcharge ) {
                    $role = $surcharge['wholesale_role'];
                    /* translators: %1$s wholesale role name */
                    $wholesale_role_name = sprintf( __( '%1$s role does not exist anymore', 'woocommerce-wholesale-prices-premium' ), $role );
                    if ( isset( $this->_all_wholesale_roles[ $role ]['roleName'] ) ) {
                        $wholesale_role_name = $this->_all_wholesale_roles[ $role ]['roleName'];
                    }

                    $payment_method = ! empty( $available_gateways[ $surcharge['payment_gateway'] ]->title && $available_gateways[ $surcharge['payment_gateway'] ]->title !== $available_gateways[ $surcharge['payment_gateway'] ]->method_title ) ? $available_gateways[ $surcharge['payment_gateway'] ]->method_title . ' | ' . $available_gateways[ $surcharge['payment_gateway'] ]->title : $available_gateways[ $surcharge['payment_gateway'] ]->method_title;

                    /* translators: %1$s payment gateway title */
                    $surcharge_gateway_title = isset( $available_gateways[ $surcharge['payment_gateway'] ] ) ? $payment_method : sprintf( __( 'Warning: The payment gateway <b>%1$s</b> does not exist anymore' ), $available_gateways[ $surcharge['payment_gateway'] ] );

                    $group_gateway_surcharges[] = array(
                        'key'                  => $idx,
                        'wholesale_role'       => $role,
                        'wholesale_role_name'  => $wholesale_role_name,
                        'payment_gateway'      => $surcharge['payment_gateway'],
                        'payment_gateway_text' => $surcharge_gateway_title,
                        'surcharge_title'      => $surcharge['surcharge_title'],
                        'surcharge_type'       => $surcharge['surcharge_type'],
                        'surcharge_type_text'  => $surcharge_types[ $surcharge['surcharge_type'] ],
                        'surcharge_amount'     => $surcharge['surcharge_amount'],
                        'taxable'              => $surcharge['taxable'],
                        'taxable_text'         => $taxable_types[ $surcharge['taxable'] ],
                    );
                }
            }

            $gateway_controls['gateway_surcharge_options'] = array(
                array(
                    'type'         => 'group',
                    'label'        => __( 'Wholesale Payment Gateway', 'woocommerce-wholesale-prices-premium' ),
                    'id'           => 'wwpp_group_payment_gateway_surcharge_mapping',
                    'classes'      => 'group-table-data margin-top',
                    'group_action' => 'group_payment_gateway_surcharge_mapping_save',
                    'fields'       => array(
                        array(
                            'type'     => 'select',
                            'label'    => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'wholesale_role',
                            'label_id' => 'wholesale_role_name',
                            'default'  => '',
                            'options'  => $this->all_roles(),
                        ),
                        array(
                            'type'     => 'select',
                            'label'    => __( 'Payment Gateways', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'payment_gateway',
                            'label_id' => 'payment_gateway_text',
                            'default'  => '',
                            'options'  => $available_payment_gateways,
                        ),
                        array(
                            'type'  => 'text',
                            'label' => __( 'Surcharge Title', 'woocommerce-wholesale-prices-premium' ),
                            'id'    => 'surcharge_title',
                        ),
                        array(
                            'type'     => 'select',
                            'label'    => __( 'Surcharge Type', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'surcharge_type',
                            'label_id' => 'surcharge_type_text',
                            'options'  => $surcharge_types,
                        ),
                        array(
                            'type'        => 'number',
                            'label'       => __( 'Surcharge Amount', 'woocommerce-wholesale-prices-premium' ),
                            'id'          => 'surcharge_amount',
                            'description' => __( 'If surcharge type is percentage, then input amount in percent (%). Ex. 3 percent then input 3, 30 percent then input 30, 0.3 percent then input 0.3.', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        array(
                            'type'     => 'select',
                            'label'    => __( 'Taxable?', 'woocommerce-wholesale-prices-premium' ),
                            'id'       => 'taxable',
                            'label_id' => 'taxable_text',
                            'default'  => '',
                            'options'  => $taxable_types,
                        ),
                    ),
                    'table'        => array(
                        'paginated'     => true,
                        'editable'      => true,
                        'can_delete'    => true,
                        'edit_action'   => 'group_payment_gateway_surcharge_mapping_edit',
                        'delete_action' => 'group_payment_gateway_surcharge_mapping_delete',
                        'fields'        => array(
                            array(
                                'title'     => __( 'Wholesale Role', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'wholesale_role_name',
                                'field'     => 'wholesale_role',
                                'key'       => 'wholesale_role_name',
                            ),
                            array(
                                'title'     => __( 'Payment Gateway', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'payment_gateway_text',
                                'field'     => 'payment_gateway',
                                'key'       => 'payment_gateway_text',
                            ),
                            array(
                                'title'     => __( 'Surcharge Title', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'surcharge_title',
                                'field'     => 'surcharge_title',
                                'key'       => 'surcharge_title',
                            ),
                            array(
                                'title'     => __( 'Surcharge Type', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'surcharge_type_text',
                                'field'     => 'surcharge_type',
                                'key'       => 'surcharge_type_text',
                            ),
                            array(
                                'title'     => __( 'Surcharge Amount', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'surcharge_amount',
                                'field'     => 'surcharge_amount',
                                'key'       => 'surcharge_amount',
                            ),
                            array(
                                'title'     => __( 'Taxable', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'taxable_text',
                                'field'     => 'taxable',
                                'key'       => 'taxable_text',
                            ),
                            array(
                                'title'     => __( 'Action', 'woocommerce-wholesale-prices-premium' ),
                                'dataIndex' => 'operation',
                                'field'     => 'operation',
                                'key'       => 'operation',
                            ),
                        ),
                        'data'          => $group_gateway_surcharges,
                    ),
                    'button_label' => __( 'Add Mapping', 'woocommerce-wholesale-prices-premium' ),
                ),
            );

            return $gateway_controls;
        }

        /**
         * Cache tab controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function cache_tab_controls() {

            $cache_controls = array();

            $enable_wholesale_price_cache        = get_option( 'wwpp_enable_wholesale_price_cache' );
            $enable_var_prod_price_range_caching = get_option( 'wwpp_enable_var_prod_price_range_caching' );
            $enable_product_cache                = get_option( 'wwpp_enable_product_cache' );

            $cache_controls['cache_options'] = array(
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Enable wholesale price caching', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_enable_wholesale_price_cache',
                    'input_label' => __( 'When enabled, products with wholesale price will be cached to improve loading time when the product has multiple tier of wholesale prices.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $enable_wholesale_price_cache,
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Enable variable product price range caching', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_enable_var_prod_price_range_caching',
                    'input_label' => __( 'When enabled, wholesale price ranges for variable products will be cached by the system. This speeds up the loading of your category pages and single product pages.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $enable_var_prod_price_range_caching,
                ),
                array(
                    'type'         => 'button',
                    'label'        => __( 'Clear variable product price range and wholesale price cache', 'woocommerce-wholesale-prices-premium' ),
                    'id'           => 'wwpp_clear_var_prod_price_range_caching',
                    'button_label' => __( 'Clear Cache', 'woocommerce-wholesale-prices-premium' ),
                    'action'       => 'clear_var_prod_price_range_caching',
                    'description'  => __( 'Clear all cached wholesale variable product price ranges and the wholesale prices. Note: the cache system keeps itself up to date, so only do this if you are experiencing price range problems.', 'woocommerce-wholesale-prices-premium' ),
                    'confirmation' => true,
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Enable product ID caching', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_enable_product_cache',
                    'input_label' => __( 'When enabled, product IDs will be cached for visibility purposes for each user role to reduce the load time for large product catalogs.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $enable_product_cache,
                ),
                array(
                    'type'         => 'button',
                    'label'        => __( 'Clear product ID cache', 'woocommerce-wholesale-prices-premium' ),
                    'id'           => 'wwpp_wwpp_clear_product_caching',
                    'button_label' => __( 'Clear Cache', 'woocommerce-wholesale-prices-premium' ),
                    'action'       => 'clear_product_caching',
                    'description'  => __( 'Clear all product ID caches for each role. Caches are automatically rebuilt when visiting the shop page or other product listings.', 'woocommerce-wholesale-prices-premium' ),
                    'confirmation' => true,
                ),
            );

            return $cache_controls;
        }

        /**
         * Help tab controls.
         *
         * @param array $controls Tax tab controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function admin_settings_help_controls( $controls ) {

            $controls['shipping_options'] = array();

            $settings_help_clean_plugin_options_on_uninstall = get_option( 'wwpp_settings_help_clean_plugin_options_on_uninstall' );

            $controls['debug_tools'] = array(
                array(
                    'type'         => 'button',
                    'label'        => __( 'Clear Unused Product Meta', 'woocommerce-wholesale-prices-premium' ),
                    'id'           => 'wwpp_clear_unused_product_meta',
                    'button_label' => __( 'Clear Unused Product Meta', 'woocommerce-wholesale-prices-premium' ),
                    'action'       => 'clear_unused_product_meta',
                    'description'  => __( 'Option to clear product meta that isn\'t used. This is a result from deleting wholesale role. This is useful for import/export to have a clean product meta data.', 'woocommerce-wholesale-prices-premium' ),
                ),
                array(
                    'type'         => 'button',
                    'label'        => __( 'Product Visibility Meta', 'woocommerce-wholesale-prices-premium' ),
                    'id'           => 'wwpp_initialize_product_visibility_meta',
                    'button_label' => __( 'Re-Initialize Product Visibility Meta', 'woocommerce-wholesale-prices-premium' ),
                    'action'       => 'initialize_product_visibility_meta',
                    'description'  => __( 'Re-initialize the product visibility meta data for all simple and variable products in the system. Sometimes after product importing or manual database manipulation, the visibility meta used to determine the visibility of your products to wholesalers will be malformed. This button resets all the product visibility meta data so your product visibility options are properly respected.', 'woocommerce-wholesale-prices-premium' ),
                ),
                array(
                    'type'        => 'checkbox',
                    'label'       => __( 'Clean up plugin options on un-installation', 'woocommerce-wholesale-prices-premium' ),
                    'id'          => 'wwpp_settings_help_clean_plugin_options_on_uninstall',
                    'input_label' => __( 'If checked, removes all plugin options when this plugin is uninstalled. <strong>Warning:<strong> This process is irreversible.', 'woocommerce-wholesale-prices-premium' ),
                    'default'     => $settings_help_clean_plugin_options_on_uninstall,
                ),
                array(
                    'type'         => 'button',
                    'label'        => __( 'Refetch Plugin Update Data', 'woocommerce-wholesale-prices-premium' ),
                    'id'           => 'wwpp_force_fetch_update_data',
                    'button_label' => __( 'Refetch Plugin Update Data', 'woocommerce-wholesale-prices-premium' ),
                    'action'       => 'force_fetch_update_data',
                    'description'  => __( 'This will refetch the plugin update data. Useful for debugging failed plugin update operations.', 'woocommerce-wholesale-prices-premium' ),
                    'confirmation' => true,
                ),
            );

            return $controls;
        }

        /**
         * Upgrade tab controls.
         *
         * @param array $controls Tax tab controls.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function admin_settings_upgrade_controls( $controls ) {

            $controls['upgrade_options'] = array();

            $wws_installed_label = array(
                'type'    => 'paragraph',
                'id'      => 'wwp_bundle_param_installed',
                'content' => __( '<em><span class="dashicons dashicons-yes-alt"></span> Installed<em>', 'woocommerce-wholesale-prices-premium' ),
                'classes' => 'wwp-package-link wwp-installed-label wwp-package-active',
            );

            // WWPP is active or not.
            $wwp_wwpp_is_active       = WWP_Helper_Functions::is_wwpp_active();
            $wwp_wwpp_installed_label = array(
                'type'         => 'button',
                'id'           => 'wwp_bundle_link1',
                'button_label' => __( 'Learn more about Prices Premium', 'woocommerce-wholesale-prices-premium' ),
                'link'         => 'https://wholesalesuiteplugin.com/woocommerce-wholesale-prices-premium/?utm_source=wwp&utm_medium=upsell&utm_campaign=upgradepagewwpplearnmore',
                'external'     => true,
                'classes'      => 'wwp-package-link',
            );
            if ( $wwp_wwpp_is_active ) {
                $wwp_wwpp_installed_label = $wws_installed_label;
            }

            // WWOF is active or not.
            $wwp_wwof_is_active       = WWP_Helper_Functions::is_wwof_active();
            $wwp_wwof_installed_label = array(
                'type'         => 'button',
                'id'           => 'wwp_bundle_link2',
                'button_label' => __( 'Learn more about Order Form', 'woocommerce-wholesale-prices-premium' ),
                'link'         => 'https://wholesalesuiteplugin.com/woocommerce-wholesale-order-form/?utm_source=wwp&utm_medium=upsell&utm_campaign=upgradepagewwoflearnmore',
                'external'     => true,
                'classes'      => 'wwp-package-link',
            );
            if ( $wwp_wwof_is_active ) {
                $wwp_wwof_installed_label = $wws_installed_label;
            }

            // WWLC is active or not.
            $wwp_wwlc_is_active       = WWP_Helper_Functions::is_wwlc_active();
            $wwp_wwlc_installed_label = array(
                'type'         => 'button',
                'id'           => 'wwp_bundle_link3',
                'button_label' => __( 'Learn more about Lead Capture', 'woocommerce-wholesale-prices-premium' ),
                'link'         => 'https://wholesalesuiteplugin.com/woocommerce-wholesale-prices-premium/?utm_source=wwp&utm_medium=upsell&utm_campaign=upgradepagewwlclearnmore',
                'external'     => true,
                'classes'      => 'wwp-package-link',
            );
            if ( $wwp_wwlc_is_active ) {
                $wwp_wwlc_installed_label = $wws_installed_label;
            }

            $controls['upgrade_options2'] = array(
                array(
                    'type'    => 'html',
                    'id'      => 'wwp_settings_upgrade_code_block2',
                    'classes' => 'wwp-upgrade-code2-block',
                    'fields'  => array(
                        array(
                            'type'    => 'heading',
                            'id'      => 'wwp_heading_upgrade2',
                            'classes' => 'wwp-heading-upgrade',
                            'tag'     => 'h2',
                            'content' => __( 'Wholesale Suite Bundle', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        array(
                            'type'    => 'paragraph',
                            'id'      => 'wwp_paragraph_upgrade2',
                            'classes' => 'wwp-paragraph-upgrade',
                            'content' => __( 'Everything you need to sell to wholesale customers in WooCommerce. The most complete wholesale solution for building wholesale sales into your existing WooCommerce driven store.', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        array(
                            'type'    => 'heading',
                            'id'      => 'wwp_heading_package1',
                            'classes' => 'wwp-heading-package1 wwp-heading-package-bundle ' . ( $wwp_wwpp_is_active ? 'wwp-package-active' : '' ),
                            'tag'     => 'h3',
                            'content' => __( 'WooCommerce Wholesale Prices Premium', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        array(
                            'type'    => 'paragraph',
                            'id'      => 'wwp_paragraph_package1',
                            'classes' => 'wwp-paragraph-upgrade wwp-paragraph-package-bundle ' . ( $wwp_wwpp_is_active ? 'wwp-package-active' : '' ),
                            'content' => __( 'Easily add wholesale pricing to your products. Control product visibility. Satisfy your country\'s strictest tax requirements & control pricing display. Force wholesalers to use certain shipping & payment gateways. Enforce order minimums and individual product minimums. and 100\'s of other product and pricing related wholesale features.', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        array(
                            'type'    => 'image',
                            'id'      => 'wwp_bundle_image1',
                            'classes' => 'wwp-bundle-img',
                            'url'     => esc_url( WWP_IMAGES_URL ) . 'upgrade-page-wwpp-box.png',
                        ),
                        $wwp_wwpp_installed_label,
                        array(
                            'type'    => 'heading',
                            'id'      => 'wwp_heading_package2',
                            'classes' => 'wwp-heading-package2 wwp-heading-package-bundle ' . ( $wwp_wwof_is_active ? 'wwp-package-active' : '' ),
                            'tag'     => 'h3',
                            'content' => __( 'WoWooCommerce Wholesale Order Form', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        array(
                            'type'    => 'paragraph',
                            'id'      => 'wwp_paragraph_package2',
                            'classes' => 'wwp-paragraph-upgrade wwp-paragraph-package-bundle ' . ( $wwp_wwof_is_active ? 'wwp-package-active' : '' ),
                            'content' => __( 'Decrease frustration and increase order size with the most efficient one-page WooCommerce order form. Your wholesale customers will love it. No page loading means less back & forth, full ajax enabled add to cart buttons, responsive layout for on-the-go ordering and your whole product catalog available at your customer\'s fingertips.', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        array(
                            'type'    => 'image',
                            'id'      => 'wwp_bundle_image2',
                            'classes' => 'wwp-bundle-img',
                            'url'     => esc_url( WWP_IMAGES_URL ) . 'upgrade-page-wwof-box.png',
                        ),
                        $wwp_wwof_installed_label,
                        array(
                            'type'    => 'heading',
                            'id'      => 'wwp_heading_package3',
                            'classes' => 'wwp-heading-package3 wwp-heading-package-bundle ' . ( $wwp_wwlc_is_active ? 'wwp-package-active' : '' ),
                            'tag'     => 'h3',
                            'content' => __( 'WooCommerce Wholesale Lead Capture', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        array(
                            'type'    => 'paragraph',
                            'id'      => 'wwp_paragraph_package3',
                            'classes' => 'wwp-paragraph-upgrade wwp-paragraph-package-bundle ' . ( $wwp_wwlc_is_active ? 'wwp-package-active' : '' ),
                            'content' => __( 'Take the pain out of manually recruiting & registering wholesale customers. Lead Capture will save you admin time and recruit wholesale customers for your WooCommerce store on autopilot. Full registration form builder, automated email onboarding email sequence, full automated or manual approvals system and much more.', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        array(
                            'type'    => 'image',
                            'id'      => 'wwp_bundle_image3',
                            'classes' => 'wwp-bundle-img',
                            'url'     => esc_url( WWP_IMAGES_URL ) . 'upgrade-page-wwlc-box.png',
                        ),
                        $wwp_wwlc_installed_label,
                    ),
                ),
            );

            return $controls;
        }

        /**
         * All user role options.
         *
         * @since  2.0
         * @access public
         *
         * @return array
         */
        public function all_roles() {

            $roles     = array();
            $roles[''] = __( 'Choose Wholesale Role', 'woocommerce-wholesale-prices-premium' );
            if ( ! empty( $this->_all_wholesale_roles ) ) {
                foreach ( $this->_all_wholesale_roles as $role_key => $role ) {
                    $roles[ $role_key ] = $role['roleName'];
                }
            }

            return $roles;
        }

        /**
         * Admin old settings page redirection.
         *
         * @since  2.0
         * @access public
         */
        public function admin_old_wholesale_settings_redirect() {
            // For wholesale prices tabs.
            if ( strpos( $_SERVER['REQUEST_URI'], 'page=wc-settings&tab=wwp_settings' ) !== false ) {
                wp_safe_redirect( admin_url( 'admin.php?page=wholesale-settings&tab=wholesale_prices' ) );
                exit;
            }
        }

        /**
         * Execute model.
         *
         * @since  2.0
         * @access public
         */
        public function run() {

            // Admin init.
            add_action( 'admin_init', array( $this, 'admin_old_wholesale_settings_redirect' ) );

            // Filter settings tabs.
            add_filter( 'wwp_admin_setting_default_tabs', array( $this, 'admin_settings_tabs' ), 10, 1 );

            // Filter General tab controls.
            add_filter(
                'wwp_admin_setting_default_general_controls',
                array(
                    $this,
                    'admin_settings_general_controls',
                ),
                10,
                1
            );
            // Filter Prices tab controls.
            add_filter(
                'wwp_admin_setting_default_prices_controls',
                array(
                    $this,
                    'admin_settings_prices_controls',
                ),
                10,
                1
            );
            // Filter Tax tab controls.
            add_filter(
                'wwp_admin_setting_default_tax_controls',
                array(
                    $this,
                    'admin_settings_tax_controls',
                ),
                10,
                1
            );
            // Filter Tax tab controls.
            add_filter(
                'wwp_admin_setting_default_help_controls',
                array(
                    $this,
                    'admin_settings_help_controls',
                ),
                10,
                1
            );
            // Filter Tax tab controls.
            add_filter(
                'wwp_admin_setting_default_upgrade_controls',
                array(
                    $this,
                    'admin_settings_upgrade_controls',
                ),
                10,
                1
            );
            // Filter additional controls.
            add_filter( 'wwp_admin_setting_default_controls', array( $this, 'admin_setting_default_controls' ), 10, 1 );
        }
    }
}
