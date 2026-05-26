<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Top_Products_Wholesale_Revenue' ) ) {

    /**
     * Top Products by Wholesale Revenue Payload.
     *
     * @since 2.0.8.2
     */
    class Top_Products_Wholesale_Revenue {

        use WWPP_Analytics_Query_Trait;
        use WWPP_Top_Products_Price_Format_Trait;

        /**
         * Contains the instance of the 'Top_Products_Wholesale_Revenue' class.
         *
         * @var object
         */
        private static $instance = null;

        /**
         * Allowed ORDER BY columns.
         *
         * @var array
         */
        private $allowed_order_by = array( 'total_product_revenue', 'total_units_sold', 'number_of_orders', 'average_price_unit' );

        /**
         * Contains the singleton instance of the 'Top_Products_Wholesale_Revenue'.
         *
         * @return object The current instance of the class.
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Returns top products ranked by wholesale revenue (line total).
         *
         * @since 2.0.8.2
         * @since 2.1.0 Added $response_format parameter to support chartjs output.
         *
         * @param boolean $is_hpos         Whether HPOS is enabled.
         * @param string  $start_date      Start date filter.
         * @param string  $end_date        End date filter.
         * @param string  $user_role       Wholesale role to filter by.
         * @param string  $order_by        Column to sort by.
         * @param string  $order           Sort direction: ASC or DESC.
         * @param int     $limit           Maximum number of products to return. 0 = no limit.
         * @param int     $offset          Current offset.
         * @param int     $category_id     Product category term ID to filter by. 0 = no filter.
         * @param string  $response_format Response format: 'raw' or 'chartjs'.
         * @return array|\WP_Error
         */
        public function get_top_products_wholesale_revenue( $is_hpos = false, $start_date = '', $end_date = '', $user_role = '', $order_by = 'total_product_revenue', $order = 'DESC', $limit = 15, $offset = 0, $category_id = 0, $response_format = 'raw' ) {
            global $wpdb;

            $place_holders = $this->placeholders_builder( $start_date, $end_date, $user_role, $limit, $offset, $category_id );
            $query         = $this->build_top_products_revenue_query( $start_date, $end_date, $wpdb->prefix, $is_hpos, $order_by, $order, $limit, $category_id );
            $results       = $wpdb->get_results( $wpdb->prepare( $query, $place_holders ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built safely with prepare.

            if ( ! empty( $wpdb->last_error ) ) {
                return new \WP_Error( 'db_error', __( 'A database error occurred.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 500 ) );
            }

            return ! empty( $results ) ? ( 'raw' === $response_format ? $this->format_price_columns( $results, array( 'total_product_revenue', 'average_price_unit' ) ) : $this->map_response_for_visual_representation( $results ) ) : array();
        }

        /**
         * Maps raw DB query results into a Chart.js-compatible bar chart payload.
         *
         * Extracts product names as chart labels and `total_product_revenue` as data points,
         * returning a pre-styled blue bar chart dataset ready for Chart.js consumption.
         *
         * @since 2.1.0
         *
         * @param array $responses Rows from the wholesale revenue query. Each row must contain
         *                         'product_name' (string) and 'total_product_revenue' (float|string).
         * @return array {
         *     @type string[] $labels   Product names used as y-axis labels.
         *     @type array    $datasets Chart.js dataset with `label`, `data`, and style keys.
         * }
         */
        protected function map_response_for_visual_representation( $responses ) {
            $labels = array();

            $datasets = array(
                'label'           => __( 'Revenue', 'woocommerce-wholesale-prices-premium' ),
                'data'            => array(),
                'formattedData'   => array(),
                'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                'borderColor'     => 'rgba(59, 130, 246, 1)',
                'borderWidth'     => 1,
                'borderRadius'    => 8,
            );

            foreach ( $responses as $response ) {
                array_push( $labels, $response['product_name'] );
                array_push( $datasets['data'], (float) $response['total_product_revenue'] );
                array_push(
                    $datasets['formattedData'],
                    wc_price(
                        $response['total_product_revenue'],
                        array(
                            'in_span' => false,
                        )
                    )
                );
            }

            return array(
                'datasets' => array( $datasets ),
                'labels'   => $labels,
            );
        }

        /**
         * Builds the SQL query for top products by wholesale revenue.
         *
         * @param string  $start_date  Start date.
         * @param string  $end_date    End date.
         * @param string  $prefix      DB table prefix.
         * @param boolean $is_hpos     Whether HPOS is enabled.
         * @param string  $order_by    Column to sort by.
         * @param string  $order       ASC or DESC.
         * @param int     $limit       Max rows to return. 0 = no limit.
         * @param int     $category_id Product category term ID to filter by. 0 = no filter.
         * @return string Prepared SQL string.
         */
        protected function build_top_products_revenue_query( $start_date = '', $end_date = '', $prefix = 'wp_', $is_hpos = false, $order_by = 'total_product_revenue', $order = 'DESC', $limit = 15, $category_id = 0 ) {
            $order_items_table            = "{$prefix}woocommerce_order_items";
            $order_items_alias            = 'wwoi';
            $order_itemmeta_table         = "{$prefix}woocommerce_order_itemmeta";
            $order_itemmeta_product_alias = 'wwoim';
            $order_itemmeta_qty_alias     = 'wwoim_';
            $order_itemmeta_total_alias   = 'wwoim__';

            // HPOS vs legacy for order status and order meta.
            $map = $this->get_table_mapping( $prefix, $is_hpos );

            $order_by_column = in_array( $order_by, $this->allowed_order_by, true ) ? $order_by : 'total_product_revenue';
            $order_dir       = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
            $date_sql        = $this->build_date_query( $start_date, $end_date, $is_hpos );
            $limit_sql       = $limit > 0 ? 'LIMIT %d OFFSET %d' : '';
            $category_join   = $this->build_category_join_clause( $prefix, $category_id, $order_itemmeta_product_alias, $is_hpos );

            $final_sql = "SELECT
                            {$order_itemmeta_product_alias}.meta_value AS product_id,
                            {$order_items_alias}.order_item_name AS product_name,
                            COUNT( DISTINCT {$order_items_alias}.order_id ) AS number_of_orders,
                            SUM( CAST( {$order_itemmeta_total_alias}.meta_value AS DECIMAL(10,2) ) ) AS total_product_revenue,
                            CAST(
                                SUM( CAST( {$order_itemmeta_total_alias}.meta_value AS DECIMAL(10,2) ) )
                                / SUM( {$order_itemmeta_qty_alias}.meta_value )
                            AS DECIMAL(10,2)) AS average_price_unit,
                            SUM( {$order_itemmeta_qty_alias}.meta_value ) AS total_units_sold

                            FROM (
                                SELECT DISTINCT {$map['table_alias']}.{$map['id_column']} AS order_id
                                FROM {$map['table_name']} {$map['table_alias']}

                                /* fetch wholesale orders | fetch wholesale customer orders */
                                JOIN {$map['meta_table_name']} {$map['meta_table_alias']}
                                    ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}.{$map['meta_id_column']}
                                    AND {$map['meta_table_alias']}.meta_key = %s
                                    AND {$map['meta_table_alias']}.meta_value = %s

                                WHERE {$map['table_alias']}.{$map['post_status_column']} = %s
                                {$date_sql}
                            ) AS qualified_orders

                        /* fetch order items for qualified wholesale orders */
                        JOIN {$order_items_table} {$order_items_alias}
                            ON {$order_items_alias}.order_id = qualified_orders.order_id
                            AND {$order_items_alias}.order_item_type = %s

                        /* fetch order product items */
                        JOIN {$order_itemmeta_table} {$order_itemmeta_product_alias}
                            ON {$order_items_alias}.order_item_id = {$order_itemmeta_product_alias}.order_item_id
                            AND {$order_itemmeta_product_alias}.meta_key = %s

                        /* filter by product category */
                        {$category_join}

                        /* fetch order product quantities */
                        JOIN {$order_itemmeta_table} {$order_itemmeta_qty_alias}
                            ON {$order_items_alias}.order_item_id = {$order_itemmeta_qty_alias}.order_item_id
                            AND {$order_itemmeta_qty_alias}.meta_key = %s

                        /* fetch order product line totals */
                        JOIN {$order_itemmeta_table} {$order_itemmeta_total_alias}
                            ON {$order_items_alias}.order_item_id = {$order_itemmeta_total_alias}.order_item_id
                            AND {$order_itemmeta_total_alias}.meta_key = %s

                        GROUP BY product_id, product_name
                        ORDER BY {$order_by_column} {$order_dir}
                        {$limit_sql};";

            return $final_sql;
        }

        /**
         * Builds placeholder values for the top products revenue prepared statement.
         *
         * @param string $start_date Start date.
         * @param string $end_date   End date.
         * @param string $role       Wholesale role slug; empty for generic wholesale.
         * @param int    $limit       Max rows. 0 = no limit (no placeholder needed).
         * @param int    $offset      Offset for the query.
         * @param int    $category_id Product category term ID to filter by. 0 = no filter.
         * @return array Values for wpdb::prepare placeholders.
         */
        protected function placeholders_builder( $start_date = '', $end_date = '', $role = '', $limit = 15, $offset = 0, $category_id = 0 ) {
            $order_type_meta_key = ! empty( $role ) ? '_wwpp_wholesale_order_type' : '_wwpp_order_type';
            $order_type_meta_val = ! empty( $role ) ? $role : 'wholesale';

            // Subquery placeholders: meta_key, meta_value, wc-completed, [dates], then outer JOIN.
            $placeholders = array(
                $order_type_meta_key,
                $order_type_meta_val,
                'wc-completed',
            );

            if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
                $placeholders[] = $start_date;
                $placeholders[] = $end_date;
            } elseif ( ! empty( $start_date ) ) {
                $placeholders[] = $start_date;
            } elseif ( ! empty( $end_date ) ) {
                $placeholders[] = $end_date;
            }

            // Outer JOIN placeholders: line_item type, then _product_id, category (if set), _qty, _line_total.
            $placeholders[] = 'line_item';
            $placeholders[] = '_product_id';

            if ( $category_id > 0 ) {
                $placeholders[] = $category_id;
            }

            $placeholders[] = '_qty';
            $placeholders[] = '_line_total';

            if ( $limit > 0 ) {
                $placeholders[] = $limit;
                $placeholders[] = $offset;
            }

            return $placeholders;
        }

        /**
         * Returns the count of distinct products purchased across all completed wholesale orders.
         *
         * @param boolean $is_hpos    Whether HPOS is enabled.
         * @param string  $start_date Start date filter.
         * @param string  $end_date   End date filter.
         * @param string  $user_role   Wholesale role to filter by.
         * @param int     $category_id Product category term ID to filter by. 0 = no filter.
         * @return array|\WP_Error
         */
        public function get_total_top_products_wholesale_revenue( $is_hpos = false, $start_date = '', $end_date = '', $user_role = '', $category_id = 0 ) {
            global $wpdb;

            $place_holders = $this->total_placeholders_builder( $start_date, $end_date, $user_role, $category_id );
            $query         = $this->build_total_top_products_revenue_query( $start_date, $end_date, $wpdb->prefix, $is_hpos, $category_id );
            $results       = $wpdb->get_results( $wpdb->prepare( $query, $place_holders ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built safely with prepare.

            if ( ! empty( $wpdb->last_error ) ) {
                return new \WP_Error( 'db_error', __( 'A database error occurred.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 500 ) );
            }

            return isset( $results[0] ) && ! empty( $results[0] ) ? $results[0] : array();
        }

        /**
         * Builds the SQL query that counts distinct products across completed wholesale orders (for pagination).
         *
         * @param string  $start_date Start date.
         * @param string  $end_date   End date.
         * @param string  $prefix     DB table prefix.
         * @param boolean $is_hpos     Whether HPOS is enabled.
         * @param int     $category_id Product category term ID to filter by. 0 = no filter.
         * @return string Prepared SQL string.
         */
        protected function build_total_top_products_revenue_query( $start_date = '', $end_date = '', $prefix = 'wp_', $is_hpos = false, $category_id = 0 ) {
            $order_items_table    = "{$prefix}woocommerce_order_items";
            $order_items_alias    = 'wwoi';
            $order_itemmeta_table = "{$prefix}woocommerce_order_itemmeta";
            $order_itemmeta_alias = 'wwoim';

            $map = $this->get_table_mapping( $prefix, $is_hpos );

            $date_sql      = $this->build_date_query( $start_date, $end_date, $is_hpos );
            $category_join = $this->build_category_join_clause( $prefix, $category_id, $order_itemmeta_alias, $is_hpos );

            $final_sql = "SELECT
                            COUNT( DISTINCT {$order_itemmeta_alias}.meta_value ) AS total_products
                        FROM (
                            SELECT DISTINCT {$map['table_alias']}.{$map['id_column']} AS order_id
                            FROM {$map['table_name']} {$map['table_alias']}

                            /* fetch wholesale orders | fetch wholesale customer orders */
                            JOIN {$map['meta_table_name']} {$map['meta_table_alias']}
                                ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}.{$map['meta_id_column']}
                                AND {$map['meta_table_alias']}.meta_key = %s
                                AND {$map['meta_table_alias']}.meta_value = %s

                            WHERE {$map['table_alias']}.{$map['post_status_column']} = %s
                            {$date_sql}
                        ) AS qualified_orders

                        /* fetch order items for qualified wholesale orders */
                        JOIN {$order_items_table} {$order_items_alias}
                            ON {$order_items_alias}.order_id = qualified_orders.order_id
                            AND {$order_items_alias}.order_item_type = %s

                        /* fetch order product items */
                        JOIN {$order_itemmeta_table} {$order_itemmeta_alias}
                            ON {$order_items_alias}.order_item_id = {$order_itemmeta_alias}.order_item_id
                            AND {$order_itemmeta_alias}.meta_key = %s

                        /* filter by product category */
                        {$category_join};";

            return $final_sql;
        }

        /**
         * Builds placeholder values for the total top products revenue prepared statement.
         *
         * @param string $start_date  Start date.
         * @param string $end_date    End date.
         * @param string $role        Wholesale role slug; empty for generic wholesale.
         * @param int    $category_id Product category term ID to filter by. 0 = no filter.
         * @return array Values for wpdb::prepare placeholders.
         */
        protected function total_placeholders_builder( $start_date = '', $end_date = '', $role = '', $category_id = 0 ) {
            $order_type_meta_key = ! empty( $role ) ? '_wwpp_wholesale_order_type' : '_wwpp_order_type';
            $order_type_meta_val = ! empty( $role ) ? $role : 'wholesale';

            // Subquery placeholders: meta_key, meta_value, wc-completed, [dates], then outer JOIN.
            $placeholders = array(
                $order_type_meta_key,
                $order_type_meta_val,
                'wc-completed',
            );

            if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
                $placeholders[] = $start_date;
                $placeholders[] = $end_date;
            } elseif ( ! empty( $start_date ) ) {
                $placeholders[] = $start_date;
            } elseif ( ! empty( $end_date ) ) {
                $placeholders[] = $end_date;
            }

            // Outer JOIN placeholders: line_item type, then _product_id, category (if set).
            $placeholders[] = 'line_item';
            $placeholders[] = '_product_id';

            if ( $category_id > 0 ) {
                $placeholders[] = $category_id;
            }

            return $placeholders;
        }
    }
}
