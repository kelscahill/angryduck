<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that houses the logic relating caching.
 *
 * @since 1.16.0
 * @since 2.1.0 Fix wholesale price cache invalidation when Aelia Currency Switcher is active (issue #1590).
 * @since 2.1.1 Store wholesale price caches per wholesale role (and currency) in transients instead of per user in usermeta (issue #1783).
 */
class WWPP_Cache {


    /**
     * Class Properties
     */

    /**
     * Property that holds the single main instance of WWPP_Cache.
     *
     * @since 1.16.0
     * @access private
     * @var WWPP_Cache
     */
    private static $_instance;

    /**
     * In-memory product category count buckets for the current request, keyed by bucket transient key.
     *
     * Loaded lazily from a transient on first access and written back once on shutdown so a shop page
     * of N category tiles performs at most one transient read + one transient write per role bucket
     * (issue #1792).
     *
     * @since 2.1.1
     * @access private
     * @var array
     */
    private $product_cat_count_buckets = array();

    /**
     * Bucket keys whose in-memory contents changed this request and need persisting on shutdown.
     *
     * @since 2.1.1
     * @access private
     * @var array
     */
    private $dirty_product_cat_count_buckets = array();

    /**
     * Whether the shutdown flush for count buckets has been registered this request.
     *
     * @since 2.1.1
     * @access private
     * @var bool
     */
    private $product_cat_count_flush_registered = false;

    /**
     * Wholesale price cache key.
     *
     * @since 1.27.8
     * @since 2.1.1 Cache moved from per-user usermeta to per-role transients (options table);
     *              see delete_wholesale_price_on_shop_v3_cache().
     *
     * SECURITY: Do NOT add another option or transient whose name starts with this exact string.
     * `delete_wholesale_price_on_shop_v3_cache()` purges the cache with
     * `DELETE FROM {options} WHERE option_name LIKE '_transient_wwpp_product_wholesale_price_on_shop_v3_cache%'`
     * (and the matching `_transient_timeout_` prefix) to catch every per-role/per-currency transient
     * variant — any sibling key sharing this prefix will be silently destroyed on every cache purge.
     */
    const WHOLESALE_PRICE_CACHE_KEY = 'wwpp_product_wholesale_price_on_shop_v3_cache';

    /**
     * Variable product price range cache key.
     *
     * @since 2.1.1
     */
    const VARIABLE_PRICE_RANGE_CACHE_KEY = 'wwpp_variable_product_price_range_cache';

    /**
     * Product category count badge cache key prefix.
     *
     * Used to cache the wholesale-filtered product count rendered next to each category tile on the
     * shop/category pages (issue #1792). The current epoch is embedded in the transient name so a
     * bump orphans stale entries, which are then reclaimed on their WEEK_IN_SECONDS expiry.
     *
     * @since 2.1.1
     */
    const PRODUCT_CAT_COUNT_CACHE_KEY = 'wwpp_product_cat_count_cache';

    /**
     * Option name holding the product category count cache epoch.
     *
     * @since 2.1.1
     */
    const PRODUCT_CAT_COUNT_EPOCH_OPTION = 'wwpp_product_cat_count_epoch';

    /**
     * Class Methods
     */

    /**
     * WWPP_Cache constructor.
     *
     * @since 1.16.0
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Cache model.
     */
    public function __construct( $dependencies ) {}

    /**
     * Ensure that only one instance of WWPP_Cache is loaded or can be loaded (Singleton Pattern).
     *
     * @since 1.16.0
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Cache model.
     * @return WWPP_Cache
     */
    public static function instance( $dependencies ) {
        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( $dependencies );
        }

        return self::$_instance;
    }

    /**
     * Hashing
     */

    /**
     * Set settings meta hash.
     *
     * @since 1.16.0
     * @since 1.27.8 Delete wholesale price cache.
     * @access public
     * @return string Generated hash.
     */
    public function set_settings_meta_hash() {
        $hash = uniqid( '', true );

        update_option( 'wwpp_settings_hash', $hash, 'no' );

        $this->delete_wholesale_price_on_shop_v3_cache();

        return $hash;
    }

    /**
     * Set product category meta hash.
     *
     * @since 1.16.0
     * @since 1.27.8 Delete wholesale price cache.
     * @access public
     *
     * @param int    $term_id Term Id.
     * @param int    $taxonomy_term_id Taxonomy term id.
     * @param string $taxonomy Taxonomy.
     * @return string|boolean Generated hash or false when operation fails
     */
    public function set_product_category_meta_hash( $term_id, $taxonomy_term_id, $taxonomy = 'product_cat' ) {

        $this->delete_wholesale_price_on_shop_v3_cache();

        if ( 'product_cat' === $taxonomy ) {
            $hash = uniqid( '', true );
            update_option( 'wwpp_product_cat_hash', $hash, 'no' );
            return $hash;
        }

        return false;
    }

    /**
     * Set product category meta hash.
     *
     * @since 1.16.0
     * @since 1.27.8 Delete wholesale price cache.
     * @access public
     *
     * @param int    $term_id          Term Id.
     * @param int    $taxonomy_term_id Taxonomy term id.
     * @param object $deleted_term     Deleted term object.
     * @param array  $object_ids       List of term object ids.
     */
    public function set_product_category_meta_hash_delete_term( $term_id, $taxonomy_term_id, $deleted_term, $object_ids ) { // phpcs:ignore.
        $this->delete_wholesale_price_on_shop_v3_cache();
        $this->set_product_category_meta_hash( $term_id, $taxonomy_term_id, 'product_cat' );
    }

    /**
     * Set product meta hash.
     *
     * @since 1.16.0
     * @since 1.27.8 Delete wholesale price cache. Updated hook from save_post to save_post_product.
     * @since 2.1.1 Rely on per-product hash bumps instead of a blanket cache purge; bump the parent
     *              product's hash on variation save so its price-range cache invalidates (issue #1783).
     * @access public
     *
     * @param int     $post_id      Post id.
     * @param boolean $bypass_check Flag to whether bypass the action validity check.
     * @return string|boolean Generated hash or false when operation fails
     */
    public function set_product_meta_hash( $post_id, $bypass_check = false ) {

        if ( true === $bypass_check ||
            WWP_Helper_Functions::check_if_valid_save_post_action( $post_id, 'product' ) ||
            WWP_Helper_Functions::check_if_valid_save_post_action( $post_id, 'product_variation' )
        ) {
            // Get product object.
            $product = wc_get_product( $post_id );

            if ( ! $product ) {
                return false;
            }

            $hash = uniqid( '', true );
            $product->update_meta_data( 'wwpp_product_hash', $hash );

            // Save product meta.
            $product->save();

            /*
             * A variation's price feeds its parent variable product's cached price range, and that
             * range entry is stamped with the PARENT's wwpp_product_hash. Saving a variation only
             * bumps the variation's own hash, so bump the parent's too (meta-only, to avoid a
             * recursive product save) — otherwise the parent's range cache would serve a stale range
             * after a variation edit. This replaces the previous blanket purge on every save, which
             * destroyed every role's cache for unrelated products on each edit; per-product hash
             * bumps already invalidate the changed product on read (issue #1783).
             */
            $parent_id = $product->get_parent_id();
            if ( $parent_id ) {
                $parent = wc_get_product( $parent_id );
                if ( $parent ) {
                    $parent->update_meta_data( 'wwpp_product_hash', uniqid( '', true ) );
                    $parent->save_meta_data();
                }
            }

            return $hash;
        }

        return false;
    }

    /**
     * Rotate a product's validity hash on non-admin (REST / import / programmatic) saves.
     *
     * The admin save paths rotate the hash via set_product_meta_hash() (hooked on
     * save_post_product / woocommerce_save_product_variation). Those hooks either do not fire
     * on a REST API, CSV import, or programmatic save (variations fire
     * woocommerce_update_product_variation, not the admin-only woocommerce_save_product_variation),
     * or are gated behind WWP_Helper_Functions::check_if_valid_save_post_action(), which bails when
     * $_POST is empty — as it is on those paths. Without a fresh hash the per-role v3 wholesale price
     * cache (and the variable price-range cache) keep serving the price stamped before the change.
     *
     * This central handler covers the non-admin paths for both simple/parent products
     * (woocommerce_new_product / woocommerce_update_product) and variations
     * (woocommerce_new_product_variation / woocommerce_update_product_variation). It writes the hash
     * with a meta-only save (save_meta_data, never a full save()) so it cannot re-fire these same
     * hooks and recurse. For a variation it also bumps the parent variable product's hash so its
     * cached price range invalidates, mirroring set_product_meta_hash().
     *
     * @since 2.1.1
     * @access public
     *
     * @param int $product_id Product or variation ID.
     * @return void
     */
    public function rotate_product_hash_on_save( $product_id ) {

        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return;
        }

        $product->update_meta_data( 'wwpp_product_hash', uniqid( '', true ) );
        $product->save_meta_data();

        // A variation feeds its parent's cached price range (stamped with the PARENT's hash), so bump
        // the parent too — meta-only, to avoid a recursive product save.
        $parent_id = $product->get_parent_id();
        if ( $parent_id ) {
            $parent = wc_get_product( $parent_id );
            if ( $parent ) {
                $parent->update_meta_data( 'wwpp_product_hash', uniqid( '', true ) );
                $parent->save_meta_data();
            }
        }
    }

    /**
     * Public Functions
     */

    /**
     * Check variable product price range cache if valid.
     *
     * @since 1.16.0
     * @access public
     *
     * @param int        $user_id    User Id.
     * @param WC_Product $product    WC_Product object.
     * @param array      $cache_data Cache data.
     * @return boolean True if cache is valid, false otherwise.
     */
    public function check_variable_product_price_range_cache_if_valid( $user_id, $product, $cache_data ) {

        return 'variable' === $product->get_type() && $this->price_cache_entry_hashes_match( $product, $cache_data );
    }

    /**
     * Set variable product price range cache.
     *
     * @since 1.16.0
     * @since 2.1.1 Store per wholesale role (and currency) in a transient bucket instead of per user in usermeta.
     * @access public
     *
     * @param int        $user_id             User Id.
     * @param WC_Product $product             WC_Product object.
     * @param array      $user_wholesale_role Array of wholesale roles for the current user.
     * @param array      $args                Data to cache.
     */
    public function set_variable_product_price_range_cache( $user_id, $product, $user_wholesale_role, $args ) {

        if ( $product->get_type() === 'variable' ) {

            $product_id = $product->get_id();

            // Ensure the validity hashes exist without purging (a missing hash means the cache is empty).
            $this->ensure_validity_hashes_exist();

            $settings_hash    = get_option( 'wwpp_settings_hash' );
            $product_cat_hash = get_option( 'wwpp_product_cat_hash' );
            $product_hash     = $this->ensure_product_hash( $product );

            $role_cached_data = $this->get_role_cache_bucket( self::VARIABLE_PRICE_RANGE_CACHE_KEY, $user_wholesale_role );

            $hashes_arr = array(
                'wwpp_settings_hash'    => $settings_hash,
                'wwpp_product_cat_hash' => $product_cat_hash,
                'wwpp_product_hash'     => $product_hash,
            );

            $cache_data                      = wp_parse_args( $args, $hashes_arr );
            $role_cached_data[ $product_id ] = $cache_data;

            $this->set_role_cache_bucket( self::VARIABLE_PRICE_RANGE_CACHE_KEY, $user_wholesale_role, $role_cached_data );
        }
    }

    /**
     * Get cache variable product price range cache.
     *
     * @since 1.16.0
     * @since 2.1.1 Read from the per wholesale role (and currency) transient bucket instead of per-user usermeta.
     * @access public
     *
     * @param int        $user_id             User Id.
     * @param WC_Product $product             WC_Product object.
     * @param array      $user_wholesale_role Array of wholesale roles for the current user.
     * @return array|boolean Array of cached data if successful, boolean false otherwise.
     */
    public function get_cache_variable_product_price_range_cache( $user_id, $product, $user_wholesale_role ) {

        if ( 'variable' === $product->get_type() ) {
            $product_id = $product->get_id();

            $role_cached_data = $this->get_role_cache_bucket( self::VARIABLE_PRICE_RANGE_CACHE_KEY, $user_wholesale_role );

            return array_key_exists( $product_id, $role_cached_data ) ? $role_cached_data[ $product_id ] : false;
        }

        return false;
    }

    /**
     * Get variable product price range cache.
     *
     * @since 1.16.0
     * @access public
     *
     * @param boolean|array $cache_data Cached data, false by default.
     * @param int           $user_id User Id.
     * @param WC_Product    $product WC_Product object.
     * @param array         $user_wholesale_role Array of wholesale roles for the current user.
     * @return array Cached data.
     */
    public function get_variable_product_price_range_cache( $cache_data, $user_id, $product, $user_wholesale_role ) { // phpcs:ignore.
        if ( 'yes' !== get_option( 'wwpp_enable_var_prod_price_range_caching', 'yes' ) ) {
            return false;
        }

        // No wholesale role → no per-role bucket to read (mirror the wholesale-price entry points).
        if ( empty( $user_wholesale_role ) ) {
            return $cache_data;
        }

        // Bypass the shared per-role cache for per-user overrides or location-dependent tax display.
        if ( $this->should_bypass_role_price_cache( $user_id ) ) {
            return $cache_data;
        }

        $variable_product_price_range_cache = $this->get_cache_variable_product_price_range_cache( $user_id, $product, $user_wholesale_role );
        if ( false !== $variable_product_price_range_cache && $this->check_variable_product_price_range_cache_if_valid( $user_id, $product, $variable_product_price_range_cache ) ) {
            $cache_data = $variable_product_price_range_cache;
        }

        return $cache_data;
    }

    /**
     * Maybe set variable price range cache.
     *
     * @since 1.16.0
     * @access public
     *
     * @param int        $user_id             User Id.
     * @param WC_Product $product             WC_Product object.
     * @param array      $user_wholesale_role Array of wholesale roles for the current user.
     * @param array      $args                Array of data to cache.
     */
    public function maybe_set_variable_price_range_cache( $user_id, $product, $user_wholesale_role, $args ) {
        if ( 'yes' !== get_option( 'wwpp_enable_var_prod_price_range_caching', 'yes' ) ) {
            return false;
        }

        // No wholesale role → nothing to cache under (mirror the wholesale-price entry points).
        if ( empty( $user_wholesale_role ) ) {
            return false;
        }

        // Bypass the shared per-role cache for per-user overrides or location-dependent tax display.
        if ( $this->should_bypass_role_price_cache( $user_id ) ) {
            return false;
        }

        $variable_product_price_range_cache = $this->get_cache_variable_product_price_range_cache( $user_id, $product, $user_wholesale_role );

        if ( false === $variable_product_price_range_cache || ! $this->check_variable_product_price_range_cache_if_valid( $user_id, $product, $variable_product_price_range_cache ) ) {
            if ( is_array( $args ) && isset( $args['min_price'] ) && isset( $args['max_price'] ) && isset( $args['some_variations_have_wholesale_price'] ) ) {
                $this->set_variable_product_price_range_cache( $user_id, $product, $user_wholesale_role, $args );
            }
        }
    }

    /**
     * Hook on WC product create or update.
     *
     * @since 1.23.2
     * @access public
     *
     * @param int $product_id Product ID.
     */
    public function product_update( $product_id ) { // phpcs:ignore.

        $wwpp_product_cache_option = get_option( 'wwpp_enable_product_cache' );

        if ( 'yes' === $wwpp_product_cache_option ) {
            $this->clear_product_transients_cache();
        }
    }

    /**
     * Hook on Product Category Add, Update, Delete.
     *
     * @since 1.23.2
     * @access public
     *
     * @param int $term_id The term ID.
     * @param int $taxonomy_term_id The taxonomy term ID.
     */
    public function product_cat_update( $term_id, $taxonomy_term_id ) { // phpcs:ignore.

        $wwpp_product_cache_option = get_option( 'wwpp_enable_product_cache' );

        if ( 'yes' === $wwpp_product_cache_option ) {
            $this->clear_product_transients_cache();
        }
    }

    /**
     * Hook on User update.
     *
     * @since 1.23.2
     * @access public
     *
     * @param int    $user_id        User ID.
     * @param object $old_user_data  WP_User Object.
     */
    public function profile_update( $user_id, $old_user_data ) { // phpcs:ignore.

        $wwpp_product_cache_option = get_option( 'wwpp_enable_product_cache' );

        if ( 'yes' === $wwpp_product_cache_option ) {
            $this->clear_product_transients_cache( 'wwpp_cached_products_ids_' . $user_id );
        }
    }

    /**
     * Delete product listing cached transients.
     *
     * @since 1.23.2
     *
     * @param string $transient_name The name of the transient.
     * @access public
     */
    public function clear_product_transients_cache( $transient_name = null ) {

        global $wpdb;

        if ( null !== $transient_name ) {
            delete_transient( $transient_name );
        } else {
            $results = $wpdb->get_results(
                "SELECT option_name FROM $wpdb->options WHERE option_name LIKE '_transient_wwpp_cached_products_ids%'",
                ARRAY_A
            );

            // Delete visitor product transients cache.
            delete_transient( 'wwpp_cached_products_ids' );

            // Delete transients.
            if ( ! empty( $results ) ) {
                foreach ( $results as $key => $name ) {
                    $transient_name = str_replace( '_transient_', '', $name['option_name'] );
                    delete_transient( $transient_name );
                }
            }
        }
    }

    /**
     * AJAX
     */

    /**
     * Regenerate new hash for caching feature. This will in turn invalidate all existing cache.
     *
     * @since 1.16.0
     * @since 1.27.8 Delete wholesale price cache.
     * @since 2.1.1 Require the manage_woocommerce capability.
     * @access public
     */
    public function ajax_regenerate_new_cache_hash() {
        if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX || ! current_user_can( 'manage_woocommerce' ) ) {
            $response = array(
                'status'    => 'fail',
                'error_msg' => __( 'Invalid AJAX Operation', 'woocommerce-wholesale-prices-premium' ),
            );
        } elseif ( ! check_ajax_referer( 'wwpp_regenerate_new_cache_hash', 'ajax-nonce', false ) ) {
            $response = array(
                'status'    => 'fail',
                'error_msg' => __( 'Security check failed', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            $this->set_settings_meta_hash();
            $this->set_product_category_meta_hash( null, null, 'product_cat' );
            $this->delete_wholesale_price_on_shop_v3_cache();

            $response = array(
                'status'      => 'success',
                'success_msg' => __( 'Successfully cleared all variable product price range and wholesale price cache.', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * This will purge all product transients cache for wholesale customers including regular/visitors cache.
     *
     * @since 1.23.2
     * @since 2.1.1 Require the manage_woocommerce capability.
     * @access public
     */
    public function ajax_clear_product_transients_cache() {
        if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX || ! current_user_can( 'manage_woocommerce' ) ) {
            $response = array(
                'status'    => 'fail',
                'error_msg' => __( 'Invalid AJAX Operation', 'woocommerce-wholesale-prices-premium' ),
            );
        } elseif ( ! check_ajax_referer( 'wwpp_clear_product_transients_cache', 'ajax-nonce', false ) ) {
            $response = array(
                'status'    => 'fail',
                'error_msg' => __( 'Security check failed', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            $this->clear_product_transients_cache();

            $response = array(
                'status'      => 'success',
                'success_msg' => __( 'Successfully cleared all products transients cache', 'woocommerce-wholesale-prices-premium' ),
            );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Register ajax handlers.
     *
     * @since 1.16.0
     * @access public
     */
    public function register_ajax_handlers() {
        add_action( 'wp_ajax_wwpp_regenerate_new_cache', array( $this, 'ajax_regenerate_new_cache_hash' ) );
        add_action( 'wp_ajax_wwpp_clear_product_transients_cache', array( $this, 'ajax_clear_product_transients_cache' ) );
    }

    /**
     * Build the per wholesale role (and per currency) cache key for a price cache bucket.
     *
     * The active currency is appended when a currency switcher (Aelia or WOOCS) is active so
     * wholesale prices stay isolated per currency, preserving the previous per-user behaviour.
     * Without this, moving the cache from per-user to per-role (issue #1783) would let one
     * customer's currency price be served to another customer of the same role.
     *
     * @since 2.1.1
     * @since 2.1.1 Also isolate per currency when the WOOCS (WooCommerce Currency Switcher) plugin
     *              is active, not just Aelia — the free plugin converts the wholesale price for WOOCS too.
     * @access private
     *
     * @param string       $base_key            Base cache key.
     * @param array|string $user_wholesale_role Wholesale role(s); the slug is taken from index 0 of the array.
     * @return string Transient key.
     */
    private function build_role_cache_key( $base_key, $user_wholesale_role ) {
        $role = is_array( $user_wholesale_role ) ? ( isset( $user_wholesale_role[0] ) ? $user_wholesale_role[0] : '' ) : (string) $user_wholesale_role;
        $key  = $base_key . '_' . $role;

        // Isolate the cache per active currency for both supported switchers. The free plugin converts the
        // wholesale price to the active currency for Aelia and WOOCS, so the cached value is currency-specific.
        // WCML is intentionally not included here: its compat path feeds the general-discount HTML table, not the
        // get_product_wholesale_price_on_shop_v3() value this transient caches, so it needs no per-currency key.
        if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active()
            || WWP_Helper_Functions::is_plugin_active( 'woocommerce-currency-switcher/index.php' ) ) {
            $key .= '_' . get_woocommerce_currency();
        }

        return $key;
    }

    /**
     * Get the per wholesale role (and currency) cache bucket.
     *
     * @since 2.1.1
     * @access private
     *
     * @param string       $base_key            Base cache key.
     * @param array|string $user_wholesale_role Wholesale role(s).
     * @return array Cache bucket keyed by product id.
     */
    private function get_role_cache_bucket( $base_key, $user_wholesale_role ) {
        $bucket = get_transient( $this->build_role_cache_key( $base_key, $user_wholesale_role ) );

        return is_array( $bucket ) ? $bucket : array();
    }

    /**
     * Store the per wholesale role (and currency) cache bucket.
     *
     * @since 2.1.1
     * @access private
     *
     * @param string       $base_key            Base cache key.
     * @param array|string $user_wholesale_role Wholesale role(s).
     * @param array        $bucket              Cache bucket keyed by product id.
     */
    private function set_role_cache_bucket( $base_key, $user_wholesale_role, $bucket ) {
        set_transient( $this->build_role_cache_key( $base_key, $user_wholesale_role ), $bucket, WEEK_IN_SECONDS );
    }

    /**
     * Whether a user has per-user pricing overrides that make their wholesale price differ from
     * other customers sharing the same wholesale role. Such users must bypass the per-role cache,
     * otherwise they could be served (or could pollute) another customer's prices.
     *
     * @since 2.1.1
     * @access private
     *
     * @param int $user_id User ID.
     * @return boolean True when the user has a per-user pricing override.
     */
    private function user_has_per_user_pricing_override( $user_id ) {
        if ( $user_id <= 0 ) {
            return false;
        }

        /*
         * The "override per-user discount" gate enables BOTH the per-user flat discount
         * (wwpp_wholesale_discount) and the per-user quantity discount mapping
         * (wwpp_wholesale_discount_qty_discount_mapping). Either can make this user's price differ
         * from role peers, so treat the gate alone as a bypass signal rather than requiring a
         * numeric flat value (a qty-mapping-only override has an empty flat discount).
         */
        if ( 'yes' === get_user_meta( $user_id, 'wwpp_override_wholesale_discount', true ) ) {
            return true;
        }

        // Per-user tax exemption override (anything other than the role-level 'global').
        if ( in_array( get_user_meta( $user_id, 'wwpp_tax_exemption', true ), array( 'yes', 'no' ), true ) ) {
            return true;
        }

        return false;
    }

    /**
     * Whether the displayed wholesale price varies by the customer's tax location.
     *
     * The cached price fields (e.g. `wholesale_price`, and the variable-product `min_price`/`max_price`
     * derived from `wc_get_price_to_display()`) are tax-display values. When the store displays prices
     * INCLUDING tax AND calculates tax from the customer's billing/shipping address, two customers
     * sharing a wholesale role but located in different tax zones compute different displayed prices.
     * A single per-role bucket cannot represent that, so such stores must bypass the cache (read and
     * write) to avoid serving one zone's price to a customer in another. Location-independent stores
     * (taxes off, prices displayed excluding tax, or tax based on the shop base address) are unaffected.
     *
     * @since 2.1.1
     * @access private
     *
     * @return boolean True when the displayed price depends on the customer's tax location.
     */
    private function price_display_depends_on_customer_location() {
        if ( ! function_exists( 'wc_tax_enabled' ) || ! wc_tax_enabled() ) {
            return false;
        }

        return 'incl' === get_option( 'woocommerce_tax_display_shop' ) &&
            'base' !== get_option( 'woocommerce_tax_based_on' );
    }

    /**
     * Whether the shared per-role cache must be bypassed for this request.
     *
     * Bypass when the user has per-user pricing overrides (their price differs from role peers) or
     * when the store's tax-display config makes the displayed price vary by the customer's location.
     *
     * @since 2.1.1
     * @access private
     *
     * @param int $user_id User ID.
     * @return boolean True when the per-role cache must not be read or written for this request.
     */
    private function should_bypass_role_price_cache( $user_id ) {
        return $this->user_has_per_user_pricing_override( $user_id ) ||
            $this->price_display_depends_on_customer_location();
    }

    /**
     * Ensure the global validity hashes (settings + product category) exist WITHOUT purging the caches.
     *
     * The cache-warm path needs these hashes to stamp entries. A missing hash means no entry could
     * have validated, so the cache is necessarily empty — which makes the purge inside
     * set_settings_meta_hash() / set_product_category_meta_hash() wasted work (and a needless
     * full-bucket purge stampede if the hashes were ever cleared on a warm store). Generate them in
     * place instead.
     *
     * @since 2.1.1
     * @access private
     */
    private function ensure_validity_hashes_exist() {
        if ( empty( get_option( 'wwpp_settings_hash' ) ) ) {
            update_option( 'wwpp_settings_hash', uniqid( '', true ), 'no' );
        }

        if ( empty( get_option( 'wwpp_product_cat_hash' ) ) ) {
            update_option( 'wwpp_product_cat_hash', uniqid( '', true ), 'no' );
        }
    }

    /**
     * Ensure a product carries a wwpp_product_hash used to validate cache entries on read.
     *
     * Products created programmatically or imported may never have run the save hooks that stamp
     * the hash. We stamp it here with a meta-only write so cache entries become validatable; a full
     * product save is deliberately avoided because it would re-trigger save_post_product ->
     * set_product_meta_hash and churn the product hash while we are warming the cache.
     *
     * @since 2.1.1
     * @access private
     *
     * @param WC_Product $product WC_Product object.
     * @return string The product hash.
     */
    private function ensure_product_hash( $product ) {
        $product_hash = $product->get_meta( 'wwpp_product_hash', true );

        if ( empty( $product_hash ) ) {
            $product_hash = uniqid( '', true );
            $product->update_meta_data( 'wwpp_product_hash', $product_hash );
            $product->save_meta_data();
        }

        return $product_hash;
    }

    /**
     * Build a cache entry stamped with the current validity hashes.
     *
     * @since 2.1.1
     * @access private
     *
     * @param WC_Product $product WC_Product object.
     * @param array      $data    The price data to cache.
     * @return array Cache entry containing the data and the validity hash stamps.
     */
    private function build_price_cache_entry( $product, $data ) {
        return array(
            'data'                  => $data,
            'wwpp_settings_hash'    => get_option( 'wwpp_settings_hash' ),
            'wwpp_product_cat_hash' => get_option( 'wwpp_product_cat_hash' ),
            'wwpp_product_hash'     => $product->get_meta( 'wwpp_product_hash', true ),
        );
    }

    /**
     * Validate the hash stamps embedded in a cached entry against the current hashes.
     *
     * @since 2.1.1
     * @access private
     *
     * @param WC_Product $product WC_Product object.
     * @param array      $entry   Cached entry.
     * @return boolean True when all three hashes are present and match.
     */
    private function price_cache_entry_hashes_match( $product, $entry ) {
        if ( ! is_array( $entry ) ) {
            return false;
        }

        $settings_hash    = get_option( 'wwpp_settings_hash' );
        $product_cat_hash = get_option( 'wwpp_product_cat_hash' );
        $product_hash     = $product->get_meta( 'wwpp_product_hash', true );

        if ( empty( $settings_hash ) || empty( $product_cat_hash ) || empty( $product_hash ) ) {
            return false;
        }

        return isset( $entry['wwpp_settings_hash'], $entry['wwpp_product_cat_hash'], $entry['wwpp_product_hash'] ) &&
            $settings_hash === $entry['wwpp_settings_hash'] &&
            $product_cat_hash === $entry['wwpp_product_cat_hash'] &&
            $product_hash === $entry['wwpp_product_hash'];
    }

    /**
     * Wholesale Price Cache
     */

    /**
     * Get the wholesale price on cache.
     *
     * @since 1.27.8
     *
     * @param boolean|array $cache_data             False if no cached data. Array if there's a cached data.
     * @param int           $user_id                The user id.
     * @param object        $product                The product object.
     * @param int           $product_id             The product id.
     * @param array         $user_wholesale_role    The wholesale role.
     * @access public
     */
    public function get_product_wholesale_price_on_shop_v3_cache( $cache_data, $user_id, $product, $product_id, $user_wholesale_role ) {

        /**
         * Only do this for simple and variation for now. Dont use cache when in product listings admin. Get only simple
         * or variation cached data for now
         */
        if ( is_admin() ||
            'yes' !== get_option( 'wwpp_enable_wholesale_price_cache', 'yes' ) ||
            empty( $user_wholesale_role ) ||
            ! in_array( $product->get_type(), array( 'simple', 'variation' ), true ) ) {
            return $cache_data;
        }

        // Bypass the shared per-role cache for per-user overrides or location-dependent tax display.
        if ( $this->should_bypass_role_price_cache( $user_id ) ) {
            return $cache_data;
        }

        $role_cache_key = $this->build_role_cache_key( self::WHOLESALE_PRICE_CACHE_KEY, $user_wholesale_role );

        // Per-request, non-persistent copy of the role bucket so repeated lookups in one request avoid the transient layer.
        $role_cached_data = wp_cache_get( $role_cache_key, 'wwpp_cache' );

        if ( false === $role_cached_data ) {
            $role_cached_data = $this->get_role_cache_bucket( self::WHOLESALE_PRICE_CACHE_KEY, $user_wholesale_role );
            wp_cache_set( $role_cache_key, $role_cached_data, 'wwpp_cache' );
        }

        if ( is_array( $role_cached_data ) && array_key_exists( $product_id, $role_cached_data ) ) {
            $entry = $role_cached_data[ $product_id ];
            if ( isset( $entry['data'] ) && $this->price_cache_entry_hashes_match( $product, $entry ) ) {
                return $entry['data'];
            }
        }

        return $cache_data;
    }

    /**
     * Maybe set the wholesale price cache.
     *
     * @since 1.27.8
     * @since 2.1.1 Store per wholesale role (and currency) in a transient bucket with validity-hash stamps instead of per user in usermeta.
     *
     * @param int    $user_id                The user id.
     * @param object $product                The product object.
     * @param int    $product_id             The product id.
     * @param array  $user_wholesale_role    The wholesale role.
     * @param array  $price_arr              The wholesale price data.
     * @access public
     */
    public function maybe_set_wholesale_price_on_shop_v3_cache( $user_id, $product, $product_id, $user_wholesale_role, $price_arr ) {

        /**
         * Only do this for simple and variation for now. Dont use cache when in product listings admin. Cache
         * simple and variation for now
         */
        if ( $user_id <= 0 ||
            $product_id <= 0 ||
            'yes' !== get_option( 'wwpp_enable_wholesale_price_cache', 'yes' ) ||
            is_admin() ||
            empty( $user_wholesale_role ) ||
            ! in_array( $product->get_type(), array( 'simple', 'variation' ), true ) ) {
            return false;
        }

        // Bypass the shared per-role cache for per-user overrides or location-dependent tax display.
        if ( $this->should_bypass_role_price_cache( $user_id ) ) {
            return false;
        }

        // Ensure the global validity hashes exist so cached entries can be validated on read. Use the
        // non-purging helper: a missing hash means the cache is already empty, so set_settings_meta_hash()'s
        // full purge would be wasted work (and a stampede on a warm store if the hash was ever cleared).
        $this->ensure_validity_hashes_exist();

        // Cache entries are validated by the product hash on read; ensure the product carries one.
        $this->ensure_product_hash( $product );

        $role_cached_data = $this->get_role_cache_bucket( self::WHOLESALE_PRICE_CACHE_KEY, $user_wholesale_role );

        /*
         * Refresh the entry when it is missing or its hash stamps are stale. An up-to-date entry is
         * left untouched so a REST request recomputing a single product does not clobber the role
         * bucket (issue-#1441).
         *
         * Note: this read-modify-write of the shared role bucket is not atomic. Two concurrent requests
         * warming different products in the same role can race and one write may overwrite the other's
         * new entry. This is self-correcting — the dropped product is simply recomputed and re-stored on
         * the next request — and never serves stale data (entries are hash-validated on read).
         */
        if ( ! isset( $role_cached_data[ $product_id ] ) || ! $this->price_cache_entry_hashes_match( $product, $role_cached_data[ $product_id ] ) ) {
            $role_cached_data[ $product_id ] = $this->build_price_cache_entry( $product, $price_arr );

            // Save cache (transient) and refresh the per-request memo so repeat lookups in this request hit.
            $this->set_role_cache_bucket( self::WHOLESALE_PRICE_CACHE_KEY, $user_wholesale_role, $role_cached_data );
            wp_cache_set( $this->build_role_cache_key( self::WHOLESALE_PRICE_CACHE_KEY, $user_wholesale_role ), $role_cached_data, 'wwpp_cache' );
        }
    }

    /**
     * Delete wholesale price cache.
     *
     * @since 1.27.8
     * @since 2.1.0 Match Aelia-suffixed cache keys via LIKE so cache busts correctly when Aelia Currency Switcher is active (issue #1590).
     * @since 2.1.1 Caches are stored in per-role transients and validated by hash on read; this becomes a best-effort cleanup of the
     *              transient buckets for BOTH price caches (base key and every per-role/per-currency variant). Correctness is
     *              guaranteed by hash invalidation, so it is safe even when transients live in a persistent object cache this query
     *              cannot reach.
     * @access public
     */
    public function delete_wholesale_price_on_shop_v3_cache() {
        // Best-effort cleanup covers BOTH price caches, so only bail when neither is enabled.
        $price_cache_on = 'yes' === get_option( 'wwpp_enable_wholesale_price_cache', 'yes' );
        $range_cache_on = 'yes' === get_option( 'wwpp_enable_var_prod_price_range_caching', 'yes' );

        if ( ! $price_cache_on && ! $range_cache_on ) {
            return;
        }

        global $wpdb;

        // Clean up the transient rows (value + timeout) for both price caches — the base key and every
        // per-role/per-currency variant. Correctness is guaranteed by hash invalidation on read, so this
        // is best-effort cleanup that keeps the options table from accumulating stale rows.
        foreach ( array( self::WHOLESALE_PRICE_CACHE_KEY, self::VARIABLE_PRICE_RANGE_CACHE_KEY ) as $base_key ) {
            $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $wpdb->esc_like( '_transient_' . $base_key ) . '%',
                    $wpdb->esc_like( '_transient_timeout_' . $base_key ) . '%'
                )
            );
        }
    }

    /**
     * Delete all WWPP Reports Cache data.
     *
     * @since 2.0.8.1
     * @return int|bool Number of rows affected or false on error.
     */
    public function invalidate_reports_cache() {
        global $wpdb;
        $cache_key_prefix    = 'wwpp_reports';
        $wild_card_parameter = '%' . $wpdb->esc_like( $cache_key_prefix ) . '%';
        $sanitized_sql       = $wpdb->prepare( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE %s", $wild_card_parameter );
        return $wpdb->query( $sanitized_sql ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Product Category Count Badge Cache (issue #1792)
     */

    /**
     * Get the current product category count cache epoch, creating it if missing.
     *
     * The epoch is embedded in every count transient name; bumping it (on product/category changes)
     * instantly invalidates all cached counts without a sweeping DELETE.
     *
     * @since 2.1.1
     * @access private
     *
     * @return string The epoch token.
     */
    private function get_product_cat_count_epoch() {
        $epoch = get_option( self::PRODUCT_CAT_COUNT_EPOCH_OPTION );

        if ( empty( $epoch ) ) {
            $epoch = uniqid( '', true );
            update_option( self::PRODUCT_CAT_COUNT_EPOCH_OPTION, $epoch, 'no' );
        }

        return $epoch;
    }

    /**
     * Bump the product category count cache epoch, invalidating every cached count badge.
     *
     * Hooked to product and product category mutations. Accepts (and ignores) any hook arguments.
     *
     * @since 2.1.1
     * @access public
     */
    public function bump_product_cat_count_cache_epoch() {
        update_option( self::PRODUCT_CAT_COUNT_EPOCH_OPTION, uniqid( '', true ), 'no' );
    }

    /**
     * Bump the count cache epoch only when product category term relationships change.
     *
     * The set_object_terms hook fires for every taxonomy and post type, so guard on the product
     * category taxonomy to avoid needless bumps (e.g. assigning post tags). This covers a product added
     * to / removed from a category outside a full product save (bulk / Quick Edit reassignment).
     *
     * @since 2.1.1
     * @access public
     *
     * @param int    $object_id Object ID.
     * @param array  $terms     Terms set.
     * @param array  $tt_ids    Term taxonomy ids.
     * @param string $taxonomy  Taxonomy slug.
     */
    public function maybe_bump_count_cache_on_term_assignment( $object_id, $terms, $tt_ids, $taxonomy ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        if ( 'product_cat' === $taxonomy ) {
            $this->bump_product_cat_count_cache_epoch();
        }
    }

    /**
     * Bump the count cache epoch only when a product is trashed, untrashed or permanently deleted.
     *
     * Trashing/deleting a product changes a category count but does not fire save_post_product, so
     * these generic post hooks are guarded on the product post type to avoid bumping on unrelated
     * posts (pages, orders, etc.).
     *
     * @since 2.1.1
     * @access public
     *
     * @param int $post_id Post ID.
     */
    public function maybe_bump_count_cache_on_product_delete( $post_id ) {
        if ( 'product' === get_post_type( $post_id ) ) {
            $this->bump_product_cat_count_cache_epoch();
        }
    }

    /**
     * Build the per-role bucket transient key holding all category counts for a role.
     *
     * Counts for every category tile sharing the same role, only-wholesale gate and category-level
     * restriction set live in ONE transient (a term_id => count map). This keeps a 30-tile shop page
     * to a single transient read + a single write per request instead of one transient per tile. The
     * epoch invalidates every bucket on a product/category mutation; the restricted-id hash makes a
     * change to the restriction option (which alters the counts) yield a different bucket key.
     *
     * @since 2.1.1
     * @access private
     *
     * @param string $role               Wholesale role slug ('' for guests).
     * @param array  $restricted_cat_ids Restricted category ids excluded from the counts.
     * @param bool   $only_wholesale     Only-wholesale gate flag.
     * @return string Bucket transient key.
     */
    private function build_product_cat_count_bucket_key( $role, $restricted_cat_ids, $only_wholesale ) {
        $restricted = array_map( 'intval', array_values( (array) $restricted_cat_ids ) );
        sort( $restricted );

        return implode(
            '_',
            array(
                self::PRODUCT_CAT_COUNT_CACHE_KEY,
                $this->get_product_cat_count_epoch(),
                $role,
                (int) $only_wholesale,
                substr( md5( implode( ',', $restricted ) ), 0, 8 ),
            )
        );
    }

    /**
     * Get the wholesale-filtered product count for a category tile, backed by a per-role bucket cache.
     *
     * The bucket is loaded from its transient once per request (then memoised), and any newly computed
     * counts are written back once on shutdown. A cache miss computes the count with a single bounded
     * COUNT(*) query (no post hydration).
     *
     * @since 2.1.1
     * @access public
     *
     * @param int    $term_id            Category term ID.
     * @param string $role               Wholesale role slug ('' for guests).
     * @param array  $restricted_cat_ids Category term ids to exclude (NOT IN).
     * @param bool   $only_wholesale     Whether to count only products with a wholesale price.
     * @return int Number of matching published products.
     */
    public function get_product_cat_count( $term_id, $role, $restricted_cat_ids, $only_wholesale ) {
        $bucket_key = $this->build_product_cat_count_bucket_key( $role, $restricted_cat_ids, $only_wholesale );
        $term_id    = (int) $term_id;

        // Load the role bucket once per request (transient read), then serve from memory.
        if ( ! isset( $this->product_cat_count_buckets[ $bucket_key ] ) ) {
            $bucket = get_transient( $bucket_key );

            $this->product_cat_count_buckets[ $bucket_key ] = is_array( $bucket ) ? $bucket : array();
        }

        if ( array_key_exists( $term_id, $this->product_cat_count_buckets[ $bucket_key ] ) ) {
            return (int) $this->product_cat_count_buckets[ $bucket_key ][ $term_id ];
        }

        // Miss: one bounded COUNT(*), cached in memory and flagged for a single write at shutdown.
        $count = WWPP_WPDB_Helper::get_products_count_by_category( $term_id, $role, $restricted_cat_ids, $only_wholesale );

        $this->product_cat_count_buckets[ $bucket_key ][ $term_id ] = $count;
        $this->dirty_product_cat_count_buckets[ $bucket_key ]       = true;
        $this->register_product_cat_count_flush();

        return $count;
    }

    /**
     * Register the shutdown handler that persists dirty count buckets (once per request).
     *
     * @since 2.1.1
     * @access private
     */
    private function register_product_cat_count_flush() {
        if ( ! $this->product_cat_count_flush_registered ) {
            add_action( 'shutdown', array( $this, 'flush_product_cat_count_buckets' ) );
            $this->product_cat_count_flush_registered = true;
        }
    }

    /**
     * Persist every count bucket touched this request to its transient in a single write each.
     *
     * @since 2.1.1
     * @access public
     */
    public function flush_product_cat_count_buckets() {
        foreach ( array_keys( $this->dirty_product_cat_count_buckets ) as $bucket_key ) {
            if ( isset( $this->product_cat_count_buckets[ $bucket_key ] ) ) {
                set_transient( $bucket_key, $this->product_cat_count_buckets[ $bucket_key ], WEEK_IN_SECONDS );
            }
        }

        $this->dirty_product_cat_count_buckets = array();
    }

    /**
     * Whether any product has a per-product wholesale visibility restriction configured.
     *
     * A product with no restriction stores the catch-all 'all' value, so the presence of any other
     * value means at least one product is restricted to specific wholesale roles. The guarded
     * early-return in the category count badge relies on this: when it is false (and there is no
     * wholesale role and no category-level restriction) the badge can return WooCommerce's native
     * count with zero extra queries. Result is cached (epoch-stamped) so it is one query per page at
     * most, zero while warm.
     *
     * @since 2.1.1
     * @access public
     *
     * @return bool True when at least one product is restricted to specific roles.
     */
    public function store_has_per_product_visibility_restriction() {
        $key = self::PRODUCT_CAT_COUNT_CACHE_KEY . '_has_restriction_' . $this->get_product_cat_count_epoch();

        $memo = wp_cache_get( $key, 'wwpp_cache' );
        if ( false !== $memo ) {
            return (bool) $memo;
        }

        $cached = get_transient( $key );
        if ( false !== $cached ) {
            wp_cache_set( $key, (int) $cached, 'wwpp_cache' );
            return (bool) $cached;
        }

        global $wpdb;
        $has_restriction = (int) (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> 'all' LIMIT 1",
                WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER
            )
        );

        set_transient( $key, $has_restriction, WEEK_IN_SECONDS );
        wp_cache_set( $key, $has_restriction, 'wwpp_cache' );

        return (bool) $has_restriction;
    }

    /**
     * Execute Model
     */

    /**
     * Execute model.
     *
     * @since 1.16.0
     * @since 2.1.1 Invalidate the product category count badge cache on product and category mutations (issue #1792).
     * @since 2.1.1 Register the per-request price-cache memo group as non-persistent so it does not leak across
     *              requests on sites with a persistent object cache (correctness still rides on hash validation).
     * @access public
     */
    public function run() {

        // The 'wwpp_cache' group is only a per-request memo of the per-role transient buckets; keep it
        // request-scoped so a persistent object cache (Redis/Memcached) does not retain it across requests.
        if ( function_exists( 'wp_cache_add_non_persistent_groups' ) ) {
            wp_cache_add_non_persistent_groups( array( 'wwpp_cache' ) );
        }

        // AJAX.
        add_action( 'init', array( $this, 'register_ajax_handlers' ) );

        // On every product category change, WC settings change and Product update, we create new hashes.
        add_action( 'woocommerce_settings_saved', array( $this, 'set_settings_meta_hash' ), 10 );
        add_action( 'created_product_cat', array( $this, 'set_product_category_meta_hash' ), 10, 2 ); // New Product Cat.
        add_action( 'edit_term', array( $this, 'set_product_category_meta_hash' ), 10, 3 ); // Edit Product Cat.
        add_action( 'delete_product_cat', array( $this, 'set_product_category_meta_hash_delete_term' ), 10, 4 ); // Delete Product Cat.
        add_action( 'save_post_product', array( $this, 'set_product_meta_hash' ), 10, 1 );
        add_action( 'woocommerce_save_product_variation', array( $this, 'set_product_meta_hash' ), 10, 1 );

        // Rotate the per-product validity hash on non-admin (REST / import / programmatic) saves, which
        // bypass set_product_meta_hash()'s admin-only hooks and its empty-$_POST gate.
        add_action( 'woocommerce_new_product', array( $this, 'rotate_product_hash_on_save' ), 10, 1 );
        add_action( 'woocommerce_update_product', array( $this, 'rotate_product_hash_on_save' ), 10, 1 );
        add_action( 'woocommerce_new_product_variation', array( $this, 'rotate_product_hash_on_save' ), 10, 1 );
        add_action( 'woocommerce_update_product_variation', array( $this, 'rotate_product_hash_on_save' ), 10, 1 );

        // Wholesale Price range cache.
        add_filter( 'wwp_get_variable_product_price_range_cache', array( $this, 'get_variable_product_price_range_cache' ), 10, 4 );
        add_action( 'wwp_after_variable_product_compute_price_range', array( $this, 'maybe_set_variable_price_range_cache' ), 10, 4 );

        /**
         * On product create or update, remove transient if caching is enabled so that the front end will be up to date.
         * This will re-run the query and build cache when user visits page with product listing like product shortcode,
         * widget or shop page.
         */
        add_action( 'woocommerce_update_product', array( $this, 'product_update' ), 10, 1 );

        // Delete product listing cache on Product Category Create, Update, Delete.
        add_action( 'edited_product_cat', array( $this, 'product_cat_update' ), 10, 2 );
        add_action( 'create_product_cat', array( $this, 'product_cat_update' ), 10, 2 );
        add_action( 'delete_product_cat', array( $this, 'product_cat_update' ), 10, 2 );

        // Delete product listing cache on profile update. They might update the override wholesale price per user.
        add_action( 'profile_update', array( $this, 'profile_update' ), 10, 2 );

        // Wholesale price data cache.
        add_filter( 'wwp_get_product_wholesale_price_on_shop_v3_cache', array( $this, 'get_product_wholesale_price_on_shop_v3_cache' ), 10, 5 );
        add_action( 'wwp_after_get_product_wholesale_price_on_shop_v3', array( $this, 'maybe_set_wholesale_price_on_shop_v3_cache' ), 10, 5 );

        // Invalidate both price caches on general discount mapping changes by bumping the settings hash.
        // Bumping the hash invalidates cached entries on read (object-cache-safe) and also cleans up the transient rows.
        add_action( 'wwpp_add_wholesale_role_general_discount_mapping', array( $this, 'set_settings_meta_hash' ), 10, 1 );
        add_action( 'wwpp_delete_wholesale_role_general_discount_mapping', array( $this, 'set_settings_meta_hash' ), 10, 1 );
        add_action( 'wwpp_edit_wholesale_role_general_discount_mapping', array( $this, 'set_settings_meta_hash' ), 10, 1 );

        // Invalidate the product category count badge cache (issue #1792) whenever a product is
        // created/updated/deleted, a product category term changes, or a product is added to /
        // removed from a category (including bulk / Quick Edit term reassignment).
        add_action( 'save_post_product', array( $this, 'bump_product_cat_count_cache_epoch' ), 10 );
        add_action( 'woocommerce_update_product', array( $this, 'bump_product_cat_count_cache_epoch' ), 10 );
        add_action( 'trashed_post', array( $this, 'maybe_bump_count_cache_on_product_delete' ), 10, 1 );
        add_action( 'untrashed_post', array( $this, 'maybe_bump_count_cache_on_product_delete' ), 10, 1 );
        add_action( 'before_delete_post', array( $this, 'maybe_bump_count_cache_on_product_delete' ), 10, 1 );
        add_action( 'created_product_cat', array( $this, 'bump_product_cat_count_cache_epoch' ), 10 );
        add_action( 'edited_product_cat', array( $this, 'bump_product_cat_count_cache_epoch' ), 10 );
        add_action( 'delete_product_cat', array( $this, 'bump_product_cat_count_cache_epoch' ), 10 );
        add_action( 'set_object_terms', array( $this, 'maybe_bump_count_cache_on_term_assignment' ), 10, 4 );
    }
}
