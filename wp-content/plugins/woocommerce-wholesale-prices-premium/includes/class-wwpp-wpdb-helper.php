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
         * Count published products visible to a wholesale role under a certain category (and its children).
         *
         * Single prepared COUNT(DISTINCT ID) that replaces the previous two-query pipeline
         * (get_products_by_category() + a WP_Query over those IDs) used to render the category count
         * badge. It replicates the same filters the shop listing applies:
         *  - posts joined to the category term and its descendants;
         *  - the per-product wholesale visibility meta (meta_value IN ( role, 'all' ));
         *  - an optional NOT IN exclusion of category-level restricted term ids;
         *  - an optional "only show wholesale products" gate (has wholesale price OR price > 0),
         *    casting the price as DECIMAL(10,2) so values below 1.00 are not truncated to 0.
         *
         * @since 2.1.1
         *
         * @param int    $term_id            The category term ID.
         * @param string $wholesale_role     Current wholesale role slug, or '' for guests/regular users.
         * @param array  $restricted_cat_ids Category term ids to exclude (NOT IN). Flat array of ids.
         * @param bool   $only_wholesale     Whether to count only products that have a wholesale price.
         * @return int Number of matching published products.
         */
        public static function get_products_count_by_category( $term_id, $wholesale_role, $restricted_cat_ids = array(), $only_wholesale = false ) {

            global $wpdb;

            $child_categories   = get_term_children( $term_id, 'product_cat' );
            $child_categories[] = $term_id;
            $cat_ids            = array_map( 'intval', $child_categories );

            // Visibility meta values: the role slug (empty string for guests) and the catch-all 'all'.
            $visibility_values = array( (string) $wholesale_role, 'all' );

            // Build the prepared argument list in the SAME order the placeholders appear in the SQL.
            // The visibility JOIN is emitted before the WHERE term_id IN clause, so its values come first.
            $where_args = array();

            // Visibility meta key, then its allowed values (the JOIN's meta_key = %s ... IN (...)).
            $where_args[] = WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER;

            $visibility_placeholders = implode( ',', array_fill( 0, count( $visibility_values ), '%s' ) );
            $where_args              = array_merge( $where_args, $visibility_values );

            $cat_in_placeholders = implode( ',', array_fill( 0, count( $cat_ids ), '%d' ) );
            $where_args          = array_merge( $where_args, $cat_ids );

            // Optional category-level restriction (NOT IN). Mirrors the listing's tax_query NOT IN.
            $restricted_sql     = '';
            $restricted_cat_ids = array_map( 'intval', array_values( (array) $restricted_cat_ids ) );
            if ( ! empty( $restricted_cat_ids ) ) {
                // Match the shop listing's tax_query NOT IN (default include_children => true): expand
                // each restricted parent to its descendants so products in a restricted parent's child
                // categories are excluded too, mirroring the positive $cat_ids expansion above.
                $expanded_restricted = $restricted_cat_ids;
                foreach ( $restricted_cat_ids as $restricted_id ) {
                    $children = get_term_children( $restricted_id, 'product_cat' );
                    if ( is_array( $children ) ) {
                        $expanded_restricted = array_merge( $expanded_restricted, $children );
                    }
                }
                $restricted_cat_ids = array_values( array_unique( array_map( 'intval', $expanded_restricted ) ) );

                $restricted_placeholders = implode( ',', array_fill( 0, count( $restricted_cat_ids ), '%d' ) );
                $restricted_sql          = " AND {$wpdb->posts}.ID NOT IN (
                        SELECT object_id
                        FROM {$wpdb->term_relationships} rtr
                        INNER JOIN {$wpdb->term_taxonomy} rtt ON rtr.term_taxonomy_id = rtt.term_taxonomy_id
                        WHERE rtt.taxonomy = 'product_cat' AND rtt.term_id IN ( {$restricted_placeholders} )
                    )";
                $where_args              = array_merge( $where_args, $restricted_cat_ids );
            }

            // Optional "only show wholesale products to wholesale users" gate.
            $wholesale_sql = '';
            if ( $only_wholesale && ! empty( $wholesale_role ) ) {
                $wholesale_sql = " AND (
                        EXISTS (
                            SELECT 1 FROM {$wpdb->postmeta} hwp
                            WHERE hwp.post_id = {$wpdb->posts}.ID
                                AND hwp.meta_key = %s AND hwp.meta_value = 'yes'
                        )
                        OR EXISTS (
                            SELECT 1 FROM {$wpdb->postmeta} wp
                            WHERE wp.post_id = {$wpdb->posts}.ID
                                AND wp.meta_key = %s AND CAST( wp.meta_value AS DECIMAL(10,2) ) > 0
                        )
                    )";
                $where_args[]  = $wholesale_role . '_have_wholesale_price';
                $where_args[]  = $wholesale_role . '_wholesale_price';
            }

            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, Squiz.Strings.DoubleQuoteUsage.NotRequired -- Ignored for allowing interpolation in IN query; all values are passed through $wpdb->prepare.
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT( DISTINCT {$wpdb->posts}.ID )
                    FROM {$wpdb->posts}
                        INNER JOIN {$wpdb->term_relationships} ON (
                            {$wpdb->posts}.ID = {$wpdb->term_relationships}.object_id
                        )
                        INNER JOIN {$wpdb->term_taxonomy} ON (
                            {$wpdb->term_relationships}.term_taxonomy_id = {$wpdb->term_taxonomy}.term_taxonomy_id
                        )
                        INNER JOIN {$wpdb->postmeta} vis ON (
                            {$wpdb->posts}.ID = vis.post_id
                            AND vis.meta_key = %s
                            AND vis.meta_value IN ( {$visibility_placeholders} )
                        )
                    WHERE
                        {$wpdb->posts}.post_status = 'publish'
                        AND {$wpdb->posts}.post_type = 'product'
                        AND {$wpdb->term_taxonomy}.taxonomy = 'product_cat'
                        AND {$wpdb->term_taxonomy}.term_id IN ( {$cat_in_placeholders} )
                        {$restricted_sql}
                        {$wholesale_sql}",
                    $where_args
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, Squiz.Strings.DoubleQuoteUsage.NotRequired -- Ignored for allowing interpolation in IN query; all values are passed through $wpdb->prepare.
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
