<?php
/**
 * Top Products Price Format Trait.
 *
 * @package WWPP
 * @since   2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! trait_exists( 'WWPP_Top_Products_Price_Format_Trait' ) ) {

    /**
     * Price formatting helper for top-products analytics classes.
     *
     * Provides format_price_columns() for use by Top_Products_Wholesale_Volume
     * and Top_Products_Wholesale_Revenue only. Do not use this trait in other
     * analytics classes — they format prices inline within their own response builders.
     *
     * @since 2.1.0
     */
    trait WWPP_Top_Products_Price_Format_Trait {

        /**
         * Formats specified monetary columns in query results using WooCommerce price formatting.
         *
         * @since 2.1.0
         * @param array $results Raw query results to format.
         * @param array $columns Column keys to format as prices.
         * @return array Results with the specified columns formatted as prices.
         */
        protected function format_price_columns( $results, $columns ) {
            foreach ( $results as &$row ) {
                foreach ( $columns as $column ) {
                    if ( isset( $row[ $column ] ) ) {
                        $row[ $column ] = wc_price( wc_format_decimal( $row[ $column ], 2 ), array( 'in_span' => false ) );
                    }
                }
            }
            unset( $row );

            return $results;
        }
    }
}
