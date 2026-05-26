<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Automattic\WooCommerce\Utilities\OrderUtil;

if ( ! class_exists( 'WWPP_Category_Performance_Api_Controller' ) ) {

    /**
     * Category Performance Api Controller containing category-related analytics response.
     *
     * @since 2.0.8.2
     */
    class WWPP_Category_Performance_Api_Controller extends WWPP_Sales_Controller {

        /**
         * Supported order_by columns for category performance sorting.
         */
        const CATEGORY_SUPPORTED_ORDER_BY = array( 'category_id', 'category_name', 'total_category_wholesale_revenue' );

        /**
         * Namespace of the api.
         *
         * @var string
         */
        private $namespace = 'wholesale/v1/analytics';

        /**
         * Api base name.
         *
         * @var string
         */
        private $rest_base = 'category-performance';

        /**
         * Instantiate the class.
         *
         * @return void
         */
        public function __construct() {
            $this->require_api_controllers();
        }

        /**
         * Register routes for 'Category Performance' api.
         *
         * @return void
         */
        public function run() {
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base,
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_category_performance' ),
                    'permission_callback' => array( $this, 'get_category_performance_permission_callback' ),
                )
            );
        }

        /**
         * Payload for 'category-performance' api.
         *
         * @param \WP_REST_Request $req The request payload.
         * @return array|\WP_REST_Response|\WP_Error
         */
        public function get_category_performance( \WP_REST_Request $req ) {
            $query_parameters = $req->get_query_params() ?? array();
            $role             = $this->get_current_user_role( $query_parameters );
            $dates            = $this->get_requested_dates( $query_parameters );
            $pagination       = $this->get_pagination_params( $query_parameters, 0, 'total_category_wholesale_revenue', 'DESC' );
            $output_format    = $this->get_response_output_format( $query_parameters );
            $limit            = $pagination['limit'];
            $order_by         = $pagination['order_by'];
            $order            = $pagination['order'];
            $offset           = $pagination['offset'];
            $category_id      = $this->get_requested_category_id( $query_parameters );
            $start_date       = $dates['current_start_date'];
            $end_date         = $dates['current_end_date'];

            if ( ! in_array( $order_by, self::CATEGORY_SUPPORTED_ORDER_BY, true ) ) {
                /* translators: %s: order_by value */
                return new \WP_Error( 'unsupported_order_by', sprintf( __( 'The order_by "%s" is not supported.', 'woocommerce-wholesale-prices-premium' ), $order_by ), array( 'status' => 400 ) );
            }

            $cache_key_prefix = 'wwpp_reports_category_performance_';
            $cache_key        = $cache_key_prefix . hash( 'sha256', wp_json_encode( $query_parameters ) );

            // check if the value exists, based on the cache key.
            $response = get_transient( $cache_key );

            if ( false !== $response ) {
                return rest_ensure_response( $response );
            }

            try {
                $hpos     = OrderUtil::custom_orders_table_usage_is_enabled();
                $instance = Category_Performance::instance();
                $response = $instance->get_category_performance( $hpos, $role, $start_date, $end_date, $order_by, $order, $limit, $offset, $output_format, $category_id );

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
         * Extract pagination and sorting parameters from the request query parameters.
         *
         * @since 2.1.0
         *
         * @param array  $query_parameters  The parameters received from \WP_REST_Request object.
         * @param int    $default_limit     Default number of items per page.
         * @param string $default_order_by  Default column to sort by.
         * @param string $default_order     Default sort direction: ASC or DESC.
         * @return array {
         *     @type int    $page      Current page number (1-based).
         *     @type int    $limit     Number of items per page. 0 = no limit.
         *     @type int    $offset    Calculated row offset for SQL ( ( page - 1 ) * limit ).
         *     @type string $order_by  Column to sort by.
         *     @type string $order     Sort direction: ASC or DESC.
         * }
         */
        public function get_pagination_params( $query_parameters, $default_limit = 0, $default_order_by = 'total_category_wholesale_revenue', $default_order = 'DESC' ) {
            $page     = isset( $query_parameters['page'] ) ? absint( $query_parameters['page'] ) : 1;
            $page     = max( 1, $page );
            $limit    = isset( $query_parameters['limit'] ) ? absint( $query_parameters['limit'] ) : $default_limit;
            $offset   = $limit > 0 ? ( $page - 1 ) * $limit : 0;
            $order_by = isset( $query_parameters['order_by'] ) ? sanitize_text_field( $query_parameters['order_by'] ) : $default_order_by;
            $order    = isset( $query_parameters['order'] ) ? strtoupper( sanitize_text_field( $query_parameters['order'] ) ) : strtoupper( $default_order );
            $order    = 'ASC' === $order ? 'ASC' : 'DESC';

            return array(
                'page'     => $page,
                'limit'    => $limit,
                'offset'   => $offset,
                'order_by' => $order_by,
                'order'    => $order,
            );
        }

        /**
         * Get the 'API' controllers.
         *
         * @return void
         */
        public function require_api_controllers() {
            require_once WWPP_INCLUDES_PATH . 'analytics/category-performance/class-category-performance.php';
        }

        /**
         * Permission callback for the category performance REST API endpoint.
         * Determines whether the current request is allowed to access the category performance analytics.
         *
         * @return bool True if the request has permission, false otherwise.
         */
        public function get_category_performance_permission_callback() {
            return current_user_can( 'manage_woocommerce' );
        }
    }
}
