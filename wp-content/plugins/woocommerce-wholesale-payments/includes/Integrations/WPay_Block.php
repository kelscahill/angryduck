<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Integrations
 */

namespace RymeraWebCo\WPay\Integrations;

use RymeraWebCo\WPay\Integrations\Payment_Block;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use WP_Error;
use WP_REST_Request;

/**
 * WPay_Block class.
 *
 * @since 1.0.0
 */
class WPay_Block {

    /**
     * Declare compatibility for 'cart_checkout_blocks'.
     *
     * @since 1.0.0
     * @return void
     */
    public function declare_cart_checkout_blocks_compatibility() {
        // Check if the required class exists.
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            // Declare compatibility for 'cart_checkout_blocks'.
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WPAY_PLUGIN_FILE, true );
        }
    }

    /**
     * Register the custom Payment Method Type.
     *
     * @param PaymentMethodRegistry $payment_method_registry Payment method registry.
     *
     * @since 1.0.0
     * @return void
     */
    public function wpay_blocks_payment_method_type_registration( PaymentMethodRegistry $payment_method_registry ) {
        $payment_method_registry->register( new Payment_Block() );
    }

    /**
     * Register the checkout process payment route.
     *
     * @since 1.0.0
     * @return void
     */
    public function wpay_register_checkout_process_payment() {
        register_rest_route(
            'wpay/v1',
            '/checkout/choose-plan',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'wpay_rest_checkout_process_action' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Process the checkout action.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @since 1.0.0
     * @return WP_Error|WP_REST_Response
     */
    public function wpay_rest_checkout_process_action( $request ) {
        $data = $request->get_json_params();

        // Handle payment method attached flag.
        if ( isset( $data['wpay_pm_attached'] ) && true === $data['wpay_pm_attached'] ) {
            $this->wpay_set_pm_attached_flag();
        }

        // Handle plan selection (can be sent together with wpay_pm_attached).
        if ( isset( $data['plan_id'] ) ) {
            $this->wpay_set_data_session( $data['plan_id'] );
        }

        // Return success if either flag was set or plan was selected.
        if ( isset( $data['wpay_pm_attached'] ) || isset( $data['plan_id'] ) ) {
            $response = array(
                'success' => true,
                'message' => 'Checkout action processed successfully',
            );

            return rest_ensure_response( $response );
        }

        // If neither was provided, return error.
        return new WP_Error( 'wpay_missing_data', __( 'Missing required data (plan_id or wpay_pm_attached).', 'woocommerce-wholesale-payments' ), array( 'status' => 400 ) );
    }

    /**
     * Set the selected plan in the session.
     *
     * @param int $plan_id Plan ID.
     *
     * @since 1.0.0
     * @return void
     */
    private function wpay_set_data_session( $plan_id ) {
        if ( ! wc()->session ) {
            wc()->initialize_session();
        }
        wc()->session->set( 'wpay_selected_plan', $plan_id );
    }

    /**
     * Set the payment method attached flag in the session.
     *
     * @since 1.0.0
     * @return void
     */
    private function wpay_set_pm_attached_flag() {
        if ( ! wc()->session ) {
            wc()->initialize_session();
        }
        wc()->session->set( 'wpay_pm_attached', true );
    }

    /**
     * Get the payment method attached flag from the session.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function wpay_get_pm_attached_flag() {
        if ( ! wc()->session ) {
            return false;
        }
        return (bool) wc()->session->get( 'wpay_pm_attached', false );
    }

    /**
     * Clear the payment method attached flag from the session.
     *
     * @since 1.0.0
     * @return void
     */
    public static function wpay_clear_pm_attached_flag() {
        if ( wc()->session ) {
            wc()->session->set( 'wpay_pm_attached', false );
        }
    }

    /**
     * Run the class.
     */
    public function run() {
        if ( class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ) {
            add_action( 'before_woocommerce_init', array( $this, 'declare_cart_checkout_blocks_compatibility' ) );
            add_action( 'woocommerce_blocks_payment_method_type_registration', array( $this, 'wpay_blocks_payment_method_type_registration' ), 10, 1 );

            // Add action to process payment.
            add_action( 'rest_api_init', array( $this, 'wpay_register_checkout_process_payment' ) );
        }
    }
}
