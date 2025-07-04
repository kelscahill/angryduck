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
use RymeraWebCo\WPay\Helpers\WPAY_Invoices;
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

        /***************************************************************************
         * Register payment plan routes
         ***************************************************************************
         *
         * Here we register our plugin REST routes.
         */
        register_rest_route(
            $this->namespace,
            "/$this->rest_base/(?P<order_id>\d+)/pay",
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'pay' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ), // only authenticated users.
            )
        );
        register_rest_route(
            $this->namespace,
            "/$this->rest_base/create-invoice-payment-item",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_invoice_payment_item' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
            )
        );
        register_rest_route(
            $this->namespace,
            "/$this->rest_base/create-invoice-payment",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_invoice_payment' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
            )
        );
        register_rest_route(
            $this->namespace,
            "/$this->rest_base/finalize-invoice-payment",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'finalize_invoice_payment' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
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
        if ( empty( $params ) ) {
            return new WP_Error( 'wpay_connect_webhook_empty', __( 'Empty params.', 'woocommerce-wholesale-payments' ) );
        }

        $logger = wc_get_logger();
        if ( 'event' !== $params['object'] ) {
            $logger->error(
                sprintf(/* translators: %s = Params received */
                    __( 'Invalid object. Params: %s', 'woocommerce-wholesale-payments' ),
                    PHP_EOL . print_r( $params, true )// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
                ),
                array( 'source' => 'wpay_connect_webhook' )
            );

            return new WP_Error( 'wpay_connect_webhook_invalid_object', __( 'Invalid object.', 'woocommerce-wholesale-payments' ) );
        }

        $headers = $request->get_headers();
        if ( empty( $headers['stripe_signature'][0] ) ) {
            $logger->error(
                sprintf(/* translators: %s = Params received */
                    __( 'Invalid signature (empty). Params: %s', 'woocommerce-wholesale-payments' ),
                    PHP_EOL . print_r( $params, true )// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
                ),
                array( 'source' => 'wpay_connect_webhook' )
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
                sprintf(/* translators: %s = Params received */
                    __( 'Invalid signature. Params: %s', 'woocommerce-wholesale-payments' ),
                    PHP_EOL . print_r( $params, true )// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
                ),
                array( 'source' => 'wpay_connect_webhook' )
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
                        sprintf(/* translators: %s = Params received */
                            __( 'Invoice not found. Params: %s', 'woocommerce-wholesale-payments' ),
                            PHP_EOL . print_r( $params, true )// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
                        ),
                        array( 'source' => 'wpay_connect_webhook' )
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

        $updated_invoice_data = array();
        $status               = $order->get_meta( '_wpay_stripe_invoice_status' );
        $stripe_invoices      = array();

        // Check if invoice is auto charge.
        $auto_charge = $order->get_meta( '_wpay_auto_charge' );
        if ( 'yes' === $auto_charge ) {
            foreach ( $invoice_id as $invoice_item ) {
                $item_invoice_id = $invoice_item;

                $updated_invoice = Stripe::instance()->get_invoice( $item_invoice_id );
                if ( is_wp_error( $updated_invoice ) ) {
                    return $updated_invoice;
                } else {
                    $updated_invoice_data[] = $updated_invoice;

                    // Update invoice in WPAY.
                    WPAY_Invoices::update_invoice_status( $order_id, $item_invoice_id, $updated_invoice['status'] );
                }
            }

            // Get all invoices from WPAY.
            $statusses       = array();
            $stripe_invoices = WPAY_Invoices::get_invoices( $order_id );
            if ( ! empty( $stripe_invoices ) ) {
                foreach ( $stripe_invoices as $invoice ) {
                    $statusses[] = ! empty( $invoice->stripe_invoice_status ) ? $invoice->stripe_invoice_status : 'pending';
                }
            }

            $status = ! empty( $statusses ) && is_array( $statusses ) ? end( $statusses ) : $status;
        } else {
            $updated_invoice = Stripe::instance()->get_invoice( $invoice_id );
            if ( is_wp_error( $updated_invoice ) ) {
                return $updated_invoice;
            } else {
                $updated_invoice_data = $updated_invoice;

                $progress = WPay::get_invoice_payment_progress_status( $updated_invoice );
                $status   = $progress['status'];
            }
        }

        if ( empty( $updated_invoice_data ) ) {
            return new WP_Error( 'wpay_stripe_invoice_not_found', __( 'Stripe invoice not found.', 'woocommerce-wholesale-payments' ) );
        }

        $order->update_meta_data( '_wpay_stripe_invoice', $updated_invoice_data );
        $order->update_meta_data( '_wpay_stripe_invoice_status', $status );

        $order->save_meta_data();

        $extras           = array();
        $amount_paid      = 0;
        $amount_remaining = 0;
        $order_invoices   = array();

        // If not auto charge.
        if ( 'yes' !== $auto_charge ) {
            // Get updated amounts due.
            if ( ! empty( $updated_invoice_data['amounts_due'] ) ) {
                $extras['amounts_due'] = array_map( array( new Payment_Order( $order ), 'map_amount_due' ), $updated_invoice_data['amounts_due'] );
            }

            $amount_paid      = $updated_invoice_data['amount_paid'];
            $amount_remaining = $updated_invoice_data['amount_remaining'];
        } else {
            $order_invoices = $updated_invoice_data;
        }

        $updated_invoice['order_invoices'] = array_map( array( WPAY_Invoices::class, 'map_invoice' ), $stripe_invoices );
        $updated_invoice['auto_charge']    = $auto_charge;
        $updated_invoice['order_link']     = get_edit_post_link( $order->get_id() );
        $updated_invoice['order_currency'] = array(
            'code'   => $order->get_currency(),
            'symbol' => get_woocommerce_currency_symbol( $order->get_currency() ),
        );

        // Get updated line items.
        $extras['line_items'] = array(
            'subtotal'         => wc_price( $order->get_subtotal() ),
            'total'            => wc_price( $order->get_total() ),
            'excluding_tax'    => wc_price( $order->get_total() - $order->get_total_tax() ),
            'tax'              => wc_price( $order->get_total_tax() ),
            'amount_paid'      => wc_price( $amount_paid / 100 ),
            'amount_remaining' => wc_price( $amount_remaining / 100 ),
        );

        $response = array(
            'message'  => __( 'Order status refreshed', 'woocommerce-wholesale-payments' ),
            'invoice'  => $updated_invoice,
            'progress' => $status,
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

    /**
     * Pay invoice.
     *
     * @param WP_REST_Request $request REST request object.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function pay( $request ) {

        $order_id = (int) $request->get_param( 'order_id' );

        if ( empty( $order_id ) ) {
            return new WP_Error( 'wpay_order_id_not_found', __( 'Order ID not found.', 'woocommerce-wholesale-payments' ) );
        }

        $order      = wc_get_order( $order_id );
        $invoice_id = $order->get_meta( '_wpay_stripe_invoice_id' );

        if ( empty( $invoice_id ) ) {
            return new WP_Error( 'wpay_stripe_invoice_id_not_found', __( 'Stripe invoice ID not found.', 'woocommerce-wholesale-payments' ) );
        }

        $updated_invoice_data = array();
        $status               = $order->get_meta( '_wpay_stripe_invoice_status' );
        $stripe_invoices      = array();

        // Check if invoice is auto charge.
        $auto_charge = $order->get_meta( '_wpay_auto_charge' );
        if ( 'yes' === $auto_charge ) {
            // Get all invoices from WPAY.
            $statusses       = array();
            $stripe_invoices = WPAY_Invoices::get_invoices( $order_id );
            if ( ! empty( $stripe_invoices ) ) {
                foreach ( $stripe_invoices as $invoice ) {
                    $item_invoice_id = $invoice->invoice_id;

                    if ( ! empty( $item_invoice_id ) && 'paid' !== $invoice->stripe_invoice_status ) {
                        $updated_invoice = Stripe::instance()->pay_invoice( $item_invoice_id, 'true' );
                        if ( is_wp_error( $updated_invoice ) ) {
                            return $updated_invoice;
                        } else {
                            $updated_invoice_data[] = $updated_invoice;
                            $statusses[]            = $updated_invoice['status'];

                            // Update invoice in WPAY.
                            WPAY_Invoices::update_invoice_status( $order_id, $item_invoice_id, $updated_invoice['status'] );
                        }
                    } else {
                        $statusses[] = ! empty( $invoice->stripe_invoice_status ) ? $invoice->stripe_invoice_status : 'pending';
                    }
                }
            }

            $status = ! empty( $statusses ) && is_array( $statusses ) ? end( $statusses ) : $status;
        } else {
            $updated_invoice = Stripe::instance()->pay_invoice( $invoice_id, 'true' );
            if ( is_wp_error( $updated_invoice ) ) {
                return $updated_invoice;
            } else {
                $updated_invoice_data = $updated_invoice;

                $progress = WPay::get_invoice_payment_progress_status( $updated_invoice );
                $status   = $progress['status'];
            }
        }

        if ( empty( $updated_invoice_data ) ) {
            return new WP_Error( 'wpay_stripe_invoice_not_found', __( 'Stripe invoice not found.', 'woocommerce-wholesale-payments' ) );
        }

        $order->update_meta_data( '_wpay_stripe_invoice', $updated_invoice_data );
        $order->update_meta_data( '_wpay_stripe_invoice_status', $status );

        $order->save_meta_data();

        $extras           = array();
        $amount_paid      = 0;
        $amount_remaining = 0;
        $order_invoices   = array();

        // If not auto charge.
        if ( 'yes' !== $auto_charge ) {
            // Get updated amounts due.
            if ( ! empty( $updated_invoice_data['amounts_due'] ) ) {
                $extras['amounts_due'] = array_map( array( new Payment_Order( $order ), 'map_amount_due' ), $updated_invoice_data['amounts_due'] );
            }

            $amount_paid      = $updated_invoice_data['amount_paid'];
            $amount_remaining = $updated_invoice_data['amount_remaining'];
        } else {
            $order_invoices = $updated_invoice_data;
        }

        $updated_invoice['order_invoices'] = array_map( array( WPAY_Invoices::class, 'map_invoice' ), $stripe_invoices );
        $updated_invoice['auto_charge']    = $auto_charge;
        $updated_invoice['order_link']     = get_edit_post_link( $order->get_id() );
        $updated_invoice['order_currency'] = array(
            'code'   => $order->get_currency(),
            'symbol' => get_woocommerce_currency_symbol( $order->get_currency() ),
        );

        // Get updated line items.
        $extras['line_items'] = array(
            'subtotal'         => wc_price( $order->get_subtotal() ),
            'total'            => wc_price( $order->get_total() ),
            'excluding_tax'    => wc_price( $order->get_total() - $order->get_total_tax() ),
            'tax'              => wc_price( $order->get_total_tax() ),
            'amount_paid'      => wc_price( $amount_paid / 100 ),
            'amount_remaining' => wc_price( $amount_remaining / 100 ),
        );

        $response = array(
            'message'  => __( 'Order paid successfully', 'woocommerce-wholesale-payments' ),
            'invoice'  => $updated_invoice,
            'progress' => $status,
            'extras'   => $extras,
        );

        return $this->rest_response( $response, $request );
    }

    /**
     * Create invoice payment item.
     *
     * @param WP_REST_Request $request REST request object.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function create_invoice_payment_item( $request ) {
        $customer_id = $request->get_param( 'customer_id' );
        $invoice_id  = $request->get_param( 'invoice_id' );
        $amount      = $request->get_param( 'amount' );

        $response = Stripe::instance()->add_invoice_item( $customer_id, $invoice_id, $amount );
        return $this->rest_response( $response, $request );
    }

    /**
     * Create invoice payment.
     *
     * @param WP_REST_Request $request REST request object.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function create_invoice_payment( $request ) {
        $customer_id = $request->get_param( 'customer_id' );
        $amounts_due = $request->get_param( 'amounts_due' );

        $response = Stripe::instance()->create_invoice( $customer_id, $amounts_due );
        if ( ! is_wp_error( $response ) ) {
            $invoice_id = $response['id'];
            $response   = array(
                'message'    => __( 'Invoice created successfully.', 'woocommerce-wholesale-payments' ),
                'invoice_id' => $invoice_id,
            );
        }
        return $this->rest_response( $response, $request );
    }

    /**
     * Finalize invoice payment.
     *
     * @param WP_REST_Request $request REST request object.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function finalize_invoice_payment( $request ) {

        $response = Stripe::instance()->finalize_invoice( $request->get_param( 'invoice_id' ) );
        return $this->rest_response( $response, $request );
    }
}
