<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Wholesale_Price_Variable_Product' ) ) {

    /**
     * Model that houses the logic of wholesale prices for variable products.
     *
     * @since 1.13.4
     * @since 2.1.1 Keep products priced only via a category-level wholesale discount visible in the catalog when only showing wholesale products to wholesale users.
     */
    class WWPP_Wholesale_Price_Variable_Product {
        /*
        |--------------------------------------------------------------------------
        | Class Constants
        |--------------------------------------------------------------------------
         */

        /**
         * Action Scheduler group for the plugin's one-shot data migrations.
         *
         * @since 2.1.1
         * @var string
         */
        const SEED_AS_GROUP = 'wwpp-migrations';

        /**
         * Number of variable products seeded per Action Scheduler batch.
         *
         * Mirrors the role-automation sweep chunk size so large catalogs are
         * migrated across several short AS ticks instead of one long-running job.
         *
         * @since 2.1.1
         * @var int
         */
        const SEED_BATCH_SIZE = 100;

        /*
        |--------------------------------------------------------------------------
        | Class Properties
        |--------------------------------------------------------------------------
         */

        /**
         * Property that holds the single main instance of WWPP_Admin_Custom_Fields_Variable_Product.
         *
         * @since 1.13.0
         * @access private
         * @var WWPP_Admin_Custom_Fields_Variable_Product
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since 1.13.0
         * @access private
         * @var WWPP_Wholesale_Roles
         */
        private $_wwpp_wholesale_roles;

        /**
         * Model that houses the logic of product wholesale price on per wholesale role level.
         *
         * @since 1.16.0
         * @access private
         * @var WWPP_Wholesale_Price_Wholesale_Role
         */
        private $_wwpp_wholesale_price_wholesale_role;

        /**
         * In-request cache of computed "has restricted variations" results, keyed by product id.
         *
         * `filter_variable_product_price_hash()` can run several times for the same
         * variable product within a single page render, so this dedupes the meta read
         * (and any one-time backfill write) to a single evaluation per product per request.
         *
         * @since 2.1.1
         * @access private
         * @var array
         */
        private $_has_restricted_variations_cache = array();

        /**
         * In-request cache of `filter_variable_simple_pre_get_post__in()` results.
         *
         * The `wwpp_pre_get_post__in` filter can fire several times per request
         * (main shop/category/search query, widgets, shortcodes, WWOF and block
         * queries), each time re-running three catalog-wide `IN()` queries plus a
         * bundle lookup over the same id list. Keyed by `md5( id-list ) . '|' . role`
         * this serves an identical ( id-list, role ) computation once per request.
         * Request scoped only (PHP-FPM bootstraps fresh per request), so it needs no
         * invalidation and cannot return stale visibility across requests.
         *
         * @since 2.1.1
         * @access private
         * @var array
         */
        private $_pre_get_post__in_cache = array();

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWPP_Admin_Custom_Fields_Variable_Product constructor.
         *
         * @since 1.13.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Admin_Custom_Fields_Variable_Product model.
         */
        public function __construct( $dependencies ) {

            $this->_wwpp_wholesale_roles                = $dependencies['WWPP_Wholesale_Roles'];
            $this->_wwpp_wholesale_price_wholesale_role = $dependencies['WWPP_Wholesale_Price_Wholesale_Role'];
        }

        /**
         * Ensure that only one instance of WWPP_Admin_Custom_Fields_Variable_Product is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.13.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Admin_Custom_Fields_Variable_Product model.
         * @return WWPP_Admin_Custom_Fields_Variable_Product
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Get curent user wholesale role.
         *
         * @since 1.15.0
         * @access private
         *
         * @return string User role string or empty string.
         */
        private function _get_current_user_wholesale_role() {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            $wholesale_role = ( is_array( $user_wholesale_role ) && ! empty( $user_wholesale_role ) ) ? $user_wholesale_role[0] : '';

            return apply_filters( 'wwpp_get_current_wholesale_role', $wholesale_role );
        }

        /**
         * Filter the display format of variable product price for wholesale customers.
         *
         * @since 1.14.0
         * @since 1.16.0 Include bug fix for WWPP-483.
         * @access public
         *
         * @param array $args Array of arguments.
         * @return string Filtered variable product formatted price.
         */
        public function filter_wholesale_customer_variable_product_price_range( $args ) {

            if ( ! empty( $args['wholesale_price'] ) && $args['min_price'] !== $args['max_price'] && $args['min_price'] < $args['max_price'] ) {

                $return_value = array();
                $display_mode = get_option( 'wwpp_settings_variable_product_price_display' );

                if ( in_array( $display_mode, array( 'minimum', 'maximum' ), true ) ) {

                    $pos = strrpos( $args['wholesale_price_title_text'], ':' );
                    if ( false !== $pos ) {
                        $args['wholesale_price_title_text'] = substr_replace( $args['wholesale_price_title_text'], '', $pos, strlen( ':' ) );
                    }

                    $args['wholesale_price_title_text'] .= 'minimum' === $display_mode ? __( ' From: ', 'woocommerce-wholesale-prices-premium' ) : __( ' To: ', 'woocommerce-wholesale-prices-premium' );

                    $return_value['wholesale_price_title_text'] = $args['wholesale_price_title_text'];

                }

                switch ( $display_mode ) {

                    case 'minimum':
                        $return_value['wholesale_price'] = WWP_Helper_Functions::wwp_formatted_price( $args['min_price'] );

                        if ( ! $args['return_wholesale_price_only'] ) {

                            $wsprice                          = ! empty( $args['min_wholesale_price_without_taxing'] ) ? $args['min_wholesale_price_without_taxing'] : null;
                            $return_value['wholesale_price'] .= WWP_Wholesale_Prices::get_wholesale_price_suffix( $args['product'], $args['user_wholesale_role'], $wsprice );

                        }

                        return $return_value;

                    case 'maximum':
                        $return_value['wholesale_price'] = WWP_Helper_Functions::wwp_formatted_price( $args['max_price'] );

                        if ( ! $args['return_wholesale_price_only'] ) {

                            $wsprice                          = ! empty( $args['max_wholesale_price_without_taxing'] ) ? $args['max_wholesale_price_without_taxing'] : null;
                            $return_value['wholesale_price'] .= WWP_Wholesale_Prices::get_wholesale_price_suffix( $args['product'], $args['user_wholesale_role'], $wsprice );

                        }

                        return $return_value;

                    default:
                        $return_value['wholesale_price'] = WWP_Helper_Functions::wwp_formatted_price( $args['min_price'] ) . ' - ' . WWP_Helper_Functions::wwp_formatted_price( $args['max_price'] );

                        $price_suffix = get_option( 'wwpp_settings_override_price_suffix' );
                        if ( empty( $price_suffix ) ) {
                            $price_suffix = get_option( 'woocommerce_price_display_suffix' );
                        }

                        if ( ! str_contains( $price_suffix, '{price_including_tax}' ) && ! str_contains( $price_suffix, '{price_excluding_tax}' ) && ! $args['return_wholesale_price_only'] ) {

                            $wsprice                          = ! empty( $args['max_wholesale_price_without_taxing'] ) ? $args['max_wholesale_price_without_taxing'] : null;
                            $return_value['wholesale_price'] .= WWP_Wholesale_Prices::get_wholesale_price_suffix( $args['product'], $args['user_wholesale_role'], $wsprice );

                        }

                        return $return_value;

                }
            } else {
                return array( 'wholesale_price' => $args['wholesale_price'] );
            }
        }

        /**
         * Filter available variable product variations.
         * The main purpose for this is to address the product price range of a variable product for non wholesale customers.
         * You see in wwpp, you can set some variations of a variable product to be exclusive only to a certain wholesale roles.
         * Now if we dont do the code below, the price range computation for regular customers will include those variations that are exclusive only to certain wholesale roles.
         * Therefore making the calculation wrong. That is why we need to filter the variation ids of a variable product depending on the current user's role.
         * This function is a replacement to our in-house built function 'filter_regular_customer_variable_product_price_range' which is not really efficient.
         * Basically 'filter_regular_customer_variable_product_price_range' function re invents the wheel and we are recreating the price range for non wholesale users ourselves. Not good.
         * 'filter_regular_customer_variable_product_price_range' function is now removed.
         *
         * Important Note: WooCommerce tend to save a cache data of a product on transient, that is why sometimes this hook 'woocommerce_get_children' will not be executed
         * if there is already a cached data on transient. No worries tho, on version 1.15.0 of WWPP we are now clearing WC transients on WWPP activation so we are sure that 'woocommerce_get_children' will be executed.
         * We only need to do that once on WWPP activation coz, individual product transient is cleared on every product update on the backend.
         * So if they update the variation visibility on the backend, of course they will hit save to save the changes, that will clear the transient for this product and in turn executing this callback. So all good.
         *
         * @since 1.15.0
         * @since 1.25.1  Fix issue regarding variable product that has some wholesale price and some are not still counted in the price range if "Only Show Wholesale Products To Wholesale Customers" is enabled.
         * @since 2.1.1   Prime the variation meta cache once before the per-variation get_post_meta() loop to collapse N single-row queries into one.
         * @access public
         *
         * @param array               $children Array of variation ids.
         * @param WC_Product_Variable $product  Variable product instance.
         * @return array Filtered array of variation ids.
         */
        public function filter_available_variable_product_variations( $children, $product ) {

            if ( $this->is_not_admin() && WWP_Helper_Functions::wwp_get_product_type( $product ) === 'variable' ) {

                $product_id                           = $product->get_id();
                $user_wholesale_role                  = $this->_get_current_user_wholesale_role();
                $only_show_to_wholesale_users         = get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false ) === 'yes' ? true : false;
                $variations_with_wholesale_price_list = WWP_Helper_Functions::get_formatted_meta_data( $product, $user_wholesale_role . '_variations_with_wholesale_price' ); // Per product/variation wholesale price.
                $category_level_wholesale_price       = $product->get_meta( $user_wholesale_role . '_have_wholesale_price_set_by_product_cat', true ); // Category wholesale price.
                $filtered_children                    = array();

                // Ignore Category Discount.
                $disregard_cat_level_discount = apply_filters( 'wwpp_disregard_cat_level_discount', $product->get_meta( 'wwpp_ignore_cat_level_wholesale_discount', true ) );

                // Ignore role level discount.
                $disregard_role_level_discount = apply_filters( 'wwpp_disregard_role_level_discount', $product->get_meta( 'wwpp_ignore_role_level_wholesale_discount', true ) );

                // Variations that has wholesale price.
                $variations_with_wholesale_price = isset( $user_wholesale_role ) ? $variations_with_wholesale_price_list : array();

                // General Discount.
                $wholesale_role_discount = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, array() );

                if ( ! empty( $children ) ) {
                    // Prime the meta cache for every variation in a single query so the
                    // per-variation get_post_meta() reads below hit the object cache
                    // instead of issuing one SELECT against wp_postmeta per variation.
                    // Collapse N single-row meta queries into one.
                    update_meta_cache( 'post', $children );

                    // Get all children of the variable product.
                    // @since 2.0.0.6 Changes for load issues on variable products with many variations.
                    // @since 2.0.1 Change back to get_post_meta to avoid performance issue.
                    foreach ( $children as $variation_id ) {
                        $roles_variation_is_visible = get_post_meta( $variation_id, WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER, false );

                        if ( ! empty( $roles_variation_is_visible ) && ( in_array( 'all', $roles_variation_is_visible, true ) || in_array( $user_wholesale_role, $roles_variation_is_visible, true ) ) ) {
                            $filtered_children[] = $variation_id;
                        }
                    }
                }

                // Only display variations that is visible for the current wholesale customer.
                if ( is_array( $variations_with_wholesale_price ) && is_array( $filtered_children ) ) {
                    $variations_with_wholesale_price = array_intersect( $variations_with_wholesale_price, $filtered_children );
                }

                // Only Show Wholesale Products To Wholesale Customers is enabled
                // No General Discount
                // Has Category Level Wholesale Pricing
                // Disregard Category Level is enabled
                // Has variations with wholesale price set in product level.
                if (
                    $only_show_to_wholesale_users && array_key_exists( $user_wholesale_role, $wholesale_role_discount ) && 'yes' === $category_level_wholesale_price && 'yes' === $disregard_cat_level_discount && 'yes' === $disregard_role_level_discount && ! empty( $variations_with_wholesale_price )
                ) {
                    return $variations_with_wholesale_price;
                }

                // Only Show Wholesale Products To Wholesale Customers is enabled
                // No General Discount
                // Has Category Level Wholesale Pricing
                // Disregard Category Level is enabled
                // Has variations with wholesale price set in product level.
                if (
                    $only_show_to_wholesale_users && ! array_key_exists( $user_wholesale_role, $wholesale_role_discount ) && 'yes' === $category_level_wholesale_price && 'yes' === $disregard_cat_level_discount && ! empty( $variations_with_wholesale_price )
                ) {
                    return $variations_with_wholesale_price;
                }

                // Only Show Wholesale Products To Wholesale Customers is enabled
                // Has General Discount
                // Has Category Level Wholesale Pricing
                // Disregard Category Level is enable
                // Disregard Role Level is enabled
                // Has variations with wholesale price set in product level.
                if (
                    $only_show_to_wholesale_users && array_key_exists( $user_wholesale_role, $wholesale_role_discount ) && 'yes' === $disregard_cat_level_discount && 'yes' === $disregard_role_level_discount && ! empty( $variations_with_wholesale_price )
                ) {
                    return $variations_with_wholesale_price;
                }

                // Only Show Wholesale Products To Wholesale Customers is enabled
                // Has variations with wholesale price set in product level
                // Has General Discount
                // No Category Discount
                // Disregard Role Level is enabled.
                if ( $only_show_to_wholesale_users && ! empty( $variations_with_wholesale_price ) && array_key_exists( $user_wholesale_role, $wholesale_role_discount ) && 'yes' !== $category_level_wholesale_price && 'yes' === $disregard_role_level_discount ) {
                    return $variations_with_wholesale_price;
                }

                // Only Show Wholesale Products To Wholesale Customers is enabled
                // Has variations with wholesale price set in product level
                // No General Discount
                // No Category Discount.
                if ( $only_show_to_wholesale_users && ! empty( $variations_with_wholesale_price ) && ! array_key_exists( $user_wholesale_role, $wholesale_role_discount ) && 'yes' !== $category_level_wholesale_price ) {
                    return $variations_with_wholesale_price;
                }

                return $filtered_children;

            }

            return $children;
        }

        /**
         * WWPP-574
         * This is related to the issue about WC caching the variable product price
         * So sometimes it uses the cached price on calculating the price range of a variable product
         * and since we have a feature that restricts variations to certain user role, this could result in the price range being miscalculated by WooCommerce.
         * Therefore the solution really is to update the cache hash, updating the cache hash invalidates the cache therefore forcing WooCommerce
         * to get the price data fresh from the db.
         *
         * Important Note: This could have introduced a potential speed penalty specially for variable products with many variations.
         *
         * @since 1.16.5
         * @access public
         *
         * @param array      $price_hash  Price hash.
         * @param WC_Product $product     WC_Product instance.
         * @param boolean    $for_display For display I guess?.
         * @return array Filtered price hash.
         */
        public function filter_variable_product_price_hash( $price_hash, $product, $for_display ) { // phpcs:ignore.

            if ( $this->is_not_admin() && 'variable' === WWP_Helper_Functions::wwp_get_product_type( $product ) ) {

                $user_wholesale_role = $this->_get_current_user_wholesale_role();

                // Make the variation-prices hash role-aware unconditionally so each
                // wholesale role (and retail) gets its own `wc_var_prices` transient.
                // Previously the role was only mixed in when a role-restricted
                // variation was found, so in the common case retail and wholesale
                // shared one transient and wholesale users never got role-aware cache
                // hits. Role-level only (never per-user) so the cache stays shared
                // across users of the same role. The settings hash is defensive: the
                // cached transient holds retail variation prices (the wholesale
                // adjustment is applied at render time, not stored in the array), so
                // rather than enumerate every global setting that could affect
                // downstream rendering we simply rotate the key whenever any wholesale
                // config changes.
                $price_hash[] = 'wwpp_role_' . ( empty( $user_wholesale_role ) ? 'retail' : $user_wholesale_role );
                $price_hash[] = 'wwpp_settings_' . (string) get_option( 'wwpp_settings_hash', '' );

                // Products with role-restricted variations expose a different visible
                // set per role. Detect that in O(1) via the precomputed parent flag
                // instead of the previous O( variations ) get_post_meta loop. Because
                // the role above already keys the cache per role, this flag is a cache
                // refinement only — a stale flag can never leak prices across roles.
                if ( $this->product_has_restricted_variations( $product ) ) {
                    $price_hash[] = 'wwpp_restricted';
                }
            }

            return $price_hash;
        }

        /**
         * Remove <wholesale_role>_wholesale_price if exist in variable product.
         *
         * @since 1.24.7
         * @access public
         *
         * @param object $post_id    WC Product ID.
         * @param object $post       WP_Post Object.
         * @param bool   $update     true if updating.
         */
        public function on_variable_product_save( $post_id, $post, $update ) { // phpcs:ignore.

            $product = wc_get_product( $post_id );

            if ( $product && 'variable' === $product->get_type() ) {

                $wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

                foreach ( $wholesale_roles as $role_key => $role ) {

                    $wholesale_price = $product->get_meta( $role_key . '_wholesale_price', true );

                    if ( $wholesale_price ) {
                        $product->delete_meta_data( $role_key . '_wholesale_price' );
                    }
                }
            }
        }

        /**
         * Whether a variable product has any role-restricted variations.
         *
         * Reads the precomputed `wwpp_has_restricted_variations` parent flag in
         * O(1). Legacy products have no flag yet, so the first read computes and
         * stores it once — an absent flag is NOT treated as "unrestricted", which
         * would otherwise let restricted variations leak into cached price ranges.
         *
         * The result is memoized per product for the duration of the request so
         * repeated hash computations for the same product within one page render
         * don't re-read the meta or repeat the first-read backfill write.
         *
         * @since 2.1.1
         * @access private
         *
         * @param WC_Product $product Variable product.
         * @return bool True if any variation is restricted to specific role(s).
         */
        private function product_has_restricted_variations( $product ) {

            $product_id = $product->get_id();

            if ( isset( $this->_has_restricted_variations_cache[ $product_id ] ) ) {
                return $this->_has_restricted_variations_cache[ $product_id ];
            }

            $flag = $product->get_meta( 'wwpp_has_restricted_variations', true );

            // Backfill unless the flag is one of the two known-valid states.
            // `WC_Data::get_meta()` returns '' for a legacy product with no flag
            // yet; whitelisting 'yes'/'no' also treats any unexpected value as
            // "needs recompute", so an absent or invalid flag is never assumed
            // "unrestricted".
            if ( ! in_array( $flag, array( 'yes', 'no' ), true ) ) {
                $flag = $this->compute_has_restricted_variations( $product ) ? 'yes' : 'no';
                $product->update_meta_data( 'wwpp_has_restricted_variations', $flag );
                $product->save_meta_data();
            }

            $has_restricted = 'yes' === $flag;

            $this->_has_restricted_variations_cache[ $product_id ] = $has_restricted;

            return $has_restricted;
        }

        /**
         * Compute whether any variation is restricted to specific wholesale role(s).
         *
         * A variation is "restricted" when it carries a visibility-filter meta row
         * pinned to a specific role — i.e. a value that is neither the empty string
         * nor the catch-all 'all'. The visibility meta is stored as one plain-string
         * row per selected role (or a single 'all' row when unrestricted), never a
         * serialized array (see
         * `WWPP_Admin_Custom_Fields_Variable_Product::save_variation_wholesale_role_visibility_filter_field`),
         * so a direct `meta_value NOT IN ( '', 'all' )` test is exact.
         *
         * Uses a single short-circuiting `LIMIT 1` query rather than reading and
         * meta-priming every variation, because this runs from the lazy backfill on
         * the front-end variation-prices hash path. Querying the DB directly (rather
         * than `$product->get_children()`) is also required for correctness: the
         * latter runs through WWPP's own `woocommerce_get_children` visibility filter,
         * which hides the very role-restricted variations we need to detect.
         *
         * @since 2.1.1
         * @access private
         *
         * @param WC_Product $product Variable product.
         * @return bool
         */
        private function compute_has_restricted_variations( $product ) {

            global $wpdb;

            $has_restricted = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded existence check; the result is cached via the parent flag meta, so no object-cache layer applies.
                $wpdb->prepare(
                    "SELECT 1
                     FROM {$wpdb->posts} AS p
                     INNER JOIN {$wpdb->postmeta} AS pm ON pm.post_id = p.ID
                     WHERE p.post_parent = %d
                       AND p.post_type = 'product_variation'
                       AND p.post_status IN ( 'publish', 'private' )
                       AND pm.meta_key = %s
                       AND pm.meta_value NOT IN ( '', 'all' )
                     LIMIT 1",
                    $product->get_id(),
                    WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER
                )
            );

            return ! empty( $has_restricted );
        }

        /**
         * Recompute the parent "has restricted variations" flag when variations sync.
         *
         * Hooked on `woocommerce_variable_product_sync_data`, which WooCommerce fires
         * whenever a variable product's variations change (admin save, import, or a
         * programmatic `WC_Product_Variable::sync()`) — the canonical point at which
         * variation visibility meta can change. The flag is persisted explicitly via
         * `save_meta_data()` so it does not depend on the caller saving the product
         * afterwards; when WC's own `sync()` also saves, the second write is a no-op.
         * Legacy products never re-synced are covered by the lazy backfill in
         * `product_has_restricted_variations()`.
         *
         * @since 2.1.1
         * @access public
         *
         * @param WC_Product_Variable $product Variable product being synced.
         */
        public function sync_restricted_variations_flag( $product ) {

            if ( $product instanceof WC_Product_Variable ) {
                $product->update_meta_data( 'wwpp_has_restricted_variations', $this->compute_has_restricted_variations( $product ) ? 'yes' : 'no' );
                $product->save_meta_data();
            }
        }

        /**
         * Idempotently schedule the one-shot restricted-variations flag backfill.
         *
         * The precomputed `wwpp_has_restricted_variations` parent flag introduced in
         * #1806 was lazily backfilled on the front-end variation-prices hash path,
         * which meant a `GET` could write product meta for legacy products. This
         * enqueues a batched Action Scheduler job that seeds the flag up front on
         * upgrade so the hot path is read-only for legacy catalogs too. The front-end
         * lazy backfill in `product_has_restricted_variations()` remains as a safety
         * net for any product the job has not reached yet.
         *
         * Hooked on `init` because Action Scheduler initializes its data store on
         * `init` priority 1 — scheduling earlier triggers `_doing_it_wrong` notices.
         * Cheap on the hot path: once the backfill completes, a single `get_option`
         * read short-circuits it. Guarded three ways against duplicate schedules: the
         * completion option (fast path), an `as_has_scheduled_action()` check that
         * short-circuits when a batch is already pending, and `$unique = true` on the
         * enqueue so Action Scheduler's uniqueness index rejects a duplicate at the DB
         * level even if two requests race past the check (the same two-layer guard as
         * `WWPP_Wholesale_Role_Automation_Bootstrap::maybe_schedule_sweep()`). A batch
         * that is already *in progress* but has not yet re-enqueued can still let a
         * concurrent request schedule one extra page-1 action, but the seeding is
         * idempotent — `seed_restricted_variations_batch()` only writes products still
         * lacking a valid flag — so a duplicate does no meaningful work.
         *
         * @since 2.1.1
         * @access public
         *
         * @return void
         */
        public function maybe_schedule_restricted_variations_seeding() {

            if ( ! function_exists( 'as_enqueue_async_action' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
                return;
            }

            if ( 'yes' === get_option( WWPP_OPTION_HAS_RESTRICTED_VARIATIONS_SEEDED, false ) ) {
                return;
            }

            if ( as_has_scheduled_action( WWPP_ACTION_SEED_HAS_RESTRICTED_VARIATIONS, null, self::SEED_AS_GROUP ) ) {
                return;
            }

            as_enqueue_async_action(
                WWPP_ACTION_SEED_HAS_RESTRICTED_VARIATIONS,
                array( 'page' => 1 ),
                self::SEED_AS_GROUP,
                true
            );
        }

        /**
         * Action Scheduler callback: seed the restricted-variations flag for one batch.
         *
         * Pages through existing variable products in `SEED_BATCH_SIZE` chunks and
         * computes `wwpp_has_restricted_variations` for any product that does not
         * already carry a valid flag, reusing the same
         * `compute_has_restricted_variations()` logic as the sync hook and the
         * front-end lazy backfill (single source of truth) so the seeded value never
         * diverges from what the lazy path would have written. Products already
         * flagged 'yes'/'no' are skipped, so the job is idempotent and safe to re-run
         * or overlap. Re-enqueues itself for the next page until a short page signals
         * the catalog is exhausted, then records completion.
         *
         * Scope is limited to `publish`/`private` products because those are the only
         * statuses that reach the front-end variation-prices hash path (admin/draft
         * contexts are guarded by `is_not_admin()` in
         * `filter_variable_product_price_hash()`); this also matches the variation
         * status filter in `compute_has_restricted_variations()`. Products in other
         * statuses still receive the flag on their next save via
         * `sync_restricted_variations_flag()`.
         *
         * Note on offset paging: the flag write is parent-product meta and does not
         * change the `type = variable` result set or its ID ordering, so the job's own
         * writes never make batches skip or repeat. It is not immune to *concurrent
         * deletions*, though — if already-processed variable products are deleted or
         * unpublished between batches, later rows shift into earlier offsets and the
         * next page can start past products that were never seeded. That is acceptable
         * here: the lazy backfill in `product_has_restricted_variations()` remains as
         * the safety net, so any product the job skips takes a single legacy-style flag
         * write on its first front-end render.
         *
         * @since 2.1.1
         * @access public
         *
         * @param int $page 1-based page of variable products to process.
         * @return void
         */
        public function seed_restricted_variations_batch( $page = 1 ) {

            $page = max( 1, (int) $page );

            $product_ids = wc_get_products(
                array(
                    'type'    => 'variable',
                    'status'  => array( 'publish', 'private' ),
                    'limit'   => self::SEED_BATCH_SIZE,
                    'page'    => $page,
                    'orderby' => 'ID',
                    'order'   => 'ASC',
                    'return'  => 'ids',
                )
            );

            foreach ( $product_ids as $product_id ) {

                $product = wc_get_product( $product_id );

                if ( ! $product instanceof WC_Product_Variable ) {
                    continue;
                }

                $flag = $product->get_meta( 'wwpp_has_restricted_variations', true );

                // Skip products already carrying a valid flag so the job is idempotent.
                if ( in_array( $flag, array( 'yes', 'no' ), true ) ) {
                    continue;
                }

                $product->update_meta_data( 'wwpp_has_restricted_variations', $this->compute_has_restricted_variations( $product ) ? 'yes' : 'no' );
                $product->save_meta_data();
            }

            // A full page implies more products may remain — process the next one.
            // No `function_exists( 'as_enqueue_async_action' )` guard here: this method
            // only ever runs as the Action Scheduler callback, so the API is guaranteed
            // present. Guarding it would conflate "cannot enqueue" with "catalog
            // exhausted" and let a full page fall through to the completion write below,
            // marking the backfill done with pages still remaining. A full page must
            // always re-enqueue and return.
            if ( count( $product_ids ) >= self::SEED_BATCH_SIZE ) {
                // NOT $unique: Action Scheduler marks this batch's own action
                // `in-progress` before invoking the callback, and its uniqueness check
                // matches any pending *or running* action with the same hook + group
                // (args are ignored — see ActionScheduler_DBStore::build_where_clause_for_insert()).
                // Passing $unique = true here would therefore be rejected by the
                // still-running current action, silently stalling the backfill after
                // page 1. The `init` gate's own enqueue keeps $unique = true to guard
                // the initial kickoff; the chain must not. Mirrors the role-automation
                // sweep, whose per-page chunk enqueue is likewise non-unique.
                as_enqueue_async_action(
                    WWPP_ACTION_SEED_HAS_RESTRICTED_VARIATIONS,
                    array( 'page' => $page + 1 ),
                    self::SEED_AS_GROUP
                );

                return;
            }

            // Catalog exhausted — mark the backfill complete so the `init` gate short-circuits.
            update_option( WWPP_OPTION_HAS_RESTRICTED_VARIATIONS_SEEDED, 'yes', false );
        }

        /**
         * Filter variable variation prices when "Only Show Wholesale Products To Wholesale Customers" is enabled.
         * Only list variation price if the variation has wholesale price.
         *
         * @since 1.25.1
         * @since 2.1.1 Prime the variation meta cache once before the per-variation get_post_meta() loop to collapse N single-row queries into one.
         * @access public
         *
         * @param array  $transient_cached_prices_array      Variation prices array.
         * @param object $product                            WP_Post Object.
         * @param bool   $for_display                        If true, prices will be adapted for display based on the `woocommerce_tax_display_shop` setting (including or excluding taxes).
         */
        public function filter_wholesale_variation_prices( $transient_cached_prices_array, $product, $for_display ) { // phpcs:ignore.

            $only_show_to_wholesale_users = get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false ) === 'yes' ? true : false;
            $user_wholesale_role          = $this->_get_current_user_wholesale_role();
            $wholesale_role_discount      = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, array() );

            // General Discount. No restriction for general discount.
            if ( array_key_exists( $user_wholesale_role, $wholesale_role_discount ) ) {
                return $transient_cached_prices_array;
            }

            $variations_with_wholesale_price    = WWP_Helper_Functions::get_formatted_meta_data( $product, $user_wholesale_role . '_variations_with_wholesale_price' ); // Per product/variation wholesale price.
            $variation_ids_with_wholesale_price = array_map( 'intval', $variations_with_wholesale_price );
            $category_level_wholesale_price     = $product->get_meta( $user_wholesale_role . '_have_wholesale_price_set_by_product_cat', true ); // Category wholesale price.

            // If Wholesale Discount is set in Category then don't filter variations.
            if ( 'yes' === $category_level_wholesale_price ) {
                return $transient_cached_prices_array;
            }

            // Prime the meta cache for every variation in a single query so the
            // per-variation get_post_meta() reads in the loop below hit the object
            // cache instead of issuing one SELECT against wp_postmeta per variation.
            // Collapse N single-row meta queries into one.
            $variation_ids_to_prime = array();
            foreach ( $transient_cached_prices_array as $values ) {
                $variation_ids_to_prime = array_merge( $variation_ids_to_prime, array_keys( $values ) );
            }
            if ( ! empty( $variation_ids_to_prime ) ) {
                update_meta_cache( 'post', array_unique( $variation_ids_to_prime ) );
            }

            foreach ( $transient_cached_prices_array as $key => $values ) {

                foreach ( $values as $variation_id => $price ) {

                    // Wholesale Exclusive Variation
                    // Array of wholesale role(s) in which this variation is exclusive to show.
                    $roles_variation_is_visible = get_post_meta( $variation_id, WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER, false );

                    if ( empty( $roles_variation_is_visible ) || in_array( 'all', $roles_variation_is_visible, true ) || in_array( $user_wholesale_role, $roles_variation_is_visible, true ) ) {

                        if ( $only_show_to_wholesale_users && ! empty( $user_wholesale_role ) ) {

                            if ( ! in_array( $variation_id, $variation_ids_with_wholesale_price, true ) ) {

                                // If Wholesale Price is not set in the product level then hide non wholesale variation product.
                                unset( $transient_cached_prices_array[ $key ][ $variation_id ] );

                            }
                        }
                    } elseif ( ! in_array( $user_wholesale_role, $roles_variation_is_visible, true ) ) {
                        // Variation is exclusive to another wholesale role. We need to hide for this variation.
                        unset( $transient_cached_prices_array[ $key ][ $variation_id ] );
                    }
                }
            }

            return $transient_cached_prices_array;
        }

        /**
         * Check if user is not admin
         *
         * @since 1.25.2
         * @access public
         */
        public function is_not_admin() {

            $is_not_admin = ! current_user_can( 'manage_options' );

            return apply_filters( 'user_is_not_admin_check', ( $is_not_admin || defined( 'REST_REQUEST' ) ) ? true : false );
        }

        /**
         * Filter products pre_get_post__in if General discount is empty and "Only Show Wholesale Products To Wholesale Customers" is enabled.
         * Remove variable products that has no wholesale price set.
         * Remove simple products that has no wholesale price set.
         *
         * @since 2.0.1
         * @since 2.1.1 Memoize the computed result per ( id-list, role ) for the duration of the request to avoid re-running the catalog-wide queries on every invocation.
         * @since 2.1.1 Spare simple and variable products whose wholesale price comes from a category-level wholesale discount (have wholesale price flag set to yes) instead of pruning them for lacking a per-product/per-variation wholesale price.
         * @access public
         *
         * @param array $post__in Array of post ids.
         * @param array $args     Array of arguments.
         * @return array Filtered array of post ids.
         */
        public function filter_variable_simple_pre_get_post__in( $post__in, $args ) { // phpcs:ignore.

            $user_wholesale_role     = $this->_get_current_user_wholesale_role();
            $user_wholesale_discount = $this->_wwpp_wholesale_price_wholesale_role->get_user_wholesale_role_level_discount( get_current_user_id(), $user_wholesale_role );

            $restricted_products = $post__in;
            if ( empty( $user_wholesale_discount['discount'] ) && 'yes' === get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false ) ) {
                global $wpdb;

                // Check if $post__in is empty.
                if ( empty( $post__in ) ) {
                    return $post__in;
                }

                // Serve an identical ( id-list, role ) computation once per request.
                // Sort a copy of the id list before hashing so the same set in a
                // different order resolves to the same key (a cache hit, not a miss).
                $cache_key_ids = $post__in;
                sort( $cache_key_ids );
                $cache_key = md5( implode( ',', $cache_key_ids ) ) . '|' . $user_wholesale_role;
                if ( isset( $this->_pre_get_post__in_cache[ $cache_key ] ) ) {
                    return $this->_pre_get_post__in_cache[ $cache_key ];
                }

                // phpcs:disable
                // Products flagged as having a wholesale price (the flag is set to 'yes' when a
                // per-product/per-variation wholesale price OR a category-level discount applies).
                // These must never be pruned even when no per-product/per-variation wholesale
                // price meta is set. Fetched once and reused by both prune branches below.
                $flagged_query = "
                    SELECT pm.post_id
                    FROM {$wpdb->postmeta} pm
                    WHERE pm.meta_key = %s
                    AND pm.meta_value = 'yes'
                    AND pm.post_id IN (" . implode(',', array_map('intval', $post__in)) . ")
                ";
                $products_with_wholesale_price_flag = $wpdb->get_col(
                    $wpdb->prepare(
                        $flagged_query,
                        $user_wholesale_role . '_have_wholesale_price',
                    )
                );

                // Variable Products.
                // Get variables that has no wholesale price set.
                $variable_query = "
                    SELECT p.post_parent
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm 
                    ON p.ID = pm.post_id
                    WHERE pm.meta_key = %s
                    AND pm.meta_value = ''
                    AND p.post_parent IN (" . implode(',', array_map('intval', $post__in)) . ")
                    AND p.post_type = 'product_variation'
                    GROUP BY p.post_parent
                ";
                $variables = $wpdb->get_col(
                    $wpdb->prepare(
                        $variable_query,
                        $user_wholesale_role . '_wholesale_price',
                    )
                );

                $remaining_products = $post__in;
                if ( ! empty( $variables ) ) {
                    $variables_without_wholesale_price = $variables;

                    // Get vrariations that has wholesale price set.
                    $variable_with_wholesale_query = "
                        SELECT p.post_parent
                        FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->postmeta} pm 
                        ON p.ID = pm.post_id
                        WHERE pm.meta_key = %s
                        AND pm.meta_value != ''
                        AND p.post_parent IN (" . implode(',', array_map('intval', $post__in)) . ")
                        AND p.post_type = 'product_variation'
                        GROUP BY p.post_parent
                    ";
                    $variables_with_wholesale_price = $wpdb->get_col(
                        $wpdb->prepare(
                            $variable_with_wholesale_query,
                            $user_wholesale_role . '_wholesale_price',
                        )
                    );

                    // Check if variables with wholesale price is not empty.
                    if ( ! empty( $variables_with_wholesale_price ) ) {
                        $variables_without_wholesale_price = array_diff( $variables, $variables_with_wholesale_price );
                    }

                    // Spare variable parents flagged as having a wholesale price (e.g. category-level discount).
                    $variables_without_wholesale_price = array_diff( $variables_without_wholesale_price, $products_with_wholesale_price_flag );

                    $remaining_products = array_diff( $post__in, $variables_without_wholesale_price );
                }

                // Simple Products.
                $simple_query = "
                    SELECT p.ID
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm 
                    ON p.ID = pm.post_id
                    WHERE pm.meta_key = %s
                    AND pm.meta_value = ''
                    AND p.ID IN (" . implode(',', array_map('intval', $remaining_products)) . ")
                    AND p.post_type = 'product'
                    GROUP BY p.ID
                ";
                $simple = $wpdb->get_col(
                    $wpdb->prepare(
                        $simple_query,
                        $user_wholesale_role . '_wholesale_price',
                    )
                );

                if ( ! empty( $simple ) ) {
                    // Spare simple products flagged as having a wholesale price (e.g. category-level discount).
                    $simple             = array_diff( $simple, $products_with_wholesale_price_flag );
                    $remaining_products = array_diff( $remaining_products, $simple );
                }
                // phpcs:enable

                // Filter bundle products.
                $bundle_products = wc_get_products(
                    array(
                        'type'    => 'bundle',
                        'status'  => 'publish',
                        'limit'   => -1,
                        'include' => $post__in,
                    )
                );

                $bundle_restricted_products = array();
                if ( ! empty( $bundle_products ) ) {
                    foreach ( $bundle_products as $bundle_product ) {
                        $have_wholesale_price = $bundle_product->get_meta( $user_wholesale_role . '_have_wholesale_price', true );
                        if ( ! empty( $have_wholesale_price ) && 'yes' === $have_wholesale_price ) {
                            $bundle_restricted_products[] = $bundle_product->get_id();
                        }
                    }
                }

                $restricted_products = array_merge( $remaining_products, $bundle_restricted_products );

                $this->_pre_get_post__in_cache[ $cache_key ] = $restricted_products;
            }

            return $restricted_products;
        }

        /*
        |--------------------------------------------------------------------------
        | Execute Model
        |--------------------------------------------------------------------------
         */

        /**
         * Execute model.
         *
         * @since 1.13.4
         * @access public
         */
        public function run() {

            add_filter( 'wwp_filter_variable_product_wholesale_price_range', array( $this, 'filter_wholesale_customer_variable_product_price_range' ), 10, 1 );
            add_filter( 'woocommerce_get_children', array( $this, 'filter_available_variable_product_variations' ), 1, 2 );
            add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'filter_variable_product_price_hash' ), 10, 3 );

            // Remove meta data that is needed for variable product.
            add_action( 'save_post_product', array( $this, 'on_variable_product_save' ), 10, 3 );
            add_action( 'woocommerce_variable_product_sync_data', array( $this, 'sync_restricted_variations_flag' ), 10, 1 );

            // One-shot upgrade backfill of the restricted-variations parent flag (#1817).
            add_action( 'init', array( $this, 'maybe_schedule_restricted_variations_seeding' ) );
            add_action( WWPP_ACTION_SEED_HAS_RESTRICTED_VARIATIONS, array( $this, 'seed_restricted_variations_batch' ), 10, 1 );

            // Filter variable variation price ranges when "Only Show Wholesale Products To Wholesale Customers" is enabled.
            add_filter( 'woocommerce_variation_prices', array( $this, 'filter_wholesale_variation_prices' ), 10, 3 );
            add_filter( 'wwpp_pre_get_post__in', array( $this, 'filter_variable_simple_pre_get_post__in' ), 10, 2 );
        }
    }
}
