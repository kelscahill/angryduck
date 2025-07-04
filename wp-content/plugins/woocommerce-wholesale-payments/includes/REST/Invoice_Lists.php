<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WPay
 */

namespace RymeraWebCo\WPay\REST;

use RymeraWebCo\WPay\Abstracts\Abstract_REST;
use RymeraWebCo\WPay\Helpers\Payment_Orders;
use RymeraWebCo\WPay\Traits\Singleton_Trait;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class REST
 *
 * @since   1.0.2
 * @package RymeraWebCo\WPay
 */
class Invoice_Lists extends Abstract_REST {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @since 1.0.2
     * @var Invoice_Lists $instance object
     */
    protected static $instance;

    /**
     * Register the routes for the objects of the controller.
     *
     * @since 1.0.2
     */
    public function register_routes() {

        /***************************************************************************
         * Register invoice lists endpoint
         ***************************************************************************
         *
         * Register the invoice lists endpoint handler.
         */
        register_rest_route(
            $this->namespace,
            "/$this->rest_base/invoice/(?P<id>[\d]+)",
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'invoice_item' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
            )
        );
        register_rest_route(
            $this->namespace,
            "/{$this->rest_base}s",
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'invoice_lists' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
            )
        );
    }

    /**
     * `invoice-lists` endpoint handler.
     *
     * @param WP_REST_Request $request REST request object.
     *
     * @return WP_REST_Response|WP_Error REST response object or WP_Error object.
     */
    public function invoice_lists( $request ) {

        [
            'page' => $page,
        ] = wp_parse_args(
            $request->get_params(),
            array(
                'page' => 1,
            )
        );

        $invoices = Payment_Orders::get_orders( $request->get_params() );

        $total       = $invoices['total'];
        $total_pages = $invoices['totalPages'];
        $data        = $invoices['data'];

        $response = array(
            'total'       => absint( $total ),
            'totalPages'  => absint( $total_pages ),
            'currentPage' => absint( $page ),
            'data'        => $data,
            'statuses'    => Payment_Orders::get_statuses(),
            'customers'   => Payment_Orders::get_customers( $request->get_params() ),
        );

        return $this->rest_response( $response, $request );
    }

    /**
     * `invoice-item` endpoint handler.
     *
     * @param WP_REST_Request $request REST request object.
     *
     * @return WP_REST_Response|WP_Error REST response object or WP_Error object.
     */
    public function invoice_item( $request ) {

        $plan = Payment_Plan_Factory::get_instance( $request->get_param( 'id' ) );

        if ( ! $request->get_param( 'id' ) || ! $plan ) {
            return new WP_Error( 'invalid_id', __( 'Invalid invoice ID.', 'woocommerce-wholesale-payments' ), array( 'status' => 404 ) );
        }

        return $this->rest_response( $plan, $request );
    }
}
