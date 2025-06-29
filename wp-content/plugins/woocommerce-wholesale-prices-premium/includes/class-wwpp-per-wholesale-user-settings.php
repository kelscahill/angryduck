<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Per_Wholesale_User_Settings' ) ) {

    /**
     * Model that houses the logic of per wholesale user settings.
     *
     * @since 1.16.0
     */
    class WWPP_Per_Wholesale_User_Settings {

        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWPP_Per_Wholesale_User_Settings.
         *
         * @since 1.16.0
         * @access private
         * @var WWPP_Per_Wholesale_User_Settings
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since 1.16.0
         * @access private
         * @var WWPP_Wholesale_Roles
         */
        private $_wwpp_wholesale_roles;

        /**
         * Class Methods
         */

        /**
         * WWPP_Per_Wholesale_User_Settings constructor.
         *
         * @since 1.16.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Per_Wholesale_User_Settings model.
         */
        public function __construct( $dependencies ) {
            $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
        }

        /**
         * Ensure that only one instance of WWPP_Per_Wholesale_User_Settings is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.16.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Per_Wholesale_User_Settings model.
         * @return WWPP_Per_Wholesale_User_Settings
         */
        public static function instance( $dependencies ) {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Get per wholesale user settings fields.
         *
         * @since 1.16.0
         * @access private
         *
         * @return array Array of per wholesale user settings fields.
         */
        private function _get_per_wholesale_user_settings() {
            // Shipping Options.
            $wc_shipping_zones = WC_Shipping_Zones::get_zones();
            $wc_default_zone   = WC_Shipping_Zones::get_zone( 0 );
            $zone_options      = array( $wc_default_zone->get_id() => $wc_default_zone->get_zone_name() );

            foreach ( $wc_shipping_zones as $zone ) {
                $zone_options[ $zone['zone_id'] ] = $zone['zone_name'];
            }

            $wc_shipping_methods        = WC()->shipping->load_shipping_methods();
            $non_zoned_shipping_methods = array();

            foreach ( $wc_shipping_methods as $shipping_method ) {
                if ( ! $shipping_method->supports( 'shipping-zones' ) && 'yes' === $shipping_method->enabled ) {
                    $non_zoned_shipping_methods[ $shipping_method->id ] = $shipping_method->method_title;
                }
            }

            // Gateway Options.
            $available_gateways = WC()->payment_gateways->payment_gateways();
            if ( ! is_array( $available_gateways ) ) {
                $available_gateways = array();
            }

            $gateway_options = array();
            foreach ( $available_gateways as $gateway_key => $gateway ) {
                $gateway_options[ $gateway_key ] = $gateway->title;
            }

            return apply_filters(
                'wwpp_per_wholesale_user_settings_fields',
                array(
                    'wwpp_min_order_req_subheading'      => array(
                        'id'    => 'wwpp_min_order_req_subheading',
                        'label' => __( 'Override Minimum Order Requirements', 'woocommerce-wholesale-prices-premium' ),
                        'type'  => 'subheading',
                    ),
                    'wwpp_override_min_order_qty'        => array(
                        'id'      => 'wwpp_override_min_order_qty',
                        'label'   => __( 'Override Minimum Order Quantity', 'woocommerce-wholesale-prices-premium' ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'select',
                        'options' => array(
                            'no'  => __( 'No', 'woocommerce-wholesale-prices-premium' ),
                            'yes' => __( 'Yes', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        'default' => 'no',
                    ),
                    'wwpp_min_order_qty'                 => array(
                        'id'      => 'wwpp_min_order_qty',
                        'label'   => __( 'Minimum Order Quantity', 'woocommerce-wholesale-prices-premium' ),
                        'desc'    => __( 'Set as zero or leave blank to have no minimum quantity required.', 'woocommerce-wholesale-prices-premium' ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'text',
                        'default' => '',
                    ),
                    'wwpp_override_min_order_price'      => array(
                        'id'      => 'wwpp_override_min_order_price',
                        /* Translators: $1 currency symbol */
                        'label'   => sprintf( __( 'Override Minimum Order Subtotal (%1$s)', 'woocommerce-wholesale-prices-premium' ), get_woocommerce_currency_symbol() ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'select',
                        'options' => array(
                            'no'  => __( 'No', 'woocommerce-wholesale-prices-premium' ),
                            'yes' => __( 'Yes', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        'default' => 'no',
                    ),
                    'wwpp_min_order_price'               => array(
                        'id'      => 'wwpp_min_order_price',
                        'label'   => __( 'Minimum Order Subtotal', 'woocommerce-wholesale-prices-premium' ),
                        'desc'    => __( "Calculated using the product's defined wholesale price (before tax and shipping). Set to zero or leave blank to disable. Please enter price using dot (.) as decimal separator and without comma separator.", 'woocommerce-wholesale-prices-premium' ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'text',
                        'default' => '',
                    ),
                    'wwpp_min_order_logic'               => array(
                        'id'      => 'wwpp_min_order_logic',
                        'label'   => __( 'Minimum Order Logic', 'woocommerce-wholesale-prices-premium' ),
                        'desc'    => __( 'Either (minimum order quantity "AND" minimum order sub-total) or (minimum order quantity "OR" minimum order sub-total). Only applied if both minimum items and price is set', 'woocommerce-wholesale-prices-premium' ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'select',
                        'options' => array(
                            'and' => __( 'AND', 'woocommerce-wholesale-prices-premium' ),
                            'or'  => __( 'OR', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        'default' => 'AND',
                    ),
                    'wwpp_tax_exemption_subheading'      => array(
                        'id'    => 'wwpp_tax_exemption_subheading',
                        'label' => __( 'Override Tax Exemption', 'woocommerce-wholesale-prices-premium' ),
                        'type'  => 'subheading',
                    ),
                    'wwpp_tax_exemption'                 => array(
                        'id'      => 'wwpp_tax_exemption',
                        'label'   => __( 'Tax Exemption', 'woocommerce-wholesale-prices-premium' ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'select',
                        'options' => array(
                            'global' => __( 'Use Global Option', 'woocommerce-wholesale-prices-premium' ),
                            'no'     => __( 'No', 'woocommerce-wholesale-prices-premium' ),
                            'yes'    => __( 'Yes', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        'default' => 'global',
                    ),
                    'wwpp_wholesale_discount_subheading' => array(
                        'id'    => 'wwpp_wholesale_discount_subheading',
                        'label' => __( 'Override Wholesale General Discount', 'woocommerce-wholesale-prices-premium' ),
                        'type'  => 'subheading',
                    ),
                    'wwpp_override_wholesale_discount'   => array(
                        'id'      => 'wwpp_override_wholesale_discount',
                        'label'   => __( 'Override Wholesale General Discount', 'woocommerce-wholesale-prices-premium' ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'select',
                        'options' => array(
                            'no'  => __( 'No', 'woocommerce-wholesale-prices-premium' ),
                            'yes' => __( 'Yes', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        'default' => 'no',
                    ),
                    'wwpp_wholesale_discount'            => array(
                        'id'      => 'wwpp_wholesale_discount',
                        'label'   => __( 'Percent Discount (%)', 'woocommerce-wholesale-prices-premium' ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'text',
                        'default' => '',
                    ),
                    'wwpp_override_wholesale_discount_qty_discount_mapping' => array(
                        'id'      => 'wwpp_override_wholesale_discount_qty_discount_mapping',
                        'label'   => __( 'Override General Quantity Based Discounts?', 'woocommerce-wholesale-prices-premium' ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'select',
                        'options' => array(
                            'dont_use_general_per_wholesale_role_qty_mapping' => __( 'Disabled', 'woocommerce-wholesale-prices-premium' ),
                            'use_general_per_wholesale_role_qty_mapping'      => __( 'Use Globally Defined General Quantity Based Discounts', 'woocommerce-wholesale-prices-premium' ),
                            'specify_general_per_wholesale_role_qty_mapping'  => __( 'Define Alternate Quantity Based Discounts For This User', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        'default' => 'use_general_per_wholesale_role_qty_mapping',
                    ),
                    'wwpp_wholesale_discount_qty_discount_mapping_mode_2' => array(
                        'id'      => 'wwpp_wholesale_discount_qty_discount_mapping_mode_2',
                        'label'   => __( 'Apply Discounts Based On Individual Product Quantities?', 'woocommerce-wholesale-prices-premium' ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'select',
                        'options' => array(
                            'yes' => __( 'Yes', 'woocommerce-wholesale-prices-premium' ),
                            'no'  => __( 'No', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        'default' => 'no',
                    ),
                    'wwpp_wholesale_discount_qty_discount_mapping' => array(
                        'id'      => 'wwpp_wholesale_discount_qty_discount_mapping',
                        'label'   => '',
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'cart_qty_based_wholesale_discount_mapping_table',
                        'default' => array(),
                    ),
                    'wwpp_shipping_options_subheading'   => array(
                        'id'    => 'wwpp_shipping_options_subheading',
                        'label' => __( 'Override Shipping Options', 'woocommerce-wholesale-prices-premium' ),
                        'type'  => 'subheading',
                    ),
                    'wwpp_override_shipping_options'     => array(
                        'id'      => 'wwpp_override_shipping_options',
                        'label'   => __( 'Override Shipping Options', 'woocommerce-wholesale-prices-premium' ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'select',
                        'options' => array(
                            'no'  => __( 'No', 'woocommerce-wholesale-prices-premium' ),
                            'yes' => __( 'Yes', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        'default' => 'no',
                    ),
                    'wwpp_shipping_methods_type'         => array(
                        'id'      => 'wwpp_shipping_methods_type',
                        'label'   => __( 'Shipping Method Type', 'woocommerce-wholesale-prices-premium' ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'select',
                        'options' => array(
                            'force_free_shipping'      => __( 'Force Free Shipping', 'woocommerce-wholesale-prices-premium' ),
                            'specify_shipping_methods' => __( 'Specify Shipping Methods', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        'default' => 'force_free_shipping',
                    ),
                    'wwpp_hide_selected_methods_from_others' => array(
                        'id'      => 'wwpp_hide_selected_methods_from_others',
                        'label'   => __( 'Hide Selected Shipping Methods (Zoned and Non Zoned) From Others', 'woocommerce-wholesale-prices-premium' ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'select',
                        'options' => array(
                            'no'                        => __( 'No', 'woocommerce-wholesale-prices-premium' ),
                            'hide_from_non_wholesale_users' => __( 'Hide from non wholesale users', 'woocommerce-wholesale-prices-premium' ),
                            'hide_from_all_other_users' => __( 'Hide from all other users (wholesale and non wholesale)', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        'default' => 'no',
                    ),
                    'wwpp_shipping_zone'                 => array(
                        'id'      => 'wwpp_shipping_zone',
                        'label'   => __( 'Shipping Zone', 'woocommerce-wholesale-prices-premium' ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'select',
                        'options' => $zone_options,
                        'default' => $wc_default_zone->get_id(),
                    ),
                    'wwpp_shipping_methods'              => array(
                        'id'          => 'wwpp_shipping_methods',
                        'label'       => __( 'Shipping Method', 'woocommerce-wholesale-prices-premium' ),
                        'placeholder' => __( 'Please Select Shipping Method...', 'woocommerce-wholesale-prices-premium' ),
                        'class'       => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'        => 'multiselect',
                        'options'     => array(),
                        'default'     => array(),
                    ),
                    'wwpp_specify_non_zoned_shipping_methods' => array(
                        'id'      => 'wwpp_specify_non_zoned_shipping_methods',
                        'label'   => __( 'Specify Non Zoned Shipping Methods', 'woocommerce-wholesale-prices-premium' ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'select',
                        'options' => array(
                            'no'  => __( 'No', 'woocommerce-wholesale-prices-premium' ),
                            'yes' => __( 'Yes', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        'default' => 'no',
                    ),
                    'wwpp_non_zoned_shipping_methods'    => array(
                        'id'          => 'wwpp_non_zoned_shipping_methods',
                        'label'       => __( 'Non Zoned Shipping Methods', 'woocommerce-wholesale-prices-premium' ),
                        'placeholder' => __( 'Please Select Unzone Shipping Method', 'woocommerce-wholesale-prices-premium' ),
                        'class'       => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'        => 'multiselect',
                        'options'     => $non_zoned_shipping_methods,
                        'default'     => array(),
                    ),
                    'wwpp_payment_gateway_options_subheading' => array(
                        'id'    => 'wwpp_payment_gateway_options_subheading',
                        'label' => __( 'Override Payment Gateway Options', 'woocommerce-wholesale-prices-premium' ),
                        'type'  => 'subheading',
                    ),
                    'wwpp_override_payment_gateway_options' => array(
                        'id'      => 'wwpp_override_payment_gateway_options',
                        'label'   => __( 'Override Payment Gateway Options', 'woocommerce-wholesale-prices-premium' ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'select',
                        'options' => array(
                            'no'  => __( 'No', 'woocommerce-wholesale-prices-premium' ),
                            'yes' => __( 'Yes', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        'default' => 'no',
                    ),
                    'wwpp_payment_gateway_options'       => array(
                        'id'          => 'wwpp_payment_gateway_options',
                        'label'       => __( 'Payment Gateway Options', 'woocommerce-wholesale-prices-premium' ),
                        'placeholder' => __( 'Please Select Payment Gateways...', 'woocommerce-wholesale-prices-premium' ),
                        'class'       => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'        => 'multiselect',
                        'options'     => $gateway_options,
                        'default'     => array(),
                    ),
                    'wwpp_override_payment_gateway_surcharge' => array(
                        'id'      => 'wwpp_override_payment_gateway_surcharge',
                        'label'   => __( 'Override Payment Gateway Surcharge', 'woocommerce-wholesale-prices-premium' ),
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'select',
                        'options' => array(
                            'use_general_surcharge_mapping' => __( 'Use general wholesale role payment gateway surcharge', 'woocommerce-wholesale-prices-premium' ),
                            'do_not_use_general_surcharge_mapping' => __( 'Do not use general wholesale role payment gateway surcharge', 'woocommerce-wholesale-prices-premium' ),
                            'specify_surcharge_mapping' => __( 'Specify payment gateway surcharge', 'woocommerce-wholesale-prices-premium' ),
                        ),
                        'default' => 'use_general_surcharge_mapping',
                    ),
                    'wwpp_payment_gateway_surcharge_mapping' => array(
                        'id'      => 'wwpp_payment_gateway_surcharge_mapping',
                        'label'   => '',
                        'class'   => 'wwpp_per_wholesale_user_settings_field form_field',
                        'type'    => 'payment_gateway_surcharge_mapping_table',
                        'default' => array(),
                    ),
                )
            );
        }

        /**
         * Setup default values for per wholesale user settings fields.
         *
         * @since 1.16.0
         * @access private
         *
         * @param array   $settings_arr Array of settings fields.
         * @param WP_User $user         WP_User object.
         * @return array Filtered array of settings fields.
         */
        private function _setup_default_value_for_per_wholesale_user_settings_fields( $settings_arr, $user ) {
            return array_map(
                function ( $arr ) use ( $user ) {

                    switch ( $arr['id'] ) {
                        case 'wwpp_override_min_order_qty':
                        case 'wwpp_override_min_order_price':
                        case 'wwpp_override_wholesale_discount':
                        case 'wwpp_override_shipping_options':
                        case 'wwpp_specify_non_zoned_shipping_methods':
                        case 'wwpp_override_payment_gateway_options':
                        case 'wwpp_wholesale_discount_qty_discount_mapping_mode_2':
                            $default_value = get_user_meta( $user->ID, $arr['id'], true );
                            if ( in_array( $default_value, array( 'yes', 'no' ), true ) ) {
                                $arr['default'] = $default_value;
                            }

                            break;
                        case 'wwpp_hide_selected_methods_from_others':
                            $default_value = get_user_meta( $user->ID, $arr['id'], true );
                            if ( in_array( $default_value, array( 'no', 'hide_from_non_wholesale_users', 'hide_from_all_other_users' ), true ) ) {
                                $arr['default'] = $default_value;
                            }

                            break;
                        case 'wwpp_override_wholesale_discount_qty_discount_mapping':
                            $default_value = get_user_meta( $user->ID, $arr['id'], true );
                            if ( in_array( $default_value, array( 'dont_use_general_per_wholesale_role_qty_mapping', 'use_general_per_wholesale_role_qty_mapping', 'specify_general_per_wholesale_role_qty_mapping' ), true ) ) {
                                $arr['default'] = $default_value;
                            }

                            break;
                        case 'wwpp_override_payment_gateway_surcharge':
                            $default_value = get_user_meta( $user->ID, $arr['id'], true );
                            if ( in_array( $default_value, array( 'do_not_use_general_surcharge_mapping', 'use_general_surcharge_mapping', 'specify_surcharge_mapping' ), true ) ) {
                                $arr['default'] = $default_value;
                            }

                            break;
                        case 'wwpp_min_order_qty':
                        case 'wwpp_min_order_price':
                        case 'wwpp_wholesale_discount':
                            $default_value = get_user_meta( $user->ID, $arr['id'], true );
                            if ( is_numeric( $default_value ) || empty( $default_value ) ) {
                                $arr['default'] = $default_value;
                            }

                            break;
                        case 'wwpp_min_order_logic':
                            $default_value = get_user_meta( $user->ID, $arr['id'], true );
                            if ( in_array( $default_value, array( 'and', 'or' ), true ) ) {
                                $arr['default'] = $default_value;
                            }

                            break;
                        case 'wwpp_tax_exemption':
                            $default_value = get_user_meta( $user->ID, $arr['id'], true );
                            if ( in_array( $default_value, array( 'global', 'yes', 'no' ), true ) ) {
                                $arr['default'] = $default_value;
                            }

                            break;
                        case 'wwpp_shipping_methods_type':
                            $default_value = get_user_meta( $user->ID, $arr['id'], true );
                            if ( in_array( $default_value, array( 'force_free_shipping', 'specify_shipping_methods' ), true ) ) {
                                $arr['default'] = $default_value;
                            }

                            break;
                        case 'wwpp_shipping_methods':
                        case 'wwpp_non_zoned_shipping_methods':
                            $default_value = get_user_meta( $user->ID, $arr['id'], true );
                            if ( ! is_array( $default_value ) ) {
                                $default_value = array();
                            }

                            $arr['default'] = $default_value;

                            break;
                        case 'wwpp_shipping_zone':
                            $default_value = get_user_meta( $user->ID, $arr['id'], true );

                            $arr['default'] = $default_value;

                            break;
                        case 'wwpp_payment_gateway_options':
                            $default_value = get_user_meta( $user->ID, $arr['id'], true );
                            if ( ! is_array( $default_value ) ) {
                                $default_value = array();
                            }

                            $arr['default'] = $default_value;

                            break;
                    }

                    return $arr;
                },
                $settings_arr
            );
        }

        /**
         * Display per wholesale user settings fields.$_COOKIE
         *
         * @since 1.16.0
         * @access public
         *
         * @param WP_User $user WP_User object.
         */
        public function display_per_wholesale_user_settings_fields( $user ) {
            $wwpp_shipping_zone    = get_user_meta( $user->ID, 'wwpp_shipping_zone', true );
            $wwpp_shipping_methods = get_user_meta( $user->ID, 'wwpp_shipping_methods', true );

            if ( ! is_array( $wwpp_shipping_methods ) ) {
                $wwpp_shipping_methods = array();
            }

            wp_localize_script(
                'wwpp_user_profile_js',
                'wwpp_user_profile_args',
                array(
                    'i18n_shipping_method_placeholder'     => __( 'Please Select Shipping Method...', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_failed_to_get_zoned_methods'     => __( 'Failed to get shipping methods for the selected shipping zone', 'woocommerce-wholesale-prices-premium' ),
                    'wwpp_shipping_zone'                   => $wwpp_shipping_zone,
                    'wwpp_zone_shipping_methods'           => $wwpp_shipping_methods,
                    'wwpp_get_zone_shipping_methods_nonce' => wp_create_nonce( 'wwpp_get_zone_shipping_methods' ),
                )
            );

            wp_localize_script(
                'wwpp_wholesale_role_cart_qty_based_wholesale_discount_js',
                'wwpp_wrcqbwd_params',
                array(
                    'user_id'                            => $user->ID,
                    'i18n_please_specify_wholesale_role' => __( 'Please specify wholesale role', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_invalid_start_qty'             => __( 'Invalid start quantity', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_invalid_end_qty'               => __( 'Invalid end quantity', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_invalid_percent_discount'      => __( 'Invalid percent discount', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_form_error'                    => __( 'Form Error', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_no_mappings_found'             => __( 'No Mappings Found', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_add_mapping_error'             => __( 'Add Mapping Error', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_failed_to_record_new_mapping_entry' => __( 'Failed to record new mapping entry', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_confirm_remove_mapping'        => __( 'Clicking OK will remove the current wholesale role/discount mapping', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_delete_mapping_error'          => __( 'Delete Mapping Error', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_failed_to_deleted_mapping'     => __( 'Failed to delete specified mapping entry', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_edit_mapping_error'            => __( 'Edit Mapping Error', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_failed_edit_mapping'           => __( 'Failed to edit mapping entry', 'woocommerce-wholesale-prices-premium' ),
                    'add_wholesale_role_qty_based_discount_mapping_nonce' => wp_create_nonce( 'wwpp_add_wholesale_role_qty_based_discount_mapping' ),
                    'edit_wholesale_role_qty_based_discount_mapping_nonce' => wp_create_nonce( 'wwpp_edit_wholesale_role_qty_based_discount_mapping' ),
                    'delete_wholesale_role_qty_based_discount_mapping_nonce' => wp_create_nonce( 'wwpp_delete_wholesale_role_qty_based_discount_mapping' ),
                )
            );

            wp_localize_script(
                'wwpp_payment_gateway_controls_custom_field_js',
                'wwpp_payment_gateway_controls_custom_field_params',
                array(
                    'user_id'                              => $user->ID,
                    'i18n_specify_field_values'            => __( 'Please specify values for the following field/s', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_payment_gateway_added'           => __( 'Successfully Added Payment Gateway Surcharge Mapping', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_failed_add_payment_gateway'      => __( 'Failed To Add New Payment Gateway Surcharge Mapping', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_specify_field_values_with_colon' => __( 'Please specify values for the following field/s:', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_form_error'                      => __( 'Form Error', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_payment_gateway_updated'         => __( 'Successfully Updated Payment Gateway Surcharge Mapping', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_failed_update_payment_gateway'   => __( 'Failed To Update Payment Gateway Surcharge Mapping', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_click_ok_remove_payment_gateway' => __( 'Clicking OK will remove the current payment gateway surcharge mapping', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_no_mapping_found'                => __( 'No Mappings Found', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_payment_gateway_deleted'         => __( 'Successfully Deleted Payment Gateway Surcharge Mapping', 'woocommerce-wholesale-prices-premium' ),
                    'i18n_failed_delete_payment_gateway'   => __( 'Failed To Delete Payment Gateway Surcharge Mapping', 'woocommerce-wholesale-prices-premium' ),
                    'add_wholesale_role_payment_gateway_mapping_nonce' => wp_create_nonce( 'wwppAddWholesaleRolePaymentGatewayMapping' ),
                    'update_wholesale_role_payment_gateway_mapping_nonce' => wp_create_nonce( 'wwppUpdateWholesaleRolePaymentGatewayMapping' ),
                    'delete_wholesale_role_payment_gateway_mapping_nonce' => wp_create_nonce( 'wwppDeleteWholesaleRolePaymentGatewayMapping' ),
                    'add_payment_gateway_surcharge_nonce'  => wp_create_nonce( 'wwppAddPaymentGatewaySurcharge' ),
                    'update_payment_gateway_surcharge_nonce' => wp_create_nonce( 'wwppUpdatePaymentGatewaySurcharge' ),
                    'delete_payment_gateway_surcharge_nonce' => wp_create_nonce( 'wwppDeletePaymentGatewaySurcharge' ),
                )
            );

            $user_wholesale_roles = $this->_wwpp_wholesale_roles->getUserWholesaleRole( $user );
            $all_wholesale_roles  = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

            if ( ! empty( $user_wholesale_roles ) ) {
                $all_wholesale_roles = array( $user_wholesale_roles[0] => $all_wholesale_roles[ $user_wholesale_roles[0] ] ); // Only make available the current wholesale role of the current user.
            }

            $cart_qty_discount_mapping = get_user_meta( $user->ID, 'wwpp_wholesale_discount_qty_discount_mapping', true );
            if ( ! is_array( $cart_qty_discount_mapping ) ) {
                $cart_qty_discount_mapping = array();
            }

            $payment_gateway_surcharge = get_user_meta( $user->ID, 'wwpp_payment_gateway_surcharge_mapping', true );
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

            if ( ! empty( $user_wholesale_roles ) ) {
                $per_wholesale_user_settings = $this->_setup_default_value_for_per_wholesale_user_settings_fields( $this->_get_per_wholesale_user_settings(), $user );
                require_once WWPP_VIEWS_PATH . '/wholesale-user-settings/wwpp-view-per-wholesale-user-settings.php';
            }
        }

        /**
         * Save per wholesale user settings fields.
         *
         * @since 1.16.0
         * @access public
         *
         * @param int $user_id User id.
         */
        public function save_per_wholesale_user_settings_fields( $user_id ) {

            if ( isset( $_POST['_wpnonce'] ) && ! wp_verify_nonce( $_POST['_wpnonce'], 'update-user_' . $user_id ) ) {
                return;
            }

            if ( ! current_user_can( 'edit_user', $user_id ) ) {
                wp_die( esc_html__( 'Sorry, you are not allowed to edit this user.', 'woocommerce-wholesale-prices-premium' ) );
            }

            foreach ( $_POST as $key => $data ) {
                switch ( $key ) {
                    case 'wwpp_override_min_order_qty':
                    case 'wwpp_override_min_order_price':
                    case 'wwpp_override_wholesale_discount':
                    case 'wwpp_override_shipping_options':
                    case 'wwpp_specify_non_zoned_shipping_methods':
                    case 'wwpp_override_payment_gateway_options':
                    case 'wwpp_wholesale_discount_qty_discount_mapping_mode_2':
                        $val = trim( sanitize_text_field( $data ) );

                        if ( in_array( $val, array( 'yes', 'no' ), true ) ) {
                            update_user_meta( $user_id, $key, $val );
                        }

                        break;
                    case 'wwpp_hide_selected_methods_from_others':
                        $val = trim( sanitize_text_field( $data ) );

                        if ( in_array( $val, array( 'no', 'hide_from_non_wholesale_users', 'hide_from_all_other_users' ), true ) ) {
                            update_user_meta( $user_id, $key, $val );
                        }

                        break;
                    case 'wwpp_override_wholesale_discount_qty_discount_mapping':
                        $val = trim( sanitize_text_field( $data ) );

                        if ( in_array( $val, array( 'dont_use_general_per_wholesale_role_qty_mapping', 'use_general_per_wholesale_role_qty_mapping', 'specify_general_per_wholesale_role_qty_mapping' ), true ) ) {
                            update_user_meta( $user_id, $key, $val );
                        }

                        break;
                    case 'wwpp_override_payment_gateway_surcharge':
                        $val = trim( sanitize_text_field( $data ) );

                        if ( in_array( $val, array( 'do_not_use_general_surcharge_mapping', 'use_general_surcharge_mapping', 'specify_surcharge_mapping' ), true ) ) {
                            update_user_meta( $user_id, $key, $val );
                        }

                        break;
                    case 'wwpp_min_order_qty':
                        $val = trim( sanitize_text_field( $data ) );

                        if ( is_numeric( $val ) || empty( $val ) ) {
                            update_user_meta( $user_id, $key, $val );
                        }

                        break;
                    case 'wwpp_min_order_price':
                    case 'wwpp_wholesale_discount':
                        $val = trim( sanitize_text_field( $data ) );

                        if ( empty( $val ) || ( is_numeric( $val ) && filter_var( $val, FILTER_VALIDATE_FLOAT ) !== false ) ) {
                            update_user_meta( $user_id, $key, $val );
                        }

                        break;
                    case 'wwpp_min_order_logic':
                        $val = trim( sanitize_text_field( $data ) );

                        if ( in_array( $val, array( 'and', 'or' ), true ) ) {
                            update_user_meta( $user_id, $key, $val );
                        }

                        break;
                    case 'wwpp_tax_exemption':
                        $val = trim( sanitize_text_field( $data ) );

                        if ( in_array( $val, array( 'global', 'yes', 'no' ), true ) ) {
                            update_user_meta( $user_id, $key, $val );
                        }

                        break;
                    case 'wwpp_shipping_methods_type':
                        $val = trim( sanitize_text_field( $data ) );

                        if ( in_array( $val, array( 'force_free_shipping', 'specify_shipping_methods' ), true ) ) {
                            update_user_meta( $user_id, $key, $val );
                        }

                        break;
                    case 'wwpp_shipping_methods':
                        if ( 'yes' !== $_POST['wwpp_override_shipping_options'] ||
                            'specify_shipping_methods' !== $_POST['wwpp_shipping_methods_type'] ) {
                            $val = array();
                        } else {
                            $val = ! is_array( $data ) ? array() : WWPP_Helper_Functions::sanitize_array( $data );
                            $val = array_map(
                                function ( $item ) {
                                    return (int) $item;
                                },
                                $val
                            );
                        }

                        update_user_meta( $user_id, $key, $val );

                        break;
                    case 'wwpp_non_zoned_shipping_methods':
                        if ( 'yes' !== $_POST['wwpp_override_shipping_options'] ||
                            'specify_shipping_methods' !== $_POST['wwpp_shipping_methods_type'] ||
                            'yes' !== $_POST['wwpp_specify_non_zoned_shipping_methods'] ) {
                            $val = array();
                        } else {
                            $val = ! is_array( $data ) ? array() : WWPP_Helper_Functions::sanitize_array( $data );
                            $val = array_map(
                                function ( $item ) {
                                return trim( $item );
                                },
                                $val
                            );
                        }

                        update_user_meta( $user_id, $key, $val );

                        break;
                    case 'wwpp_shipping_zone':
                        $val = (int) trim( sanitize_text_field( $data ) );
                        update_user_meta( $user_id, $key, $val );

                        break;
                    case 'wwpp_payment_gateway_options':
                        if ( 'yes' !== $_POST['wwpp_override_payment_gateway_options'] ) {
                            $val = array();
                        } else {
                            $val = ! is_array( $data ) ? array() : WWPP_Helper_Functions::sanitize_array( $data );
                            $val = array_map(
                                function ( $item ) {
                                    return trim( $item );
                                },
                                $val
                            );
                        }

                        update_user_meta( $user_id, $key, $val );

                        break;
                }
            }
        }

        /**
         * Execute model.
         *
         * @since 1.16.0
         * @access public
         */
        public function run() {
            // Add Custom Fields To Admin User Edit Page.
            add_action( 'show_user_profile', array( $this, 'display_per_wholesale_user_settings_fields' ), 10, 1 );
            add_action( 'edit_user_profile', array( $this, 'display_per_wholesale_user_settings_fields' ), 10, 1 );

            // Save Custom Fields On Admin User Edit Page.
            add_action( 'personal_options_update', array( $this, 'save_per_wholesale_user_settings_fields' ), 10, 1 );
            add_action( 'edit_user_profile_update', array( $this, 'save_per_wholesale_user_settings_fields' ), 10, 1 );
        }
    }
}
