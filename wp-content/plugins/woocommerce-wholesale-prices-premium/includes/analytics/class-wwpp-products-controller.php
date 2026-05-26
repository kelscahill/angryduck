<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Automattic\WooCommerce\Utilities\OrderUtil;

if ( ! class_exists( 'WWPP_Products_Controller' ) ) {

    /**
     * Top Products by Wholesale Volume Api Controller containing product volume analytics response.
     *
     * @since 2.0.8.2
     */
    class WWPP_Products_Controller extends WWPP_Sales_Controller {

        /**
         * Namespace of the api.
         *
         * @var string
         */
        private $namespace = 'wholesale/v1/analytics';

        const PRODUCT_OUTPUT_FORMATS = array( 'raw', 'chartjs' );

        /**
         * Api base name.
         *
         * @var string
         */
        private $rest_base = 'products';

        /**
         * Instantiate the class.
         *
         * @return void
         */
        public function __construct() {
            $this->require_api_controllers();
        }

        /**
         * Register routes for 'Top Products by Wholesale Volume' api.
         *
         * @return void
         */
        public function run() {
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base,
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_top_products_wholesale_volume' ),
                    'permission_callback' => array( $this, 'get_top_products_wholesale_volume_permission_callback' ),
                )
            );
        }

        /**
         * Payload for 'top-products-wholesale-volume' api.
         *
         * @param \WP_REST_Request $req The request payload.
         * @return array|\WP_REST_Response|\WP_Error
         */
        public function get_top_products_wholesale_volume( \WP_REST_Request $req ) {
            $query_parameters = $req->get_query_params() ?? array();

            $supported_metrics = array( 'volume', 'total_products_by_volume', 'revenue', 'total_products_by_revenue' );
            // Note: `average_price_unit` is only meaningful for the `revenue` metric. If passed with
            // `volume`, the query class will silently fall back to its own default sort column.
            $supported_order_by = array( 'product_name', 'total_units_sold', 'total_product_revenue', 'number_of_orders', 'average_order_value', 'average_price_unit' );

            $metric        = $this->get_requested_metric( $query_parameters );
            $role          = $this->get_current_user_role( $query_parameters );
            $dates         = $this->get_requested_dates( $query_parameters );
            $pagination    = $this->get_pagination_params( $query_parameters, 15, 'total_units_sold', 'DESC' );
            $output_format = $this->get_response_output_format( $query_parameters );
            $start_date    = $dates['current_start_date'];
            $end_date      = $dates['current_end_date'];
            $limit         = $pagination['limit'];
            $order_by      = $pagination['order_by'];
            $order         = $pagination['order'];
            $offset        = $pagination['offset'];
            $category_id   = $this->get_requested_category_id( $query_parameters );

            if ( empty( $metric ) ) {
                return new \WP_Error( 'missing_metric', __( 'The metric parameter is required.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
            }

            if ( ! in_array( $metric, $supported_metrics, true ) ) {
                /* translators: %s: metric name */
                return new \WP_Error( 'unsupported_metric', sprintf( __( 'The metric "%s" is not supported.', 'woocommerce-wholesale-prices-premium' ), $metric ), array( 'status' => 400 ) );
            }

            if ( in_array( $metric, array( 'volume', 'revenue' ), true ) && ! in_array( $order_by, $supported_order_by, true ) ) {
                /* translators: %s: order_by value */
                return new \WP_Error( 'unsupported_order_by', sprintf( __( 'The order_by "%s" is not supported.', 'woocommerce-wholesale-prices-premium' ), $order_by ), array( 'status' => 400 ) );
            }

            if ( in_array( $metric, array( 'volume', 'revenue' ), true ) && ! in_array( $output_format, self::PRODUCT_OUTPUT_FORMATS, true ) ) {
                /* translators: %s: output format */
                return new \WP_Error( 'unsupported_output_format', sprintf( __( 'The output format %s is not supported for the products api metric. Supported formats are: raw, chartjs.', 'woocommerce-wholesale-prices-premium' ), $output_format ), array( 'status' => 400 ) );
            }

            $cache_key = 'wwpp_reports_top_products_' . $metric . '_' . hash( 'sha256', wp_json_encode( $query_parameters ) );

            // check if the value exists, based on the cache key.
            $response = get_transient( $cache_key );

            if ( false !== $response ) {
                return rest_ensure_response( $response );
            }

            try {
                $hpos     = OrderUtil::custom_orders_table_usage_is_enabled();
                $response = null;

                if ( 'volume' === $metric ) {
                    $instance = Top_Products_Wholesale_Volume::instance();
                    $response = $instance->get_top_products_wholesale_volume( $hpos, $start_date, $end_date, $role, $order_by, $order, $limit, $offset, $category_id, $output_format );
                } elseif ( 'total_products_by_volume' === $metric ) {
                    $instance = Top_Products_Wholesale_Volume::instance();
                    $response = $instance->get_total_top_products_wholesale_volume( $hpos, $start_date, $end_date, $role, $category_id );
                } elseif ( 'revenue' === $metric ) {
                    $instance = Top_Products_Wholesale_Revenue::instance();
                    $response = $instance->get_top_products_wholesale_revenue( $hpos, $start_date, $end_date, $role, $order_by, $order, $limit, $offset, $category_id, $output_format );
                } elseif ( 'total_products_by_revenue' === $metric ) {
                    $instance = Top_Products_Wholesale_Revenue::instance();
                    $response = $instance->get_total_top_products_wholesale_revenue( $hpos, $start_date, $end_date, $role, $category_id );
                }

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
            require_once WWPP_INCLUDES_PATH . 'analytics/top-products-wholesale-volume/class-top-products-wholesale-volume.php';
            require_once WWPP_INCLUDES_PATH . 'analytics/top-products-wholesale-revenue/class-top-products-wholesale-revenue.php';
        }

        /**
         * Get the requested product category ID from query parameters.
         *
         * @param array $query_parameters The parameters received from \WP_REST_Request object.
         * @return int Category term ID, or 0 if not provided.
         */
        public function get_requested_category_id( $query_parameters ) {
            return isset( $query_parameters['category'] ) ? absint( $query_parameters['category'] ) : 0;
        }

        /**
         * Permission callback for the top products wholesale volume REST API endpoint.
         * Determines whether the current request is allowed to access the top products wholesale volume analytics.
         *
         * @return bool True if the request has permission, false otherwise.
         */
        public function get_top_products_wholesale_volume_permission_callback() {
            return current_user_can( 'manage_woocommerce' );
        }
    }
}
