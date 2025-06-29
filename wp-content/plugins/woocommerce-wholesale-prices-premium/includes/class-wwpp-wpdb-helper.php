<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


if ( ! class_exists( 'WWPP_WPDB_Helper' ) ) {

    /**
     * This class contains lots of helper functions that are perform via wpdb for speed.
     *
     * Class WWPP_WPDB_Helper
     */
    class WWPP_WPDB_Helper {

        /**
         * Get products under a certain category.
         *
         * @since 1.7.0
         * @since 1.14.8 Include children of the current category.
         *
         * @param int $termId The term ID.
         * @return mixed
         */
        public static function get_products_by_category( $termId ) {

            $child_categories   = get_term_children( $termId, 'product_cat' );
            $child_categories[] = $termId;
            $cat_ids            = $child_categories;

            global $wpdb;
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, Squiz.Strings.DoubleQuoteUsage.NotRequired -- Ignored for allowing interpolation in IN query.
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT 
                        DISTINCT ID 
                    FROM 
                        $wpdb->posts 
                        LEFT JOIN $wpdb->term_relationships ON (
                            $wpdb->posts.ID = $wpdb->term_relationships.object_id
                        ) 
                        LEFT JOIN $wpdb->term_taxonomy ON (
                            $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
                        ) 
                    WHERE 
                        $wpdb->posts.post_status = 'publish' 
                        AND $wpdb->posts.post_type = 'product' 
                        AND $wpdb->term_taxonomy.taxonomy = 'product_cat' 
                        AND $wpdb->term_taxonomy.term_id IN ( " . implode( ',', array_fill( 0, count( $cat_ids ), '%d' ) ) . " ) 
                    ORDER BY 
                        post_date DESC",
                    $cat_ids
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, Squiz.Strings.DoubleQuoteUsage.NotRequired -- Ignored for allowing interpolation in IN query.
        }

        /**
         * Set meta to list of products. Requires a list of product ids, and they should have the same meta key and
         * value to set. Not used atm, might be helpful in the future.
         *
         * @since 1.7.0
         *
         * @param string $metaKey The meta key.
         * @param string $metaVal The meta value.
         * @param array  $postIds Array of post ids.
         */
        public static function update_post_meta( $metaKey, $metaVal, $postIds ) {

            if ( is_array( $postIds ) && ! empty( $postIds ) ) {

                global $wpdb;
                $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_value = %d WHERE meta_key = %s AND post_id IN( " . implode( ',', array_fill( 0, count( $postIds ), '%d' ) ) . " )", $metaVal, $metaKey, $postIds ) ); // phpcs:ignore.
            }
        }
    }

}
