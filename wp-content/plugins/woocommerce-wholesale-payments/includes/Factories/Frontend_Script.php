<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Factories
 */

namespace RymeraWebCo\WPay\Factories;

use RymeraWebCo\WPay\Abstracts\Abstract_Class;
use RymeraWebCo\WPay\Helpers\Helper;
use RymeraWebCo\WPay\Helpers\WPay;

/**
 * Frontend_Script class.
 *
 * @since 1.0.2
 */
class Frontend_Script extends Abstract_Class {

    /**
     * Enqueue admin scripts.
     *
     * @since 1.0.2
     * @return void
     */
    public function frontend_enqueue_scripts() {
        // Stripe.js for Payment Element.
        wp_register_script(
            'wpay-stripe-js',
            'https://js.stripe.com/v3/',
            array(),
            Helper::get_plugin_data( 'Version' ),
            true
        );
        wp_enqueue_script( 'wpay-stripe-js' );
        wp_register_script(
            'wpay-frontend-scripts',
            plugins_url( 'static/', WPAY_PLUGIN_FILE ) . 'js/wpay-scripts.js',
            array(
                'jquery',
            ),
            Helper::get_plugin_data( 'Version' ),
            true
        );
        wp_enqueue_script( 'wpay-frontend-scripts' );

        // Get customer ID from user meta if user is logged in.
        $customer_id = null;
        $user_id     = get_current_user_id();
        if ( $user_id ) {
            $customer_id = get_user_meta( $user_id, '_wpay_stripe_customer_id', true );
        }

        // Localized settings for classic checkout capture.
        $l10n = array(
            // Match Rymera_Stripe default rest_base => 'rymera-stripe'.
            'restUrl'         => esc_url_raw( rest_url( 'wpay/v1/rymera-stripe' ) ),
            'checkoutRestUrl' => esc_url_raw( rest_url( 'wpay/v1/checkout/choose-plan' ) ),
            'nonce'           => wp_create_nonce( 'wp_rest' ),
            'publishableKey'  => WPay::get_token( 'publishable_key' ),
            'customerId'      => $customer_id ? $customer_id : null,
            'wpayDebug'       => defined( 'WPAY_DEBUG' ) && WPAY_DEBUG,
            'i18n'            => array(
                'savePaymentMethod'            => __( 'Save payment method', 'woocommerce-wholesale-payments' ),
                'savingPaymentMethod'          => __( 'Saving payment method...', 'woocommerce-wholesale-payments' ),
                'saving'                       => __( 'Saving...', 'woocommerce-wholesale-payments' ),
                'saved'                        => __( 'Payment method saved. You can now place your order.', 'woocommerce-wholesale-payments' ),
                'error'                        => __( 'Error: ', 'woocommerce-wholesale-payments' ),
                'paymentMethodNotInitialized'  => __( 'Payment method not initialized. Please refresh the page and try again.', 'woocommerce-wholesale-payments' ),
                'completePaymentInformation'   => __( 'Please complete your payment information before placing the order.', 'woocommerce-wholesale-payments' ),
                'customerInfoNotAvailable'     => __( 'Customer information not available. Please refresh and try again.', 'woocommerce-wholesale-payments' ),
                'processingPaymentMethod'      => __( 'Processing payment method...', 'woocommerce-wholesale-payments' ),
                'failedToCreatePaymentMethod'  => __( 'Failed to create payment method', 'woocommerce-wholesale-payments' ),
                'failedToSavePaymentMethod'    => __( 'Failed to save payment method. Please try again.', 'woocommerce-wholesale-payments' ),
                'placeOrder'                   => __( 'Place order', 'woocommerce-wholesale-payments' ),
                'unableToCreateStripeCustomer' => __( 'Unable to create Stripe customer. Please reload the page and try again.', 'woocommerce-wholesale-payments' ),
                'paymentInformationReady'      => __( 'Payment information is ready. You can now place your order.', 'woocommerce-wholesale-payments' ),
            ),
        );
        wp_add_inline_script( 'wpay-frontend-scripts', 'window.wpayCheckout = ' . wp_json_encode( $l10n ) . ';', 'before' );
    }

    /**
     * Run the class.
     */
    public function run() {
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_scripts' ), 11 );
    }
}
