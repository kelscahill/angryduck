<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WPay
 */

namespace RymeraWebCo\WPay\REST;

use RymeraWebCo\WPay\Abstracts\Abstract_REST;
use RymeraWebCo\WPay\Factories\Payment_Plan as Payment_Plan_Factory;
use RymeraWebCo\WPay\Helpers\Payment_Plans;
use RymeraWebCo\WPay\Traits\Singleton_Trait;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class REST
 *
 * @since   1.0.0
 * @package RymeraWebCo\WPay
 */
class Payment_Plan extends Abstract_REST {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @since 3.0.6
     * @var Payment_Plan $instance object
     */
    protected static $instance;

    /**
     * REST constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
    }

    /**
     * Register the routes for the objects of the controller.
     *
     * @since 1.0.0
     */
    public function register_routes() {

        /***************************************************************************
         * Register payment plans endpoint
         ***************************************************************************
         *
         * Register the payment plans endpoint handler.
         */
        register_rest_route(
            $this->namespace,
            "/$this->rest_base",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'payment_plan' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
            )
        );
        register_rest_route(
            $this->namespace,
            "/$this->rest_base/(?P<id>[\d]+)",
            array(
                'methods'             => 'PUT',
                'callback'            => array( $this, 'post_payment_plan' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
            )
        );
        register_rest_route(
            $this->namespace,
            "/$this->rest_base/(?P<id>[\d]+)",
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'payment_plan' ),
                'permission_callback' => array( $this, 'delete_item_permissions_check' ),
            )
        );
        register_rest_route(
            $this->namespace,
            "/$this->rest_base/(?P<id>[\d]+)",
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'payment_plan' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
            )
        );
        register_rest_route(
            $this->namespace,
            "/{$this->rest_base}s",
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'payment_plans' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
            )
        );
    }

    /**
     * `payment-plans` endpoint handler.
     *
     * @param WP_REST_Request $request REST request object.
     *
     * @return WP_REST_Response|WP_Error REST response object or WP_Error object.
     */
    public function payment_plans( $request ) {

        [
            'page' => $page,
        ] = wp_parse_args(
            $request->get_params(),
            array(
                'page' => 1,
            )
        );

        $plans = Payment_Plans::get_payment_plans( $request->get_params() );

        $total       = $plans->found_posts;
        $total_pages = $plans->max_num_pages;
        $data        = $plans->posts;

        $response = array(
            'total'       => absint( $total ),
            'totalPages'  => absint( $total_pages ),
            'currentPage' => absint( $page ),
            'data'        => $data,
        );

        return $this->rest_response( $response, $request );
    }

    /**
     * `payment-plan` endpoint handler.
     *
     * @param WP_REST_Request $request REST request object.
     *
     * @return WP_REST_Response|WP_Error REST response object or WP_Error object.
     */
    public function payment_plan( $request ) {

        $http_method = $request->get_method();
        $method      = strtolower( $http_method ) . '_payment_plan';
        $methods     = explode( ',', WP_REST_Server::ALLMETHODS );
        $methods     = array_map( 'trim', $methods );

        if ( ! in_array( $http_method, $methods, true ) || ! method_exists( $this, $method ) ) {
            return new WP_Error( 'unknown_method', __( 'Unknown method.', 'woocommerce-wholesale-payments' ), array( 'status' => 405 ) );
        }

        return $this->$method( $request );
    }

    /**
     * Get payment plans.
     *
     * @param WP_REST_Request $request REST request object.
     *
     * @return WP_REST_Response|WP_Error REST response object or WP_Error object.
     */
    public function get_payment_plan( $request ) {

        $plan = Payment_Plan_Factory::get_instance( $request->get_param( 'id' ) );

        if ( ! $request->get_param( 'id' ) || ! $plan ) {
            return new WP_Error( 'invalid_id', __( 'Invalid payment plan ID.', 'woocommerce-wholesale-payments' ), array( 'status' => 404 ) );
        }

        return $this->rest_response( $plan, $request );
    }

    /**
     * Create a new payment plan.
     *
     * @param WP_REST_Request $request REST request object.
     *
     * @return WP_REST_Response|WP_Error REST response object or WP_Error object.
     */
    public function post_payment_plan( $request ) {

        $id         = (int) $request->get_param( 'id' );
        $set_status = $request->get_param( 'set_status' );
        $enabled    = $request->get_param( 'enabled' );

        if ( 'no' === $set_status ) {
            $title              = $request->get_param( 'title' );
            $description        = $request->get_param( 'description' );
            $breakdown          = $request->get_param( 'breakdown' );
            $wholesale_roles    = $request->get_param( 'wholesale_roles' );
            $allowed_users      = $request->get_param( 'allowed_users' );
            $apply_restrictions = $request->get_param( 'apply_restrictions' );
            $apply_auto_charge  = $request->get_param( 'apply_auto_charge' );

            if ( empty( $title ) ) {
                return new WP_Error( 'invalid_title', __( 'Invalid payment plan title.', 'woocommerce-wholesale-payments' ), array( 'status' => 400 ) );
            }

            if ( empty( $breakdown ) ) {
                return new WP_Error( 'invalid_breakdown', __( 'Invalid payment plan breakdown.', 'woocommerce-wholesale-payments' ), array( 'status' => 400 ) );
            }

            $plan = Payment_Plan_Factory::save(
                array(
                    'ID'                 => $id,
                    'post_title'         => $title,
                    'post_content'       => $description,
                    'breakdown'          => $breakdown,
                    'enabled'            => $enabled,
                    'wholesale_roles'    => $wholesale_roles,
                    'allowed_users'      => $allowed_users,
                    'apply_restrictions' => $apply_restrictions,
                    'apply_auto_charge'  => $apply_auto_charge,
                )
            );

            if ( is_wp_error( $plan ) ) {
                return $plan;
            } elseif ( ! $plan ) {
                return new WP_Error( 'invalid_plan', __( 'Invalid payment plan.', 'woocommerce-wholesale-payments' ), array( 'status' => 400 ) );
            }

            $response = array(
                'message' => $id
                    ? __( 'Payment plan updated successfully.', 'woocommerce-wholesale-payments' )
                    : __( 'Payment plan created successfully.', 'woocommerce-wholesale-payments' ),
                'plan'    => $plan,
            );
        } else {
            if ( ! $id ) {
                return new WP_Error( 'invalid_id', __( 'Invalid payment plan ID.', 'woocommerce-wholesale-payments' ), array( 'status' => 404 ) );
            }

            $plan = Payment_Plan_Factory::set_plan_status(
                array(
                    'ID'      => $id,
                    'enabled' => $enabled,
                )
            );

            $response = array(
                'message' => __( 'Payment plan updated successfully.', 'woocommerce-wholesale-payments' ),
                'plan'    => $plan,
            );
        }

        return $this->rest_response( $response, $request );
    }

    /**
     * Delete a payment plan.
     *
     * @param WP_REST_Request $request REST request object.
     *
     * @return WP_REST_Response|WP_Error REST response object or WP_Error object.
     */
    public function delete_payment_plan( $request ) {

        $plan = Payment_Plan_Factory::get_instance( $request->get_param( 'id' ) );

        if ( ! $request->get_param( 'id' ) || ! $plan ) {
            return new WP_Error( 'invalid_id', __( 'Invalid payment plan ID.', 'woocommerce-wholesale-payments' ), array( 'status' => 404 ) );
        }

        $plan->delete();

        return $this->rest_response( array( 'message' => __( 'Payment plan deleted successfully.', 'woocommerce-wholesale-payments' ) ), $request );
    }
}
