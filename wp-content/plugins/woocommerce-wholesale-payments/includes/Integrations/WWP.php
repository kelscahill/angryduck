<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Integrations
 */

namespace RymeraWebCo\WPay\Integrations;

use RymeraWebCo\WPay\Abstracts\Abstract_Class;
use RymeraWebCo\WPay\Helpers\WPay;
use RymeraWebCo\WPay\Helpers\RCS;
use WP_Error;

/**
 * WWP class.
 *
 * @since 1.0.0
 */
class WWP extends Abstract_Class {

    /**
     * Add WPAY settings tab to the admin settings page.
     *
     * @param array $tabs The existing tabs.
     *
     * @since 1.0.0
     * @since 1.0.1 Added Stripe tab.
     * @return mixed
     */
    public function admin_settings_tab( $tabs ) {

        $tabs['wpay'] = array(
            'label' => __( 'Wholesale Payments', 'woocommerce-wholesale-payments' ),
            'child' => array(
                'general' => array(
                    'sort'     => 2,
                    'key'      => 'general',
                    'label'    => __( 'General', 'woocommerce-wholesale-payments' ),
                    'sections' => array(
                        'api_settings'      => array(
                            'label' => __( 'API Settings', 'woocommerce-wholesale-payments' ),
                            'desc'  => '',
                        ),
                        'checkout_settings' => array(
                            'label' => __( 'Checkout Settings', 'woocommerce-wholesale-payments' ),
                            'desc'  => '',
                        ),
                    ),
                ),
            ),
        );

        return $tabs;
    }

    /**
     * Add WPAY settings to the admin settings page.
     *
     * @param array $controls The existing controls.
     *
     * @since 1.0.0
     * @since 1.0.1 Added Stripe settings.
     * @return mixed
     */
    public function wpay_admin_settings( $controls ) {

        $controls['wpay']['general'] = array(
            'api_settings'      => array(
                array(
                    'type'    => 'select',
                    'label'   => __( 'API Mode', 'woocommerce-wholesale-payments' ),
                    'id'      => 'wpay_api_mode',
                    'default' => WPay::get_api_mode(),
                    'options' => array(
                        'live' => __( 'Live', 'woocommerce-wholesale-payments' ),
                        'test' => __( 'Test', 'woocommerce-wholesale-payments' ),
                    ),
                ),
            ),
            'checkout_settings' => array(
                array(
                    'type'    => 'text',
                    'label'   => __( 'Payment Method Name', 'woocommerce-wholesale-payments' ),
                    'id'      => WPay::get_payment_method_name( 'key' ),
                    'default' => WPay::get_payment_method_name(),
                ),
            ),
        );

        if ( WPay::get_account_number() ) {
            $controls['wpay']['general']['api_settings'][] = array(
                'type'     => 'text',
                'label'    => __( 'Stripe Connect Account', 'woocommerce-wholesale-payments' ),
                'id'       => 'wpay_account_number',
                'default'  => WPay::get_account_number(),
                'disabled' => true,
            );
            $controls['wpay']['general']['api_settings'][] = array(
                'type'     => 'text',
                'label'    => __( 'Live Access Token', 'woocommerce-wholesale-payments' ),
                'id'       => 'wpay_access_token',
                'default'  => WPay::get_masked_token( 'access_token', false ),
                'disabled' => true,
            );
            $controls['wpay']['general']['api_settings'][] = array(
                'type'     => 'text',
                'label'    => __( 'Live Publishable Key', 'woocommerce-wholesale-payments' ),
                'id'       => 'wpay_publishable_key',
                'default'  => WPay::get_masked_token( 'publishable_key', false ),
                'disabled' => true,
            );
            $controls['wpay']['general']['api_settings'][] = array(
                'type'     => 'text',
                'label'    => __( 'Test Access Token', 'woocommerce-wholesale-payments' ),
                'id'       => 'wpay_test_access_token',
                'default'  => WPay::get_masked_token( 'access_token', true ),
                'disabled' => true,
            );
            $controls['wpay']['general']['api_settings'][] = array(
                'type'     => 'text',
                'label'    => __( 'Test Publishable Key', 'woocommerce-wholesale-payments' ),
                'id'       => 'wpay_test_publishable_key',
                'default'  => WPay::get_masked_token( 'publishable_key', true ),
                'disabled' => true,
            );
            $controls['wpay']['general']['api_settings'][] = array(
                'type'         => 'button',
                'id'           => 'wpay_test_connection',
                'button_label' => __( 'Test Connection', 'woocommerce-wholesale-payments' ),
                'action'       => 'test_stripe_connection',
                'description'  => __( 'This will check your connection to stripe.', 'woocommerce-wholesale-payments' ),
            );
            $controls['wpay']['general']['api_settings'][] = array(
                'type'         => 'button',
                'id'           => 'wpay_test_connection',
                'button_label' => __( 'Reset Connection', 'woocommerce-wholesale-payments' ),
                'action'       => 'reset_stripe_connection',
                'description'  => __( 'This will reset your connection to stripe.', 'woocommerce-wholesale-payments' ),
            );
        } else {
            $controls['wpay']['general']['api_settings'][] = array(
                'type'         => 'button',
                'label'        => __( 'Stripe API Settings', 'woocommerce-wholesale-payments' ),
                'id'           => 'wpay_connect_stripe_account',
                'button_label' => __( 'Connect with Stripe', 'woocommerce-wholesale-payments' ),
                'action'       => 'connect_stripe_account',
            );
        }

        return $controls;
    }

    /**
     * Connect to Stripe account.
     *
     * @return array|WP_Error
     */
    public function connect_stripe_account() {
        $response = RCS::instance()->connect();

        if ( is_wp_error( $response ) ) {
            return array(
                'status'  => 'error',
                'message' => $response->get_error_message(),
            );
        }

        return array(
            'status'   => 'success',
            'message'  => __( 'Redirecting to Stripe onboarding page. Please wait...', 'woocommerce-wholesale-payments' ),
            'redirect' => $response['onboarding_url'],
        );
    }

    /**
     * Test the stripe connection.
     *
     * @return array|WP_Error
     */
    public function test_stripe_connection() {
        $response = RCS::instance()->get_stripe_account();

        if ( is_wp_error( $response ) ) {
            if ( str_contains( $response->get_error_message(), 'account does not exist' ) ||
                str_contains( $response->get_error_message(), 'may have been revoked' ) ||
                str_contains( $response->get_error_message(), 'not found' ) ) {
                WPay::save_account_number( '' );
            }

            return array(
                'status'  => 'error',
                'message' => $response->get_error_message(),
            );
        }

        $status  = 'error';
        $message = __( 'Stripe account not connected!', 'woocommerce-wholesale-payments' );
        if ( WPay::get_account_number() === $response['account_number'] ) {
            $status  = 'success';
            $message = __( 'Stripe account connected!', 'woocommerce-wholesale-payments' );
        }

        return array(
            'status'  => $status,
            'message' => $message,
        );
    }

    /**
     * Reset the stripe connection.
     *
     * @return array
     */
    public function reset_stripe_connection() {
        $reset_fields = array(
            'account_number',
            'display_name',
            'live_access_token',
            'live_publishable_key',
            'live_scope',
            'live_token_type',
            'live_webhook_secret',
            'refresh_token',
            'test_access_token',
            'test_publishable_key',
            'test_scope',
            'test_token_type',
            'test_webhook_secret',
        );

        foreach ( $reset_fields as $reset_field ) {
            update_option( "wpay_$reset_field", '' );
        }

        return array(
            'status'   => 'success',
            'message'  => __( 'Connection reset! Please connect a Stripe account.', 'woocommerce-wholesale-payments' ),
            'redirect' => admin_url( 'admin.php?page=wholesale-settings&tab=wpay' ),
        );
    }

    /**
     * Run the integration.
     */
    public function run() {

        add_filter( 'wwp_admin_setting_default_tabs', array( $this, 'admin_settings_tab' ) );
        add_filter( 'wwp_admin_setting_default_controls', array( $this, 'wpay_admin_settings' ) );

        // Add the Stripe API settings.
        add_action( 'wwp_trigger_connect_stripe_account', array( $this, 'connect_stripe_account' ), 10, 1 );
        add_action( 'wwp_trigger_test_stripe_connection', array( $this, 'test_stripe_connection' ), 10, 1 );
        add_action( 'wwp_trigger_reset_stripe_connection', array( $this, 'reset_stripe_connection' ), 10, 1 );
    }
}
