<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Category_Performance' ) ) {

    /**
     * Category Performance Payload.
     *
     * @since 2.0.8.2
     */
    class Category_Performance {

        use WWPP_Analytics_Query_Trait;
        use WWPP_Top_Products_Price_Format_Trait;

        /**
         * Contains the instance of the 'Category_Performance' class.
         *
         * @var object
         */
        private static $instance = null;

        /**
         * Contains the singleton instance of the 'Category_Performance'.
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
         * Returns category performance data (revenue per category).
         *
         * @since 2.2.0
         *
         * @param boolean $is_hpos         Whether HPOS is enabled.
         * @param string  $user_role       Wholesale role to filter by.
         * @param string  $start_date      Start date filter (Y-m-d H:i:s). Empty string = no filter.
         * @param string  $end_date        End date filter (Y-m-d H:i:s). Empty string = no filter.
         * @param string  $order_by        Column to sort by. One of: category_id, category_name, total_category_wholesale_revenue.
         * @param string  $order           Sort direction: ASC or DESC.
         * @param int     $limit           Number of rows to return. 0 = no limit.
         * @param int     $offset          Row offset for pagination.
         * @param boolean $response_format Whether to show data in chartjs/raw format.
         * @param int     $category_id     Specific category term ID to filter by. 0 = no filter.
         * @return array|\WP_Error List of categories with revenue and metadata, or WP_Error on failure.
         */
        public function get_category_performance( $is_hpos = false, $user_role = '', $start_date = '', $end_date = '', $order_by = 'total_category_wholesale_revenue', $order = 'DESC', $limit = 5, $offset = 0, $response_format = 'raw', $category_id = 0 ) {
            global $wpdb;

            $place_holders = $this->placeholders_builder( $user_role, $start_date, $end_date, $limit, $offset, $category_id );
            $query         = $this->build_category_performance_query( $wpdb->prefix, $is_hpos, $start_date, $end_date, $order_by, $order, $limit, $category_id );
            $results       = $wpdb->get_results( $wpdb->prepare( $query, $place_holders ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built safely with prepare.

            if ( ! empty( $wpdb->last_error ) ) {
                return new \WP_Error( 'db_error', __( 'A database error occurred.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 500 ) );
            }

            $data = ! empty( $results ) ? ( 'raw' === $response_format ? $this->format_price_columns( $results, array( 'total_category_wholesale_revenue' ) ) : $this->map_response_for_visual_representation( $results ) ) : array();

            return $data;
        }


        /**
         * Maps raw category revenue data into a chart-ready format.
         *
         * Transforms a flat list of category records into `labels` (category names)
         * and `datasets` (revenue values with bar chart styling) for frontend rendering.
         *
         * @since 2.2.0
         *
         * @param array $responses List of rows, each with `category_name` and `total_category_wholesale_revenue`.
         * @return array {
         *     @type array $labels   Category names.
         *     @type array $datasets Nested dataset array with backgroundColor, borderRadius, data, and label.
         * }
         */
        public function map_response_for_visual_representation( $responses ) {
            $labels   = array();
            $datasets = array(
                array(
                    'backgroundColor' => '#3B82F6',
                    'borderRadius'    => 6,
                    'data'            => array(),
                    'label'           => __( 'Revenue', 'woocommerce-wholesale-prices-premium' ),
                    'yAxisID'         => 'y1',
                ),
            );

            foreach ( $responses as $response ) {
                $labels[]              = wp_specialchars_decode( $response['category_name'] );
                $datasets[0]['data'][] = array(
                    'x'           => wp_specialchars_decode( $response['category_name'] ),
                    'y'           => (float) $response['total_category_wholesale_revenue'],
                    'parsedPrice' => wc_price( wc_format_decimal( $response['total_category_wholesale_revenue'], 2 ), array( 'in_span' => false ) ),
                    'prefixText'  => __( 'Revenue: ', 'woocommerce-wholesale-prices-premium' ),
                    'categoryID'  => (int) $response['category_id'],
                );
            }

            return array(
                'datasets' => $datasets,
                'labels'   => $labels,
            );
        }

        /**
         * Builds the SQL query for category revenue (order items → categories).
         *
         * Starts from order items and filters completed wholesale orders via a
         * subquery, then resolves product categories through term relationships.
         * Supports both HPOS (wc_orders / wc_orders_meta) and legacy (posts / postmeta).
         *
         * @since 2.2.0
         *
         * @param string  $prefix      DB table prefix.
         * @param boolean $is_hpos     Whether HPOS is enabled.
         * @param string  $start_date  Start date filter (Y-m-d H:i:s). Empty string = no filter.
         * @param string  $end_date    End date filter (Y-m-d H:i:s). Empty string = no filter.
         * @param string  $order_by    Column to sort by. One of: category_id, category_name, total_category_wholesale_revenue.
         * @param string  $order       Sort direction: ASC or DESC.
         * @param int     $limit       Number of rows to return. 0 = no limit (no LIMIT clause added).
         * @param int     $category_id Specific category term ID to filter by. 0 = no filter.
         * @return string Prepared SQL string.
         */
        protected function build_category_performance_query( $prefix = 'wp_', $is_hpos = false, $start_date = '', $end_date = '', $order_by = 'total_category_wholesale_revenue', $order = 'DESC', $limit = 0, $category_id = 0 ) {
            $allowed_order_by = array( 'category_id', 'category_name', 'total_category_wholesale_revenue' );
            $order_by         = in_array( $order_by, $allowed_order_by, true ) ? $order_by : 'total_category_wholesale_revenue';
            $order            = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

            $order_items_table        = "{$prefix}woocommerce_order_items";
            $order_items_alias        = 'wwoi';
            $order_itemmeta_table     = "{$prefix}woocommerce_order_itemmeta";
            $order_itemmeta_alias     = 'wwoim';
            $posts_table              = "{$prefix}posts";
            $posts_alias              = 'wpost';
            $term_relationships_table = "{$prefix}term_relationships";
            $term_relationships_alias = 'wtr';
            $term_taxonomy_table      = "{$prefix}term_taxonomy";
            $term_taxonomy_alias      = 'wtt';
            $terms_table              = "{$prefix}terms";
            $terms_alias              = 'wt';

            $map      = $this->get_table_mapping( $prefix, $is_hpos );
            $date_sql = $this->build_date_query( $start_date, $end_date, $is_hpos );

            $final_sql = "SELECT
                            {$terms_alias}.term_id AS category_id,
                            {$terms_alias}.name AS category_name,
                            CAST(SUM({$order_itemmeta_alias}_.meta_value) AS DECIMAL(10,2)) AS total_category_wholesale_revenue
                        FROM {$order_items_table} {$order_items_alias}
                        JOIN (
                            SELECT DISTINCT {$map['table_alias']}.{$map['id_column']} AS wholesale_order_id
                            FROM {$map['table_name']} {$map['table_alias']}
                            JOIN {$map['meta_table_name']} {$map['meta_table_alias']}
                                ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}.{$map['meta_id_column']}
                                AND {$map['meta_table_alias']}.meta_key = %s
                                AND {$map['meta_table_alias']}.meta_value = %s
                            WHERE {$map['table_alias']}.{$map['post_status_column']} = %s
                            {$date_sql}
                        ) AS filtered_wholesale_orders
                            ON {$order_items_alias}.order_id = filtered_wholesale_orders.wholesale_order_id
                            AND {$order_items_alias}.order_item_type = %s
                        JOIN {$order_itemmeta_table} {$order_itemmeta_alias}
                            ON {$order_items_alias}.order_item_id = {$order_itemmeta_alias}.order_item_id
                            AND {$order_itemmeta_alias}.meta_key = %s
                        JOIN {$order_itemmeta_table} {$order_itemmeta_alias}_
                            ON {$order_items_alias}.order_item_id = {$order_itemmeta_alias}_.order_item_id
                            AND {$order_itemmeta_alias}_.meta_key = %s
                        JOIN {$posts_table} {$posts_alias}
                            ON {$posts_alias}.ID = {$order_itemmeta_alias}.meta_value
                        JOIN {$term_relationships_table} {$term_relationships_alias}
                            ON {$term_relationships_alias}.object_id = IF( {$posts_alias}.post_parent > 0, {$posts_alias}.post_parent, {$posts_alias}.ID )
                        JOIN {$term_taxonomy_table} {$term_taxonomy_alias}
                            ON {$term_relationships_alias}.term_taxonomy_id = {$term_taxonomy_alias}.term_taxonomy_id
                        JOIN {$terms_table} {$terms_alias}
                            ON {$term_taxonomy_alias}.term_id = {$terms_alias}.term_id
                            AND {$term_taxonomy_alias}.taxonomy = %s";

            if ( $category_id > 0 ) {
                $final_sql .= "
                        WHERE {$terms_alias}.term_id = %d";
            }

            $final_sql .= "
                        GROUP BY category_id
                        ORDER BY {$order_by} {$order}";

            if ( $limit > 0 ) {
                $final_sql .= ' LIMIT %d OFFSET %d';
            }

            $final_sql .= ';';

            return $final_sql;
        }

        /**
         * Builds placeholder values for the category performance prepared statement.
         *
         * Placeholder order matches the query built by build_category_performance_query:
         * 1. order type meta_key   (_wwpp_order_type or role-based key)
         * 2. order type meta_value (wholesale or role slug)
         * 3. order status          (wc-completed)
         * 4. start_date            (only when $start_date is non-empty)
         * 5. end_date              (only when $end_date is non-empty)
         * 6. order item type       (line_item)
         * 7. _product_id meta_key
         * 8. _line_total meta_key
         * 9. taxonomy              (product_cat)
         * 10. category_id          (only when $category_id > 0)
         * 11. limit                (only when $limit > 0)
         * 12. offset               (only when $limit > 0)
         *
         * @since 2.2.0
         *
         * @param string $role        Wholesale role slug; empty for generic wholesale.
         * @param string $start_date  Start date filter (Y-m-d H:i:s). Empty string = no placeholder added.
         * @param string $end_date    End date filter (Y-m-d H:i:s). Empty string = no placeholder added.
         * @param int    $limit       Number of rows to return. 0 = no limit (no placeholders added).
         * @param int    $offset      Row offset for pagination.
         * @param int    $category_id Specific category term ID to filter by. 0 = no filter.
         * @return array Values for wpdb::prepare placeholders.
         */
        protected function placeholders_builder( $role = '', $start_date = '', $end_date = '', $limit = 0, $offset = 0, $category_id = 0 ) {
            $order_type_group = array( '_wwpp_order_type', 'wholesale' );
            $item_meta_group  = array( '_product_id', '_line_total' );
            $taxonomy_group   = array( 'product_cat' );

            if ( ! empty( $role ) ) {
                $order_type_group = array( '_wwpp_wholesale_order_type', $role );
            }

            $placeholders = array_merge( $order_type_group, array( 'wc-completed' ) );

            if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
                $placeholders[] = $start_date;
                $placeholders[] = $end_date;
            } elseif ( ! empty( $start_date ) ) {
                $placeholders[] = $start_date;
            } elseif ( ! empty( $end_date ) ) {
                $placeholders[] = $end_date;
            }

            $placeholders = array_merge( $placeholders, array( 'line_item' ), $item_meta_group, $taxonomy_group );

            if ( $category_id > 0 ) {
                $placeholders[] = $category_id;
            }

            if ( $limit > 0 ) {
                $placeholders[] = $limit;
                $placeholders[] = $offset;
            }

            return $placeholders;
        }
    }
}
