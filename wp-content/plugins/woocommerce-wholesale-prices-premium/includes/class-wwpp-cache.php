<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that houses the logic relating caching.
 *
 * @since 1.16.0
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
     * Wholesale price cache key.
     *
     * @since 1.27.8
     */
    const WHOLESALE_PRICE_CACHE_KEY = 'wwpp_product_wholesale_price_on_shop_v3_cache';

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
     * @access public
     *
     * @param int     $post_id      Post id.
     * @param boolean $bypass_check Flag to whether bypass the action validity check.
     * @return string|boolean Generated hash or false when operation fails
     */
    public function set_product_meta_hash( $post_id, $bypass_check = false ) {

        $this->delete_wholesale_price_on_shop_v3_cache();

        if ( true === $bypass_check ||
            WWP_Helper_Functions::check_if_valid_save_post_action( $post_id, 'product' ) ||
            WWP_Helper_Functions::check_if_valid_save_post_action( $post_id, 'product_variation' )
        ) {
            // Get product object.
            $product = wc_get_product( $post_id );

            $hash = uniqid( '', true );
            $product->update_meta_data( 'wwpp_product_hash', $hash );

            // Save product meta.
            $product->save();
            return $hash;
        }

        return false;
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

        if ( $product->get_type() === 'variable' ) {
            $settings_hash    = get_option( 'wwpp_settings_hash' );
            $product_cat_hash = get_option( 'wwpp_product_cat_hash' );
            $product_hash     = $product->get_meta( 'wwpp_product_hash', true );

            if ( ! empty( $settings_hash ) && ! empty( $product_cat_hash ) && ! empty( $product_hash ) ) {
                return $settings_hash === $cache_data['wwpp_settings_hash'] && $product_cat_hash === $cache_data['wwpp_product_cat_hash'] && $product_hash === $cache_data['wwpp_product_hash'];
            }
        }

        return false;
    }

    /**
     * Set variable product price range cache.
     *
     * @since 1.16.0
     * @access public
     *
     * @param int        $user_id User Id.
     * @param WC_Product $product WC_Product object.
     * @param array      $args    Data to cache.
     */
    public function set_variable_product_price_range_cache( $user_id, $product, $args ) {

        if ( $product->get_type() === 'variable' ) {

            $product_id       = $product->get_id();
            $settings_hash    = get_option( 'wwpp_settings_hash' );
            $product_cat_hash = get_option( 'wwpp_product_cat_hash' );
            $product_hash     = $product->get_meta( 'wwpp_product_hash', true );

            if ( empty( $settings_hash ) ) {
                $settings_hash = $this->set_settings_meta_hash();
            }

            if ( empty( $product_cat_hash ) ) {
                $product_cat_hash = $this->set_product_category_meta_hash( false, false, 'product_cat' );
            }

            if ( empty( $product_hash ) ) {
                $product_hash = $this->set_product_meta_hash( $product_id, true );
            }

            $user_cached_data = $this->aelia_currency_compat( $user_id );

            if ( ! is_array( $user_cached_data ) ) {
                $user_cached_data = array();
            }

            $hashes_arr = array(
                'wwpp_settings_hash'    => $settings_hash,
                'wwpp_product_cat_hash' => $product_cat_hash,
                'wwpp_product_hash'     => $product_hash,
            );

            $cache_data                      = wp_parse_args( $args, $hashes_arr );
            $user_cached_data[ $product_id ] = $cache_data;

            $this->aelia_currency_compat( $user_id, $user_cached_data );
        }
    }

    /**
     * Get cache variable product price range cache.
     *
     * @since 1.16.0
     * @access public
     *
     * @param int        $user_id User Id.
     * @param WC_Product $product WC_Product object.
     * @return array|boolean Array of cached data if successful, boolean false otherwise.
     */
    public function get_cache_variable_product_price_range_cache( $user_id, $product ) {

        if ( 'variable' === $product->get_type() ) {
            $product_id = $product->get_id();

            $user_cached_data = $this->aelia_currency_compat( $user_id );
            if ( ! is_array( $user_cached_data ) ) {
                $user_cached_data = array();
            }

            return array_key_exists( $product_id, $user_cached_data ) ? $user_cached_data[ $product_id ] : false;
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
        if ( 'yes' !== get_option( 'wwpp_enable_var_prod_price_range_caching' ) ) {
            return false;
        }

        $variable_product_price_range_cache = $this->get_cache_variable_product_price_range_cache( $user_id, $product );
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
        if ( 'yes' !== get_option( 'wwpp_enable_var_prod_price_range_caching' ) ) {
            return false;
        }

        $variable_product_price_range_cache = $this->get_cache_variable_product_price_range_cache( $user_id, $product );

        if ( false === $variable_product_price_range_cache || ! $this->check_variable_product_price_range_cache_if_valid( $user_id, $product, $variable_product_price_range_cache ) ) {
            if ( is_array( $args ) && isset( $args['min_price'] ) && isset( $args['max_price'] ) && isset( $args['some_variations_have_wholesale_price'] ) ) {
                $this->set_variable_product_price_range_cache( $user_id, $product, $args );
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
     * @access public
     */
    public function ajax_regenerate_new_cache_hash() {
        if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
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
     * @access public
     */
    public function ajax_clear_product_transients_cache() {
        if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
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
     * Compatibility with aelia currency switcher plugin.
     *
     * @since 1.23.9
     * @since 1.27.8 Add 3rd parameter $user_cache_key
     *
     * @param int    $user_id The user ID.
     * @param array  $user_cached_data The cached user data.
     * @param string $user_cache_key The user's cache key.
     * @access public
     */
    public function aelia_currency_compat( $user_id, $user_cached_data = false, $user_cache_key = 'wwpp_variable_product_price_range_cache' ) {
        if ( WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {
            $activeCurrency  = get_woocommerce_currency();
            $user_cache_key .= '_' . $activeCurrency;
        }

        if ( ! empty( $user_cached_data ) ) {
            // Set/update cache.
            update_user_meta( $user_id, $user_cache_key, $user_cached_data );
        } else {
            // Get cache.
            return get_user_meta( $user_id, $user_cache_key, true );
        }
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
            'yes' !== get_option( 'wwpp_enable_wholesale_price_cache' ) ||
            empty( $user_wholesale_role ) ||
            ! in_array( $product->get_type(), array( 'simple', 'variation' ), true ) ) {
            return $cache_data;
        }

        $user_wholesale_role      = isset( $user_wholesale_role ) ? $user_wholesale_role[0] : '';
        $non_persistent_cache_key = 'get_product_wholesale_price_on_shop_v3_cache';
        $user_cached_data         = wp_cache_get( $non_persistent_cache_key );

        if ( false === $user_cached_data ) {
            // Get cache.
            $user_cached_data = $this->aelia_currency_compat( $user_id, false, self::WHOLESALE_PRICE_CACHE_KEY );

            if ( ! is_array( $user_cached_data ) ) {
                $user_cached_data = array();
            }

            // Set non-persistent cache.
            wp_cache_set( $non_persistent_cache_key, $user_cached_data );
        }

        return ( is_array( $user_cached_data ) && array_key_exists( $product_id, $user_cached_data ) ) ?
            $user_cached_data[ $product_id ] :
            false;
    }

    /**
     * Maybe set the wholesale price cache.
     *
     * @since 1.27.8
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
            'yes' !== get_option( 'wwpp_enable_wholesale_price_cache' ) ||
            is_admin() ||
            ! in_array( $product->get_type(), array( 'simple', 'variation' ), true ) ) {
            return false;
        }

        // Get cache.
        $user_cached_data = $this->aelia_currency_compat( $user_id, false, self::WHOLESALE_PRICE_CACHE_KEY );

        if ( ! is_array( $user_cached_data ) ) {
            $user_cached_data = array();
        }

        $user_cached_data[ $product_id ] = $price_arr;

        // Save cache.
        $this->aelia_currency_compat( $user_id, $user_cached_data, self::WHOLESALE_PRICE_CACHE_KEY );
    }

    /**
     * Delete wholesale price cache.
     *
     * @since 1.27.8
     * @access public
     */
    public function delete_wholesale_price_on_shop_v3_cache() {
        if ( 'yes' !== get_option( 'wwpp_enable_wholesale_price_cache' ) ) {
            return;
        }

        global $wpdb;

        // phpcs:disable.
        $product_ids = array();
        $action      = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';

        if ( $action ) {
            switch ( $action ) {
                // Saving variation(s).
                case 'woocommerce_save_variations':
                    if ( isset( $_REQUEST['variable_post_id'] ) && ! empty( $_REQUEST['variable_post_id'] ) ) {
                        $product_ids = $_REQUEST['variable_post_id'];
                    }
                    break;

                // Saving a product.
                case 'editpost':
                    if ( isset( $_REQUEST['ID'] ) && ! empty( $_REQUEST['ID'] ) ) {
                        $product_ids[] = $_REQUEST['ID'];
                    }
                    break;

                // Bulk edit.
                case 'edit':
                    if ( isset( $_REQUEST['post'] ) && ! empty( $_REQUEST['post'] ) ) {
                        $product_ids = $_REQUEST['post'];
                    }
                    break;

                // Trash/Untrash.
                case 'trash':
                case 'untrash':
                    if ( isset( $_REQUEST['post'] ) && ! empty( $_REQUEST['post'] ) ) {
                        if ( is_array( $_REQUEST['post'] ) ) {
                            // Single trash/untrash.
                            $product_ids = $_REQUEST['post'];
                        } else {
                            // Bulk trash/untrash.
                            $product_ids[] = $_REQUEST['post'];
                        }
                    }
                    break;

            }
        }
        // phpcs:enable.

        if ( ! empty( $product_ids ) ) {

            // Get users with wholesale price cache meta.
            $users_with_cache = $wpdb->get_results( $wpdb->prepare( "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s", self::WHOLESALE_PRICE_CACHE_KEY ) );

            if ( $users_with_cache ) {

                // Loop all users with price cache meta.
                foreach ( $users_with_cache as $user ) {
                    $user_cached_data = get_user_meta( $user->user_id, self::WHOLESALE_PRICE_CACHE_KEY, true );

                    // Remove only cached related to updated product id.
                    foreach ( $product_ids as $product_id ) {
                        if ( isset( $user_cached_data[ $product_id ] ) ) {
                            unset( $user_cached_data[ $product_id ] );
                        }
                    }

                    update_user_meta( $user->user_id, self::WHOLESALE_PRICE_CACHE_KEY, $user_cached_data );
                }
            }
        } else {
            // Purge all cache.
            $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE meta_key = %s", self::WHOLESALE_PRICE_CACHE_KEY ) );
        }
    }

    /**
     * Execute Model
     */

    /**
     * Execute model.
     *
     * @since 1.16.0
     * @access public
     */
    public function run() {
        // AJAX.
        add_action( 'init', array( $this, 'register_ajax_handlers' ) );

        // On every product category change, WC settings change and Product update, we create new hashes.
        add_action( 'woocommerce_settings_saved', array( $this, 'set_settings_meta_hash' ), 10 );
        add_action( 'created_product_cat', array( $this, 'set_product_category_meta_hash' ), 10, 2 ); // New Product Cat.
        add_action( 'edit_term', array( $this, 'set_product_category_meta_hash' ), 10, 3 ); // Edit Product Cat.
        add_action( 'delete_product_cat', array( $this, 'set_product_category_meta_hash_delete_term' ), 10, 4 ); // Delete Product Cat.
        add_action( 'save_post_product', array( $this, 'set_product_meta_hash' ), 10, 1 );
        add_action( 'woocommerce_save_product_variation', array( $this, 'set_product_meta_hash' ), 10, 1 );

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

        // Delete wholesale price data cache on general discount update.
        add_action( 'wwpp_add_wholesale_role_general_discount_mapping', array( $this, 'delete_wholesale_price_on_shop_v3_cache' ), 10, 1 );
        add_action( 'wwpp_delete_wholesale_role_general_discount_mapping', array( $this, 'delete_wholesale_price_on_shop_v3_cache' ), 10, 1 );
        add_action( 'wwpp_edit_wholesale_role_general_discount_mapping', array( $this, 'delete_wholesale_price_on_shop_v3_cache' ), 10, 1 );
    }
}
