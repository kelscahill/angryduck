<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Integrations
 */

namespace RymeraWebCo\WPay\Integrations;

use RymeraWebCo\WPay\Integrations\Payment_Block;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

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
     * @param Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry Payment method registry.
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
     * @return WP_REST_Response
     */
    public function wpay_rest_checkout_process_action( $request ) {
        $data = $request->get_json_params();

        if ( ! isset( $data['plan_id'] ) ) {
            return new \WP_Error( 'wpay_missing_plan_id', __( 'Missing plan ID.', 'woocommerce-wholesale-payments' ), array( 'status' => 400 ) );
        }

        // Set the selected plan in the session.
        $this->wpay_set_data_session( $data['plan_id'] );

        $response = array(
            'success' => true,
            'message' => 'Checkout action processed successfully',
        );

        return rest_ensure_response( $response );
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
