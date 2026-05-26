<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


if ( ! class_exists( 'Average_Order_Value' ) ) {

    /**
     * Average Order Value Payload.
     */
    class Average_Order_Value {

        use WWPP_Analytics_Query_Trait;
        use WWPP_Analytics_Date_Time_Trait;

        /**
         * Contains the instance of the 'Average_Order_Value' class.
         *
         * @var object
         */
        private static $instance = null;

        /**
         * Contains the singleton instance of the 'Average_Order_Value'.
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
         * Main function to get the average order value.
         *
         * @param boolean $comparison_mode Check if comparison mode is enabled.
         * @param boolean $is_hpos Check if HPOS is enabled.
         * @param string  $current_start_date Check if current start date is provided.
         * @param string  $current_end_date Check if current end date is provided.
         * @param string  $previous_start_date Check if current previous start date is provided.
         * @param string  $previous_end_date Check if previous end date is provided.
         * @param string  $user_role The user role requesting the sale.
         *
         * @return array
         */
        public function get_average_order_value( $comparison_mode = false, $is_hpos = false, $current_start_date = '', $current_end_date = '', $previous_start_date = '', $previous_end_date = '', $user_role = '' ) {
            global $wpdb;

            $place_holders = $comparison_mode ? array_merge( $this->placeholders_builder( $user_role, $current_start_date, $current_end_date, $is_hpos ), $this->placeholders_builder( $user_role, $previous_start_date, $previous_end_date, $is_hpos ) ) : $this->placeholders_builder( $user_role, $current_start_date, $current_end_date, $is_hpos );
            $query         = $comparison_mode ? $this->build_comparison_average_order_value_query( $current_start_date, $current_end_date, $previous_start_date, $previous_end_date, $is_hpos, $wpdb->prefix ) : $this->build_average_order_value_query( $current_start_date, $current_end_date, $wpdb->prefix, $is_hpos );
            $results       = $wpdb->get_results( $wpdb->prepare( $query, $place_holders ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built safely with prepare.

            if ( ! empty( $wpdb->last_error ) ) {
                return new \WP_Error( 'db_error', __( 'A database error occurred.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 500 ) );
            }

            $data = isset( $results[0] ) && ! empty( $results[0] ) ? $results[0] : array();

            $response = $data;

            return $response;
        }

        /**
         * Build non-comparison 'Average Order Value' query.
         *
         * @param string  $start_date The start date.
         * @param string  $end_date The end date.
         * @param string  $prefix The DB table prefix.
         * @param boolean $is_hpos Check if HPOS is enabled.
         * @param boolean $include_semi_colon Include semi-colon on the last query.
         *
         * @return string
         */
        protected function build_average_order_value_query( $start_date, $end_date, $prefix = 'wp_', $is_hpos = false, $include_semi_colon = true ) {
            $table_mapper     = $this->get_table_mapping( $prefix, $is_hpos );
            $semi_colon       = $include_semi_colon ? ';' : '';
            $sum_column_name  = $is_hpos ? "{$table_mapper['table_alias']}.{$table_mapper['meta_value_column']}" : "{$table_mapper['meta_table_alias']}.{$table_mapper['meta_value_column']}";
            $additional_query = $is_hpos ?
                                            "JOIN {$table_mapper['meta_table_name']} {$table_mapper['meta_table_alias']} 
                                                ON {$table_mapper['table_alias']}.{$table_mapper['id_column']} = {$table_mapper['meta_table_alias']}.{$table_mapper['meta_id_column']} 
                                                AND {$table_mapper['meta_table_alias']}.meta_key = %s 
                                                AND {$table_mapper['meta_table_alias']}.meta_value = %s"
                                            :
                                            "JOIN {$table_mapper['meta_table_name']} {$table_mapper['meta_table_alias']} 
                                                ON {$table_mapper['meta_table_alias']}.{$table_mapper['meta_id_column']} = {$table_mapper['table_alias']}.{$table_mapper['id_column']} 
                                                AND {$table_mapper['meta_table_alias']}.meta_key = %s 
                                            JOIN {$table_mapper['meta_table_name']} {$table_mapper['meta_table_alias']}_ 
                                                ON {$table_mapper['table_alias']}.{$table_mapper['id_column']} = {$table_mapper['meta_table_alias']}_.{$table_mapper['meta_id_column']} 
                                                AND {$table_mapper['meta_table_alias']}_.meta_key = %s 
                                                AND {$table_mapper['meta_table_alias']}_.meta_value = %s";

            $query = "SELECT COALESCE( CAST( SUM( $sum_column_name ) / COUNT(DISTINCT {$table_mapper['table_alias']}.{$table_mapper['id_column']}) AS DECIMAL(10, 2) ), 0.00 ) AS average_order_value 
                        FROM {$table_mapper['table_name']} {$table_mapper['table_alias']} 
                            {$additional_query}
                        WHERE {$table_mapper['table_alias']}.{$table_mapper['post_status_column']} = %s
                            AND {$table_mapper['table_alias']}.{$table_mapper['post_type_column']} = %s
                        {$this->build_date_query( $start_date, $end_date, $is_hpos )}{$semi_colon}";

            return $query;
        }

        /**
         * Build placeholders for query.
         *
         * @param string  $role The user role.
         * @param string  $start_date The start date for current period.
         * @param string  $end_date The end date for current period.
         * @param boolean $is_hpos Check if HPOS is enabled. Unused but required for call signature consistency.
         *
         * @return array
         */
        protected function placeholders_builder( $role, $start_date = '', $end_date = '', $is_hpos = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $is_hpos required for consistent call signature
            $first_group  = $is_hpos ? array() : array( '_order_total' );
            $second_group = ! empty( $role ) ? array( '_wwpp_wholesale_order_type', $role ) : array( '_wwpp_order_type', 'wholesale' );
            $last_group   = array( 'wc-completed', 'shop_order' );

            if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
                $last_group[] = $start_date;
                $last_group[] = $end_date;
            } elseif ( ! empty( $start_date ) ) {
                $last_group[] = $start_date;
            } elseif ( ! empty( $end_date ) ) {
                $last_group[] = $end_date;
            }

            return array_merge( $first_group, $second_group, $last_group );
        }

        /**
         * Build comparison 'Average Order Value' query.
         *
         * @param string  $current_period_start_date The start date for current period.
         * @param string  $current_period_end_date The end date for current period.
         * @param string  $previous_period_start_date The start date for previous period.
         * @param string  $previous_period_end_date The end date for previous period.
         * @param boolean $is_hpos Check if HPOS is enabled.
         * @param string  $prefix The table prefix.
         *
         * @return string
         */
        protected function build_comparison_average_order_value_query( $current_period_start_date, $current_period_end_date, $previous_period_start_date, $previous_period_end_date, $is_hpos = false, $prefix = 'wp_' ) {
            $current_period_query = "(
                {$this->build_average_order_value_query($current_period_start_date, $current_period_end_date, $prefix, $is_hpos, false)}
            ) as currentPeriodSubQuery";

            $previous_period_query = "(
                {$this->build_average_order_value_query($previous_period_start_date, $previous_period_end_date, $prefix, $is_hpos, false)}
            ) as previousPeriodSubQuery";

            $final_query = "SELECT currentPeriodSubQuery.average_order_value as current_period_average_value, 
                                   previousPeriodSubQuery.average_order_value as previous_period_average_value 
                                    FROM {$current_period_query}, {$previous_period_query};";

            return $final_query;
        }

        /**
         * Get the overall average order value summary covering all-time, current/last week, and current/last year.
         *
         * @since 2.1.0
         *
         * @param boolean $is_hpos              Whether HPOS is enabled.
         * @param string  $current_start_date   Optional start date filter (Y-m-d H:i:s) for the all-time figure.
         * @param string  $current_end_date     Optional end date filter (Y-m-d H:i:s) for the all-time figure.
         * @param string  $user_role            Optional wholesale role to filter by.
         * @param string  $previous_start_date  Optional start date filter (Y-m-d H:i:s) for the last year sub-query.
         * @param string  $previous_end_date    Optional end date filter (Y-m-d H:i:s) for the last year sub-query.
         *
         * @return array|\WP_Error Summary data array, or WP_Error on DB failure.
         */
        public function get_average_order_summary( $is_hpos = false, $current_start_date = '', $current_end_date = '', $user_role = '', $previous_start_date = '', $previous_end_date = '' ) {
            global $wpdb;

            $place_holders = $this->build_average_order_summary_placeholders( $is_hpos, $current_start_date, $current_end_date, $user_role, $previous_start_date, $previous_end_date );
            $query         = $this->build_average_order_summary_query( $wpdb->prefix, $is_hpos, $current_start_date, $current_end_date, $previous_start_date, $previous_end_date );
            $results       = $wpdb->get_results( $wpdb->prepare( $query, $place_holders ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built safely with prepare.

            if ( ! empty( $wpdb->last_error ) ) {
                return new \WP_Error( 'db_error', __( 'A database error occurred.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 500 ) );
            }

            $data = isset( $results[0] ) && ! empty( $results[0] ) ? $results[0] : array();

            return $this->build_summary_response( $data, $previous_start_date, $previous_end_date );
        }

        /**
         * Build the summary response array from raw average order value query data.
         *
         * @since 2.1.0
         *
         * @param array  $data                Raw associative row returned by get_average_order_summary().
         * @param string $previous_start_date Start date of the previous period (Y-m-d format), used to calculate growth rates.
         * @param string $previous_end_date   End date of the previous period (Y-m-d format), used to calculate growth rates.
         *
         * @return array Formatted summary with AOV figures, growth rates, trend labels, and human-readable strings.
         */
        public function build_summary_response( $data, $previous_start_date, $previous_end_date ) {
            $all_time_aov          = isset( $data['all_time_average_order_value'] ) ? (float) $data['all_time_average_order_value'] : 0.00;
            $current_week_aov      = isset( $data['current_week_average_order_value'] ) ? (float) $data['current_week_average_order_value'] : 0.00;
            $last_week_aov         = isset( $data['last_week_average_order_value'] ) ? (float) $data['last_week_average_order_value'] : 0.00;
            $current_year_aov      = isset( $data['current_year_average_order_value'] ) ? (float) $data['current_year_average_order_value'] : 0.00;
            $last_year_aov         = isset( $data['last_year_average_order_value'] ) ? (float) $data['last_year_average_order_value'] : 0.00;
            $current_month_aov     = isset( $data['current_month_average_order_value'] ) ? (float) $data['current_month_average_order_value'] : 0.00;
            $last_month_aov        = isset( $data['last_month_average_order_value'] ) ? (float) $data['last_month_average_order_value'] : 0.00;
            $previous_period_aov   = isset( $data['previous_period_order_value'] ) ? (float) $data['previous_period_order_value'] : 0.00;
            $previous_period_label = ! empty( $previous_start_date ) && ! empty( $previous_end_date ) ? __( 'Previous Period', 'woocommerce-wholesale-prices-premium' ) : __( 'Previous Year', 'woocommerce-wholesale-prices-premium' );
            $summary               = array();

            // ii) All-time AOV.
            $summary['average_order_value_all_time'] = wc_price( wc_format_decimal( $all_time_aov, 2 ), array( 'in_span' => false ) );

            // i) Difference between all-time and last year AOV.
            $summary['average_order_value_difference'] = wc_price( wc_format_decimal( ( $all_time_aov - $previous_period_aov ), 2 ), array( 'in_span' => false ) );

            // iii) Weekly growth: (current - last) / last * 100.
            $summary['average_order_value_weekly_growth'] = $last_week_aov > 0
                ? wc_format_decimal( ( ( ( $current_week_aov - $last_week_aov ) / $last_week_aov ) * 100 ), 2 ) . '%'
                : ( $current_week_aov > 0 ? 'N/A' : '0%' );

            // iv) Yearly growth: (current year - last year) / last year * 100.
            $summary['average_order_value_yearly_growth'] = $last_year_aov > 0
                ? wc_format_decimal( ( ( ( $current_year_aov - $last_year_aov ) / $last_year_aov ) * 100 ), 0 ) . '%'
                : ( $current_year_aov > 0 ? 'N/A' : '0%' );

            // v) Monthly growth: (current month - last month) / last month * 100.
            $summary['average_order_value_monthly_growth'] = $last_month_aov > 0
                ? wc_format_decimal( ( ( ( $current_month_aov - $last_month_aov ) / $last_month_aov ) * 100 ), 2 ) . '%'
                : ( $current_month_aov > 0 ? 'N/A' : '0%' );

            // vi) Weekly trend label.
            if ( 0.0 === $current_week_aov && 0.0 === $last_week_aov ) {
                $summary['average_order_value_weekly_trend'] = 'flat';
            } else {
                $summary['average_order_value_weekly_trend'] = $current_week_aov >= $last_week_aov ? 'up' : 'down';
            }

            // vii) All-time AOV growth vs previous period.
            $summary['average_order_value_all_time_growth'] = $previous_period_aov > 0
                ? wc_format_decimal( ( ( ( $all_time_aov - $previous_period_aov ) / $previous_period_aov ) * 100 ), 2 ) . '%'
                : ( $all_time_aov > 0 ? 'N/A' : '0%' );

            // viii) All-time trend label.
            if ( 0.0 === $all_time_aov && 0.0 === $previous_period_aov ) {
                $summary['average_order_value_all_time_trend'] = 'flat';
            } else {
                $summary['average_order_value_all_time_trend'] = $all_time_aov >= $previous_period_aov ? 'up' : 'down';
            }

            // ix) Yearly trend label.
            if ( 0.0 === $current_year_aov && 0.0 === $last_year_aov ) {
                $summary['average_order_value_yearly_trend'] = 'flat';
            } else {
                $summary['average_order_value_yearly_trend'] = $current_year_aov >= $last_year_aov ? 'up' : 'down';
            }

            $label_value = ! empty( $previous_start_date ) && ! empty( $previous_end_date ) ? $previous_period_aov : $last_year_aov;

            $summary['previous_period_label'] = wc_price( wc_format_decimal( $label_value, 2 ), array( 'in_span' => false ) )
                . ' ' . $previous_period_label;

            $summary['last_week_average_order_value_label'] = sprintf(
                /* translators: %s: formatted average order value amount */
                __( 'Last week average order value %s', 'woocommerce-wholesale-prices-premium' ),
                wc_price( wc_format_decimal( $last_week_aov, 2 ), array( 'in_span' => false ) )
            );

            $summary['current_week_average_order_value_label'] = sprintf(
                /* translators: %s: formatted average order value amount */
                __( 'Current week average order value %s', 'woocommerce-wholesale-prices-premium' ),
                wc_price( wc_format_decimal( $current_week_aov, 2 ), array( 'in_span' => false ) )
            );

            return $summary;
        }

        /**
         * Build the composite SQL query for the average order value summary.
         *
         * Combines seven sub-queries — all-time, current week, last week, current year, last year,
         * current month, and last month —
         * into a single SELECT using FROM with cross-joined sub-selects, matching the pattern used
         * in Total_Wholesale_Revenue::get_overall_wholesale_summary_query().
         *
         * @since 2.1.0
         *
         * @param string  $prefix             The DB table prefix. Defaults to 'wp_'.
         * @param boolean $is_hpos            Whether HPOS is enabled.
         * @param string  $current_start_date  Optional start date filter for the all-time sub-query (Y-m-d H:i:s).
         * @param string  $current_end_date    Optional end date filter for the all-time sub-query (Y-m-d H:i:s).
         * @param string  $previous_start_date Optional start date override for the last year sub-query (Y-m-d H:i:s).
         * @param string  $previous_end_date   Optional end date override for the last year sub-query (Y-m-d H:i:s).
         *
         * @return string SQL query string with %s placeholders.
         */
        protected function build_average_order_summary_query( $prefix = 'wp_', $is_hpos = false, $current_start_date = '', $current_end_date = '', $previous_start_date = '', $previous_end_date = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $user_role applied via placeholders for all-time and last year sub-queries
            $current_week_dates  = $this->get_current_week_date_range();
            $last_week_dates     = $this->get_last_week_date_range();
            $current_year_dates  = $this->get_current_year_date_range();
            $last_year_dates     = $this->get_last_year_date_range();
            $current_month_dates = $this->get_current_month_date_range();
            $last_month_dates    = $this->get_last_month_date_range();

            $current_week_start               = $current_week_dates['start_date'];
            $current_week_end                 = $current_week_dates['end_date'];
            $last_week_start                  = $last_week_dates['start_date'];
            $last_week_end                    = $last_week_dates['end_date'];
            $current_year_start               = $current_year_dates['start_date'];
            $current_year_end                 = $current_year_dates['end_date'];
            $last_year_start                  = $last_year_dates['start_date'];
            $last_year_end                    = $last_year_dates['end_date'];
            $current_month_start              = $current_month_dates['start_date'];
            $current_month_end                = $current_month_dates['end_date'];
            $last_month_start                 = $last_month_dates['start_date'];
            $last_month_end                   = $last_month_dates['end_date'];
            $previous_period_comparison_query = ! empty( $previous_start_date ) && ! empty( $previous_end_date ) ? '(' . $this->build_average_order_value_query( $previous_start_date, $previous_end_date, $prefix, $is_hpos, false ) . ') AS previousPeriodQuery,' : '';
            $previous_period_column           = ! empty( $previous_start_date ) && ! empty( $previous_end_date ) ? 'previousPeriodQuery.average_order_value as previous_period_order_value,' : '0.00 as previous_period_order_value,';

            return "SELECT
                        allTimeQuery.average_order_value        AS all_time_average_order_value,
                        currentWeekQuery.average_order_value    AS current_week_average_order_value,
                        lastWeekQuery.average_order_value       AS last_week_average_order_value,
                        currentYearQuery.average_order_value    AS current_year_average_order_value,
                        lastYearQuery.average_order_value       AS last_year_average_order_value,
                        currentMonthQuery.average_order_value   AS current_month_average_order_value,
                        {$previous_period_column}
                        lastMonthQuery.average_order_value      AS last_month_average_order_value
                    FROM
                        (
                            {$this->build_average_order_value_query( $current_start_date, $current_end_date, $prefix, $is_hpos, false )}
                        ) AS allTimeQuery,
                        (
                            {$this->build_average_order_value_query( $current_week_start, $current_week_end, $prefix, $is_hpos, false )}
                        ) AS currentWeekQuery,
                        (
                            {$this->build_average_order_value_query( $last_week_start, $last_week_end, $prefix, $is_hpos, false )}
                        ) AS lastWeekQuery,
                        (
                            {$this->build_average_order_value_query( $current_year_start, $current_year_end, $prefix, $is_hpos, false )}
                        ) AS currentYearQuery,
                        (
                            {$this->build_average_order_value_query( $last_year_start, $last_year_end, $prefix, $is_hpos, false )}
                        ) AS lastYearQuery,
                        (
                            {$this->build_average_order_value_query( $current_month_start, $current_month_end, $prefix, $is_hpos, false )}
                        ) AS currentMonthQuery,
                        {$previous_period_comparison_query}
                        (
                            {$this->build_average_order_value_query( $last_month_start, $last_month_end, $prefix, $is_hpos, false )}
                        ) AS lastMonthQuery;";
        }

        /**
         * Build the placeholder array for the average order value summary query.
         *
         * The order must match the sub-query order in build_average_order_summary_query():
         * all-time → current week → last week → current year → last year → current month → previous period (optional) → last month.
         *
         * @since 2.1.0
         *
         * @param boolean $is_hpos              Whether HPOS is enabled.
         * @param string  $current_start_date   Optional start date filter for the all-time sub-query (Y-m-d H:i:s).
         * @param string  $current_end_date     Optional end date filter for the all-time sub-query (Y-m-d H:i:s).
         * @param string  $user_role            Optional wholesale role to filter by.
         * @param string  $previous_start_date  Optional start date override for the last year sub-query (Y-m-d H:i:s).
         * @param string  $previous_end_date    Optional end date override for the last year sub-query (Y-m-d H:i:s).
         *
         * @return array Ordered placeholder values for $wpdb->prepare().
         */
        protected function build_average_order_summary_placeholders( $is_hpos = false, $current_start_date = '', $current_end_date = '', $user_role = '', $previous_start_date = '', $previous_end_date = '' ) {
            $current_week_dates  = $this->get_current_week_date_range();
            $last_week_dates     = $this->get_last_week_date_range();
            $current_year_dates  = $this->get_current_year_date_range();
            $last_year_dates     = $this->get_last_year_date_range();
            $current_month_dates = $this->get_current_month_date_range();
            $last_month_dates    = $this->get_last_month_date_range();

            $current_week_start  = $current_week_dates['start_date'];
            $current_week_end    = $current_week_dates['end_date'];
            $last_week_start     = $last_week_dates['start_date'];
            $last_week_end       = $last_week_dates['end_date'];
            $current_year_start  = $current_year_dates['start_date'];
            $current_year_end    = $current_year_dates['end_date'];
            $last_year_start     = $last_year_dates['start_date'];
            $last_year_end       = $last_year_dates['end_date'];
            $current_month_start = $current_month_dates['start_date'];
            $current_month_end   = $current_month_dates['end_date'];
            $last_month_start    = $last_month_dates['start_date'];
            $last_month_end      = $last_month_dates['end_date'];

            $previous_period_placeholders = ! empty( $previous_start_date ) && ! empty( $previous_end_date )
                ? $this->placeholders_builder( $user_role, $previous_start_date, $previous_end_date, $is_hpos )
                : array();

            return array_merge(
                $this->placeholders_builder( $user_role, $current_start_date, $current_end_date, $is_hpos ), // all time.
                $this->placeholders_builder( $user_role, $current_week_start, $current_week_end, $is_hpos ), // current week.
                $this->placeholders_builder( $user_role, $last_week_start, $last_week_end, $is_hpos ), // last week.
                $this->placeholders_builder( $user_role, $current_year_start, $current_year_end, $is_hpos ), // current year.
                $this->placeholders_builder( $user_role, $last_year_start, $last_year_end, $is_hpos ), // last year.
                $this->placeholders_builder( $user_role, $current_month_start, $current_month_end, $is_hpos ), // current month.
                $previous_period_placeholders, // previous period.
                $this->placeholders_builder( $user_role, $last_month_start, $last_month_end, $is_hpos ) // last month.
            );
        }
    }
}
