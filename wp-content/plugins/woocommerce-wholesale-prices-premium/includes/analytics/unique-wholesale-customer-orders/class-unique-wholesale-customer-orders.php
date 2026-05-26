<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


if ( ! class_exists( 'Unique_Wholesale_Customer_Orders' ) ) {

    /**
     * Unique Wholesale Customer Orders Payload.
     */
    class Unique_Wholesale_Customer_Orders {

        use WWPP_Analytics_Query_Trait;
        use WWPP_Analytics_Date_Time_Trait;

        /**
         * Contains the instance of the 'Unique_Wholesale_Customer_Orders' class.
         *
         * @var object
         */
        private static $instance = null;

        /**
         * Contains the singleton instance of the 'Unique_Wholesale_Customer_Orders'.
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
         * Get the overall unique wholesale customer orders summary.
         *
         * Executes the composite summary query and returns formatted counts, growth rates,
         * and trend labels for all-time, current/last week, current/last month, and current/last year.
         *
         * @since 2.1.0
         *
         * @param string  $prefix              The DB table prefix.
         * @param string  $role                Optional wholesale role to filter by.
         * @param boolean $is_hpos             Whether HPOS is enabled.
         * @param string  $current_start_date  Optional start date filter (Y-m-d H:i:s) for the all-time figure.
         * @param string  $current_end_date    Optional end date filter (Y-m-d H:i:s) for the all-time figure.
         * @param string  $previous_start_date Optional start date for the previous period comparison.
         * @param string  $previous_end_date   Optional end date for the previous period comparison.
         *
         * @return array|\WP_Error Summary data array, or WP_Error on DB failure.
         */
        public function get_unique_customer_orders_summary( $prefix, $role, $is_hpos, $current_start_date = '', $current_end_date = '', $previous_start_date = '', $previous_end_date = '' ) {
            global $wpdb;

            $place_holders = $this->get_overall_customer_orders_placeholders_builder( $current_start_date, $current_end_date, $role, $previous_start_date, $previous_end_date );
            $query         = $this->get_overall_customer_orders_query( $prefix, $role, $is_hpos, $current_start_date, $current_end_date, $previous_start_date, $previous_end_date );
            $results       = $wpdb->get_results( $wpdb->prepare( $query, $place_holders ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built safely with prepare.

            if ( ! empty( $wpdb->last_error ) ) {
                return new \WP_Error( 'db_error', __( 'A database error occurred.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 500 ) );
            }

            $data = isset( $results[0] ) && ! empty( $results[0] ) ? $results[0] : array();

            return $this->build_unique_customer_orders_summary_response( $data, $previous_start_date, $previous_end_date );
        }

        /**
         * Build the summary response array from raw unique customer orders query data.
         *
         * @since 2.1.0
         *
         * @param array  $data                Raw associative row returned by get_unique_customer_orders_summary().
         * @param string $previous_start_date Start date of the previous period (Y-m-d format).
         * @param string $previous_end_date   End date of the previous period (Y-m-d format).
         *
         * @return array Formatted summary with customer counts, growth rates, and trend labels.
         */
        public function build_unique_customer_orders_summary_response( $data, $previous_start_date, $previous_end_date ) {
            $all_time_customers      = isset( $data['overall_total_customers'] ) ? (int) $data['overall_total_customers'] : 0;
            $current_week_customers  = isset( $data['total_customers_current_week'] ) ? (int) $data['total_customers_current_week'] : 0;
            $last_week_customers     = isset( $data['total_customers_last_week'] ) ? (int) $data['total_customers_last_week'] : 0;
            $current_year_customers  = isset( $data['total_customers_current_year'] ) ? (int) $data['total_customers_current_year'] : 0;
            $last_year_customers     = isset( $data['total_customers_last_year'] ) ? (int) $data['total_customers_last_year'] : 0;
            $current_month_customers = isset( $data['total_customers_current_month'] ) ? (int) $data['total_customers_current_month'] : 0;
            $last_month_customers    = isset( $data['total_customers_last_month'] ) ? (int) $data['total_customers_last_month'] : 0;
            $previous_period_count   = isset( $data['previous_period_total_customers'] ) ? (int) $data['previous_period_total_customers'] : 0;
            $previous_period_label   = ! empty( $previous_start_date ) && ! empty( $previous_end_date ) ? __( 'Previous Period', 'woocommerce-wholesale-prices-premium' ) : __( 'Previous Year', 'woocommerce-wholesale-prices-premium' );
            $summary                 = array();

            // i) All-time unique customer count.
            $summary['unique_customer_orders_all_time'] = $all_time_customers;

            // ii) Difference between all-time and previous period.
            $summary['unique_customer_orders_difference'] = $all_time_customers - $previous_period_count;

            // iii) Weekly growth: (current - last) / last * 100.
            $summary['unique_customer_orders_weekly_growth'] = $last_week_customers > 0
                ? wc_format_decimal( ( ( ( $current_week_customers - $last_week_customers ) / $last_week_customers ) * 100 ), 2 ) . '%'
                : ( $current_week_customers > 0 ? 'N/A' : '0%' );

            // iv) Yearly growth: (current year - last year) / last year * 100.
            $summary['unique_customer_orders_yearly_growth'] = $last_year_customers > 0
                ? wc_format_decimal( ( ( ( $current_year_customers - $last_year_customers ) / $last_year_customers ) * 100 ), 0 ) . '%'
                : ( $current_year_customers > 0 ? 'N/A' : '0%' );

            // v) Monthly growth: (current month - last month) / last month * 100.
            $summary['unique_customer_orders_monthly_growth'] = $last_month_customers > 0
                ? wc_format_decimal( ( ( ( $current_month_customers - $last_month_customers ) / $last_month_customers ) * 100 ), 2 ) . '%'
                : ( $current_month_customers > 0 ? 'N/A' : '0%' );

            // vi) Weekly trend label.
            if ( 0 === $current_week_customers && 0 === $last_week_customers ) {
                $summary['unique_customer_orders_weekly_trend'] = 'flat';
            } else {
                $summary['unique_customer_orders_weekly_trend'] = $current_week_customers >= $last_week_customers ? 'up' : 'down';
            }

            // vii) All-time growth vs previous period.
            $summary['unique_customer_orders_all_time_growth'] = $previous_period_count > 0
                ? wc_format_decimal( ( ( ( $all_time_customers - $previous_period_count ) / $previous_period_count ) * 100 ), 2 ) . '%'
                : ( $all_time_customers > 0 ? 'N/A' : '0%' );

            // viii) All-time trend label.
            if ( 0 === $all_time_customers && 0 === $previous_period_count ) {
                $summary['unique_customer_orders_all_time_trend'] = 'flat';
            } else {
                $summary['unique_customer_orders_all_time_trend'] = $all_time_customers >= $previous_period_count ? 'up' : 'down';
            }

            // ix) Yearly trend label.
            if ( 0 === $current_year_customers && 0 === $last_year_customers ) {
                $summary['unique_customer_orders_yearly_trend'] = 'flat';
            } else {
                $summary['unique_customer_orders_yearly_trend'] = $current_year_customers >= $last_year_customers ? 'up' : 'down';
            }

            $label_value                      = ! empty( $previous_start_date ) && ! empty( $previous_end_date ) ? $previous_period_count : $last_year_customers;
            $summary['previous_period_label'] = $label_value . ' ' . $previous_period_label;

            $summary['last_week_unique_customer_orders_label'] = sprintf(
                /* translators: %d: number of unique wholesale customers */
                __( 'Last week unique wholesale customers %d', 'woocommerce-wholesale-prices-premium' ),
                $last_week_customers
            );

            $summary['current_week_unique_customer_orders_label'] = sprintf(
                /* translators: %d: number of unique wholesale customers */
                __( 'Current week unique wholesale customers %d', 'woocommerce-wholesale-prices-premium' ),
                $current_week_customers
            );

            return $summary;
        }


        /**
         * Build the composite SQL query for the unique customer orders summary.
         *
         * Assembles subqueries for all-time, current/last week, current/last month,
         * current/last year, and optionally a custom previous period into a single
         * cross-joined SELECT statement.
         *
         * @since 2.1.0
         *
         * @param string  $prefix              The DB table prefix.
         * @param string  $role                Optional wholesale role to filter by.
         * @param boolean $is_hpos             Whether HPOS is enabled.
         * @param string  $current_start_date  Optional start date filter (Y-m-d H:i:s) for the all-time figure.
         * @param string  $current_end_date    Optional end date filter (Y-m-d H:i:s) for the all-time figure.
         * @param string  $previous_start_date Optional start date for the previous period comparison subquery.
         * @param string  $previous_end_date   Optional end date for the previous period comparison subquery.
         *
         * @return string Raw SQL query string (not yet prepared).
         */
        public function get_overall_customer_orders_query( $prefix, $role, $is_hpos, $current_start_date = '', $current_end_date = '', $previous_start_date = '', $previous_end_date = '' ) {
            /**
             * Last week dates.
             */
            $one_week_ago_dates      = $this->get_last_week_date_range();
            $one_week_ago_start_date = $one_week_ago_dates['start_date'];
            $one_week_ago_end_date   = $one_week_ago_dates['end_date'];

            /**
             * Current month dates.
             */
            $current_month_dates      = $this->get_current_month_date_range();
            $current_month_start_date = $current_month_dates['start_date'];
            $current_month_end_date   = $current_month_dates['end_date'];

            /**
             * Last month dates.
             */
            $last_month_dates      = $this->get_last_month_date_range();
            $last_month_start_date = $last_month_dates['start_date'];
            $last_month_end_date   = $last_month_dates['end_date'];

            /**
             * Current week dates
             */
            $current_week_dates      = $this->get_current_week_date_range();
            $current_week_start_date = $current_week_dates['start_date'];
            $current_week_end_date   = $current_week_dates['end_date'];

            /**
             * Current year dates
             */
            $current_year_dates      = $this->get_current_year_date_range();
            $current_year_start_date = $current_year_dates['start_date'];
            $current_year_end_date   = $current_year_dates['end_date'];

            /**
             * Previous year dates
             */
            $previous_year_dates  = $this->get_last_year_date_range();
            $last_year_start_date = $previous_year_dates['start_date'];
            $last_year_end_date   = $previous_year_dates['end_date'];

            $previous_period_comparison_query  = ! empty( $previous_start_date ) && ! empty( $previous_end_date ) ? "( {$this->build_unique_customer_query($previous_start_date, $previous_end_date, $prefix, $role, $is_hpos, false)} ) AS previousPeriodQuery," : '';
            $previous_period_comparison_column = ! empty( $previous_start_date ) && ! empty( $previous_end_date ) ? 'previousPeriodQuery.total_customers as previous_period_total_customers,' : '0 AS previous_period_total_customers,';

            /**
             * Overall Query SQL.
             */
            $overall_query = "( {$this->build_unique_customer_query($current_start_date, $current_end_date, $prefix, $role, $is_hpos, false)} ) AS total_customers_overall";

            /**
             * Current month customer orders SQL.
             */
            $current_month_customers_query = "( {$this->build_unique_customer_query($current_month_start_date, $current_month_end_date, $prefix, $role, $is_hpos, false)} ) AS total_customers_current_month";

            /**
             * Last month customer orders SQL.
             */
            $last_month_customers_query = "( {$this->build_unique_customer_query($last_month_start_date, $last_month_end_date, $prefix, $role, $is_hpos, false)} ) AS total_customers_last_month";

            /**
             * Last week customer orders SQL.
             */
            $last_week_customers_query = "( {$this->build_unique_customer_query($one_week_ago_start_date, $one_week_ago_end_date, $prefix, $role, $is_hpos, false)} ) AS total_customers_last_week";

            /**
             * Current week customer orders SQL.
             */
            $current_week_customers_query = "( {$this->build_unique_customer_query($current_week_start_date, $current_week_end_date, $prefix, $role, $is_hpos, false)} ) AS total_customers_current_week";

            /**
             * Current year customer orders SQL
             */
            $current_year_customers_query = "( {$this->build_unique_customer_query($current_year_start_date, $current_year_end_date, $prefix, $role, $is_hpos, false)} ) AS total_customers_current_year";

            /**
             * Previous year customer orders SQL
             */
            $previous_year_customers_query = "( {$this->build_unique_customer_query($last_year_start_date, $last_year_end_date, $prefix, $role, $is_hpos, false)} ) AS total_customers_last_year";

            return "
                        SELECT 
                            total_customers_overall.total_customers AS overall_total_customers,
                            total_customers_current_month.total_customers AS total_customers_current_month,
                            total_customers_last_month.total_customers AS total_customers_last_month,
                            total_customers_current_week.total_customers AS total_customers_current_week,
                            {$previous_period_comparison_column}
                            total_customers_last_week.total_customers AS total_customers_last_week,
                            total_customers_current_year.total_customers AS total_customers_current_year,
                            total_customers_last_year.total_customers AS total_customers_last_year
                        FROM
                        {$overall_query},
                        {$current_month_customers_query},
                        {$last_month_customers_query},
                        {$current_week_customers_query},
                        {$previous_period_comparison_query}
                        {$last_week_customers_query},
                        {$current_year_customers_query},
                        {$previous_year_customers_query};
                    ";
        }

        /**
         * Build the ordered placeholders array for the composite summary query.
         *
         * Returns placeholder values in the exact order the subqueries appear in
         * get_overall_customer_orders_query(): all-time, current month, last month,
         * current week, optional previous period, last week, current year, last year.
         *
         * @since 2.1.0
         *
         * @param string $current_start_date  Optional start date filter for the all-time subquery.
         * @param string $current_end_date    Optional end date filter for the all-time subquery.
         * @param string $user_role           Optional wholesale role to filter by.
         * @param string $previous_start_date Optional start date for the previous period subquery.
         * @param string $previous_end_date   Optional end date for the previous period subquery.
         *
         * @return array Flat array of placeholder values for use with wpdb::prepare().
         */
        public function get_overall_customer_orders_placeholders_builder( $current_start_date = '', $current_end_date = '', $user_role = '', $previous_start_date = '', $previous_end_date = '' ) {

            /**
             * Last week dates.
             */
            $one_week_ago_dates      = $this->get_last_week_date_range();
            $one_week_ago_start_date = $one_week_ago_dates['start_date'];
            $one_week_ago_end_date   = $one_week_ago_dates['end_date'];

            /**
             * Current month dates.
             */
            $current_month_dates      = $this->get_current_month_date_range();
            $current_month_start_date = $current_month_dates['start_date'];
            $current_month_end_date   = $current_month_dates['end_date'];

            /**
             * Last month dates.
             */
            $last_month_dates      = $this->get_last_month_date_range();
            $last_month_start_date = $last_month_dates['start_date'];
            $last_month_end_date   = $last_month_dates['end_date'];

            /**
             * Current week dates
             */
            $current_week_dates      = $this->get_current_week_date_range();
            $current_week_start_date = $current_week_dates['start_date'];
            $current_week_end_date   = $current_week_dates['end_date'];

            /**
             * Previous year dates
             */
            $previous_year_dates  = $this->get_last_year_date_range();
            $last_year_start_date = $previous_year_dates['start_date'];
            $last_year_end_date   = $previous_year_dates['end_date'];

            /**
             * Current year dates
             */
            $current_year_dates      = $this->get_current_year_date_range();
            $current_year_start_date = $current_year_dates['start_date'];
            $current_year_end_date   = $current_year_dates['end_date'];

            $overall_query_placeholder_builder     = $this->placeholders_builder( $current_start_date, $current_end_date, $user_role );
            $current_month_placeholder_builder     = $this->placeholders_builder( $current_month_start_date, $current_month_end_date, $user_role );
            $last_month_placeholder_builder        = $this->placeholders_builder( $last_month_start_date, $last_month_end_date, $user_role );
            $current_week_placeholders_builder     = $this->placeholders_builder( $current_week_start_date, $current_week_end_date, $user_role );
            $previous_period_placeholders_builder  = ! empty( $previous_start_date ) && ! empty( $previous_end_date ) ? $this->placeholders_builder( $previous_start_date, $previous_end_date, $user_role ) : array();
            $last_week_period_placeholders_builder = $this->placeholders_builder( $one_week_ago_start_date, $one_week_ago_end_date, $user_role );
            $current_year_placeholders_builder     = $this->placeholders_builder( $current_year_start_date, $current_year_end_date, $user_role );
            $previous_year_placeholders_builder    = $this->placeholders_builder( $last_year_start_date, $last_year_end_date, $user_role );

            return array_merge( $overall_query_placeholder_builder, $current_month_placeholder_builder, $last_month_placeholder_builder, $current_week_placeholders_builder, $previous_period_placeholders_builder, $last_week_period_placeholders_builder, $current_year_placeholders_builder, $previous_year_placeholders_builder );
        }

        /**
         * Build non-comparison 'Unique Wholesale Customer Orders' query.
         *
         * @param string  $start_date The start date.
         * @param string  $end_date The end date.
         * @param string  $prefix The DB table prefix.
         * @param string  $role The user role.
         * @param boolean $is_hpos Check if HPOS is enabled.
         * @param boolean $include_query_semi_colon Include semi-colon on query.
         *
         * @return string
         */
        protected function build_unique_customer_query( $start_date, $end_date, $prefix = 'wp_', $role = '', $is_hpos = false, $include_query_semi_colon = true ) {
            $users_table       = "{$prefix}users";
            $users_table_alias = 'wu';
            $map               = $this->get_table_mapping( $prefix, $is_hpos );
            $author_id_column  = $is_hpos ? 'customer_id' : 'post_author';

            $semi_colon = $include_query_semi_colon ? ';' : '';

            $additional_sql = ! empty( $role ) ? "AND {$map['meta_table_alias']}_.meta_value = %s" : "AND {$map['meta_table_alias']}_.meta_value IS NOT NULL";

            $final_query = "SELECT COUNT( DISTINCT {$users_table_alias}.ID ) as total_customers 
                                FROM {$map['table_name']} {$map['table_alias']} 
                                    JOIN {$users_table} {$users_table_alias} ON {$map['table_alias']}.{$author_id_column} = {$users_table_alias}.{$map['id_column']} 
                                        AND {$map['table_alias']}.{$map['post_type_column']} = %s
                                        AND {$map['table_alias']}.{$map['post_status_column']} = %s
                                    JOIN {$map['meta_table_name']} {$map['meta_table_alias']} ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}.{$map['meta_id_column']} 
                                        AND {$map['meta_table_alias']}.meta_key = %s 
                                        AND {$map['meta_table_alias']}.meta_value = %s
                                    JOIN {$map['meta_table_name']} {$map['meta_table_alias']}_ ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}_.{$map['meta_id_column']} 
                                        AND {$map['meta_table_alias']}_.meta_key = %s 
                                        {$additional_sql}
                                    {$this->build_date_query($start_date, $end_date, $is_hpos)}{$semi_colon}";
            return $final_query;
        }

        /**
         * Build placeholders for query.
         *
         * @param string $start_date The start date for current period.
         * @param string $end_date The end date for current period.
         * @param string $role The user role.
         *
         * @return array
         */
        protected function placeholders_builder( $start_date = '', $end_date = '', $role = '' ) {
            $first_group  = array( 'shop_order', 'wc-completed' );
            $second_group = array( '_wwpp_order_type', 'wholesale' );
            $third_group  = array( '_wwpp_wholesale_order_type' );

            if ( ! empty( $role ) ) {
                $third_group[] = $role;
            }

            if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
                $third_group[] = $start_date;
                $third_group[] = $end_date;
            } elseif ( ! empty( $start_date ) ) {
                $third_group[] = $start_date;
            } elseif ( ! empty( $end_date ) ) {
                $third_group[] = $end_date;
            }

            return array_merge( $first_group, $second_group, $third_group );
        }
    }
}
