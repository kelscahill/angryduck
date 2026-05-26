<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Total_Wholesale_Revenue' ) ) {
    /**
     * Total Wholesale Revenue Payload.
     */
    class Total_Wholesale_Revenue {

        use WWPP_Analytics_Query_Trait;
        use WWPP_Analytics_Date_Time_Trait;

        /**
         * Contains the instance of the 'Total_Wholesale_Revenue' class.
         *
         * @var object
         */
        private static $instance = null;

        /**
         * Contains the singleton instance of the 'Total_Wholesale_Revenue'
         *
         * @return object the current instance of the class.
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Main function to get the sales
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
        public function get_sales( $comparison_mode = false, $is_hpos = false, $current_start_date = '', $current_end_date = '', $previous_start_date = '', $previous_end_date = '', $user_role = '' ) {
            global $wpdb;

            $place_holders = $comparison_mode
            ? array_merge(
                $this->placeholders_builder( $user_role, $current_start_date, $current_end_date, $is_hpos ),
                $this->placeholders_builder( $user_role, $previous_start_date, $previous_end_date, $is_hpos )
            )
            : $this->placeholders_builder( $user_role, $current_start_date, $current_end_date, $is_hpos );
            $query         = $comparison_mode ? $this->build_comparison_wholesale_revenue_query( $current_start_date, $current_end_date, $previous_start_date, $previous_end_date, $is_hpos, $wpdb->prefix ) : $this->build_wholesale_revenue_query( $current_start_date, $current_end_date, $wpdb->prefix, $is_hpos );
            $results       = $wpdb->get_results( $wpdb->prepare( $query, $place_holders ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            if ( ! empty( $wpdb->last_error ) ) {
                return new \WP_Error( 'db_error', __( 'A database error occurred.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 500 ) );
            }

            $data = isset( $results[0] ) && ! empty( $results[0] ) ? $results[0] : array();

            $response = $this->build_response( $data, $comparison_mode );

            return $response;
        }

        /**
         * Get the overall wholesale revenue summary.
         *
         * @since 2.1.0
         *
         * @param boolean $is_hpos              Whether HPOS is enabled.
         * @param string  $start_date           Optional start date filter (Y-m-d H:i:s) for the overall revenue figure.
         * @param string  $end_date             Optional end date filter (Y-m-d H:i:s) for the overall revenue figure.
         * @param string  $user_role            Optional wholesale role to filter by.
         * @param string  $previous_start_date  Optional start date filter (Y-m-d H:i:s) for the previous period sub-query.
         * @param string  $previous_end_date    Optional end date filter (Y-m-d H:i:s) for the previous period sub-query.
         *
         * @return array|\WP_Error Summary data array, or WP_Error on DB failure.
         */
        public function get_wholesale_summary( $is_hpos = false, $start_date = '', $end_date = '', $user_role = '', $previous_start_date = '', $previous_end_date = '' ) {

            global $wpdb;

            $place_holders = $this->placeholders_builders_for_overall_wholesale_summary( $is_hpos, $start_date, $end_date, $user_role, $previous_start_date, $previous_end_date );
            $query         = $this->get_overall_wholesale_summary_query( $wpdb->prefix, $is_hpos, $start_date, $end_date, $previous_start_date, $previous_end_date );
            $results       = $wpdb->get_results( $wpdb->prepare( $query, $place_holders ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            if ( ! empty( $wpdb->last_error ) ) {
                return new \WP_Error( 'db_error', __( 'A database error occurred.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 500 ) );
            }

            $data = isset( $results[0] ) && ! empty( $results[0] ) ? $this->build_summary_response( $results[0], $previous_start_date, $previous_end_date ) : array();

            return $data;
        }

        /**
         * Build response data structure after it is fetched from the DB.
         *
         * @param array   $payload The response of the query.
         * @param boolean $comparison_mode The comparison mode.
         * @return array
         */
        protected function build_response( $payload, $comparison_mode = false ) {
            if ( $comparison_mode ) {
                $current_revenue              = isset( $payload['current_period'] ) && ! empty( $payload['current_period'] ) ? $payload['current_period'] : 0;
                $previous_revenue             = isset( $payload['previous_period'] ) && ! empty( $payload['previous_period'] ) ? $payload['previous_period'] : 0;
                $revenue_difference           = (float) ( $current_revenue - $previous_revenue );
                $revenue_growth_percentage    = (float) $previous_revenue > 0 ? wc_format_decimal( ( ( $revenue_difference / $previous_revenue ) * 100 ), 0 ) : ( $current_revenue > 0 ? 'N/A' : 0 );
                $previous_period_label        = __( 'Previous Period', 'woocommerce-wholesale-prices-premium' );
                $overall_total_order_count    = (float) ( $payload['current_order_count'] + $payload['previous_order_count'] );
                $current_revenue_formatted    = wc_price( wc_format_decimal( $current_revenue, 2 ), array( 'in_span' => false ) );
                $previous_period_revenue      = wc_price( wc_format_decimal( $previous_revenue, 2 ), array( 'in_span' => false ) );
                $revenue_difference_formatted = wc_price( wc_format_decimal( $revenue_difference, 2 ), array( 'in_span' => false ) );

                if ( 0.0 === (float) $current_revenue && 0.0 === (float) $previous_revenue ) {
                    $trend = 'flat';
                } else {
                    $trend = (float) $current_revenue >= (float) $previous_revenue ? 'up' : 'down';
                }

                $response = array(
                    'overall_revenue_all_time'      => $current_revenue_formatted,
                    'previous_period_total_revenue' => $previous_period_revenue . ' ' . $previous_period_label,
                    'overall_order_count_all_time'  => $overall_total_order_count,
                    'revenue_growth_yearly'         => is_numeric( $revenue_growth_percentage ) ? $revenue_growth_percentage . '%' : $revenue_growth_percentage,
                    'revenue_difference'            => $revenue_difference_formatted,
                    'current_year_trend'            => $trend,
                );

                return $response;
            }

            return array(
                'overall_revenue_all_time'     => wc_price(
                    isset( $payload['order_total'] ) ? $payload['order_total'] : 0,
                    array(
                        'in_span' => false,
                    )
                ),
                'overall_order_count_all_time' => isset( $payload['order_count'] ) ? (int) $payload['order_count'] : 0,
            );
        }

        /**
         * Build summary response with all-time revenue, weekly/yearly growth percentages, and trend directions.
         *
         * @since 2.1.0
         *
         * @param array  $data                Raw revenue data.
         * @param string $previous_start_date Start date of the previous period (Y-m-d H:i:s), used to determine label and comparison value.
         * @param string $previous_end_date   End date of the previous period (Y-m-d H:i:s), used to determine label and comparison value.
         *
         * @return array Formatted summary with revenue totals, growth percentages, and trends.
         */
        public function build_summary_response( $data, $previous_start_date = '', $previous_end_date = '' ) {
            $summary                              = array();
            $overall_order_count_all_time         = isset( $data['overall_orders_count'] ) ? $data['overall_orders_count'] : 0.00;
            $overall_revenue_all_time             = isset( $data['overall_total_order_revenue'] ) ? (float) $data['overall_total_order_revenue'] : 0.00;
            $current_week_total_wholesale_revenue = isset( $data['current_week_total_wholesale_revenue'] ) ? (float) $data['current_week_total_wholesale_revenue'] : 0.00;
            $last_week_total_wholesale_revenue    = isset( $data['last_week_total_wholesale_revenue'] ) ? (float) $data['last_week_total_wholesale_revenue'] : 0.00;
            $last_year_total_revenue              = isset( $data['last_year_total_revenue'] ) ? (float) $data['last_year_total_revenue'] : 0.00;
            $current_year_total_revenue           = isset( $data['current_year_total_revenue'] ) ? (float) $data['current_year_total_revenue'] : 0.00;
            $previous_period_revenue_data         = isset( $data['previous_period_revenue'] ) ? (float) $data['previous_period_revenue'] : 0.00;
            $date_format                          = get_option( 'date_format' );

            $previous_period_label = ! empty( $previous_start_date ) && ! empty( $previous_end_date )
                ? __( 'Previous Period', 'woocommerce-wholesale-prices-premium' )
                : __( 'Previous Year', 'woocommerce-wholesale-prices-premium' );

            // Highest revenue day last week: the peak single-day sale amount and the date it occurred.
            $last_week_highest_sale_revenue_in_a_day      = isset( $data['last_week_highest_revenue_in_a_day'] ) ? (float) $data['last_week_highest_revenue_in_a_day'] : 0;
            $last_week_highest_sale_revenue_date_in_a_day = isset( $data['last_week_highest_revenue_date_in_a_day'] ) ? $data['last_week_highest_revenue_date_in_a_day'] : 0;

            // Lowest revenue day last week: the smallest single-day sale amount and the date it occurred.
            $last_week_lowest_sale_revenue_in_a_day      = isset( $data['last_week_lowest_revenue_in_a_day'] ) ? (float) $data['last_week_lowest_revenue_in_a_day'] : 0;
            $last_week_lowest_sale_revenue_date_in_a_day = isset( $data['last_week_lowest_revenue_date_in_a_day'] ) ? $data['last_week_lowest_revenue_date_in_a_day'] : 0;

            $summary['overall_revenue_all_time']      = wc_price( wc_format_decimal( $overall_revenue_all_time, 2 ), array( 'in_span' => false ) );
            $summary['revenue_growth_weekly']         = $last_week_total_wholesale_revenue > 0 ? wc_format_decimal( ( ( ( $current_week_total_wholesale_revenue - $last_week_total_wholesale_revenue ) / $last_week_total_wholesale_revenue ) * 100 ), 2 ) . '%' : ( $current_week_total_wholesale_revenue > 0 ? 'N/A' : '0%' );
            $summary['revenue_growth_yearly']         = $last_year_total_revenue > 0 ? wc_format_decimal( ( ( ( $current_year_total_revenue - $last_year_total_revenue ) / $last_year_total_revenue ) * 100 ), 0 ) . '%' : ( $current_year_total_revenue > 0 ? 'N/A' : '0%' );
            $summary['overall_order_count_all_time']  = $overall_order_count_all_time;
            $summary['previous_period_total_revenue'] = wc_price( wc_format_decimal( $previous_period_revenue_data, 2 ), array( 'in_span' => false ) ) . ' ' . $previous_period_label;
            $summary['revenue_difference']            = wc_price( wc_format_decimal( ( $overall_revenue_all_time - $previous_period_revenue_data ), 2 ), array( 'in_span' => false ) );

            if ( 0.0 === $current_week_total_wholesale_revenue && 0.0 === $last_week_total_wholesale_revenue ) {
                $summary['current_week_trend'] = 'flat';
            } else {
                $summary['current_week_trend'] = $current_week_total_wholesale_revenue >= $last_week_total_wholesale_revenue ? 'up' : 'down';
            }

            if ( 0.0 === $current_year_total_revenue && 0.0 === $last_year_total_revenue ) {
                $summary['current_year_trend'] = 'flat';
            } else {
                $summary['current_year_trend'] = $current_year_total_revenue >= $last_year_total_revenue ? 'up' : 'down';
            }

            // All-time revenue growth percentage vs previous period, capped at 100%.
            $summary['revenue_all_time_growth'] = $previous_period_revenue_data > 0
                ? wc_format_decimal( min( ( ( ( $overall_revenue_all_time - $previous_period_revenue_data ) / $previous_period_revenue_data ) * 100 ), 100 ), 2 ) . '%'
                : ( $overall_revenue_all_time > 0 ? 'N/A' : '0%' );

            // All-time trend direction vs previous period.
            if ( 0.0 === $overall_revenue_all_time && 0.0 === $previous_period_revenue_data ) {
                $summary['revenue_all_time_trend'] = 'flat';
            } else {
                $summary['revenue_all_time_trend'] = $overall_revenue_all_time >= $previous_period_revenue_data ? 'up' : 'down';
            }

            $highest_date                              = ! empty( $last_week_highest_sale_revenue_date_in_a_day ) ? date_create( $last_week_highest_sale_revenue_date_in_a_day ) : null;
            $summary['last_week_highest_sale_summary'] = ( $highest_date instanceof \DateTimeInterface )
            ? sprintf(
                /* translators: 1: weekday name, 2: formatted date, 3: formatted price amount */
                __( 'Highest sale was on %1$s, %2$s with an amount of %3$s', 'woocommerce-wholesale-prices-premium' ),
                date_i18n( 'l', $highest_date->getTimestamp() ),
                date_i18n( $date_format, $highest_date->getTimestamp() ),
                wc_price( $last_week_highest_sale_revenue_in_a_day, array( 'in_span' => false ) )
            )
            : __( 'No highest sale data available for last week.', 'woocommerce-wholesale-prices-premium' );

            $lowest_date                              = ! empty( $last_week_lowest_sale_revenue_date_in_a_day ) ? date_create( $last_week_lowest_sale_revenue_date_in_a_day ) : null;
            $summary['last_week_lowest_sale_summary'] = ( $lowest_date instanceof \DateTimeInterface )
            ? sprintf(
                /* translators: 1: weekday name, 2: formatted date, 3: formatted price amount */
                __( 'Lowest sale was on %1$s, %2$s with an amount of %3$s', 'woocommerce-wholesale-prices-premium' ),
                date_i18n( 'l', $lowest_date->getTimestamp() ),
                date_i18n( $date_format, $lowest_date->getTimestamp() ),
                wc_price( $last_week_lowest_sale_revenue_in_a_day, array( 'in_span' => false ) )
            )
            : __( 'No lowest sale data available for last week.', 'woocommerce-wholesale-prices-premium' );

            return $summary;
        }

        /**
         * Build the HPOS-aware JOIN clause for wholesale order meta filtering.
         *
         * For HPOS: joins wc_orders_meta once to match both the wholesale flag key and value.
         * For legacy posts: joins postmeta twice — first for the order total key, then for the
         * wholesale flag key/value — because the total lives in a separate meta row.
         *
         * @since 2.1.0
         * @param array $map The table/column mapping from get_table_mapping().
         * @param bool  $is_hpos Whether HPOS is enabled.
         * @return string SQL JOIN fragment with %s placeholders.
         */
        protected function build_meta_join_clause( $map, $is_hpos ) {
            if ( $is_hpos ) {
                return "JOIN {$map['meta_table_name']} {$map['meta_table_alias']} ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}.{$map['meta_id_column']} AND {$map['meta_table_alias']}.meta_key = %s AND {$map['meta_table_alias']}.meta_value = %s";
            }

            return "JOIN {$map['meta_table_name']} {$map['meta_table_alias']} ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}.{$map['meta_id_column']} AND {$map['meta_table_alias']}.meta_key = %s JOIN {$map['meta_table_name']} {$map['meta_table_alias']}_ ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}_.{$map['meta_id_column']} AND {$map['meta_table_alias']}_.meta_key = %s AND {$map['meta_table_alias']}_.meta_value = %s";
        }

        /**
         * Build non-comparison 'Wholesale Revenue' query.
         *
         * @since 2.1.0
         * @param string  $start_date         The start date.
         * @param string  $end_date           The end date.
         * @param string  $prefix             The DB table prefix.
         * @param boolean $is_hpos            Check if HPOS is enabled.
         * @param boolean $include_semi_colon Include semi-colon.
         *
         * @return string
         */
        protected function build_wholesale_revenue_query( $start_date, $end_date, $prefix = 'wp_', $is_hpos = false, $include_semi_colon = true ) {
            $map                   = $this->get_table_mapping( $prefix, $is_hpos );
            $table_column          = $is_hpos ? "{$map['table_alias']}.{$map['meta_value_column']}" : "{$map['meta_table_alias']}.{$map['meta_value_column']}";
            $additional_join_query = $this->build_meta_join_clause( $map, $is_hpos );

            $semi_colon  = $include_semi_colon ? ';' : '';
            $final_query = "SELECT CAST(SUM({$table_column}) AS DECIMAL(10, 2)) as order_total, COUNT({$map['table_alias']}.{$map['id_column']}) as order_count
                                            FROM {$map['table_name']} {$map['table_alias']}
                                                    {$additional_join_query}
                                            WHERE {$map['table_alias']}.{$map['post_type_column']} = %s
                                                    AND {$map['table_alias']}.{$map['post_status_column']} = %s
                                            {$this->build_date_query( $start_date, $end_date, $is_hpos )}{$semi_colon}";
            return $final_query;
        }

        /**
         * Build placeholders for query.
         *
         * @since 2.1.0
         * @param string  $role The user role.
         * @param string  $start_date The start date for current period.
         * @param string  $end_date The end date for current period.
         * @param boolean $is_hpos Check if HPOS is enabled.
         *
         * @return array
         */
        protected function placeholders_builder( $role, $start_date = '', $end_date = '', $is_hpos = false ) {
            $common_placeholders   = array( 'shop_order', 'wc-completed' );
            $uncommon_placeholders = $is_hpos ? array( '_wwpp_order_type', 'wholesale' ) : array( '_order_total', '_wwpp_order_type', 'wholesale' );

            $role_placeholders = array();
            if ( ! empty( $role ) ) {
                $role_placeholders = $is_hpos
                ? array( '_wwpp_wholesale_order_type', $role )
                : array( '_order_total', '_wwpp_wholesale_order_type', $role );
            }

            if ( ! empty( $role_placeholders ) ) { // if role exists then, replace it in the $uncommon_placeholders variable.
                $uncommon_placeholders = $role_placeholders;
            }

            if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
                $common_placeholders[] = $start_date;
                $common_placeholders[] = $end_date;
            } elseif ( ! empty( $start_date ) ) {
                $common_placeholders[] = $start_date;
            } elseif ( ! empty( $end_date ) ) {
                $common_placeholders[] = $end_date;
            }

            return array_merge( $uncommon_placeholders, $common_placeholders );
        }

        /**
         * Build the composite SQL query for the overall wholesale revenue summary.
         *
         * @since 2.1.0
         * @param string  $prefix              The DB table prefix. Defaults to 'wp_'.
         * @param boolean $is_hpos             Whether HPOS is enabled.
         * @param string  $start_date          Optional start date filter for the overall revenue sub-query (Y-m-d H:i:s).
         * @param string  $end_date            Optional end date filter for the overall revenue sub-query (Y-m-d H:i:s).
         * @param string  $previous_start_date Optional start date override for the previous period sub-query (Y-m-d H:i:s).
         * @param string  $previous_end_date   Optional end date override for the previous period sub-query (Y-m-d H:i:s).
         *
         * @return string SQL query string with %s placeholders.
         */
        protected function get_overall_wholesale_summary_query( $prefix = 'wp_', $is_hpos = false, $start_date = '', $end_date = '', $previous_start_date = '', $previous_end_date = '' ) {
            $current_week_dates       = $this->get_current_week_date_range();
            $previous_week_dates      = $this->get_last_week_date_range();
            $current_year_dates       = $this->get_current_year_date_range();
            $last_year_dates          = $this->get_last_year_date_range();
            $current_week_start_date  = $current_week_dates['start_date'];
            $current_week_end_date    = $current_week_dates['end_date'];
            $previous_week_start_date = $previous_week_dates['start_date'];
            $previous_week_end_date   = $previous_week_dates['end_date'];
            $current_year_start_date  = $current_year_dates['start_date'];
            $current_year_end_date    = $current_year_dates['end_date'];
            $last_year_start_date     = $last_year_dates['start_date'];
            $last_year_end_date       = $last_year_dates['end_date'];

            $previous_period_comparison_query = ! empty( $previous_start_date ) && ! empty( $previous_end_date )
                ? '(' . $this->build_wholesale_revenue_query( $previous_start_date, $previous_end_date, $prefix, $is_hpos, false ) . ') AS previousPeriodQuery,'
                : '(' . $this->build_wholesale_revenue_query( $last_year_start_date, $last_year_end_date, $prefix, $is_hpos, false ) . ') AS previousPeriodQuery,';

            $previous_period_column = 'previousPeriodQuery.order_total AS previous_period_revenue,';

            $overall_sql = "
                                SELECT
                                    overallQuery.order_total as overall_total_order_revenue,
                                    overallQuery.order_count as overall_orders_count,
                                    currentYearQuery.order_total as current_year_total_revenue,
                                    lastYearQuery.order_total as last_year_total_revenue,
                                    currentWeekQuery.order_total as current_week_total_wholesale_revenue,
                                    lastWeekQuery.order_total as last_week_total_wholesale_revenue,
                                    {$previous_period_column}
                                    lastWeekHighestOrderDayQuery.highest_order_total as last_week_highest_revenue_in_a_day,
                                    lastWeekHighestOrderDayQuery.highest_last_week_date as last_week_highest_revenue_date_in_a_day,
                                    lastWeekLowestOrderDayQuery.lowest_order_total as last_week_lowest_revenue_in_a_day,
                                    lastWeekLowestOrderDayQuery.lowest_last_week_date as last_week_lowest_revenue_date_in_a_day
                                FROM
                                (
                                    {$this->build_wholesale_revenue_query( $start_date, $end_date, $prefix, $is_hpos, false )}
                                ) as overallQuery,
                                (
                                    {$this->build_wholesale_revenue_query( $current_year_start_date, $current_year_end_date, $prefix, $is_hpos, false )}
                                ) as currentYearQuery,
                                (
                                    {$this->build_wholesale_revenue_query( $last_year_start_date, $last_year_end_date, $prefix, $is_hpos, false )}
                                ) as lastYearQuery,
                                (
                                    {$this->build_wholesale_revenue_query( $current_week_start_date, $current_week_end_date, $prefix, $is_hpos, false )}
                                ) as currentWeekQuery,
                                (
                                    {$this->build_wholesale_revenue_query( $previous_week_start_date, $previous_week_end_date, $prefix, $is_hpos, false )}
                                ) as lastWeekQuery,
                                {$previous_period_comparison_query}
                                (
                                    {$this->last_week_min_or_max_sale_query( $prefix, $is_hpos, 'DESC', $previous_week_start_date, $previous_week_end_date )}
                                ) as lastWeekHighestOrderDayQuery,
                                (
                                    {$this->last_week_min_or_max_sale_query( $prefix, $is_hpos, 'ASC', $previous_week_start_date, $previous_week_end_date )}
                                ) as lastWeekLowestOrderDayQuery;
                            ";
            return $overall_sql;
        }

        /**
         * Build the placeholder array for the overall wholesale summary query.
         *
         * The order must match the sub-query order in get_overall_wholesale_summary_query():
         * overall → current year → last year → current week → last week → previous period (optional) → highest day → lowest day.
         *
         * @since 2.1.0
         * @param boolean $is_hpos              Whether HPOS is enabled.
         * @param string  $start_date           Optional start date filter for the overall revenue sub-query (Y-m-d H:i:s).
         * @param string  $end_date             Optional end date filter for the overall revenue sub-query (Y-m-d H:i:s).
         * @param string  $user_role            Optional wholesale role to filter by.
         * @param string  $previous_start_date  Optional start date for the previous period sub-query (Y-m-d H:i:s).
         * @param string  $previous_end_date    Optional end date for the previous period sub-query (Y-m-d H:i:s).
         *
         * @return array Ordered placeholder values for $wpdb->prepare().
         */
        public function placeholders_builders_for_overall_wholesale_summary( $is_hpos = false, $start_date = '', $end_date = '', $user_role = '', $previous_start_date = '', $previous_end_date = '' ) {
            $current_week_dates       = $this->get_current_week_date_range();
            $previous_week_dates      = $this->get_last_week_date_range();
            $current_week_start_date  = $current_week_dates['start_date'];
            $current_week_end_date    = $current_week_dates['end_date'];
            $previous_week_start_date = $previous_week_dates['start_date'];
            $previous_week_end_date   = $previous_week_dates['end_date'];
            $current_year_dates       = $this->get_current_year_date_range();
            $last_year_dates          = $this->get_last_year_date_range();
            $current_year_start_date  = $current_year_dates['start_date'];
            $current_year_end_date    = $current_year_dates['end_date'];
            $last_year_start_date     = $last_year_dates['start_date'];
            $last_year_end_date       = $last_year_dates['end_date'];

            $overall_query_placeholders               = $this->placeholders_builder( $user_role, $start_date, $end_date, $is_hpos );
            $current_year_revenue_placeholders        = $this->placeholders_builder( $user_role, $current_year_start_date, $current_year_end_date, $is_hpos );
            $previous_year_revenue_placeholders       = $this->placeholders_builder( $user_role, $last_year_start_date, $last_year_end_date, $is_hpos );
            $current_week_query_placeholders          = $this->placeholders_builder( $user_role, $current_week_start_date, $current_week_end_date, $is_hpos );
            $last_week_query_placeholders             = $this->placeholders_builder( $user_role, $previous_week_start_date, $previous_week_end_date, $is_hpos );
            $previous_period_placeholders             = ! empty( $previous_start_date ) && ! empty( $previous_end_date )
                ? $this->placeholders_builder( $user_role, $previous_start_date, $previous_end_date, $is_hpos )
                : $this->placeholders_builder( $user_role, $last_year_start_date, $last_year_end_date, $is_hpos );
            $last_week_highest_order_day_placeholders = $this->build_last_week_min_or_max_sale_query_placeholders( $is_hpos, $previous_week_start_date, $previous_week_end_date );
            $last_week_lowest_order_day_placeholders  = $this->build_last_week_min_or_max_sale_query_placeholders( $is_hpos, $previous_week_start_date, $previous_week_end_date );

            return array_merge( $overall_query_placeholders, $current_year_revenue_placeholders, $previous_year_revenue_placeholders, $current_week_query_placeholders, $last_week_query_placeholders, $previous_period_placeholders, $last_week_highest_order_day_placeholders, $last_week_lowest_order_day_placeholders );
        }

        /**
         * Build the SQL sub-query for the highest or lowest revenue day of last week.
         * Uses WordPress timezone-derived last week range (UTC) to avoid CURDATE() timezone mismatch.
         *
         * @since 2.1.0
         * @param string  $prefix              The DB table prefix. Defaults to 'wp_'.
         * @param boolean $is_hpos             Whether HPOS is enabled.
         * @param string  $order_by            'DESC' for highest day, 'ASC' for lowest. Defaults to 'DESC'.
         * @param string  $last_week_start_date Last week start in UTC (Y-m-d H:i:s).
         * @param string  $last_week_end_date   Last week end in UTC (Y-m-d H:i:s).
         *
         * @return string SQL fragment with %s placeholders.
         */
        protected function last_week_min_or_max_sale_query( $prefix = 'wp_', $is_hpos = false, $order_by = 'DESC', $last_week_start_date = '', $last_week_end_date = '' ) {
            $map        = $this->get_table_mapping( $prefix, $is_hpos );
            $sum_column = $is_hpos ? "{$map['table_alias']}.{$map['meta_value_column']}" : "{$map['meta_table_alias']}.{$map['meta_value_column']}";
            $column_1   = 'DESC' === $order_by ? 'highest_last_week_date' : 'lowest_last_week_date';
            $column_3   = 'DESC' === $order_by ? 'highest_order_total' : 'lowest_order_total';

            $additional_join_query = $this->build_meta_join_clause( $map, $is_hpos );

            $date_filter = ( ! empty( $last_week_start_date ) && ! empty( $last_week_end_date ) )
            ? " AND {$map['table_alias']}.{$map['date_column_gmt']} BETWEEN %s AND %s"
            : '';

            $sql = "SELECT
                        COALESCE(sub.{$column_1}, NULL) AS {$column_1},
                        COALESCE(sub.{$column_3}, 0.00) AS {$column_3}
                    FROM (SELECT 1) AS dummy
                    LEFT JOIN (
                        SELECT
                            DATE({$map['table_alias']}.{$map['date_column_gmt']}) AS {$column_1},
                            CAST(SUM($sum_column) AS DECIMAL(10, 2)) AS {$column_3}
                        FROM {$map['table_name']} {$map['table_alias']}
                        {$additional_join_query}
                        WHERE {$map['table_alias']}.{$map['post_type_column']} = %s
                            AND {$map['table_alias']}.{$map['post_status_column']} = %s
                            {$date_filter}
                        GROUP BY DATE({$map['table_alias']}.{$map['date_column_gmt']})
                        ORDER BY {$column_3} {$order_by}
                        LIMIT 1
                    ) AS sub ON 1=1";
            return $sql;
        }

        /**
         * Build placeholders for 'min max sale query'.
         *
         * @since 2.1.0
         * @param boolean $is_hpos             HPOS is enabled/disabled.
         * @param string  $last_week_start_date Last week start in UTC (Y-m-d H:i:s).
         * @param string  $last_week_end_date   Last week end in UTC (Y-m-d H:i:s).
         * @return array
         */
        protected function build_last_week_min_or_max_sale_query_placeholders( $is_hpos = false, $last_week_start_date = '', $last_week_end_date = '' ) {
            $base = $is_hpos ? array( '_wwpp_order_type', 'wholesale', 'shop_order', 'wc-completed' ) : array( '_order_total', '_wwpp_order_type', 'wholesale', 'shop_order', 'wc-completed' );
            if ( ! empty( $last_week_start_date ) && ! empty( $last_week_end_date ) ) {
                $base[] = $last_week_start_date;
                $base[] = $last_week_end_date;
            }
            return $base;
        }

        /**
         * Build comparison 'Wholesale Revenue' query.
         *
         * @since 2.1.0
         * @param string  $current_period_start_date The start date for current period.
         * @param string  $current_period_end_date The end date for current period.
         * @param string  $previous_period_start_date The start date for previous period.
         * @param string  $previous_period_end_date The end date for previous period.
         * @param boolean $is_hpos Check if HPOS is enabled.
         * @param string  $prefix The table prefix.
         *
         * @return string
         */
        protected function build_comparison_wholesale_revenue_query( $current_period_start_date, $current_period_end_date, $previous_period_start_date, $previous_period_end_date, $is_hpos = false, $prefix = 'wp_' ) {
            $map                   = $this->get_table_mapping( $prefix, $is_hpos );
            $table_column          = $is_hpos ? "{$map['table_alias']}.{$map['meta_value_column']}" : "{$map['meta_table_alias']}.{$map['meta_value_column']}";
            $additional_join_query = $is_hpos
            ? "AND {$map['meta_table_alias']}.meta_value = %s"
            : "JOIN {$map['meta_table_name']} {$map['meta_table_alias']}_ ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}_.{$map['meta_id_column']} AND {$map['meta_table_alias']}_.meta_key = %s AND {$map['meta_table_alias']}_.meta_value = %s";

            $final_query = "SELECT MAX(CASE WHEN period = 'current_period' THEN amount END) AS current_period, MAX(CASE WHEN period = 'previous_period' THEN amount END) AS previous_period, MAX(CASE WHEN period = 'current_period' THEN order_count END) AS current_order_count, MAX(CASE WHEN period = 'previous_period' THEN order_count END) AS previous_order_count FROM (
                                SELECT 'current_period' AS period, CAST(SUM( {$table_column} ) AS DECIMAL(10, 2)) as amount, COUNT(DISTINCT {$map['table_alias']}.{$map['id_column']}) as order_count FROM {$map['table_name']} {$map['table_alias']} JOIN {$map['meta_table_name']} {$map['meta_table_alias']} ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}.{$map['meta_id_column']} AND {$map['meta_table_alias']}.meta_key = %s {$additional_join_query} WHERE {$map['table_alias']}.{$map['post_type_column']} = %s AND {$map['table_alias']}.{$map['post_status_column']} = %s {$this->build_date_query( $current_period_start_date, $current_period_end_date, $is_hpos )}
                                UNION ALL
                                SELECT 'previous_period' as period, CAST(SUM( {$table_column} ) AS DECIMAL(10, 2)) as amount, COUNT(DISTINCT {$map['table_alias']}.{$map['id_column']}) as order_count FROM {$map['table_name']} {$map['table_alias']} JOIN {$map['meta_table_name']} {$map['meta_table_alias']} ON {$map['table_alias']}.{$map['id_column']} = {$map['meta_table_alias']}.{$map['meta_id_column']} AND {$map['meta_table_alias']}.meta_key = %s {$additional_join_query} WHERE {$map['table_alias']}.{$map['post_type_column']} = %s AND {$map['table_alias']}.{$map['post_status_column']} = %s {$this->build_date_query( $previous_period_start_date, $previous_period_end_date, $is_hpos )}
                            ) AS subQuery;";

            return $final_query;
        }
    }
}
