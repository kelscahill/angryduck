<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Integrations
 */

namespace RymeraWebCo\WWOF\Integrations;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Helpers\Product_Bundles as PB_Helper;
use RymeraWebCo\WWOF\Helpers\WWPP as WWPP_Helper;

/**
 * Product_Bundles integration class.
 *
 * Foundation issue (#933):
 *   - Include `bundle` product type in the order form query.
 *   - Augment the REST response with bundled item data required by the frontend.
 *   - Filter out bundles containing role-restricted children.
 *   - Register the plugin in localisation so the frontend can detect activation.
 *
 * UI, pricing, configuration, and add-to-cart flows are handled by downstream
 * sub-issues (#934-#942) and are intentionally out of scope here.
 *
 * @since 3.1.0
 */
class Product_Bundles extends Abstract_Class {

    /**
     * Return the current user's wholesale role slug, normalized to a string.
     *
     * `WWP_Wholesale_Roles::getUserWholesaleRole()` returns either an array
     * (typical) or a string depending on context. Callers across this class
     * only care about the first (primary) role as a plain string, so this
     * helper centralises the shape check.
     *
     * @since 3.1.0
     * @return string The wholesale role slug, or empty string if none.
     */
    private function get_current_wholesale_role() {
        global $wc_wholesale_prices;

        if ( ! isset( $wc_wholesale_prices->wwp_wholesale_roles ) ) {
            return '';
        }

        $role = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();

        return is_array( $role ) ? ( $role[0] ?? '' ) : (string) $role;
    }

    /**
     * Include `bundle` in the order form product query type whitelist.
     *
     * Hooked on `wwof_product_type_terms` which is fired from
     * `WC::rest_product_object_query()` around the tax query terms array.
     *
     * @param string[] $terms The product type terms to include in the query.
     *
     * @since 3.1.0
     * @return string[]
     */
    public function include_bundle_type( $terms ) {
        $terms[] = 'bundle';
        return $terms;
    }

    /**
     * Exclude bundles whose effective stock status is unbuyable when the
     * form's Zero Inventory setting is disabled.
     *
     * WC.php's existing `_stock_status IN ('instock', 'onbackorder')`
     * meta_query covers a bundle whose CONTAINER product is OOS, but
     * bundle-only unbuyability (`_bundled_items_stock_status =
     * 'outofstock'`, which maps to PB's `insufficientstock` / unbuyable
     * children state) is NOT filtered by that meta_query — the
     * container's `_stock_status` says "instock" even when the children
     * are not. Symptom: bundles whose required children were all OOS
     * stayed in the listing despite Zero Inventory being disabled. QA
     * flagged this as Issue 2.1 on PR #976.
     *
     * Look up the offending bundle post ids via a single direct SQL
     * query against `_bundled_items_stock_status` and append them to
     * `post__not_in`. Memoised per-request to avoid running the SQL
     * multiple times during a single REST round-trip (the order form's
     * pagination and category filter can each invoke the query layer).
     *
     * @param array                             $args           WP_Query args.
     * @param \WP_REST_Request                  $request        REST request.
     * @param \RymeraWebCo\WWOF\Integrations\WC $wc_integration WC integration instance.
     *
     * @since 3.1.0
     * @return array
     */
    public function filter_unbuyable_bundles( $args, $request, $wc_integration ) {

        $show_zero_inventory = $request->get_param( 'show_zero_inventory' );
        $show_zero_bool      = function_exists( 'wc_string_to_bool' )
            ? wc_string_to_bool( (string) $show_zero_inventory )
            : ( 'yes' === $show_zero_inventory || '1' === $show_zero_inventory || 1 === $show_zero_inventory || true === $show_zero_inventory );
        // Mirror WC.php's "enabled" alias so the legacy frontend keyword
        // still flips the filter off when the buyer explicitly opts in.
        if ( $show_zero_bool || 'enabled' === $show_zero_inventory ) {
            return $args;
        }

        $hidden_bundle_ids = self::get_unbuyable_bundle_ids();

        if ( empty( $hidden_bundle_ids ) ) {
            return $args;
        }

        $args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), $hidden_bundle_ids );

        return $args;
    }

    /**
     * Resolve every bundle post id whose effective stock status is
     * unbuyable.
     *
     * Two sources of "bundle is unbuyable" are unioned in a single SQL
     * pass:
     *
     *   1. **Container OOS** — the bundle product's own
     *      `_stock_status` postmeta is `outofstock`. WC.php's existing
     *      `_stock_status IN (instock,onbackorder)` meta_query already
     *      excludes these for non-bundle products, but the meta_query
     *      condition applies before our hook runs and is bounded by
     *      the bundle product type whitelist — confirming the
     *      container case here makes the override deterministic and
     *      keeps the union shape simple.
     *
     *   2. **Required child OOS** — at least one REQUIRED (non-optional)
     *      bundled child product has `_stock_status = 'outofstock'`. PB
     *      derives the bundle's `_bundled_items_stock_status` from
     *      child sync, but that prop is only populated when the bundle
     *      is saved AFTER PB's sync hooks fire. Bundles imported via
     *      chp or other fixture pipelines never trigger a PB save and
     *      so leave the prop empty — the previous query missed them.
     *      Going directly through the `woocommerce_bundled_items`
     *      table joined to the child's `_stock_status` postmeta is
     *      robust to PB's sync state. QA finding #3 on PR #976.
     *
     * Optional children are filtered out via the LEFT JOIN on
     * `woocommerce_bundled_itemmeta` (`meta_key='optional'`,
     * `meta_value='yes'`). Mirrors the pattern in
     * `get_role_restricted_bundle_ids()`.
     *
     * Cached in `wwof_queries` for 30 minutes plus a per-request
     * static; invalidation is TTL-only consistent with the role-
     * restricted query — admin stock-status changes take up to 30
     * minutes to reflect, acceptable for the listing path.
     *
     * @since 3.1.0
     * @return int[] Bundle post ids to add to `post__not_in`.
     */
    private static function get_unbuyable_bundle_ids() {

        static $cache = null;
        if ( null !== $cache ) {
            return $cache;
        }

        $cache_key = 'wwof_pb_unbuyable_bundles';
        $cached    = wp_cache_get( $cache_key, 'wwof_queries' );

        if ( false !== $cached ) {
            $cache = $cached;
            return $cached;
        }

        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $bundle_ids = $wpdb->get_col(
            "SELECT DISTINCT bundle_id FROM (
                /* Container OOS */
                SELECT p.ID AS bundle_id
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_stock
                    ON pm_stock.post_id = p.ID
                    AND pm_stock.meta_key = '_stock_status'
                    AND pm_stock.meta_value = 'outofstock'
                INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
                INNER JOIN {$wpdb->term_taxonomy} tt
                    ON tt.term_taxonomy_id = tr.term_taxonomy_id
                    AND tt.taxonomy = 'product_type'
                INNER JOIN {$wpdb->terms} t
                    ON t.term_id = tt.term_id
                    AND t.slug = 'bundle'
                WHERE p.post_type = 'product'
                AND p.post_status = 'publish'

                UNION

                /* Bundled-items legacy synced prop (kept as a faster path
                   when PB has had a chance to sync) */
                SELECT pm.post_id AS bundle_id
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = '_bundled_items_stock_status'
                AND pm.meta_value = 'outofstock'
                AND p.post_type = 'product'
                AND p.post_status = 'publish'

                UNION

                /* Required child OOS, derived directly from
                   woocommerce_bundled_items so the result is robust
                   regardless of whether PB has synced the parent. */
                SELECT wbi.bundle_id AS bundle_id
                FROM {$wpdb->prefix}woocommerce_bundled_items wbi
                INNER JOIN {$wpdb->posts} p ON p.ID = wbi.bundle_id
                INNER JOIN {$wpdb->postmeta} child_stock
                    ON child_stock.post_id = wbi.product_id
                    AND child_stock.meta_key = '_stock_status'
                    AND child_stock.meta_value = 'outofstock'
                LEFT JOIN {$wpdb->prefix}woocommerce_bundled_itemmeta wbim_optional
                    ON wbim_optional.bundled_item_id = wbi.bundled_item_id
                    AND wbim_optional.meta_key = 'optional'
                WHERE p.post_type = 'product'
                AND p.post_status = 'publish'
                AND ( wbim_optional.meta_value IS NULL OR wbim_optional.meta_value <> 'yes' )
            ) unbuyable"
        );
        // phpcs:enable

        $bundle_ids = array_map( 'intval', (array) $bundle_ids );

        wp_cache_set( $cache_key, $bundle_ids, 'wwof_queries', 30 * MINUTE_IN_SECONDS );
        $cache = $bundle_ids;

        return $bundle_ids;
    }

    /**
     * Exclude bundles whose REQUIRED bundled children are in a role-restricted
     * category.
     *
     * Delegates the whole visibility decision to a single cached SQL query
     * (`get_role_restricted_bundle_ids()`) that joins `woocommerce_bundled_items`
     * with the `optional` itemmeta and the `product_cat` taxonomy — so the
     * result already excludes bundles whose only restricted child is
     * optional. No per-candidate iteration / N+1 hop on the listing path.
     *
     * Reuses WWPP's memoised restricted category list via
     * `_get_restricted_product_cat_ids_for_wholesale_user()` so the role-to-cat
     * lookup runs once per request.
     *
     * @param array                             $args           WP_Query args.
     * @param \WP_REST_Request                  $request        REST request.
     * @param \RymeraWebCo\WWOF\Integrations\WC $wc_integration WC integration instance.
     *
     * @since 3.1.0
     * @return array
     */
    public function filter_role_restricted_bundles( $args, $request, $wc_integration ) {

        if ( ! WWPP_Helper::is_active() ) {
            return $args;
        }

        $user_role = $this->get_current_wholesale_role();

        if ( empty( $user_role ) ) {
            return $args;
        }

        global $wc_wholesale_prices_premium;

        if ( ! isset( $wc_wholesale_prices_premium->wwpp_query ) ) {
            return $args;
        }

        $restricted_cat_ids = $wc_wholesale_prices_premium->wwpp_query->_get_restricted_product_cat_ids_for_wholesale_user( $user_role );

        if ( empty( $restricted_cat_ids ) ) {
            return $args;
        }

        $hidden_bundle_ids = self::get_role_restricted_bundle_ids( $restricted_cat_ids );

        if ( empty( $hidden_bundle_ids ) ) {
            return $args;
        }

        $args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), $hidden_bundle_ids );

        return $args;
    }

    /**
     * Find bundles whose at-least-one REQUIRED child is in a restricted category.
     *
     * Single-query batch resolver — the LEFT JOIN against
     * `woocommerce_bundled_itemmeta` filters out optional children (`optional='yes'`)
     * so the result already represents the set the caller should hide. No
     * per-candidate iteration or per-child lookup is needed.
     *
     * Reads `term_relationships` / `term_taxonomy` directly to bypass the
     * `get_terms` filter chain that WWPP hooks at priority 99 to strip
     * restricted categories from the CURRENT user's term lookups — for the
     * very user we're trying to gate, going through the WC term helpers
     * would silently filter the restricted cat out and make this check
     * always pass. The direct SQL mirrors WWPP's own workaround in
     * `WWPP_Helper_Functions::is_product_restricted_in_category()`.
     *
     * Results are cached in the `wwof_queries` object cache group for 30
     * minutes (matching the cache tier used elsewhere in the plugin for
     * similar lookups), with an additional static per-request layer to
     * avoid even the object cache round-trip on repeated calls within a
     * single request.
     *
     * Invalidation is TTL-only — consistent with every other entry in the
     * `wwof_queries` group. An admin toggling
     * `wwpp_option_product_cat_wholesale_role_filter`, re-categorising a
     * bundled child, or flipping the `optional` flag on an existing
     * bundled item will not take effect for up to 30 minutes per role
     * cohort. Acceptable trade-off for the listing path's read-heavy
     * profile; a future hook on
     * `update_option_wwpp_option_product_cat_wholesale_role_filter` could
     * narrow the window if a store-owner report surfaces.
     *
     * @param int[] $restricted_cat_ids Restricted product_cat term ids for the current wholesale role.
     *
     * @since 3.1.0
     * @return int[] Bundle post ids to add to `post__not_in`.
     */
    private static function get_role_restricted_bundle_ids( $restricted_cat_ids ) {

        // Defensive shape coercion at entry. WWPP's
        // `_get_restricted_product_cat_ids_for_wholesale_user()` returns a
        // sequential array today, but `array_map( 'intval', ... )` alone
        // preserves keys — if WWPP ever returned an associative array
        // (`[ term_id => bool ]` is a common WP option shape), the
        // `...$restricted_cat_ids` spread below would feed VALUES, not
        // keys, into the `IN (%d,...)` placeholders. `array_values()`
        // re-indexes so the spread always lines up with the placeholders.
        $restricted_cat_ids = array_values( array_map( 'intval', (array) $restricted_cat_ids ) );

        if ( empty( $restricted_cat_ids ) ) {
            return array();
        }

        static $cache = array();

        // Sort on a copy — leaving the caller's array intact keeps the helper
        // pure even though PHP arrays are pass-by-value (defensive against
        // future refactors that might pass by reference).
        $sorted_ids = $restricted_cat_ids;
        sort( $sorted_ids );
        $key = md5( implode( ',', $sorted_ids ) );

        if ( isset( $cache[ $key ] ) ) {
            return $cache[ $key ];
        }

        $cache_key = 'wwof_pb_role_restricted_bundles_' . $key;
        $cached    = wp_cache_get( $cache_key, 'wwof_queries' );

        if ( false !== $cached ) {
            $cache[ $key ] = $cached;
            return $cached;
        }

        global $wpdb;

        $restricted_ids_placeholder = implode( ',', array_fill( 0, count( $restricted_cat_ids ), '%d' ) );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $bundle_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT wbi.bundle_id
                 FROM {$wpdb->prefix}woocommerce_bundled_items wbi
                 INNER JOIN $wpdb->term_relationships tr ON tr.object_id = wbi.product_id
                 INNER JOIN $wpdb->term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 LEFT JOIN {$wpdb->prefix}woocommerce_bundled_itemmeta wbim
                   ON wbim.bundled_item_id = wbi.bundled_item_id
                   AND wbim.meta_key = 'optional'
                 WHERE tt.taxonomy = 'product_cat'
                 AND tt.term_id IN ( $restricted_ids_placeholder )
                 AND ( wbim.meta_value IS NULL OR wbim.meta_value <> 'yes' )",
                ...$restricted_cat_ids
            )
        );
        // phpcs:enable

        $bundle_ids = array_map( 'intval', (array) $bundle_ids );

        wp_cache_set( $cache_key, $bundle_ids, 'wwof_queries', 30 * MINUTE_IN_SECONDS );
        $cache[ $key ] = $bundle_ids;

        return $bundle_ids;
    }

    /**
     * Find candidate bundles that contain at least one child in a restricted category.
     *
     * Broad pre-filter from the scaffold PR (#954). Returns every bundle with
     * at least one restricted child — REQUIRED or OPTIONAL. Optional-aware
     * narrowing is handled by the per-bundle helper
     * `is_bundle_visible_for_current_role()` below.
     *
     * Retained as a private static utility for defensive use (e.g. add-to-cart
     * fallback validation, future surfaces that want a broad candidate sweep
     * without the optional-aware narrowing). The hot listing-time path uses
     * `get_role_restricted_bundle_ids()` instead, which does the narrowing in
     * a single SQL query without an N+1 loop.
     *
     * Results are cached in the `wwof_queries` object cache group for 30
     * minutes, with an additional static per-request layer to avoid even the
     * object cache round-trip on repeated calls within a single request.
     *
     * @param int[] $restricted_cat_ids Restricted product_cat term ids.
     *
     * @since 3.1.0
     * @return int[] Candidate bundle post ids — superset of the bundles actually hidden.
     */
    private static function get_bundles_with_restricted_children( $restricted_cat_ids ) {

        if ( empty( $restricted_cat_ids ) ) {
            return array();
        }

        static $cache = array();

        // Sort on a copy — leaving the caller's array intact keeps the helper
        // pure even though PHP arrays are pass-by-value (defensive against
        // future refactors that might pass by reference).
        $sorted_ids = $restricted_cat_ids;
        sort( $sorted_ids );
        $key = md5( implode( ',', $sorted_ids ) );

        if ( isset( $cache[ $key ] ) ) {
            return $cache[ $key ];
        }

        $cache_key = 'wwof_pb_restricted_bundles_' . $key;
        $cached    = wp_cache_get( $cache_key, 'wwof_queries' );

        if ( false !== $cached ) {
            $cache[ $key ] = $cached;
            return $cached;
        }

        global $wpdb;

        $restricted_ids_placeholder = implode( ',', array_fill( 0, count( $restricted_cat_ids ), '%d' ) );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $bundle_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT wbi.bundle_id
                 FROM {$wpdb->prefix}woocommerce_bundled_items wbi
                 INNER JOIN $wpdb->term_relationships tr ON tr.object_id = wbi.product_id
                 INNER JOIN $wpdb->term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 WHERE tt.taxonomy = 'product_cat'
                 AND tt.term_id IN ( $restricted_ids_placeholder )",
                ...$restricted_cat_ids
            )
        );
        // phpcs:enable

        $bundle_ids = array_map( 'intval', (array) $bundle_ids );

        wp_cache_set( $cache_key, $bundle_ids, 'wwof_queries', 30 * MINUTE_IN_SECONDS );
        $cache[ $key ] = $bundle_ids;

        return $bundle_ids;
    }

    /**
     * Determine whether a bundle is visible to the current wholesale role.
     *
     * Per-bundle decision routine from the scaffold PR (#954). Distinguishes
     * REQUIRED from OPTIONAL children so a bundle whose only restricted child
     * is optional remains visible (the buyer can opt that child out before
     * adding to cart).
     *
     * Retained as a private utility for defensive use (per-bundle visibility
     * decision at add-to-cart, modal open, REST single-product fetch — any
     * surface that wants the optional-aware decision for a known bundle id).
     * The hot listing-time path in `filter_role_restricted_bundles()` delegates
     * to the single-query `get_role_restricted_bundle_ids()` instead so the
     * decision for every bundle costs exactly one DB query.
     *
     * Returns `true` whenever WWPP is inactive, the user has no wholesale
     * role, no product categories are restricted, or PB's DB layer is
     * unavailable — so callers can invoke it unconditionally without
     * guarding the integration's runtime requirements.
     *
     * @param int $bundle_id The bundle product post id.
     *
     * @since 3.1.0
     * @return bool True if the bundle should remain visible; false otherwise.
     */
    private function is_bundle_visible_for_current_role( $bundle_id ) {

        if ( ! WWPP_Helper::is_active() ) {
            return true;
        }

        $user_role = $this->get_current_wholesale_role();
        if ( '' === $user_role ) {
            return true;
        }

        global $wc_wholesale_prices_premium;

        if ( ! isset( $wc_wholesale_prices_premium->wwpp_query ) ) {
            return true;
        }

        $restricted_cat_ids = $wc_wholesale_prices_premium->wwpp_query->_get_restricted_product_cat_ids_for_wholesale_user( $user_role );
        if ( empty( $restricted_cat_ids ) ) {
            return true;
        }

        $restricted_cat_ids = array_map( 'intval', (array) $restricted_cat_ids );

        if ( ! class_exists( '\WC_PB_DB' ) ) {
            return true;
        }

        // `query_bundled_items()` defaults to `return = 'all'`, which yields an
        // array of plain arrays cast from `$wpdb->get_results()` rows — no
        // `get_product_id()` / `get_meta()` methods. Ask explicitly for the
        // object shape (`WC_Bundled_Item_Data` instances) so the optional/meta
        // accessors below resolve.
        $bundled_items = \WC_PB_DB::query_bundled_items(
            array(
                'bundle_id' => (int) $bundle_id,
                'return'    => 'objects',
            )
        );
        if ( empty( $bundled_items ) ) {
            return true;
        }

        $required_children = array();

        foreach ( $bundled_items as $bundled_item_data ) {

            if ( ! is_object( $bundled_item_data ) || ! method_exists( $bundled_item_data, 'get_product_id' ) ) {
                continue;
            }

            $optional_meta = method_exists( $bundled_item_data, 'get_meta' ) ? $bundled_item_data->get_meta( 'optional' ) : '';
            if ( 'yes' === $optional_meta ) {
                continue;
            }

            $child_id      = (int) $bundled_item_data->get_product_id();
            $child_cat_ids = self::get_product_cat_ids_unfiltered( $child_id );

            $required_children[ $child_id ] = $child_cat_ids;
        }

        return ! self::any_required_child_in_restricted_category( $required_children, $restricted_cat_ids );
    }

    /**
     * Fetch a product's `product_cat` term ids via direct SQL.
     *
     * `wc_get_product_term_ids()` / `wp_get_post_terms()` flow through the
     * `get_terms` filter, which WWPP hooks at priority 99 to strip categories
     * the CURRENT user can't access. That filtering is correct for the catalog
     * surface (it hides restricted categories from term widgets and lists) but
     * silently breaks #950's visibility check: for the very user we're trying
     * to gate, the restricted category is filtered OUT of the child's term
     * list, so the helper concludes the child is safe and the bundle stays
     * visible.
     *
     * Mirror WWPP's own workaround in
     * `WWPP_Helper_Functions::is_product_restricted_in_category()` — a direct
     * `term_relationships` + `term_taxonomy` join keyed on `object_id` and
     * `taxonomy = 'product_cat'`, which is what `get_terms` itself reads from
     * before the filter chain runs.
     *
     * Results are memoised per-request via a static cache keyed on
     * `$product_id`. The caller `is_bundle_visible_for_current_role()`
     * iterates one child at a time, so a bundle with N required children
     * would otherwise produce N direct-SQL queries; the cache makes
     * repeated lookups for the same product within a single request free.
     *
     * @param int $product_id The product post id.
     *
     * @since 3.1.0
     * @return int[] The raw product_cat term ids for the product, unfiltered.
     */
    private static function get_product_cat_ids_unfiltered( $product_id ) {

        $product_id = (int) $product_id;
        $cache      = &self::product_cat_ids_cache_ref();

        if ( isset( $cache[ $product_id ] ) ) {
            return $cache[ $product_id ];
        }

        global $wpdb;

        // NoCaching disable is correct — caching IS in place, just via the
        // per-request static `product_cat_ids_cache_ref()` above this query
        // (terms are stable for the request, so a longer-lived tier isn't
        // warranted).
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $term_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT tt.term_id
                 FROM {$wpdb->term_relationships} tr
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 WHERE tr.object_id = %d AND tt.taxonomy = %s",
                $product_id,
                'product_cat'
            )
        );
        // phpcs:enable

        $cache[ $product_id ] = array_map( 'intval', (array) $term_ids );

        return $cache[ $product_id ];
    }

    /**
     * Backing storage for the per-request product_cat ids cache.
     *
     * Returned by reference so the production helper and the test-reset
     * seam below share a single static array. Mirrors the existing
     * `attribute_label_cache_ref()` pattern in this file.
     *
     * @since 3.1.0
     * @return array<int, int[]>
     */
    private static function &product_cat_ids_cache_ref() {

        static $cache = array();
        return $cache;
    }

    /**
     * Reset the per-request product_cat ids cache.
     *
     * Test-only seam invoked by Pest's `afterEach` so memoised entries from
     * one scenario don't leak into the next. Production code never calls
     * this — cats are stable for the lifetime of a request.
     *
     * @since 3.1.0
     * @return void
     */
    private static function reset_product_cat_ids_cache_for_tests() {

        $cache = &self::product_cat_ids_cache_ref();
        $cache = array();
    }

    /**
     * Pure decision helper extracted for unit-testability.
     *
     * Given a `[product_id => [cat_id, ...]]` map of REQUIRED children
     * (optional children are filtered out by the caller) and a list of
     * restricted category ids, return true if any required child belongs
     * to a restricted category.
     *
     * Splitting it into a pure function lets the scaffold's tests cover the
     * four scenarios #950 cares about (all-clear, one required restricted,
     * only optional restricted, baseline) without spinning up WC, WWPP, or
     * PB.
     *
     * @param array<int, int[]> $required_children  Map of required child product id → array of category ids.
     * @param int[]             $restricted_cat_ids Restricted category ids.
     *
     * @since 3.1.0
     * @return bool
     */
    private static function any_required_child_in_restricted_category( $required_children, $restricted_cat_ids ) {

        if ( empty( $required_children ) || empty( $restricted_cat_ids ) ) {
            return false;
        }

        $restricted = array_map( 'intval', (array) $restricted_cat_ids );

        foreach ( $required_children as $cat_ids ) {
            if ( ! is_array( $cat_ids ) || empty( $cat_ids ) ) {
                continue;
            }
            if ( array_intersect( array_map( 'intval', $cat_ids ), $restricted ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Backing storage for the per-request attribute-label cache.
     *
     * Returned by reference so callers (`resolve_attribute_labels` and the
     * test seam below) share a single static array instead of declaring
     * `static` storage inside each function — a function-local `static`
     * cannot be cleared from outside, which would leak state across tests
     * in the same PHP process.
     *
     * @since 3.1.0
     * @return array<string, string>
     */
    private static function &attribute_label_cache_ref() {

        static $cache = array();
        return $cache;
    }

    /**
     * Reset the attribute-label cache.
     *
     * Test-only seam invoked by Pest's `afterEach` so memoised entries
     * from one scenario don't leak into the next. Production code never
     * calls this — labels are stable for the lifetime of a request.
     *
     * @since 3.1.0
     * @return void
     */
    private static function reset_attribute_label_cache_for_tests() {

        $cache = &self::attribute_label_cache_ref();
        $cache = array();
    }

    /**
     * Resolve human-readable labels for a variation's attribute slugs.
     *
     * The order form's variation dropdown currently shows the raw slug
     * value (`oak-matte`), which #947 will swap for the term name
     * (`Oak — Matte`).
     *
     * WC stores the chosen term per variation as post meta of the form
     * `attribute_<taxonomy> => <slug>`, NOT as a term assignment on the
     * variation post. So `wc_get_product_terms()` against the variation
     * id returns an empty list. Use the slug we already hold to look up
     * the term name directly via `get_term_by()`.
     *
     * Lookups are memoised per request, keyed by `taxonomy|slug` —
     * variation id doesn't affect the result, so sharing the cache
     * across every variation row in a product list page is correct and
     * substantially raises hit rate.
     *
     * Custom (non-taxonomy) attributes already carry the human-readable
     * value in `$attributes`; pass them through unchanged.
     *
     * @param int                   $variation_id The variation post id (used only to short-circuit invalid input).
     * @param array<string, string> $attributes   Variation attributes keyed by `attribute_<taxonomy>`.
     *
     * @since 3.1.0
     * @return array<string, string> Map of attribute key → human-readable label.
     */
    private static function resolve_attribute_labels( $variation_id, $attributes ) {

        $cache = &self::attribute_label_cache_ref();

        $labels = array();

        if ( ! is_array( $attributes ) || $variation_id <= 0 ) {
            return $labels;
        }

        foreach ( $attributes as $attribute_key => $attribute_value ) {

            $key      = (string) $attribute_key;
            $value    = (string) $attribute_value;
            $taxonomy = 0 === strpos( $key, 'attribute_' ) ? substr( $key, strlen( 'attribute_' ) ) : '';

            if ( '' === $taxonomy || '' === $value || ! taxonomy_exists( $taxonomy ) ) {
                $labels[ $key ] = $value;
                continue;
            }

            $cache_key = $taxonomy . '|' . $value;

            if ( ! isset( $cache[ $cache_key ] ) ) {
                $term                = get_term_by( 'slug', $value, $taxonomy );
                $cache[ $cache_key ] = ( $term && ! is_wp_error( $term ) && '' !== $term->name ) ? $term->name : $value;
            }

            $labels[ $key ] = $cache[ $cache_key ];
        }

        return $labels;
    }

    /**
     * Augment the REST response with bundle data.
     *
     * Adds a `wwof_product_bundles` key containing bundled-item metadata the
     * frontend needs to render configuration controls, compute wholesale ranges,
     * and build add-to-cart payloads.
     *
     * @param \WP_REST_Response $response       The REST response.
     * @param \WC_Data          $wc_data        The product data.
     * @param \WP_REST_Request  $request        The request.
     *
     * @since 3.1.0
     * @return \WP_REST_Response
     */
    public function add_bundle_data_to_response( $response, $wc_data, $request ) {

        if ( ! $wc_data || 'bundle' !== $wc_data->get_type() ) {
            return $response;
        }

        $wholesale_role = $this->get_current_wholesale_role();
        $bundle         = $wc_data instanceof \WC_Product_Bundle ? $wc_data : wc_get_product( $wc_data->get_id() );

        if ( ! $bundle instanceof \WC_Product_Bundle ) {
            return $response;
        }

        $bundled_items = $bundle->get_bundled_items();
        $items         = array();

        foreach ( $bundled_items as $bundled_item ) {

            $child_product = $bundled_item->product;

            if ( ! $child_product ) {
                continue;
            }

            $child_id                   = $bundled_item->get_product_id();
            $child_type                 = $child_product->get_type();
            $variation_id               = 0;
            $default_attrs              = array();
            $available_vars             = array();
            $variation_wholesale_prices = array();

            if ( 'variable' === $child_type ) {
                $variation_id  = PB_Helper::get_default_variation_id( $bundled_item );
                $default_attrs = $bundled_item->get_selected_product_variation_attributes();
                $product_vars  = $bundled_item->get_product_variations();

                /*
                 * `$variation['display_price']` is the buyer-facing
                 * RETAIL amount PB derives via its own pricing layer
                 * (`woocommerce_bundled_item_price` + WC core's
                 * `woocommerce_product_get_price`). WWP/WWPP do not
                 * hook those filters, so the value never reflects
                 * wholesale pricing for a wholesale role — the order
                 * form's configure-panel per-child price column would
                 * therefore display retail. Look up each variation's
                 * wholesale price once via
                 * `get_variation_wholesale_price_map()` (scoped to
                 * the bundle-allowed variation set PB already gave us
                 * in `$product_vars`) and reuse the map for BOTH the
                 * per-variation `price` field below AND the bundled-
                 * item-level `wholesale_price` (the MIN derived via
                 * `min_from_wholesale_price_map()`). Fall back to
                 * `display_price` when the variation has no wholesale
                 * meta (retail-only variation, retail user with no
                 * resolvable role).
                 */
                $variation_ids_for_map = array();
                if ( is_array( $product_vars ) ) {
                    foreach ( $product_vars as $variation ) {
                        $vid = isset( $variation['variation_id'] ) ? (int) $variation['variation_id'] : 0;
                        if ( $vid > 0 ) {
                            $variation_ids_for_map[] = $vid;
                        }
                    }
                }
                $variation_wholesale_prices = PB_Helper::get_variation_wholesale_price_map( $variation_ids_for_map, $wholesale_role );

                $available_vars = is_array( $product_vars ) ? array_map(
                    function ( $variation ) use ( $variation_wholesale_prices ) {
                        $variation_id_int = isset( $variation['variation_id'] ) ? (int) $variation['variation_id'] : 0;
                        $attributes       = isset( $variation['attributes'] ) ? $variation['attributes'] : array();
                        $wholesale_price  = isset( $variation_wholesale_prices[ $variation_id_int ] )
                            ? $variation_wholesale_prices[ $variation_id_int ]
                            : '';
                        // `$variation_wholesale_prices` omits variations that have no
                        // wholesale meta for the current role (see
                        // `get_variation_wholesale_price_map()`), so a present, non-empty
                        // value means this variation IS wholesale-priced. Surface that as
                        // an explicit boolean the frontend uses to mark retail-only
                        // variations for wholesale buyers (#985) — the resolved `price`
                        // alone cannot distinguish a wholesale amount from a
                        // retail-fallback amount once the fallback has been applied below.
                        // Always `false` for retail users (empty role → empty map).
                        $has_wholesale_price = '' !== $wholesale_price;
                        $price               = $has_wholesale_price
                            ? $wholesale_price
                            : ( isset( $variation['display_price'] ) ? (string) $variation['display_price'] : '' );
                        return array(
                            'variation_id'        => $variation_id_int,
                            'attributes'          => $attributes,
                            'attribute_labels'    => self::resolve_attribute_labels( $variation_id_int, $attributes ),
                            'price'               => $price,
                            'is_wholesale_priced' => $has_wholesale_price,
                            'stock_status'        => isset( $variation['is_in_stock'] ) && $variation['is_in_stock'] ? 'instock' : 'outofstock',
                        );
                    },
                    $product_vars
                ) : array();
            }

            // Variable children derive the item-level wholesale price as
            // the MIN of the per-variation map already built above. The
            // map is built from `$product_vars` (PB's bundle-allowed
            // variation set) so the MIN matches what the buyer can
            // actually select in the dropdown — not the parent's full
            // `get_visible_children()` set, which can include variations
            // PB has excluded from this specific bundled item.
            // Simple/grouped/composite children flow through
            // `get_wholesale_price_for_child()`; that helper does not
            // handle variable parents (see its docblock).
            $item_wholesale_price = ( 'variable' === $child_type )
                ? PB_Helper::min_from_wholesale_price_map( $variation_wholesale_prices )
                : PB_Helper::get_wholesale_price_for_child( $child_id, $wholesale_role );

            $items[] = array(
                'bundled_item_id'      => (int) $bundled_item->get_id(),
                'product_id'           => $child_id,
                'title'                => (string) $bundled_item->get_title(),
                'quantity_default'     => $bundled_item->get_quantity( 'default' ),
                'quantity_min'         => $bundled_item->get_quantity( 'min' ),
                'quantity_max'         => $bundled_item->get_quantity( 'max' ),
                'optional'             => (bool) $bundled_item->is_optional(),
                'priced_individually'  => (bool) $bundled_item->is_priced_individually(),
                'type'                 => (string) $child_type,
                'price'                => (string) $bundled_item->get_price(),
                'regular_price'        => (string) $bundled_item->get_regular_price(),
                'wholesale_price'      => $item_wholesale_price,
                'discount'             => (string) $bundled_item->get_discount(),
                // Normalise PB's `in_stock` / `out_of_stock` /
                // `on_backorder` / `insufficient_stock` values to WC's
                // standard `instock` / `outofstock` / `onbackorder` so
                // every downstream consumer — `available_variations[]`,
                // the bundle container, and the frontend's bundle
                // helpers — uses one vocabulary. Without this the
                // configure-panel's per-child OOS label and
                // `isBundleEffectivelyOutOfStock()` both miss simple
                // children because PB's underscore form never matches
                // `'outofstock'`. QA follow-up on PR #976.
                'stock_status'         => str_replace( '_', '', (string) $bundled_item->get_stock_status() ),
                'variation_id'         => $variation_id,
                'default_attributes'   => $default_attrs,
                'available_variations' => $available_vars,
            );
        }

        $bundle_stock_status = (string) $bundle->get_bundle_stock_status();

        $response->data['wwof_product_bundles'] = array(
            'is_bundle'           => true,
            'bundled_items'       => $items,
            'pricing_mode'        => $bundle->contains( 'priced_individually' ) ? 'per-item' : 'static',
            'bundle_stock_status' => $bundle_stock_status,
            'min_bundle_size'     => $bundle->get_min_bundle_size(),
            'max_bundle_size'     => $bundle->get_max_bundle_size(),
        );

        /*
         * Override the response's `stock_status` for bundles whose effective
         * status (computed across container + required children) says the
         * bundle cannot be purchased. See `map_bundle_stock_status()` below
         * for the full rationale.
         *
         * @since 3.1.0 — QA finding F5 from issue #951.
         */
        $override = self::map_bundle_stock_status( $bundle_stock_status );
        if ( null !== $override ) {
            $response->data['stock_status'] = $override;
        }

        /*
         * PB's `get_price_html()` depends on bundled-item state being materialized;
         * in WC's REST preparation the response can arrive with `price_html` empty
         * for bundles (leaving retail/guest users with a blank price cell in the
         * order form). We've already loaded `get_bundled_items()` above, so
         * re-call `get_price_html()` now and persist whatever PB produces. The
         * wholesale-user override in `formatBundlePriceHtml` (TS) still wins via
         * `isWholesaleUser()` on the frontend; this only fills in the retail path.
         */
        if ( empty( $response->data['price_html'] ) ) {
            $response->data['price_html'] = (string) $bundle->get_price_html();
        }

        return $response;
    }

    /**
     * Map PB's bundle stock status enum to WC's canonical `stock_status` value.
     *
     * `WC_Product_Bundle::get_bundle_stock_status()` returns one of `instock`,
     * `outofstock`, or `insufficientstock` (no underscores — see the const
     * lookups at lines 599 + 1532 of PB's `class-wc-product-bundle.php`).
     * The bundle container's own `stock_status` is "instock" whenever the
     * parent product is published regardless of children, so the order
     * form's `InStockAmount` / `AddToCartButton` checks (which read
     * `product.stock_status`) treat all-OOS bundles as buyable — the buyer
     * would click and hit PB's `woocommerce_bundle_invalid_bundle_stock_status`
     * error toast. Both unbuyable states (`outofstock`, `insufficientstock`)
     * are mapped to WC's canonical `outofstock` so every existing frontend
     * consumer of `stock_status` treats the bundle as OOS uniformly without
     * per-call wiring.
     *
     * Returns `null` when no override applies — caller leaves the response's
     * `stock_status` field untouched. Pure (no WC / PB dependencies) so the
     * mapping can be vitest-equivalent unit-tested via reflection.
     *
     * @param string $bundle_stock_status PB's effective bundle status enum.
     *
     * @since 3.1.0
     * @return string|null Canonical `outofstock` when the bundle is unbuyable, null otherwise.
     */
    private static function map_bundle_stock_status( $bundle_stock_status ) {

        if ( in_array( $bundle_stock_status, array( 'outofstock', 'insufficientstock' ), true ) ) {
            return 'outofstock';
        }

        return null;
    }

    /**
     * Register the plugin in localisation defaults.
     *
     * Adds `woocommerce-product-bundles` to `thirdPartyPlugins` so the frontend
     * can detect activation, and appends `wwof_product_bundles` to the `_fields`
     * list so the augmented REST data is actually requested.
     *
     * @param array $defaults The localisation defaults.
     *
     * @since 3.1.0
     * @return array
     */
    public function register_third_party_plugin( $defaults ) {
        array_push( $defaults['thirdPartyPlugins'], 'woocommerce-product-bundles' );
        array_push( $defaults['_fields'], 'wwof_product_bundles' );
        return $defaults;
    }

    /**
     * Run the Product Bundles integration hooks.
     *
     * All hook registration is gated behind the `is_active()` guard so there is
     * zero overhead when Product Bundles is not installed or does not meet the
     * minimum version requirement.
     *
     * @since 3.1.0
     */
    public function run() {

        if ( ! PB_Helper::is_active() ) {
            return;
        }

        add_filter( 'wwof_product_type_terms', array( $this, 'include_bundle_type' ), 10, 1 );
        add_filter( 'wwof_wc_rest_product_object_query', array( $this, 'filter_role_restricted_bundles' ), 10, 3 );
        add_filter( 'wwof_wc_rest_product_object_query', array( $this, 'filter_unbuyable_bundles' ), 10, 3 );
        add_filter( 'wwof_wc_rest_prepare_product_object', array( $this, 'add_bundle_data_to_response' ), 10, 3 );
        add_filter( 'wwof_order_form_app_common_l10n_defaults', array( $this, 'register_third_party_plugin' ), 10, 1 );
    }
}
