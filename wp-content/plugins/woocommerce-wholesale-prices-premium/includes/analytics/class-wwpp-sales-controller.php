<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Automattic\WooCommerce\Utilities\OrderUtil;

if ( ! class_exists( 'WWPP_Sales_Controller' ) ) {
    /**
     * Sale Api Containing Sale's related response.
     */
    class WWPP_Sales_Controller {

        /**
         * Supported analytics metrics.
         *
         * @since 2.1.0
         */
        const SUPPORTED_METRICS = array( 'wholesale_revenue', 'wholesale_revenue_summary', 'average_order_value', 'average_order_value_summary', 'wholesale_vs_retail' );

        /**
         * Supported output formats for the wholesale_vs_retail metric.
         *
         * @since 2.1.0
         */
        const WHOLESALE_VS_RETAIL_OUTPUT_FORMATS = array( 'raw', 'chartjs' );

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
        private $rest_base = 'sales';

        /**
         * Instantiate the class
         */
        public function __construct() {
            $this->require_api_controllers();
        }

        /**
         * Register routes for 'Sales' api.
         *
         * @return void
         */
        public function run() {
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base,
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_sales' ),
                    'permission_callback' => array( $this, 'get_sales_permission_callback' ),
                )
            );
        }

        /**
         * Payload for 'sales' api.
         *
         * @param \WP_REST_Request $req The request payload.
         * @return array
         */
        public function get_sales( \WP_REST_Request $req ) {
            $query_parameters = $req->get_query_params() ?? array();

            $role                = $this->get_current_user_role( $query_parameters );
            $dates               = $this->get_requested_dates( $query_parameters );
            $metric              = $this->get_requested_metric( $query_parameters );
            $comparison_mode     = $this->comparison_mode( $query_parameters );
            $output_format       = $this->get_response_output_format( $query_parameters );
            $current_start_date  = $dates['current_start_date'];
            $current_end_date    = $dates['current_end_date'];
            $previous_start_date = $dates['previous_start_date'];
            $previous_end_date   = $dates['previous_end_date'];

            $cache_key_prefix = 'wwpp_reports_sales_';
            $cache_key        = $cache_key_prefix . hash( 'sha256', wp_json_encode( $query_parameters ) );

            if ( empty( $metric ) ) {
                return new \WP_Error( 'missing_metric', __( 'The metric parameter is required.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
            }

            if ( ! in_array( $metric, self::SUPPORTED_METRICS, true ) ) {
                /* translators: %s: metric name */
                return new \WP_Error( 'unsupported_metric', sprintf( __( 'The metric %s is not supported.', 'woocommerce-wholesale-prices-premium' ), $metric ), array( 'status' => 400 ) );
            }

            if ( 'wholesale_vs_retail' === $metric && ! in_array( $output_format, self::WHOLESALE_VS_RETAIL_OUTPUT_FORMATS, true ) ) {
                /* translators: %s: output format */
                return new \WP_Error( 'unsupported_output_format', sprintf( __( 'The output format %s is not supported for the wholesale_vs_retail metric. Supported formats are: raw, chartjs.', 'woocommerce-wholesale-prices-premium' ), $output_format ), array( 'status' => 400 ) );
            }

            // check if the value exists, based on the cache key.
            $response = get_transient( $cache_key );

            if ( false !== $response ) {
                return rest_ensure_response( $response );
            }

            try {
                $hpos     = OrderUtil::custom_orders_table_usage_is_enabled();
                $response = null;

                if ( 'wholesale_revenue' === $metric ) {
                    $response_object = Total_Wholesale_Revenue::instance();
                    $response        = $response_object->get_sales( $comparison_mode, $hpos, $current_start_date, $current_end_date, $previous_start_date, $previous_end_date, $role );
                } elseif ( 'average_order_value' === $metric ) {
                    $response_object = Average_Order_Value::instance();
                    $response        = $response_object->get_average_order_value( $comparison_mode, $hpos, $current_start_date, $current_end_date, $previous_start_date, $previous_end_date, $role );
                } elseif ( 'wholesale_vs_retail' === $metric ) {
                    $response_object = Wholesale_Vs_Retail::instance();
                    $response        = $response_object->get_wholesale_vs_retail( $current_start_date, $current_end_date, $role, $hpos, $output_format );
                } elseif ( 'wholesale_revenue_summary' === $metric ) {
                    $response_object = Total_Wholesale_Revenue::instance();
                    $response        = $response_object->get_wholesale_summary( $hpos, $current_start_date, $current_end_date, $role, $previous_start_date, $previous_end_date );
                } elseif ( 'average_order_value_summary' === $metric ) {
                    $response_object = Average_Order_Value::instance();
                    $response        = $response_object->get_average_order_summary( $hpos, $current_start_date, $current_end_date, $role, $previous_start_date, $previous_end_date );
                }

                if ( ! is_wp_error( $response ) ) {
                    set_transient( $cache_key, $response, apply_filters( 'wwpp_reports_sales_transient_expiration_constant', HOUR_IN_SECONDS ) );
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
            require_once WWPP_INCLUDES_PATH . 'analytics/wholesale-revenue/class-total-wholesale-revenue.php';
            require_once WWPP_INCLUDES_PATH . 'analytics/average-order-value/class-average-order-value.php';
            require_once WWPP_INCLUDES_PATH . 'analytics/wholesale-vs-retail/class-wholesale-vs-retail.php';
        }

        /**
         * Sanitize and validate a date parameter as Y-m-d H:i:s format.
         *
         * @since 2.1.0
         * @param string $value Raw date value from request.
         * @return string Sanitized date in Y-m-d H:i:s format, or empty string if invalid.
         */
        private function sanitize_date_param( $value ) {
            $sanitized = sanitize_text_field( $value );
            if ( empty( $sanitized ) ) {
                return '';
            }
            $dt  = \DateTime::createFromFormat( 'Y-m-d H:i:s', $sanitized );
            $dt_ = \DateTime::createFromFormat( 'Y-m-d', $sanitized );
            return ( ( $dt && $dt->format( 'Y-m-d H:i:s' ) === $sanitized ) || ( $dt_ && $dt_->format( 'Y-m-d' ) === $sanitized ) ) ? $sanitized : '';
        }

        /**
         * Extract date parameters from the request query parameters.
         *
         * @param array $query_parameters The parameters received from \WP_REST_Request object.
         * @return array {
         *     @type string $current_start_date  Start date for the current period.
         *     @type string $current_end_date    End date for the current period.
         *     @type string $previous_start_date Start date for the previous period.
         *     @type string $previous_end_date   End date for the previous period.
         * }
         */
        public function get_requested_dates( $query_parameters ) {
            return array(
                'current_start_date'  => isset( $query_parameters['current_start_date'] ) ? $this->sanitize_date_param( $query_parameters['current_start_date'] ) : '',
                'current_end_date'    => isset( $query_parameters['current_end_date'] ) ? $this->sanitize_date_param( $query_parameters['current_end_date'] ) : '',
                'previous_start_date' => isset( $query_parameters['previous_start_date'] ) ? $this->sanitize_date_param( $query_parameters['previous_start_date'] ) : '',
                'previous_end_date'   => isset( $query_parameters['previous_end_date'] ) ? $this->sanitize_date_param( $query_parameters['previous_end_date'] ) : '',
            );
        }

        /**
         * Get check if the comparison mode is enabled.
         *
         * @param array $query_parameters The parameters received from \WP_REST_Request object.
         * @return boolean
         */
        public function comparison_mode( $query_parameters ) {
            $dates               = $this->get_requested_dates( $query_parameters );
            $current_start_date  = $dates['current_start_date'];
            $current_end_date    = $dates['current_end_date'];
            $previous_start_date = $dates['previous_start_date'];
            $previous_end_date   = $dates['previous_end_date'];

            return ! empty( $current_start_date ) && ! empty( $current_end_date )
            && ! empty( $previous_start_date ) && ! empty( $previous_end_date );
        }

        /**
         * Get the current requested metric like 'wholesale_revenue', 'aov', 'order_count', 'wholesale_vs_retail'
         *
         * @param array $query_parameters The parameters received from \WP_REST_Request object.
         * @return string
         */
        public function get_requested_metric( $query_parameters ) {
            $metric = isset( $query_parameters['metric'] ) ? sanitize_key( $query_parameters['metric'] ) : '';
            return $metric;
        }

        /**
         * Get the current requested wholesale role.
         *
         * @param array $query_parameters The parameters received from \WP_REST_Request object.
         * @return string
         */
        public function get_current_user_role( $query_parameters ) {
            $role = isset( $query_parameters['role'] ) ? sanitize_text_field( $query_parameters['role'] ) : '';
            return $role;
        }

        /**
         * Get the response output format from query parameters.
         *
         * @since 2.2.0
         * @param array $query_parameters Request query parameters.
         * @return string Output format — 'raw' (default) or 'chartjs'.
         */
        public function get_response_output_format( $query_parameters ) {
            $output_format = isset( $query_parameters['format'] ) ? sanitize_text_field( $query_parameters['format'] ) : 'raw';
            return $output_format;
        }

        /**
         * Get the permission callback for the API.
         *
         * @return bool
         */
        public function get_sales_permission_callback() {
            return current_user_can( 'manage_woocommerce' );
        }

        /**
         * Get the requested product category ID from query parameters.
         *
         * @since 2.2.0
         * @param array $query_parameters The parameters received from \WP_REST_Request object.
         * @return int Category term ID, or 0 if not provided.
         */
        public function get_requested_category_id( $query_parameters ) {
            return isset( $query_parameters['category'] ) ? absint( $query_parameters['category'] ) : 0;
        }

        /**
         * Extract pagination and sorting parameters from the request query parameters.
         *
         * @since 2.2.0
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
        public function get_pagination_params( $query_parameters, $default_limit = 0, $default_order_by = 'id', $default_order = 'DESC' ) {
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
    }
}
