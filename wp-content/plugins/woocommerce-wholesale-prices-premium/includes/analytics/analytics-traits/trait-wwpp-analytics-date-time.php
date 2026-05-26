<?php
/**
 * Analytics Date/Time Trait.
 *
 * @package WWPP
 * @since   2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! trait_exists( 'WWPP_Analytics_Date_Time_Trait' ) ) {

    /**
     * Shared date/time helpers for analytics classes.
     *
     * Provides timezone-aware UTC date range calculation methods used across
     * analytics classes (e.g. Total_Wholesale_Revenue, Wholesale_Vs_Retail,
     * Unique_Wholesale_Customer_Orders) to avoid duplicated strtotime/gmdate logic.
     *
     * All returned dates are in Y-m-d H:i:s format and represent UTC boundaries
     * derived from the site's WordPress timezone setting, so they align correctly
     * with WooCommerce's post_date_gmt / date_created_gmt columns.
     *
     * Usage: `use WWPP_Analytics_Date_Time_Trait;` inside any analytics class.
     *
     * @since 2.1.0
     */
    trait WWPP_Analytics_Date_Time_Trait {

        /**
         * Date format used for all UTC date range strings passed to SQL queries.
         *
         * @since 2.1.0
         * @var string
         */
        private $date_range_format = 'Y-m-d H:i:s';

        /**
         * Get a date range in UTC using WordPress timezone for boundaries and wp_date() for formatting.
         *
         * @since 2.1.0
         * @param string $start_modify_str Relative date string for start (e.g. 'monday this week').
         * @param string $end_modify_str   Relative date string for end (e.g. 'sunday this week').
         * @param string $given_date       Reference date string for date calculations. Default 'now'.
         * @param array  $start_time       Start time [ hour, minute, second ]. Default [ 0, 0, 0 ].
         * @param array  $end_time         End time [ hour, minute, second ]. Default [ 23, 59, 59 ].
         * @return array { 'start_date' => string, 'end_date' => string } UTC dates in Y-m-d H:i:s format.
         */
        private function get_date_range_in_utc( $start_modify_str, $end_modify_str, $given_date = 'now', $start_time = array( 0, 0, 0 ), $end_time = array( 23, 59, 59 ) ) {
            $tz       = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
            $utc_zone = new \DateTimeZone( 'UTC' );
            $now      = new \DateTimeImmutable( $given_date, $tz );
            $start    = $now->modify( $start_modify_str );
            $end      = $now->modify( $end_modify_str );

            if ( false === $start || false === $end ) {
                return array(
                    'start_date' => '',
                    'end_date'   => '',
                );
            }

            $start_ts = $start->setTime( $start_time[0], $start_time[1], $start_time[2] )->getTimestamp();
            $end_ts   = $end->setTime( $end_time[0], $end_time[1], $end_time[2] )->getTimestamp();

            return array(
                'start_date' => wp_date( $this->date_range_format, $start_ts, $utc_zone ),
                'end_date'   => wp_date( $this->date_range_format, $end_ts, $utc_zone ),
            );
        }

        /**
         * Get the start and end dates (Mon 00:00:00 – Sun 23:59:59) of last week in UTC.
         * Uses WordPress timezone for week boundaries, then wp_date() with explicit UTC for query comparison.
         *
         * @since 2.1.0
         * @param string $given_date Reference date string for week calculations. Default 'now'.
         * @return array { 'start_date' => 'Y-m-d H:i:s', 'end_date' => 'Y-m-d H:i:s' }
         */
        public function get_last_week_date_range( $given_date = 'now' ) {
            return $this->get_date_range_in_utc( 'monday last week', 'sunday last week', $given_date );
        }

        /**
         * Get the start and end dates (Y-m-01 00:00:00 – last day 23:59:59) of the current month in UTC.
         * Uses WordPress timezone for month boundaries, then wp_date() with explicit UTC for query comparison.
         *
         * @return array { 'start_date' => 'Y-m-d H:i:s', 'end_date' => 'Y-m-d H:i:s' }
         */
        public function get_current_month_date_range() {
            return $this->get_date_range_in_utc( 'first day of this month', 'last day of this month' );
        }

        /**
         * Get the start and end dates (Mon 00:00:00 – Sun 23:59:59) of the current week in UTC.
         * Uses WordPress timezone for week boundaries, then wp_date() with explicit UTC for query comparison.
         *
         * @return array { 'start_date' => 'Y-m-d H:i:s', 'end_date' => 'Y-m-d H:i:s' }
         */
        public function get_current_week_date_range() {
            return $this->get_date_range_in_utc( 'monday this week', 'sunday this week' );
        }

        /**
         * Get the start and end dates (Y-m-01 00:00:00 – last day 23:59:59) of last month in UTC.
         * Uses WordPress timezone for month boundaries, then wp_date() with explicit UTC for query comparison.
         *
         * @return array { 'start_date' => 'Y-m-d H:i:s', 'end_date' => 'Y-m-d H:i:s' }
         */
        public function get_last_month_date_range() {
            return $this->get_date_range_in_utc( 'first day of last month', 'last day of last month' );
        }

        /**
         * Get the start and end dates (Jan 1 00:00:00 – Dec 31 23:59:59) of the current year in UTC.
         * Uses WordPress timezone for year boundaries, then wp_date() with explicit UTC for query comparison.
         *
         * @return array { 'start_date' => 'Y-m-d H:i:s', 'end_date' => 'Y-m-d H:i:s' }
         */
        public function get_current_year_date_range() {
            return $this->get_date_range_in_utc( 'first day of january this year', 'last day of december this year' );
        }

        /**
         * Get the start and end dates (Jan 1 00:00:00 – Dec 31 23:59:59) of last year in UTC.
         * Uses WordPress timezone for year boundaries, then wp_date() with explicit UTC for query comparison.
         *
         * @return array { 'start_date' => 'Y-m-d H:i:s', 'end_date' => 'Y-m-d H:i:s' }
         */
        public function get_last_year_date_range() {
            return $this->get_date_range_in_utc( 'first day of january last year', 'last day of december last year' );
        }
    }
}
