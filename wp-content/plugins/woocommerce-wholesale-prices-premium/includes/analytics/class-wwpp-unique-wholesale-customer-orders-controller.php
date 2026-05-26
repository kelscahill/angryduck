<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Automattic\WooCommerce\Utilities\OrderUtil;

if ( ! class_exists( 'WWPP_Unique_Wholesale_Customer_Orders_Controller' ) ) {

    /**
     * Unique Wholesale Customer Orders Api containing customer-related analytics response.
     *
     * @since 2.0.8.2
     */
    class WWPP_Unique_Wholesale_Customer_Orders_Controller extends WWPP_Sales_Controller {

        /**
         * Namespace of the api
         *
         * @var string
         */
        private $namespace = 'wholesale/v1/analytics';


        /**
         * Api base name
         *
         * @var string
         */
        private $rest_base = 'customers';


        /**
         * Instantiate the class
         */
        public function __construct() {
            $this->require_api_controllers();
        }

        /**
         * Register routes for 'Unique Wholesale Customer Orders' api.
         *
         * @return void
         */
        public function run() {
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base,
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_customers' ),
                    'permission_callback' => array( $this, 'get_customers_permission_callback' ),
                )
            );
        }

        /**
         * Payload for 'customers' api.
         *
         * @param \WP_REST_Request $req The request payload.
         * @return array
         */
        public function get_customers( \WP_REST_Request $req ) {

            $query_parameters = $req->get_query_params() ?? array();

            $role                = $this->get_current_user_role( $query_parameters );
            $dates               = $this->get_requested_dates( $query_parameters );
            $current_start_date  = $dates['current_start_date'];
            $current_end_date    = $dates['current_end_date'];
            $previous_start_date = $dates['previous_start_date'];
            $previous_end_date   = $dates['previous_end_date'];

            $cache_key_prefix = 'wwpp_reports_unique_wholesale_customer_orders_';
            $cache_key        = $cache_key_prefix . hash( 'sha256', wp_json_encode( $query_parameters ) );

            // check if the value exists, based on the cache key.
            $response = get_transient( $cache_key );

            if ( false !== $response ) {
                return rest_ensure_response( $response );
            }

            try {
                global $wpdb;
                $hpos     = OrderUtil::custom_orders_table_usage_is_enabled();
                $response = Unique_Wholesale_Customer_Orders::instance();
                $response = $response->get_unique_customer_orders_summary( $wpdb->prefix, $role, $hpos, $current_start_date, $current_end_date, $previous_start_date, $previous_end_date );

                if ( ! is_wp_error( $response ) ) {
                    set_transient( $cache_key, $response, HOUR_IN_SECONDS );
                }

                return rest_ensure_response( $response );
            } catch ( \Exception $e ) {
                wc_get_logger()->error( $e->getMessage(), array( 'source' => 'wwpp-analytics' ) );
                return new \WP_Error( 'wwpp_api_error', __( 'An internal server error occurred.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 500 ) );
            }
        }

        /**
         * Get the 'API' controllers.
         *
         * @return void
         */
        public function require_api_controllers() {
            require_once WWPP_INCLUDES_PATH . 'analytics/unique-wholesale-customer-orders/class-unique-wholesale-customer-orders.php';
        }

        /**
         * Permission callback for the customers REST API endpoint.
         * Determines whether the current request is allowed to access the unique wholesale customer orders analytics.
         *
         * @since 2.0.8.2
         * @return bool True if the request has permission, false otherwise.
         */
        public function get_customers_permission_callback() {
            return current_user_can( 'manage_woocommerce' );
        }
    }
}
