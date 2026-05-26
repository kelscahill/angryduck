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
     * @since 3.0.6 Register customer payment methods and setup intents routes.
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

        /***************************************************************************
         * Register customer payment methods and setup intents routes
         ***************************************************************************
         *
         * Here we register our plugin REST routes.
         */
        register_rest_route(
            $this->namespace,
            "/$this->rest_base/customer/payment-methods",
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'customer_payment_methods' ),
                    'permission_callback' => array( $this, 'customer_permissions_check' ),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_customer_payment_methods' ),
                    'permission_callback' => array( $this, 'customer_permissions_check' ),
                ),
            )
        );
        register_rest_route(
            $this->namespace,
            "/$this->rest_base/customers/(?P<customer_id>[^\/]+)/setup-intent",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_customer_setup_intent' ),
                'permission_callback' => array( $this, 'customer_permissions_check' ),
            )
        );
        register_rest_route(
            $this->namespace,
            "/$this->rest_base/customers/(?P<customer_id>[^\/]+)/attach-payment-method",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'attach_customer_payment_method' ),
                'permission_callback' => array( $this, 'customer_permissions_check' ),
            )
        );
        register_rest_route(
            $this->namespace,
            "/$this->rest_base/customers/(?P<customer_id>[^\/]+)/sync-payment-methods",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'sync_customer_payment_methods' ),
                'permission_callback' => array( $this, 'customer_permissions_check' ),
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

    /**
     * Return current user's Stripe customer payment method stats.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function customer_payment_methods( $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'wpay_auth_required', __( 'Authentication required. You must be logged in to perform this action.', 'woocommerce-wholesale-payments' ), array( 'status' => 401 ) );
        }

        $customer_id = get_user_meta( $user_id, '_wpay_stripe_customer_id', true );
        if ( empty( $customer_id ) ) {
            $user     = get_userdata( $user_id );
            $searched = Stripe::instance()->search_customer( array( 'email:' => $user->user_email ) );
            if ( is_array( $searched ) && ! empty( $searched['data'][0]['id'] ) ) {
                $customer_id = $searched['data'][0]['id'];
                update_user_meta( $user_id, '_wpay_stripe_customer_id', $customer_id );
            } else {
                // Create a Stripe customer on demand for saving PM.
                $created = Stripe::instance()->create_customer( $user->display_name, $user->user_email );
                if ( ! is_wp_error( $created ) && ! empty( $created['id'] ) ) {
                    $customer_id = $created['id'];
                    update_user_meta( $user_id, '_wpay_stripe_customer_id', $customer_id );
                }
            }
        }

        $has_default = false;
        $count       = 0;
        if ( $customer_id ) {
            $default_pm  = Stripe::instance()->customer_has_default_payment_method( $customer_id, $user_id );
            $has_default = ! is_wp_error( $default_pm ) && ! empty( $default_pm );
            $methods     = Stripe::instance()->list_payment_methods( $customer_id, 'card' );
            if ( ! is_wp_error( $methods ) && isset( $methods['data'] ) ) {
                $count = count( $methods['data'] );
            }
        }

        return $this->rest_response(
            array(
                'customer_id'  => $customer_id,
                'hasDefault'   => $has_default,
                'methodsCount' => $count,
                'publishable'  => WPay::get_token( 'publishable_key' ),
                'mode'         => WPay::get_api_mode(),
            ),
            $request
        );
    }

    /**
     * Delete all payment methods for the current customer.
     *
     * @param WP_REST_Request $request The request object.
     *
     * @since 1.0.0
     * @return WP_REST_Response|WP_Error
     */
    public function delete_customer_payment_methods( $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'wpay_auth_required', __( 'Authentication required. You must be logged in to perform this action.', 'woocommerce-wholesale-payments' ), array( 'status' => 401 ) );
        }

        // Get customer ID from user meta.
        $customer_id = get_user_meta( $user_id, '_wpay_stripe_customer_id', true );

        if ( empty( $customer_id ) ) {
            // No customer ID means no payment methods to delete.
            return $this->rest_response(
                array(
                    'success'        => true,
                    'message'        => __( 'No payment methods found to delete.', 'woocommerce-wholesale-payments' ),
                    'detached_count' => 0,
                ),
                $request
            );
        }

        // Detach all payment methods from Stripe and WooCommerce.
        $result = Stripe::instance()->detach_all_payment_methods( $customer_id, $user_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Clear payment method related user meta, but keep the customer ID.
        // The customer ID should only be deleted if the Stripe customer itself is deleted,
        // not when payment methods are detached. This prevents creating orphaned Stripe customers.
        delete_user_meta( $user_id, '_wpay_default_payment_method' );
        delete_user_meta( $user_id, '_stripe_default_payment_method' );
        // Note: _wpay_stripe_customer_id and _stripe_customer_id are intentionally NOT deleted
        // to maintain the relationship between WordPress users and Stripe customers.

        return $this->rest_response(
            array(
                'success'        => true,
                'message'        => sprintf(
                    /* translators: %d: number of payment methods detached */
                    __( 'Successfully deleted %d payment method(s).', 'woocommerce-wholesale-payments' ),
                    $result['detached_count']
                ),
                'detached_count' => $result['detached_count'],
            ),
            $request
        );
    }

    /**
     * Create SetupIntent for a Stripe customer.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function create_customer_setup_intent( $request ) {
        $customer_id = sanitize_text_field( $request->get_param( 'customer_id' ) );
        if ( empty( $customer_id ) ) {
            return new WP_Error( 'wpay_stripe_customer_id_not_found', __( 'Stripe customer ID not found.', 'woocommerce-wholesale-payments' ) );
        }

        // Verify customer ownership.
        $ownership_check = $this->verify_customer_ownership( $customer_id );
        if ( is_wp_error( $ownership_check ) ) {
            return $ownership_check;
        }

        $intent = Stripe::instance()->create_setup_intent( $customer_id, array( 'card' ) );
        return $this->rest_response( $intent, $request );
    }

    /**
     * Attach a payment method to Stripe customer and set default.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function attach_customer_payment_method( $request ) {
        $customer_id    = sanitize_text_field( $request->get_param( 'customer_id' ) );
        $payment_method = sanitize_text_field( $request->get_param( 'payment_method' ) );
        if ( empty( $payment_method ) ) {
            $payment_method = sanitize_text_field( $request->get_param( 'payment_method_id' ) );
        }
        if ( empty( $customer_id ) || empty( $payment_method ) ) {
            return new WP_Error( 'wpay_stripe_invalid_params', __( 'Invalid Stripe customer or payment method.', 'woocommerce-wholesale-payments' ) );
        }

        // Verify customer ownership.
        $ownership_check = $this->verify_customer_ownership( $customer_id );
        if ( is_wp_error( $ownership_check ) ) {
            return $ownership_check;
        }

        // Get current user ID for token creation.
        $user_id = get_current_user_id();
        $result  = Stripe::instance()->attach_payment_method_to_customer( $customer_id, $payment_method, $user_id );

        // If attachment succeeded but token creation failed, attempt sync.
        if ( ! is_wp_error( $result ) && ! empty( $user_id ) ) {
            if ( ! isset( $result['wc_token_id'] ) || empty( $result['wc_token_id'] ) ) {
                $sync_result = Stripe::instance()->sync_payment_methods_to_woocommerce( $customer_id, $user_id );
                if ( ! is_wp_error( $sync_result ) ) {
                    // Update result with sync information.
                    $result['sync_result'] = $sync_result;
                }
            }
        }

        return $this->rest_response( $result, $request );
    }

    /**
     * Synchronize payment methods between Stripe and WooCommerce.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function sync_customer_payment_methods( $request ) {
        $customer_id = sanitize_text_field( $request->get_param( 'customer_id' ) );
        $user_id     = get_current_user_id();

        if ( empty( $customer_id ) ) {
            return new WP_Error( 'wpay_stripe_customer_id_not_found', __( 'Stripe customer ID not found.', 'woocommerce-wholesale-payments' ) );
        }

        // Verify customer ownership.
        $ownership_check = $this->verify_customer_ownership( $customer_id );
        if ( is_wp_error( $ownership_check ) ) {
            return $ownership_check;
        }

        $result = Stripe::instance()->sync_payment_methods_to_woocommerce( $customer_id, $user_id );
        return $this->rest_response( $result, $request );
    }

    /**
     * Verify that the customer_id belongs to the current user.
     *
     * @param string $customer_id Stripe customer ID.
     *
     * @since 1.0.0
     * @return true|WP_Error True if ownership is verified, WP_Error otherwise.
     */
    private function verify_customer_ownership( $customer_id ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error(
                'wpay_auth_required',
                __( 'Authentication required.', 'woocommerce-wholesale-payments' ),
                array( 'status' => 401 )
            );
        }

        // Get the customer ID stored in user meta.
        $user_customer_id = get_user_meta( $user_id, '_wpay_stripe_customer_id', true );

        // If no customer ID is stored yet, verify ownership by checking Stripe customer email.
        if ( empty( $user_customer_id ) ) {
            // Fetch the customer from Stripe to verify ownership.
            $stripe_customer = Stripe::instance()->get_customer( $customer_id );
            if ( is_wp_error( $stripe_customer ) ) {
                return new WP_Error(
                    'wpay_customer_not_found',
                    __( 'Stripe customer not found.', 'woocommerce-wholesale-payments' ),
                    array( 'status' => 404 )
                );
            }

            // Get the current user's email.
            $user = get_userdata( $user_id );
            if ( ! $user ) {
                return new WP_Error(
                    'wpay_user_not_found',
                    __( 'User not found.', 'woocommerce-wholesale-payments' ),
                    array( 'status' => 404 )
                );
            }

            $user_email = $user->user_email;

            // Verify the Stripe customer email matches the user's email.
            if ( empty( $stripe_customer['email'] ) || $stripe_customer['email'] !== $user_email ) {
                return new WP_Error(
                    'wpay_customer_ownership_failed',
                    __( 'You do not have permission to access this customer account.', 'woocommerce-wholesale-payments' ),
                    array( 'status' => 403 )
                );
            }

            // Customer ownership verified - store the customer ID for future requests.
            update_user_meta( $user_id, '_wpay_stripe_customer_id', $customer_id );

            return true;
        }

        // Verify the customer_id matches the user's stored customer_id.
        if ( $customer_id !== $user_customer_id ) {
            return new WP_Error(
                'wpay_customer_ownership_failed',
                __( 'You do not have permission to access this customer account.', 'woocommerce-wholesale-payments' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Checks if a given request has access to get a specific item.
     * Overrides parent to require admin privileges for sensitive endpoints like Stripe account.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 1.0.0
     * @return WP_Error|bool True if the request has read access for the item, WP_Error object otherwise.
     */
    public function get_item_permissions_check( $request ) {
        // Require admin privileges for Stripe account endpoint (sensitive merchant data).
        $route = $request->get_route();
        if ( strpos( $route, '/account' ) !== false ) {
            return current_user_can( 'manage_woocommerce' );
        }

        // Use parent's default permission check for other endpoints.
        return parent::get_item_permissions_check( $request );
    }

    /**
     * Checks if a given request has access to customer payment method operations.
     * Allows authenticated customers to manage their own payment methods.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 1.0.0
     * @return WP_Error|bool True if the request has access, WP_Error object otherwise.
     */
    public function customer_permissions_check( $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error(
                'wpay_auth_required',
                __( 'Authentication required. You must be logged in to perform this action.', 'woocommerce-wholesale-payments' ),
                array( 'status' => 401 )
            );
        }

        // Allow authenticated customers to manage their payment methods.
        return true;
    }
}
