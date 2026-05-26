<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! trait_exists( 'WWPP_Analytics_Query_Trait' ) ) {

    /**
     * Shared SQL query helpers for analytics classes.
     *
     * Provides reusable get_table_mapping(), build_date_query(), and
     * build_category_join_clause() methods to avoid duplication across
     * Top_Products_Wholesale_Volume, Top_Products_Wholesale_Revenue, and
     * other analytics query builders.
     *
     * @since 2.1.0
     */
    trait WWPP_Analytics_Query_Trait {

        /**
         * Get the HPOS-aware table and column mapping for SQL queries.
         *
         * @since 2.1.0
         * @param string  $prefix  The DB table prefix.
         * @param boolean $is_hpos Whether HPOS is enabled.
         * @return array Table and column names keyed by identifier.
         */
        protected function get_table_mapping( $prefix, $is_hpos ) {
            return array(
                'table_name'         => $is_hpos ? "{$prefix}wc_orders" : "{$prefix}posts",
                'table_alias'        => $is_hpos ? 'wwo' : 'wpp',
                'meta_table_name'    => $is_hpos ? "{$prefix}wc_orders_meta" : "{$prefix}postmeta",
                'meta_table_alias'   => $is_hpos ? 'wwom' : 'wppm',
                'id_column'          => $is_hpos ? 'id' : 'ID',
                'post_status_column' => $is_hpos ? 'status' : 'post_status',
                'post_type_column'   => $is_hpos ? 'type' : 'post_type',
                'meta_value_column'  => $is_hpos ? 'total_amount' : 'meta_value',
                'meta_id_column'     => $is_hpos ? 'order_id' : 'post_id',
                'date_column_gmt'    => $is_hpos ? 'date_created_gmt' : 'post_date_gmt',
            );
        }

        /**
         * Build date SQL fragment based on start and end date.
         *
         * @since 2.1.0
         * @param string  $start_date The start date (Y-m-d H:i:s).
         * @param string  $end_date   The end date (Y-m-d H:i:s).
         * @param boolean $is_hpos    Whether HPOS is enabled.
         * @return string SQL fragment with %s placeholders, or empty string if no dates given.
         */
        protected function build_date_query( $start_date, $end_date, $is_hpos = false ) {
            $table_alias      = $is_hpos ? 'wwo' : 'wpp';
            $date_column_name = $is_hpos ? 'date_created_gmt' : 'post_date_gmt';

            if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
                return " AND {$table_alias}.{$date_column_name} BETWEEN %s AND %s";
            }

            if ( ! empty( $start_date ) ) {
                return " AND {$table_alias}.{$date_column_name} >= %s";
            }

            if ( ! empty( $end_date ) ) {
                return " AND {$table_alias}.{$date_column_name} <= %s";
            }

            return '';
        }

        /**
         * Builds the SQL JOIN clause for filtering products by a given category term ID,
         * correctly resolving product variations to their parent before the term lookup.
         *
         * WooCommerce assigns categories to parent products, not to variations. This method
         * JOINs {prefix}posts first and uses IF( post_parent > 0, post_parent, ID ) to
         * resolve variation IDs to their parent product before matching term_relationships.
         *
         * @since 2.1.0
         *
         * @param string  $prefix      DB table prefix.
         * @param int     $category_id Product category term ID. 0 = no filter (returns empty string).
         * @param string  $meta_alias  Alias of the order item meta table holding the product ID meta value.
         * @param boolean $is_hpos     Whether HPOS is enabled (reserved for future use; product posts always in {prefix}posts).
         * @return string SQL JOIN clause or empty string.
         */
        protected function build_category_join_clause( $prefix, $category_id, $meta_alias, $is_hpos = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $is_hpos reserved for future use
            if ( $category_id <= 0 ) {
                return '';
            }

            return "JOIN {$prefix}posts wpost
                        ON wpost.ID = {$meta_alias}.meta_value
                    JOIN {$prefix}term_relationships wtr
                        ON wtr.object_id = IF( wpost.post_parent > 0, wpost.post_parent, wpost.ID )
                    JOIN {$prefix}term_taxonomy wtt
                        ON wtt.term_taxonomy_id = wtr.term_taxonomy_id
                        AND wtt.taxonomy = 'product_cat'
                        AND wtt.term_id = %d";
        }
    }
}
