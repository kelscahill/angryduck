<?php
/**
 * Author: Rymera Wen Co.
 *
 * @package RymeraWebCo\WPay\REST
 */

namespace RymeraWebCo\WPay\REST;

use RymeraWebCo\WPay\Abstracts\Abstract_REST;
use RymeraWebCo\WPay\Helpers\RCS;
use RymeraWebCo\WPay\Helpers\Stripe;
use RymeraWebCo\WPay\Helpers\WPay;
use RymeraWebCo\WPay\Traits\Singleton_Trait;
use RymeraWebCo\WPay\Factories\Payment_Order;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Rymera_Stripe class.
 *
 * @since 1.0.0
 */
class Rymera_Stripe extends Abstract_REST {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @since 3.0.6
     * @var Rymera_Stripe $instance object
     */
    protected static $instance;

    /**
     * Register routes.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_routes() {

        /***************************************************************************
         * Register payment plan routes
         ***************************************************************************
         *
         * Here we register our plugin REST routes.
         */
        register_rest_route(
            $this->namespace,
            "/$this->rest_base/connect",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rcs_connect' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
            )
        );

        /***************************************************************************
         * Register Stripe Connect webhook
         ***************************************************************************
         *
         * Register the Stripe Connect webhook endpoint.
         */
        register_rest_route(
            $this->namespace,
            "/$this->rest_base/webhook",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'webhook' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            $this->namespace,
            "/$this->rest_base/account",
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'retrieve_stripe_account' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            "/$this->rest_base/orders/(?P<order_id>\d+)/status",
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'order_invoice_status' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ), // only authenticated users.
            )
        );

        register_rest_route(
            $this->namespace,
            "/$this->rest_base/connect/reset",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'connect_reset' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ), // only authenticated users.
            )
        );

        register_rest_route(
            $this->namespace,
            "/$this->rest_base/connect/mode",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'connect_mode' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ), // only authenticated users.
                'args'                => array(
                    'which' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );
    }

    /**
     * Retrieve Stripe account.
     *
     * @param WP_REST_Request $request The request object.
     *
     * @since 1.0.0
     * @return WP_REST_Response
     */
    public function retrieve_stripe_account( $request ) {

        $response = RCS::instance()->get_stripe_account();

        if ( is_wp_error( $response ) ) {
            if ( str_contains( $response->get_error_message(), 'account does not exist' ) ||
                str_contains( $response->get_error_message(), 'may have been revoked' ) ||
                str_contains( $response->get_error_message(), 'not found' ) ) {
                WPay::save_account_number( '' );
            }

            return $response;
        }

        $data = array();
        if ( WPay::get_account_number() === $response['account_number'] ) {
            $data['message'] = __( 'Stripe account connected!', 'woocommerce-wholesale-payments' );
        }

        return $this->rest_response( $data, $request );
    }

    /**
     * `connect` endpoint handler.
     *
     * @param WP_REST_Request $request REST request object.
     *
     * @since 1.0.0
     * @return WP_REST_Response|WP_Error REST response object or WP_Error object.
     */
    public function rcs_connect( $request ) {

        $response = RCS::instance()->connect();

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $this->rest_response( $response, $request );
    }

    /**
     * Stripe webhook handler.
     *
     * @param WP_REST_Request $request REST request object.
     *
     * @since 1.0.0
     * @return WP_REST_Response|WP_Error REST response object or WP_Error object.
     */
    public function webhook( $request ) {

        $params = $request->get_params();
        $logger = wc_get_logger();
        if ( 'event' !== $params['object'] ) {
            $logger->error(
                'wpay_connect_webhook_invalid_object',
                sprintf(/* translators: %s = Params received */
                    __( 'Invalid object. Params: %s', 'woocommerce-wholesale-payments' ),
                    PHP_EOL . print_r( $params, true )// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
                )
            );

            return new WP_Error( 'wpay_connect_webhook_invalid_object', __( 'Invalid object.', 'woocommerce-wholesale-payments' ) );
        }

        $headers = $request->get_headers();
        if ( empty( $headers['stripe_signature'][0] ) ) {
            $logger->error(
                'wpay_connect_webhook_invalid_signature',
                sprintf(/* translators: %s = Params received */
                    __( 'Invalid signature (empty). Params: %s', 'woocommerce-wholesale-payments' ),
                    PHP_EOL . print_r( $params, true )// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
                )
            );

            return new WP_Error( 'wpay_connect_webhook_invalid_signature', __( 'Invalid signature (empty).', 'woocommerce-wholesale-payments' ) );
        }

        /***************************************************************************
         * Verify webhook signature
         ***************************************************************************
         *
         * We check if this request is from Stripe by verifying the webhook
         * signature.
         */
        $verified = Stripe::instance()->verify_signature( $headers['stripe_signature'][0], $request->get_body() );
        if ( ! $verified ) {
            $logger->error(
                'wpay_connect_webhook_invalid_signature',
                sprintf(/* translators: %s = Params received */
                    __( 'Invalid signature. Params: %s', 'woocommerce-wholesale-payments' ),
                    PHP_EOL . print_r( $params, true )// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
                )
            );

            return new WP_Error( 'wpay_connect_webhook_invalid_signature', __( 'Invalid signature.', 'woocommerce-wholesale-payments' ) );
        }

        /**
         * Check custom webhook event handled
         *
         * We check if this was custom handled elsewhere, otherwise, we handle it.
         *
         * @params array|null $response The response data. Defaults to `null`.
         * @params array $params The webhook event data.
         * @params WP_REST_Request $request The REST request object.
         *
         * @since  1.0.0
         * @return array|null The response data. If `null`, we handle the webhook event.
         */
        $response = apply_filters( 'wpay_connect_webhook_handled', null, $params, $request );
        if ( null === $response && in_array( $params['type'], WPay::get_webhook_events(), true ) ) {
            if ( 'invoice.updated' === $params['type'] ) {
                $updated_invoice = $params['data']['object'];
                $invoice_id      = $updated_invoice['id'];
                if ( empty( $updated_invoice['amounts_due'] ) ) {
                    /***************************************************************************
                     * Re-fetch invoice data from Stripe
                     ***************************************************************************
                     *
                     * We are re-fetching the invoice data from Stripe to make sure we have the
                     * latest data including the `amounts_due` property which is not included
                     * in the returned webhook data above (in `$params['data']['object']`) in
                     * beta version of Stripe Invoice Payments webhook data only.
                     */
                    $updated_invoice = Stripe::instance()->get_invoice( $invoice_id );
                }
                $orders = wc_get_orders(
                    array(
                        'meta_key'     => '_wpay_stripe_invoice_id',
                        'meta_value'   => $invoice_id,
                        'meta_compare' => '=',
                    )
                );
                if ( empty( $orders ) ) {
                    $logger->error(
                        'wpay_connect_webhook_invoice_not_found',
                        sprintf(/* translators: %s = Params received */
                            __( 'Invoice not found. Params: %s', 'woocommerce-wholesale-payments' ),
                            PHP_EOL . print_r( $params, true )// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
                        )
                    );

                    return new WP_Error( 'wpay_connect_webhook_invoice_not_found', __( 'Invoice not found.', 'woocommerce-wholesale-payments' ) );
                }
                $order = $orders[0];
                $order->update_meta_data( '_wpay_stripe_invoice', $updated_invoice );
                $progress = WPay::get_invoice_payment_progress_status( $updated_invoice );
                $order->update_meta_data( '_wpay_stripe_invoice_status', $progress['status'] );
                $order->save_meta_data();
            }

            $response = array(
                'message' => sprintf(/* translators: %s = Stripe event type */
                    __( 'Stripe webhook event "%s" received', 'woocommerce-wholesale-payments' ),
                    $params['type']
                ),
            );
        }

        return $this->rest_response( apply_filters( 'wpay_connect_webhook', $response, $request ), $request );
    }

    /**
     * Refresh order invoice status from Stripe.
     *
     * @param WP_REST_Request $request REST request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function order_invoice_status( $request ) {

        $order_id   = (int) $request->get_param( 'order_id' );
        $order      = wc_get_order( $order_id );
        $invoice_id = $order->get_meta( '_wpay_stripe_invoice_id' );

        if ( empty( $invoice_id ) ) {
            return new WP_Error( 'wpay_stripe_invoice_id_not_found', __( 'Stripe invoice ID not found.', 'woocommerce-wholesale-payments' ) );
        }

        $updated_invoice = Stripe::instance()->get_invoice( $invoice_id );
        if ( is_wp_error( $updated_invoice ) ) {
            return $updated_invoice;
        }
        $order->update_meta_data( '_wpay_stripe_invoice', $updated_invoice );
        $progress = WPay::get_invoice_payment_progress_status( $updated_invoice );
        $order->update_meta_data( '_wpay_stripe_invoice_status', $progress['status'] );
        $order->save_meta_data();

        $extras = array();

        // Get updated amounts due.
        if ( ! empty( $updated_invoice['amounts_due'] ) ) {
            $extras['amounts_due'] = array_map( array( new Payment_Order( $order ), 'map_amount_due' ), $updated_invoice['amounts_due'] );
        }

        // Get updated line items.
        $extras['line_items'] = array(
            'subtotal'         => wc_price( $order->get_subtotal() ),
            'total'            => wc_price( $order->get_total() ),
            'excluding_tax'    => wc_price( $order->get_total() - $order->get_total_tax() ),
            'tax'              => wc_price( $order->get_total_tax() ),
            'amount_paid'      => wc_price( $updated_invoice['amount_paid'] / 100 ),
            'amount_remaining' => wc_price( $updated_invoice['amount_remaining'] / 100 ),
        );

        $response = array(
            'message'  => __( 'Order status refreshed', 'woocommerce-wholesale-payments' ),
            'invoice'  => $updated_invoice,
            'progress' => $progress,
            'extras'   => $extras,
        );

        return $this->rest_response( $response, $request );
    }

    /**
     * Reset Stripe Connect.
     *
     * @param WP_REST_Request $request REST request object.
     *
     * @since 1.0.0
     * @return WP_REST_Response
     */
    public function connect_reset( $request ) {

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

        $data = array(
            'message' => __( 'Connection reset! Please connect a Stripe account.', 'woocommerce-wholesale-payments' ),
        );

        return $this->rest_response( $data, $request );
    }

    /**
     * Update API mode handler.
     *
     * @param WP_REST_Request $request REST request object.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function connect_mode( $request ) {

        $mode = $request->get_param( 'which' );

        if ( ! in_array( $mode, array( 'live', 'test' ), true ) ) {
            return new WP_Error( 'wpay_connect_mode_invalid', __( 'Invalid mode.', 'woocommerce-wholesale-payments' ) );
        }

        WPay::update_api_mode( $mode );

        $response = array(
            'message'      => __( 'API mode updated successfully.', 'woocommerce-wholesale-payments' ),
            'token_in_use' => WPay::get_masked_token(),
        );

        return $this->rest_response( $response, $request );
    }
}
