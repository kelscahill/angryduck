<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that houses the logic of payment gateways.
 *
 * @since 1.3.0
 */
class WWPP_Wholesale_Role_Payment_Gateway {

    /**
     * Class Properties
     */

    /**
     * Property that holds the single main instance of WWPP_Wholesale_Role_Payment_Gateway.
     *
     * @since  1.3.0
     * @access private
     * @var WWPP_Wholesale_Role_Payment_Gateway
     */
    private static $_instance;

    /**
     * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
     *
     * @since  1.3.0
     * @access private
     * @var WWPP_Wholesale_Roles
     */
    private $_wwpp_wholesale_roles;

    /**
     * Model that houses the logic of retrieving information relating to tax.
     *
     * @since  1.27.4
     * @access private
     * @var WWPP_Tax
     */
    private $_wwpp_tax;

    /**
     * Class Methods
     */

    /**
     * WWPP_Wholesale_Role_Payment_Gateway constructor.
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Role_Payment_Gateway
     *                            model.
     *
     * @since  1.3.0
     * @access public
     */
    public function __construct( $dependencies ) {

        $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
        $this->_wwpp_tax             = $dependencies['WWPP_Tax'];
    }

    /**
     * Ensure that only one instance of WWPP_Wholesale_Role_Payment_Gateway is loaded or can be loaded (Singleton
     * Pattern).
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Role_Payment_Gateway
     *                            model.
     *
     * @since  1.13.0
     * @access public
     *
     * @return WWPP_Wholesale_Role_Payment_Gateway
     */
    public static function instance( $dependencies ) {

        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( $dependencies );
        }

        return self::$_instance;
    }

    /**
     * Get payment gateway surcharge settings.
     *
     * @param int|null $user_id User id to get gateway surcharge settings for.
     *
     * @since 2.0.0
     * @return array|false|mixed|null
     */
    private function get_payment_gateway_surcharge( $user_id = null ) {

        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        switch ( get_user_meta( $user_id, 'wwpp_override_payment_gateway_surcharge', true ) ) {
            case 'specify_surcharge_mapping':
                $payment_gateway_surcharge = get_user_meta( $user_id, 'wwpp_payment_gateway_surcharge_mapping', true );
                break;

            case 'do_not_use_general_surcharge_mapping':
                $payment_gateway_surcharge = array();
                break;

            case 'use_general_surcharge_mapping':
            default:
                $payment_gateway_surcharge = get_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING );
                break;
        }

        if ( ! is_array( $payment_gateway_surcharge ) ) {
            $payment_gateway_surcharge = array();
        }

        return $payment_gateway_surcharge;
    }

    /**
     * Apply custom payment gateway surcharge.
     *
     * @param WC_Cart $wc_cart Cart object.
     *
     * @since        1.14.0 Refactored codebase.
     * @since        1.16.0 Support per wholesale user payment gateway surcharge override.
     * @since        1.16.4
     *               Make fixed price additional payment gateway surcharge feature compatible with "Aelia Currency
     *               Switcher" plugin. No need for the percentage types as the WC total will already be in converted
     *               price
     * @since        1.24   Check from all the gateways if a payment method exist. For some reason Zipmoney didn't
     *               exist when using get_available_payment_gateways() function (this function gets enabled payment
     *               gateways). Issue from Zipmoney.
     * @since        1.27.9 Replace round with wc_format_decimal function
     * @access       public
     *
     * @since        1.3.0
     */
    public function apply_payment_gateway_surcharge( $wc_cart ) {

        $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( empty( $user_wholesale_role ) ) {
            return;
        }

        $payment_gateway_surcharge = $this->get_payment_gateway_surcharge();

        if ( empty( $payment_gateway_surcharge ) ) {
            return;
        }

        // This will get all payment gateways not the enabled ones.
        $available_gateways = WC()->payment_gateways->payment_gateways();
        if ( ! is_array( $available_gateways ) ) {
            $available_gateways = array();
        }

        if ( ! empty( $available_gateways ) ) {
            $current_gateway = array();

            // Chosen Method.
            if ( isset( WC()->session->chosen_payment_method ) && isset( $available_gateways[ WC()->session->chosen_payment_method ] ) ) {
                $current_gateway = $available_gateways[ WC()->session->chosen_payment_method ];
            } elseif ( isset( $available_gateways[ get_option( 'woocommerce_default_gateway' ) ] ) ) {
                $current_gateway = $available_gateways[ get_option( 'woocommerce_default_gateway' ) ];
            }

            if ( ! empty( $current_gateway ) ) {
                foreach ( $payment_gateway_surcharge as $mapping ) {
                    if ( $mapping['wholesale_role'] == $user_wholesale_role[0] && $mapping['payment_gateway'] == $current_gateway->id ) { // phpcs:ignore
                        if ( 'percentage' === $mapping['surcharge_type'] ) {
                            $surcharge = wc_format_decimal( ( ( $wc_cart->cart_contents_total + $wc_cart->shipping_total ) * $mapping['surcharge_amount'] ) / 100, '' );
                        } else {
                            $surcharge = $mapping['surcharge_amount'];

                            /**
                             * Make fixed price additional payment gateway surcharge feature compatible with "Aelia Currency Switcher" plugin.
                             * No need for the percentage types as the WC total will already be in converted price
                             */
                            if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {
                                $active_currency    = get_woocommerce_currency();
                                $shop_base_currency = get_option( 'woocommerce_currency' );

                                if ( $active_currency !== $shop_base_currency ) {
                                    $surcharge = WWP_ACS_Integration_Helper::convert( $surcharge, $active_currency, $shop_base_currency );
                                }
                            }
                        }

                        $taxable = ( 'yes' === $mapping['taxable'] ) ? true : false;

                        // Apply Wholesale Tax Class if wholesale role/user has tax class mapping set in the setting.
                        $tax_class = '';

                        if ( $taxable ) {
                            $tax_class = $this->_wwpp_tax->apply_proper_tax_classes( '' );
                        }

                        $wc_cart->add_fee( $mapping['surcharge_title'], $surcharge, $taxable, $tax_class );
                    }
                }
            }
        }
    }

    /**
     * Apply taxable notice to surcharge.
     *
     * @param string $cart_totals_fee_html Price html.
     * @param object $fee                  Fee object.
     *
     * @since 1.27.9 Add support for 'Price entered with tax' and tax display cart setting setting
     *
     * @since 1.3.0
     * @since 1.14.0 Refactor codebase.
     * @return string Filtered price html.
     */
    public function apply_taxable_notice_on_surcharge( $cart_totals_fee_html, $fee ) {

        $tax_display_cart_setting = $this->_wwpp_tax->wholesale_tax_display_cart( get_option( 'woocommerce_tax_display_cart' ) );

        if ( ! wc_prices_include_tax() && $fee->taxable && 'incl' === $tax_display_cart_setting ) {
            $cart_totals_fee_html .= ' <small>' . WC()->countries->inc_tax_or_vat() . '</small>';
        } elseif ( wc_prices_include_tax() && $fee->taxable && 'excl' === $tax_display_cart_setting ) {
            $cart_totals_fee_html .= ' <small>' . WC()->countries->ex_tax_or_vat() . '</small>';
        }

        return $cart_totals_fee_html;
    }

    /**
     * Get wholesale user payment gateway.
     *
     * @param int    $user_id             User id.
     * @param string $user_wholesale_role User wholesale role.
     *
     * @since  1.16.0
     * @since  2.0.0 Adds default values for $user_id and $user_wholesale_role.
     * @access public
     *
     * @return array Array of wholesale user payment gateways.
     */
    public function get_wholesale_user_payment_gateways( $user_id = null, $user_wholesale_role = null ) {

        $payment_gateways = array();

        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_wholesale_role ) {
            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();
            if ( empty( $user_wholesale_role[0] ) ) {
                return $payment_gateways;
            }

            $user_wholesale_role = $user_wholesale_role[0];
        }

        $wholesale_role_payment_gateway_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_PAYMENT_GATEWAY_MAPPING );
        if ( ! is_array( $wholesale_role_payment_gateway_mapping ) ) {
            $wholesale_role_payment_gateway_mapping = array();
        }

        if ( array_key_exists( $user_wholesale_role, $wholesale_role_payment_gateway_mapping ) ) {
            $payment_gateways = array_map(
                function ( $item ) {

                    return $item['id'];
                },
                $wholesale_role_payment_gateway_mapping[ $user_wholesale_role ]
            );
        }

        if ( get_user_meta( $user_id, 'wwpp_override_payment_gateway_options', true ) === 'yes' ) {
            $pg = get_user_meta( $user_id, 'wwpp_payment_gateway_options', true );

            if ( ! empty( $pg ) && is_array( $pg ) ) {
                $payment_gateways = $pg;
            }
        }

        return $payment_gateways;
    }

    /**
     * Filter payment gateway to be available to certain wholesale role.
     * Note: payment gateway not need to be enabled.
     *
     * @param array $available_gateways Array of available gateways.
     *
     * @since 1.14.0 Refactor codebase.
     * @since 1.16.0 Refactor codebase and add support for per wholesale user payment gateway options override.
     * @since 1.30   Add compatibility with PayPal for WooCommerce By Angell EYE
     * @since 1.30.1 Respect 'Enable for shipping methods' setting for Cash on Delivery payment method
     *
     * @since 1.3.0
     * @return array Filtered array of avaialble gateways.
     */
    public function filter_available_payment_gateways( $available_gateways ) {

        /**
         * The PayPal for WooCommerce By Angell EYE use the same hook to disable payment gateways for PayPal order checkout (custom checkout method by the plugin).
         * Due to the WWPP hook priority is higher than the plugin, this will cause the PayPal order checkout to show unwanted payment gateway in the PayPal order checkout.
         * In the PayPal order checkout page, the payment method selector is hidden by the plugin. Therefore, an error may occur because the selector is choosing another payment method.
         * In that case the PayPal order will not recognized as a PayPal payment method. Instead, it will record the first mapped payment gateway for the wholesale role.
         * The solution is to skip the wholesale available payment gateway if the checkout is done from the PayPal order checkout.
         */
        if ( WWP_Helper_Functions::is_plugin_active( 'paypal-for-woocommerce/paypal-for-woocommerce.php' ) ) {
            if ( function_exists( 'angelleye_ppcp_has_active_session' ) && angelleye_ppcp_has_active_session() ) {
                return $available_gateways;
            }
        }

        $user_id             = get_current_user_id();
        $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

        if ( current_user_can( 'manage_options' ) || empty( $user_wholesale_role ) ) {
            return $available_gateways;
        }

        $user_payment_gateways = $this->get_wholesale_user_payment_gateways( $user_id, $user_wholesale_role[0] );

        if ( empty( $user_payment_gateways ) ) {
            return $available_gateways;
        }

        $all_payment_gateways       = WC()->payment_gateways()->payment_gateways;
        $filtered_gateways          = array();
        $has_cod_enable_for_methods = false;

        foreach ( $all_payment_gateways as $gateway ) {

            // Respect 'Enable for shipping methods' setting for Cash on Delivery payment method.
            if ( 'cod' === $gateway->id ) {
                $enable_for_methods = $gateway->get_option( 'enable_for_methods' );
                $needs_shipping     = false;

                // Test if shipping is needed first.
                if ( WC()->cart && WC()->cart->needs_shipping() ) {
                    $needs_shipping = true;
                }
                $needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );

                if ( ! empty( $enable_for_methods ) && $needs_shipping ) {
                    $has_cod_enable_for_methods = true;
                    if ( ! in_array( wc_get_chosen_shipping_method_ids()[0], $enable_for_methods, true ) ) {
                        continue;
                    }
                }
            }

            if ( in_array( $gateway->id, $user_payment_gateways ) ) { // phpcs:ignore
                /**
                 * Filtered gateways.
                 *
                 * @var WC_Payment_Gateway $gateway
                 */
                $gateway->enabled = 'yes';

                $filtered_gateways[ $gateway->id ] = $gateway;
            }
        }

        if ( ! empty( $filtered_gateways ) || $has_cod_enable_for_methods ) {
            WC()->payment_gateways()->set_current_gateway( $filtered_gateways );

            return $filtered_gateways;
        } else {
            return $available_gateways;
        }
    }

    /**
     * Everytime third party plugins sets a payment token as default, we negate that effect.
     *
     * @param int    $token_id Token id.
     * @param object $token    Token object.
     *
     * @since  1.10.1
     * @since  1.14.0 Refactor codebase.
     * @access public
     */
    public function undefault_payment_token( $token_id, $token ) {

        if ( self::current_user_has_role_payment_gateway_surcharge_mapping() ) {
            global $wpdb;

            $token->set_default( false );

            $wpdb->update(
                $wpdb->prefix . 'woocommerce_payment_tokens',
                array( 'is_default' => 0 ),
                array(
                    'token_id' => $token->get_id(),
                )
            );
        }
    }

    /**
     * Set all existing payment tokens as not default.
     *
     * @since  1.10.1
     * @since  1.14.0 Refactor codebase.
     * @access public
     */
    public function undefault_existing_payment_tokens() {

        if ( self::has_role_payment_gateway_surcharge_mapping() ) {
            global $wpdb;

            $wpdb->query( 'UPDATE ' . $wpdb->prefix . 'woocommerce_payment_tokens SET is_default = 0' );
        }
    }

    /**
     * Check if current user has role payment gateway surecharge mapping.
     *
     * @since  1.10.1
     * @since  1.14.0 Refactored codebase.
     * @since  1.16.4 Consider per user overridden surcharge mapping.
     * @access public
     *
     * @return boolean
     */
    public static function current_user_has_role_payment_gateway_surcharge_mapping() {

        $user = wp_get_current_user();

        switch ( get_user_meta( $user->ID, 'wwpp_override_payment_gateway_surcharge', true ) ) {
            case 'specify_surcharge_mapping':
                $payment_gateway_surcharge = get_user_meta( $user->ID, 'wwpp_payment_gateway_surcharge_mapping', true );
                break;

            case 'do_not_use_general_surcharge_mapping':
                $payment_gateway_surcharge = array();
                break;

            case 'use_general_surcharge_mapping':
            default:
                $payment_gateway_surcharge = get_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING );
                break;
        }

        if ( ! is_array( $payment_gateway_surcharge ) ) {
            $payment_gateway_surcharge = array();
        }

        $current_user_has_mapping = false;

        foreach ( $payment_gateway_surcharge as $mapping ) {
            if ( in_array( $mapping['wholesale_role'], $user->roles ) ) { // phpcs:ignore
                $current_user_has_mapping = true;
                break;
            }
        }

        return $current_user_has_mapping;
    }

    /**
     * Check if there is a role payment gateway surcharge mapping.
     *
     * @since  1.10.1
     * @since  1.14.0 Refactor codebase.
     * @access public
     *
     * @return boolean Flag that determines if role have payment surcharge mapping.
     */
    public static function has_role_payment_gateway_surcharge_mapping() {

        $payment_gateway_surcharge = get_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING );
        if ( ! is_array( $payment_gateway_surcharge ) ) {
            $payment_gateway_surcharge = array();
        }

        return ! empty( $payment_gateway_surcharge );
    }

    /**
     * AJAX Call Handlers
     */

    /**
     * Add wholesale role / payment gateway mapping.
     *
     * @param null|array $mapping Role payment gateway mapping.
     *
     * @since  1.14.0 Refactor codebase.
     * @access public
     *
     * @since  1.3.0
     */
    public function add_wholesale_role_payment_gateway_mapping( $mapping = null ) {

        // Security checks.
        if ( ! check_ajax_referer( 'wwppAddWholesaleRolePaymentGatewayMapping', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        $mapping      = WWPP_Helper_Functions::sanitize_array( $_POST['mapping'] );
        $wrpg_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_PAYMENT_GATEWAY_MAPPING );

        if ( ! is_array( $wrpg_mapping ) ) {
            $wrpg_mapping = array();
        }

        if ( array_key_exists( $mapping['wholesale_role'], $wrpg_mapping ) ) {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Wholesale role you wish to add payment gateway mapping already exist', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            $wrpg_mapping[ $mapping['wholesale_role'] ] = $mapping['payment_gateways'];
            update_option( WWPP_OPTION_WHOLESALE_ROLE_PAYMENT_GATEWAY_MAPPING, $wrpg_mapping, 'no' );
            $response = array( 'status' => 'success' );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Update wholesale role / payment gateway mapping.
     *
     * @param null|array $mapping Role payment gateway mapping.
     *
     * @since  1.14.0 Refactor codebase.
     * @access public
     *
     * @since  1.3.0
     */
    public function update_wholesale_role_payment_gateway_mapping( $mapping = null ) {

        // Security checks.
        if ( ! check_ajax_referer( 'wwppUpdateWholesaleRolePaymentGatewayMapping', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        $mapping      = WWPP_Helper_Functions::sanitize_array( $_POST['mapping'] );
        $wrpg_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_PAYMENT_GATEWAY_MAPPING );

        if ( ! is_array( $wrpg_mapping ) ) {
            $wrpg_mapping = array();
        }

        if ( ! array_key_exists( $mapping['wholesale_role'], $wrpg_mapping ) ) {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Wholesale Role / Payment Gateway mapping you wish to edit does not exist on record', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            $wrpg_mapping[ $mapping['wholesale_role'] ] = $mapping['payment_gateways'];
            update_option( WWPP_OPTION_WHOLESALE_ROLE_PAYMENT_GATEWAY_MAPPING, $wrpg_mapping, 'no' );
            $response = array( 'status' => 'success' );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Delete wholesale role / payment gateway method.
     *
     * @param null|string $wholesale_role_key Wholesale role key.
     *
     * @since  1.14.0 Refactor codebase.
     * @access public
     *
     * @since  1.3.0
     */
    public function delete_wholesale_role_payment_gateway_mapping( $wholesale_role_key = null ) {

        // Security checks.
        if ( ! check_ajax_referer( 'wwppDeleteWholesaleRolePaymentGatewayMapping', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        $wholesale_role_key = sanitize_key( $_POST['wholesaleRoleKey'] );
        $wrpg_mapping       = get_option( WWPP_OPTION_WHOLESALE_ROLE_PAYMENT_GATEWAY_MAPPING );

        if ( ! is_array( $wrpg_mapping ) ) {
            $wrpg_mapping = array();
        }

        if ( ! array_key_exists( $wholesale_role_key, $wrpg_mapping ) ) {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Wholesale Role / Payment Gateway mapping you wish to delete does not exist on record', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            unset( $wrpg_mapping[ $wholesale_role_key ] );
            update_option( WWPP_OPTION_WHOLESALE_ROLE_PAYMENT_GATEWAY_MAPPING, $wrpg_mapping, 'no' );
            $response = array( 'status' => 'success' );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Add payment gateway surcharge to a wholesale role.
     * $surchargeData parameter is expected to be an array with the keys below.
     * wholesale_role
     * payment_gateway
     * surcharge_title
     * surcharge_type
     * surcharge_amount
     * taxable
     *
     * @param null|array $surcharge_data Array of surcharge data.
     *
     * @since  1.14.0 Refactor codebase.
     * @since  1.16.0 Support per wholesale user payment gateway surcharge override.
     * @access public
     *
     * @since  1.3.0
     */
    public function add_payment_gateway_surcharge( $surcharge_data = null ) {

        // Security checks.
        if ( ! check_ajax_referer( 'wwppAddPaymentGatewaySurcharge', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        $surcharge_data = WWPP_Helper_Functions::sanitize_array( $_POST['surchargeData'] );

        $user_id           = isset( $_POST['user_id'] ) ? sanitize_key( $_POST['user_id'] ) : 0;
        $surcharge_mapping = $user_id ? get_user_meta( $user_id, 'wwpp_payment_gateway_surcharge_mapping', true ) : get_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING );

        if ( ! is_array( $surcharge_mapping ) ) {
            $surcharge_mapping = array();
        }

        $surcharge_mapping[] = $surcharge_data;

        if ( $user_id ) {
            update_user_meta( $user_id, 'wwpp_payment_gateway_surcharge_mapping', $surcharge_mapping );
        } else {
            update_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING, $surcharge_mapping, 'no' );
        }

        $arr_keys     = array_keys( $surcharge_mapping );
        $latest_index = end( $arr_keys );

        $response = array(
            'status'       => 'success',
            'latest_index' => $latest_index,
        );

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Update payment gateway surcharge for a wholesale role.
     *
     * @param null|int   $idx            Mapping index.
     * @param null|array $surcharge_data Array of surcharge data.
     *
     * @since  1.16.0 Support per wholesale user payment gateway surcharge override.
     * @access public
     *
     * @since  1.3.0
     * @since  1.14.0 Refactor codebase.
     */
    public function update_payment_gateway_surcharge( $idx = null, $surcharge_data = null ) {

        // Security checks.
        if ( ! check_ajax_referer( 'wwppUpdatePaymentGatewaySurcharge', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        $idx            = sanitize_key( $_POST['idx'] );
        $surcharge_data = WWPP_Helper_Functions::sanitize_array( $_POST['surchargeData'] );

        $user_id           = isset( $_POST['user_id'] ) ? sanitize_key( $_POST['user_id'] ) : 0;
        $surcharge_mapping = $user_id ? get_user_meta( $user_id, 'wwpp_payment_gateway_surcharge_mapping', true ) : get_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING );

        if ( ! is_array( $surcharge_mapping ) ) {
            $surcharge_mapping = array();
        }

        if ( ! array_key_exists( $idx, $surcharge_mapping ) ) {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Payment gateway surcharge mapping you wish to update does not exist on record', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            $surcharge_mapping[ $idx ] = $surcharge_data;

            if ( $user_id ) {
                update_user_meta( $user_id, 'wwpp_payment_gateway_surcharge_mapping', $surcharge_mapping );
            } else {
                update_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING, $surcharge_mapping, 'no' );
            }

            $response = array( 'status' => 'success' );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Delete payment gateway surcharge of a wholesale user.
     *
     * @param null|int $idx Mapping index.
     *
     * @since  1.14.0 Refactor codebase.
     * @since  1.16.0 Support per wholesale user payment gateway surcharge override.
     * @access public
     *
     * @since  1.3.0
     */
    public function delete_payment_gateway_surcharge( $idx = null ) {

        // Security checks.
        if ( ! check_ajax_referer( 'wwppDeletePaymentGatewaySurcharge', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        $idx               = sanitize_key( $_POST['idx'] );
        $user_id           = isset( $_POST['user_id'] ) ? sanitize_key( $_POST['user_id'] ) : 0;
        $surcharge_mapping = $user_id ? get_user_meta( $user_id, 'wwpp_payment_gateway_surcharge_mapping', true ) : get_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING );

        if ( ! is_array( $surcharge_mapping ) ) {
            $surcharge_mapping = array();
        }

        if ( ! array_key_exists( $idx, $surcharge_mapping ) ) {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Payment gateway surcharge you want to delete does not exist on record', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            unset( $surcharge_mapping[ $idx ] );

            if ( $user_id ) {
                update_user_meta( $user_id, 'wwpp_payment_gateway_surcharge_mapping', $surcharge_mapping );
            } else {
                update_option( WWPP_OPTION_PAYMENT_GATEWAY_SURCHARGE_MAPPING, $surcharge_mapping, 'no' );
            }

            $response = array( 'status' => 'success' );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Integrate payment gateway with Paypal for Woocommerce by Angell EYE smart button.
     * Remove the smart button in various pages if Paypal for Woocommerce by Angell EYE is not mapped for the wholesale
     * role.
     *
     * @since 1.30
     */
    public function integrate_payment_gateway_with_paypal_for_woocommerce_by_angell_eye_smart_button() {

        $user_id = get_current_user_id();

        if ( null !== $user_id && $user_id > 0 ) {
            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            // If administrator/shop manager or not wholesale customer then stop here.
            if ( current_user_can( 'manage_woocommerce' ) || empty( $user_wholesale_role ) ) {
                return;
            }

            $user_payment_gateways = $this->get_wholesale_user_payment_gateways( $user_id, $user_wholesale_role[0] );

            // If wholesale role has mapped payment gateway and the Paypal for Woocommerce by Angell EYE is not mapped for the wholesale role then remove the smart button in various pages.
            if ( ! empty( $user_payment_gateways ) && ! in_array( 'angelleye_ppcp', $user_payment_gateways ) ) { // phpcs:ignore
                $angelleye_ppcp_settings                      = WC_Gateway_PPCP_AngellEYE_Settings::instance();
                $angelleye_ppcp_smart_button                  = AngellEYE_PayPal_PPCP_Smart_Button::instance();
                $angelleye_ppcp_enable_product_button         = 'yes' === $angelleye_ppcp_settings->get( 'enable_product_button', 'yes' );
                $angelleye_ppcp_enable_cart_button            = 'yes' === $angelleye_ppcp_settings->get( 'enable_cart_button', 'yes' );
                $angelleye_ppcp_checkout_disable_smart_button = 'yes' === $angelleye_ppcp_settings->get( 'checkout_disable_smart_button', 'no' );
                $angelleye_ppcp_cart_button_position          = $angelleye_ppcp_settings->get( 'cart_button_position', 'bottom' );

                if ( $angelleye_ppcp_enable_product_button ) {
                    remove_action( 'woocommerce_after_add_to_cart_form', array( $angelleye_ppcp_smart_button, 'display_paypal_button_product_page' ), 10 );
                }

                if ( $angelleye_ppcp_enable_cart_button ) {
                    if ( 'both' === $angelleye_ppcp_cart_button_position ) {
                        remove_action( 'woocommerce_before_cart_table', array( $angelleye_ppcp_smart_button, 'display_paypal_button_cart_page_top' ) );
                        remove_action( 'woocommerce_proceed_to_checkout', array( $angelleye_ppcp_smart_button, 'display_paypal_button_cart_page' ), 11 );
                    } elseif ( 'top' === $angelleye_ppcp_cart_button_position ) {
                        remove_action( 'woocommerce_before_cart_table', array( $angelleye_ppcp_smart_button, 'display_paypal_button_cart_page_top' ) );
                    } else {
                        remove_action( 'woocommerce_proceed_to_checkout', array( $angelleye_ppcp_smart_button, 'display_paypal_button_cart_page' ), 11 );
                    }
                }

                if ( $angelleye_ppcp_checkout_disable_smart_button ) {
                    remove_action( 'angelleye_ppcp_display_paypal_button_checkout_page', array( $angelleye_ppcp_smart_button, 'display_paypal_button_checkout_page' ) );
                }
            }
        }
    }

    /**
     * Reset selected payment method on checkout block
     *
     * @since 2.0.0
     * @return void
     */
    public function maybe_reset_selected_payment_method() {

        if ( ! is_admin() && ! empty( WC()->session ) && ! empty( WC()->payment_gateways() ) && WWPP_Helper_Functions::has_wc_checkout_block() ) {
            $surcharge_settings = $this->get_payment_gateway_surcharge();
            if ( ! empty( $surcharge_settings[0] ) ) {
                $payment_method = key( WC()->payment_gateways()->payment_gateways() );
                WC()->session->set( 'chosen_payment_method', $payment_method );
            }
        }
    }

    /**
     * Register WC Checkout Store API callback for setting selected payment method.
     *
     * @since 2.0.0
     * @return void
     */
    public function wc_checkout_store_api_callback() {

        woocommerce_store_api_register_update_callback(
            array(
                'namespace' => 'wwpp-block',
                'callback'  => array( $this, 'checkout_block_set_payment_method' ),
            )
        );
    }

    /**
     * Set the selected payment method in the session.
     *
     * @see https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce-blocks/packages/checkout/utils/README.md#extensioncartupdate
     * @see https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce-blocks/docs/third-party-developers/extensibility/rest-api/extend-rest-api-update-cart.md
     *
     * @param array $data The payload sent via extensionCartUpdate() javascript function.
     *
     * @return void
     */
    public function checkout_block_set_payment_method( $data ) {

        if ( empty( WC()->session ) ) {
            return;
        }

        $definition = array(
            'payment_method' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        );
        $data       = filter_var_array( $data, $definition );

        WC()->session->set( 'chosen_payment_method', $data['payment_method'] );
    }

    /**
     * Enqueue surcharge script.
     *
     * @since 2.0.0
     * @return void
     */
    public function maybe_enqueue_surcharge_script() {

        if ( WWPP_Helper_Functions::has_wc_checkout_block() ) {
            $surcharge_settings = $this->get_payment_gateway_surcharge();
            if ( ! empty( $surcharge_settings ) ) {
                $app = new \RymeraWebCo\WWPP\Vite_App(
                    'wwpp-cart-checkout-block-surcharge',
                    'src/vanilla/cart-checkout-block-surcharge.ts'
                );

                $app->enqueue();
            }
        }
    }

    /**
     * Register model ajax handlers.
     *
     * @since  1.14.0
     * @access public
     */
    public function register_ajax_handler() {

        // Wholesale role payment gateway mapping.
        add_action( 'wp_ajax_wwppAddWholesaleRolePaymentGatewayMapping', array( $this, 'add_wholesale_role_payment_gateway_mapping' ) );
        add_action( 'wp_ajax_wwppUpdateWholesaleRolePaymentGatewayMapping', array( $this, 'update_wholesale_role_payment_gateway_mapping' ) );
        add_action( 'wp_ajax_wwppDeleteWholesaleRolePaymentGatewayMapping', array( $this, 'delete_wholesale_role_payment_gateway_mapping' ) );

        // Wholesale role payment gateway surcharge mapping.
        add_action( 'wp_ajax_wwppAddPaymentGatewaySurcharge', array( $this, 'add_payment_gateway_surcharge' ) );
        add_action( 'wp_ajax_wwppUpdatePaymentGatewaySurcharge', array( $this, 'update_payment_gateway_surcharge' ) );
        add_action( 'wp_ajax_wwppDeletePaymentGatewaySurcharge', array( $this, 'delete_payment_gateway_surcharge' ) );
    }

    /**
     * Execute model.
     *
     * @since  1.14.0
     * @access public
     */
    public function run() {

        add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_payment_gateway_surcharge' ), 10, 1 );
        add_filter( 'woocommerce_cart_totals_fee_html', array( $this, 'apply_taxable_notice_on_surcharge' ), 10, 2 );
        add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_available_payment_gateways' ), 100, 1 );
        add_action( 'woocommerce_payment_token_set_default', array( $this, 'undefault_payment_token' ), 10, 2 );

        add_action( 'init', array( $this, 'register_ajax_handler' ) );

        // Angeleye Paypal integration.
        if ( WWP_Helper_Functions::is_plugin_active( 'paypal-for-woocommerce/paypal-for-woocommerce.php' ) ) {
            add_action( 'woocommerce_init', array( $this, 'integrate_payment_gateway_with_paypal_for_woocommerce_by_angell_eye_smart_button' ) );
        }

        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_surcharge_script' ) );

        add_action( 'woocommerce_checkout_init', array( $this, 'maybe_reset_selected_payment_method' ) );
        add_action( 'woocommerce_blocks_loaded', array( $this, 'wc_checkout_store_api_callback' ) );
    }
}
