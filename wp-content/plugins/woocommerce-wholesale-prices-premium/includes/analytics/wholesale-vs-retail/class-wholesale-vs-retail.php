<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


if ( ! class_exists( 'Wholesale_Vs_Retail' ) ) {

    /**
     * Wholesale Vs Retail Payload.
     *
     * Data model: Both the free WWP plugin (class-wwp-order.php) and WWPP explicitly write
     * _wwpp_order_type = 'wholesale' or 'retail' on every order at checkout. However, orders
     * placed before the plugin was installed will not have this meta key at all, and should
     * still count as retail. To correctly include those older orders, retail revenue is derived
     * as total_completed_revenue − wholesale_revenue rather than querying _wwpp_order_type = 'retail'
     * directly (which would miss meta-less orders via INNER JOIN).
     *
     * Query structure: Uses CTEs (WITH clause) for readability:
     * - wholesale:              SUM of order totals for completed wholesale orders in the main period.
     * - total:                  SUM of order totals scoped to (this role OR retail/no-meta) in the main period.
     * - last_week_wholesale:    same as wholesale for the previous week.
     * - last_week_total:        same as total for the previous week.
     * - current_week_wholesale: same as wholesale for the current week (Mon–Sun).
     * - current_week_total:     same as total for the current week (Mon–Sun).
     * Final SELECT derives retail = total − wholesale for all three periods.
     *
     * Role scoping: When $role is set, the total CTE is narrowed to (this role's orders) + (retail/no-meta
     * orders) so that other wholesale roles do not inflate the retail figure.
     */
    class Wholesale_Vs_Retail {

        use WWPP_Analytics_Query_Trait;
        use WWPP_Analytics_Date_Time_Trait;

        /**
         * Contains the instance of the 'Wholesale_Vs_Retail' class.
         *
         * @var object
         */
        private static $instance = null;

        /**
         * Contains the singleton instance of the 'Wholesale_Vs_Retail'.
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
         * Main function to get the wholesale vs retail data.
         *
         * @param string  $current_start_date Start date (Y-m-d H:i:s).
         * @param string  $current_end_date   End date (Y-m-d H:i:s).
         * @param string  $user_role          Wholesale role to filter by.
         * @param boolean $is_hpos            Whether HPOS is enabled.
         * @param boolean $response_format    Whether to show data in chart js/raw format.
         *
         * @return array|\WP_Error
         */
        public function get_wholesale_vs_retail( $current_start_date = '', $current_end_date = '', $user_role = '', $is_hpos = false, $response_format = 'raw' ) {
            global $wpdb;
            $place_holders = $this->build_wholesale_vs_retail_placeholders( $current_start_date, $current_end_date, $user_role );
            $query         = $this->build_wholesale_vs_retail_revenue( $current_start_date, $current_end_date, $user_role, $is_hpos, $wpdb->prefix );
            $results       = $wpdb->get_results( $wpdb->prepare( $query, $place_holders ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built safely with prepare.

            if ( ! empty( $wpdb->last_error ) ) {
                return new \WP_Error( 'db_error', __( 'A database error occurred.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 500 ) );
            }

            $data = isset( $results[0] ) && ! empty( $results[0] ) ? ( 'raw' === $response_format ? $results[0] : $this->map_response_for_visual_representation( $results[0] ) ) : array();

            return $data;
        }


        /**
         * Map the raw DB response to a structure suitable for visual representation.
         *
         * Calculates wholesale/retail share percentages and week-over-week revenue
         * growth rates for both channels.
         *
         * @param array $response Raw query result row from get_wholesale_vs_retail().
         *
         * @return array {
         *     @type array $chart_data    Donut/pie slices with label, value, and color.
         *     @type array $summary_items Share percentages with Tailwind color classes.
         *     @type float $wholesale_revenue_growth Week-over-week wholesale growth (%).
         *     @type float $retail_revenue_growth    Week-over-week retail growth (%).
         * }
         */
        public function map_response_for_visual_representation( $response ) {
            $total_wholesale_revenue              = (float) $response['total_wholesale_revenue'];
            $current_week_total_wholesale_revenue = (float) $response['current_week_total_wholesale_revenue'];
            $last_week_total_wholesale_revenue    = (float) $response['last_week_total_wholesale_revenue'];
            $total_retail_revenue                 = (float) $response['total_retail_revenue'];
            $last_week_total_retail_revenue       = (float) $response['last_week_total_retail_revenue'];
            $current_week_total_retail_revenue    = (float) $response['current_week_total_retail_revenue'];
            $previous_week_label                  = __( 'Vs Last Week', 'woocommerce-wholesale-prices-premium' );

            $total_sales          = (float) ( $total_wholesale_revenue + $total_retail_revenue );
            $wholesale_percentage = $total_sales > 0 ? wc_format_decimal( ( ( $total_wholesale_revenue / $total_sales ) * 100 ), 1 ) . '%' : 0;
            $retail_percentage    = $total_sales > 0 ? wc_format_decimal( ( ( $total_retail_revenue / $total_sales ) * 100 ), 1 ) . '%' : 0;

            $wholesale_revenue_growth = $last_week_total_wholesale_revenue > 0
                ? wc_format_decimal( ( ( $current_week_total_wholesale_revenue - $last_week_total_wholesale_revenue ) / $last_week_total_wholesale_revenue ) * 100, 2 ) . '%'
                : ( $current_week_total_wholesale_revenue > 0 ? 'N/A' : '0.0%' );

            $retail_revenue_growth = $last_week_total_retail_revenue > 0
                ? wc_format_decimal( ( ( $current_week_total_retail_revenue - $last_week_total_retail_revenue ) / $last_week_total_retail_revenue ) * 100, 2 ) . '%'
                : ( $current_week_total_retail_revenue > 0 ? 'N/A' : '0.0%' );

            $wholesale_revenue_growth_weekly_trend = 0.0 === $current_week_total_wholesale_revenue && 0.0 === $last_week_total_wholesale_revenue ?
                                                'flat' :
                                                ( $current_week_total_wholesale_revenue >= $last_week_total_wholesale_revenue ?
                                                'up' : 'down' );

            $retail_revenue_growth_weekly_trend = 0.0 === $current_week_total_retail_revenue && 0.0 === $last_week_total_retail_revenue ?
                                                'flat' :
                                                ( $current_week_total_retail_revenue >= $last_week_total_retail_revenue ?
                                                'up' : 'down' );

            $chart_data = array(
                array(
                    'label' => __( 'Wholesale', 'woocommerce-wholesale-prices-premium' ),
                    'value' => $total_wholesale_revenue,
                    'color' => '#46BF93',
                ),
                array(
                    'label' => __( 'Retail', 'woocommerce-wholesale-prices-premium' ),
                    'value' => (float) $response['total_retail_revenue'],
                    'color' => '#3b82f6',
                ),
            );

            $summary_items = array(
                array(
                    'label'      => __( 'Wholesale Share', 'woocommerce-wholesale-prices-premium' ),
                    'percentage' => $wholesale_percentage,
                    'colorClass' => 'text-[#46BF93]',
                    'bgClass'    => 'bg-[#46BF93]',
                ),
                array(
                    'label'      => __( 'Retail Share', 'woocommerce-wholesale-prices-premium' ),
                    'percentage' => $retail_percentage,
                    'colorClass' => 'text-blue-600',
                    'bgClass'    => 'bg-blue-600',
                ),
            );

            return array(
                'chart_data'            => $chart_data,
                'summary_items'         => $summary_items,
                'wholesale_data'        => array(
                    'revenue'                     => wc_price(
                        wc_format_decimal( $current_week_total_wholesale_revenue, 2 ),
                        array(
                            'in_span' => false,
                        )
                    ),
                    'revenue_weekly_growth'       => $wholesale_revenue_growth,
                    'revenue_growth_weekly_trend' => $wholesale_revenue_growth_weekly_trend,
                ),
                'retail_data'           => array(
                    'revenue'                     => wc_price(
                        wc_format_decimal( $current_week_total_retail_revenue, 2 ),
                        array(
                            'in_span' => false,
                        )
                    ),
                    'revenue_weekly_growth'       => $retail_revenue_growth,
                    'revenue_growth_weekly_trend' => $retail_revenue_growth_weekly_trend,
                ),
                'total_sales'           => wc_price(
                    wc_format_decimal( $total_sales ),
                    array(
                        'in_span' => false,
                    )
                ),
                'previous_period_label' => $previous_week_label,
            );
        }

        /**
         * Build placeholder values for the wholesale vs retail CTE prepared statement.
         *
         * Placeholder order matches the CTE definition order in build_wholesale_vs_retail_revenue():
         * 1. wholesale CTE              — main period
         * 2. total CTE                  — main period
         * 3. last_week_wholesale CTE    — previous week
         * 4. last_week_total CTE        — previous week
         * 5. current_week_wholesale CTE — current week (Mon–Sun)
         * 6. current_week_total CTE     — current week (Mon–Sun)
         *
         * When $role is set, the total CTE also receives the role placeholder so it can
         * exclude other wholesale roles from the total, keeping retail = total − wholesale accurate.
         *
         * @param string $start_date Main period start (Y-m-d H:i:s).
         * @param string $end_date   Main period end (Y-m-d H:i:s).
         * @param string $role       Wholesale role slug (optional filter).
         *
         * @return array Placeholder values in query order.
         */
        public function build_wholesale_vs_retail_placeholders( $start_date, $end_date, $role = '' ) {
            $one_week_prior_dates      = $this->get_last_week_date_range();
            $one_week_prior_start_date = $one_week_prior_dates['start_date'];
            $one_week_prior_end_date   = $one_week_prior_dates['end_date'];

            $current_week_dates      = $this->get_current_week_date_range();
            $current_week_start_date = $current_week_dates['start_date'];
            $current_week_end_date   = $current_week_dates['end_date'];

            // CTE order: wholesale (main), total (main), last_week_wholesale, last_week_total, current_week_wholesale, current_week_total.
            $placeholders = $this->wholesale_placeholders_builder( $start_date, $end_date, $role );
            $placeholders = array_merge( $placeholders, $this->all_orders_placeholders_builder( $start_date, $end_date, $role ) );
            $placeholders = array_merge( $placeholders, $this->wholesale_placeholders_builder( $one_week_prior_start_date, $one_week_prior_end_date, $role ) );
            $placeholders = array_merge( $placeholders, $this->all_orders_placeholders_builder( $one_week_prior_start_date, $one_week_prior_end_date, $role ) );
            $placeholders = array_merge( $placeholders, $this->wholesale_placeholders_builder( $current_week_start_date, $current_week_end_date, $role ) );
            $placeholders = array_merge( $placeholders, $this->all_orders_placeholders_builder( $current_week_start_date, $current_week_end_date, $role ) );

            return $placeholders;
        }

        /**
         * Build CTE-based SQL query that computes wholesale, retail, and total revenue.
         *
         * Uses six CTEs (two when $include_previous_week is false):
         * - wholesale / total:                        main period.
         * - last_week_wholesale / last_week_total:    previous week for trend comparison.
         * - current_week_wholesale / current_week_total: current week (Mon–Sun).
         *
         * Retail revenue = total − wholesale, which correctly includes pre-plugin orders
         * (no _wwpp_order_type meta) as retail, consistent with how the free WWP plugin
         * defines retail orders (meta = 'retail' OR meta NOT EXISTS).
         *
         * @param string  $start_date Main period start (Y-m-d H:i:s).
         * @param string  $end_date   Main period end (Y-m-d H:i:s).
         * @param string  $role       Wholesale role slug (optional filter).
         * @param boolean $is_hpos    Whether HPOS is enabled.
         * @param string  $prefix     Database table prefix (e.g. 'wp_').
         *
         * Note: ($start_date| $end_date are used for only total wholesale, total retail revenue).
         *
         * @return string SQL string.
         */
        protected function build_wholesale_vs_retail_revenue( $start_date, $end_date, $role = '', $is_hpos = false, $prefix = 'wp_' ) {
            $one_week_prior_dates = $this->get_last_week_date_range();

            $one_week_prior_start_date = $one_week_prior_dates['start_date'];
            $one_week_prior_end_date   = $one_week_prior_dates['end_date'];

            $current_week_dates      = $this->get_current_week_date_range();
            $current_week_start_date = $current_week_dates['start_date'];
            $current_week_end_date   = $current_week_dates['end_date'];

            $wholesale_cte              = $this->build_wholesale_cte_body( $start_date, $end_date, $role, $is_hpos, $prefix );
            $total_cte                  = $this->build_total_cte_body( $start_date, $end_date, $role, $is_hpos, $prefix );
            $last_week_wholesale_cte    = $this->build_wholesale_cte_body( $one_week_prior_start_date, $one_week_prior_end_date, $role, $is_hpos, $prefix );
            $last_week_total_cte        = $this->build_total_cte_body( $one_week_prior_start_date, $one_week_prior_end_date, $role, $is_hpos, $prefix );
            $current_week_wholesale_cte = $this->build_wholesale_cte_body( $current_week_start_date, $current_week_end_date, $role, $is_hpos, $prefix );
            $current_week_total_cte     = $this->build_total_cte_body( $current_week_start_date, $current_week_end_date, $role, $is_hpos, $prefix );

            return "WITH
				wholesale AS (
					{$wholesale_cte}
				),
				total AS (
					{$total_cte}
				),
				last_week_wholesale AS (
					{$last_week_wholesale_cte}
				),
				last_week_total AS (
					{$last_week_total_cte}
				),
				current_week_wholesale AS (
					{$current_week_wholesale_cte}
				),
				current_week_total AS (
					{$current_week_total_cte}
				)
			SELECT
				COALESCE( wholesale.order_total, 0 )                                                                                             AS total_wholesale_revenue,
				COALESCE( last_week_wholesale.order_total, 0 )                                                                                   AS last_week_total_wholesale_revenue,
				CAST( ( COALESCE( total.order_total, 0 ) - COALESCE( wholesale.order_total, 0 ) ) AS DECIMAL(10, 2) )                            AS total_retail_revenue,
				CAST( ( COALESCE( last_week_total.order_total, 0 ) - COALESCE( last_week_wholesale.order_total, 0 ) ) AS DECIMAL(10, 2) )        AS last_week_total_retail_revenue,
				COALESCE( current_week_wholesale.order_total, 0 )                                                                                AS current_week_total_wholesale_revenue,
				CAST( ( COALESCE( current_week_total.order_total, 0 ) - COALESCE( current_week_wholesale.order_total, 0 ) ) AS DECIMAL(10, 2) )  AS current_week_total_retail_revenue
			FROM wholesale, total, last_week_wholesale, last_week_total, current_week_wholesale, current_week_total;";
        }

        /**
         * Build the body of the 'wholesale' CTE.
         *
         * Sums order totals for completed wholesale orders only.
         * Filters by _wwpp_order_type = 'wholesale' (hardcoded in SQL — never user-supplied).
         * Optionally narrows to a specific wholesale role via _wwpp_wholesale_order_type.
         *
         * HPOS: total_amount is a direct column on wc_orders — JOIN wc_orders_meta for order type.
         * Legacy: JOIN postmeta for _order_total (revenue source), JOIN postmeta for _wwpp_order_type filter.
         *
         * @param string  $start_date Start date (Y-m-d H:i:s).
         * @param string  $end_date   End date (Y-m-d H:i:s).
         * @param string  $role       Wholesale role slug; empty for all wholesale roles.
         * @param boolean $is_hpos    Whether HPOS is enabled.
         * @param string  $prefix     DB table prefix.
         *
         * @return string SQL SELECT body for the CTE (no trailing semicolon).
         */
        protected function build_wholesale_cte_body( $start_date, $end_date, $role = '', $is_hpos = false, $prefix = 'wp_' ) {
            $map          = $this->get_table_mapping( $prefix, $is_hpos );
            $table_column = $is_hpos ? "{$map['table_alias']}.{$map['meta_value_column']}" : "{$map['meta_table_alias']}.{$map['meta_value_column']}";

            // Optional role filter — meta_key hardcoded, meta_value is a %s placeholder.
            $role_filter = '';
            if ( $is_hpos && ! empty( $role ) ) {
                $role_filter = " JOIN {$map['meta_table_name']} {$map['meta_table_alias']}_ ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}_.{$map['meta_id_column']} AND {$map['meta_table_alias']}_.meta_key = '_wwpp_wholesale_order_type' AND {$map['meta_table_alias']}_.meta_value = %s";
            } elseif ( ! $is_hpos && ! empty( $role ) ) {
                $role_filter = " JOIN {$map['meta_table_name']} {$map['meta_table_alias']}__ ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}__.{$map['meta_id_column']} AND {$map['meta_table_alias']}__.meta_key = '_wwpp_wholesale_order_type' AND {$map['meta_table_alias']}__.meta_value = %s";
            }

            // HPOS: JOIN on _wwpp_order_type = 'wholesale'; total_amount is a direct column.
            // Legacy: JOIN _order_total meta for the revenue value, JOIN _wwpp_order_type = 'wholesale' to filter.
            $order_type_join = $is_hpos
                ? "JOIN {$map['meta_table_name']} {$map['meta_table_alias']} ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}.{$map['meta_id_column']} AND {$map['meta_table_alias']}.meta_key = '_wwpp_order_type' AND {$map['meta_table_alias']}.meta_value = 'wholesale'"
                : "JOIN {$map['meta_table_name']} {$map['meta_table_alias']} ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}.{$map['meta_id_column']} AND {$map['meta_table_alias']}.meta_key = '_order_total'
				   JOIN {$map['meta_table_name']} {$map['meta_table_alias']}_ ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}_.{$map['meta_id_column']} AND {$map['meta_table_alias']}_.meta_key = '_wwpp_order_type' AND {$map['meta_table_alias']}_.meta_value = 'wholesale'";

            return "SELECT CAST( SUM( {$table_column} ) AS DECIMAL(10, 2) ) AS order_total
					FROM {$map['table_name']} {$map['table_alias']}
						{$order_type_join}{$role_filter}
					WHERE {$map['table_alias']}.{$map['post_type_column']} = %s
						AND {$map['table_alias']}.{$map['post_status_column']} = %s
					{$this->build_date_query( $start_date, $end_date, $is_hpos )}";
        }

        /**
         * Build the body of the 'total' CTE.
         *
         * When no role is given: sums ALL completed orders (retail = total − all_wholesale).
         * When a role is given: scopes to (this role's orders) + (retail/no-meta orders only) so
         * that other wholesale roles do not inflate the retail figure. The filter is:
         *   (matched role JOIN IS NOT NULL) OR (_wwpp_order_type = 'retail') OR (_wwpp_order_type NOT EXISTS)
         *
         * HPOS: total_amount is a direct column on wc_orders — no JOIN needed for the revenue value.
         * Legacy: JOIN postmeta for _order_total to read the revenue value.
         *
         * @param string  $start_date Start date (Y-m-d H:i:s).
         * @param string  $end_date   End date (Y-m-d H:i:s).
         * @param string  $role       Wholesale role slug; empty to include all orders.
         * @param boolean $is_hpos    Whether HPOS is enabled.
         * @param string  $prefix     DB table prefix.
         *
         * @return string SQL SELECT body for the CTE (no trailing semicolon).
         */
        protected function build_total_cte_body( $start_date, $end_date, $role = '', $is_hpos = false, $prefix = 'wp_' ) {
            $map          = $this->get_table_mapping( $prefix, $is_hpos );
            $table_column = $is_hpos ? "{$map['table_alias']}.{$map['meta_value_column']}" : "{$map['meta_table_alias']}.{$map['meta_value_column']}";

            if ( empty( $role ) ) {
                // No role: sum every completed order; retail = total − wholesale handles pre-plugin orders.
                $join_query  = $is_hpos
                    ? ''
                    : "JOIN {$map['meta_table_name']} {$map['meta_table_alias']} ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}.{$map['meta_id_column']} AND {$map['meta_table_alias']}.meta_key = '_order_total'";
                $where_extra = '';
            } else { //phpcs:ignore
                // Role filter: include only (this role's orders) + (retail/no-meta orders).
                // LEFT JOIN on _wwpp_order_type to detect retail vs. absent meta.
                // LEFT JOIN on _wwpp_wholesale_order_type (with role value) to detect role-matched orders.
                if ( $is_hpos ) {
                    $join_query  = "LEFT JOIN {$map['meta_table_name']} {$map['meta_table_alias']} ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}.{$map['meta_id_column']} AND {$map['meta_table_alias']}.meta_key = '_wwpp_order_type'
					LEFT JOIN {$map['meta_table_name']} {$map['meta_table_alias']}_ ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}_.{$map['meta_id_column']} AND {$map['meta_table_alias']}_.meta_key = '_wwpp_wholesale_order_type' AND {$map['meta_table_alias']}_.meta_value = %s";
                    $where_extra = "AND (
						{$map['meta_table_alias']}_.{$map['meta_id_column']} IS NOT NULL
						OR {$map['meta_table_alias']}.meta_value = 'retail'
						OR {$map['meta_table_alias']}.{$map['meta_id_column']} IS NULL
					)";
                } else {
                    $join_query  = "JOIN {$map['meta_table_name']} {$map['meta_table_alias']} ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}.{$map['meta_id_column']} AND {$map['meta_table_alias']}.meta_key = '_order_total'
					LEFT JOIN {$map['meta_table_name']} {$map['meta_table_alias']}_ ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}_.{$map['meta_id_column']} AND {$map['meta_table_alias']}_.meta_key = '_wwpp_order_type'
					LEFT JOIN {$map['meta_table_name']} {$map['meta_table_alias']}__ ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}__.{$map['meta_id_column']} AND {$map['meta_table_alias']}__.meta_key = '_wwpp_wholesale_order_type' AND {$map['meta_table_alias']}__.meta_value = %s";
                    $where_extra = "AND (
						{$map['meta_table_alias']}__.{$map['meta_id_column']} IS NOT NULL
						OR {$map['meta_table_alias']}_.meta_value = 'retail'
						OR {$map['meta_table_alias']}_.{$map['meta_id_column']} IS NULL
					)";
                }
            }

            return "SELECT CAST( SUM( {$table_column} ) AS DECIMAL(10, 2) ) AS order_total
					FROM {$map['table_name']} {$map['table_alias']}
						{$join_query}
					WHERE {$map['table_alias']}.{$map['post_type_column']} = %s
						AND {$map['table_alias']}.{$map['post_status_column']} = %s
						{$where_extra}
					{$this->build_date_query( $start_date, $end_date, $is_hpos )}";
        }

        /**
         * Build placeholder values for the wholesale CTE.
         *
         * Placeholder order: [(optional role), 'shop_order', 'wc-completed', (optional dates)].
         * Meta keys (_wwpp_order_type, _wwpp_wholesale_order_type, _order_total) are hardcoded in SQL.
         *
         * @param string $start_date Start date (Y-m-d H:i:s).
         * @param string $end_date   End date (Y-m-d H:i:s).
         * @param string $role       Wholesale role slug; empty for all wholesale roles.
         *
         * @return array
         */
        protected function wholesale_placeholders_builder( $start_date = '', $end_date = '', $role = '' ) {
            $placeholders   = ! empty( $role ) ? array( $role ) : array();
            $placeholders[] = 'shop_order';
            $placeholders[] = 'wc-completed';

            if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
                $placeholders[] = $start_date;
                $placeholders[] = $end_date;
            } elseif ( ! empty( $start_date ) ) {
                $placeholders[] = $start_date;
            } elseif ( ! empty( $end_date ) ) {
                $placeholders[] = $end_date;
            }

            return $placeholders;
        }

        /**
         * Build placeholder values for the total-orders CTE.
         *
         * Without role: ['shop_order', 'wc-completed', (optional dates)].
         * With role:    [(role), 'shop_order', 'wc-completed', (optional dates)] — the role
         * placeholder feeds the LEFT JOIN on _wwpp_wholesale_order_type in build_total_cte_body().
         *
         * @param string $start_date Start date (Y-m-d H:i:s).
         * @param string $end_date   End date (Y-m-d H:i:s).
         * @param string $role       Wholesale role slug; empty to include all orders.
         *
         * @return array
         */
        protected function all_orders_placeholders_builder( $start_date = '', $end_date = '', $role = '' ) {
            $placeholders   = ! empty( $role ) ? array( $role ) : array();
            $placeholders[] = 'shop_order';
            $placeholders[] = 'wc-completed';

            if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
                $placeholders[] = $start_date;
                $placeholders[] = $end_date;
            } elseif ( ! empty( $start_date ) ) {
                $placeholders[] = $start_date;
            } elseif ( ! empty( $end_date ) ) {
                $placeholders[] = $end_date;
            }

            return $placeholders;
        }
    }
}
