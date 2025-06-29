<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Query' ) ) {

    /**
     * Model that houses the logic of filtering on woocommerce query.
     *
     * @since 1.12.8
     * @see WWPP_Product_Visibility They are related in a way that WWPP_Product_Visibility filter product to be visible only to certain user roles.
     */
    class WWPP_Query {


        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWPP_Query.
         *
         * @since 1.12.8
         * @access private
         * @var WWPP_Query
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since 1.12.8
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
         * Array of registered wholesale roles.
         *
         * @since 1.13.0
         * @access private
         * @var array
         */
        private $_registered_wholesale_roles;

        /**
         * Product category wholesale role filter.
         *
         * @since 1.16.0
         * @access public
         * @var array
         */
        private $_product_cat_wholesale_role_filter;

        /**
         * Class Methods
         */

        /**
         * WWPP_Query constructor.
         *
         * @since 1.12.8
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Query model.
         */
        public function __construct( $dependencies ) {
            $this->_wwpp_wholesale_roles                = $dependencies['WWPP_Wholesale_Roles'];
            $this->_wwpp_wholesale_price_wholesale_role = $dependencies['WWPP_Wholesale_Price_Wholesale_Role'];

            $this->_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

            $this->_product_cat_wholesale_role_filter = get_option( WWPP_OPTION_PRODUCT_CAT_WHOLESALE_ROLE_FILTER );
            if ( ! is_array( $this->_product_cat_wholesale_role_filter ) ) {
                $this->_product_cat_wholesale_role_filter = array();
            }
        }

        /**
         * Ensure that only one instance of WWPP_Query is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.12.8
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Query model.
         * @return WWPP_Query
         */
        public static function instance( $dependencies ) {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * If using products shortcode, filter the product ids of only visible products for the current user.
         *
         * @since 1.23.4
         * @access public
         *
         * @param array $products_arr   List of product ids visible for this user.
         * @param array $args           WP_Query args.
         *
         * @return array
         */
        public function filter_products( $products_arr, $args ) {
            if ( ! empty( $args['post__in'] ) ) {
                return array_intersect( $args['post__in'], $products_arr );
            }

            return $products_arr;
        }

        /**
         * Fix for WWPP-751. Avoid 2 or more joins on meta query which cause heavy load time on the shop page and product shortcodes if database is huge.
         * Gather meta query here so we can cache if the feature is enabled.
         * Store wholesale products ids in a cache. We will use transient for this which will delete weekely.
         * Different ways transient cache gets deleted:
         * 1. Transients was set to expire weekly
         * 2. Transients can be purge via clear cache in the settings.
         * 3. Transients are cleard during product update
         * 4. Transients are cleard during category update
         * 5. Transients are cleared when updating wholesale price via the general discount
         * 6. A transient specific for user gets cleared when updating the profile incase discount is set per user.
         *
         * Transients Cache naming
         * 1. regular users         = wwpp_cached_products_ids
         * 2. wholesale_users users = wwpp_cached_products_ids_<wholesale_role_key>
         * 3. per user              = wwpp_cached_products_ids_<user_id>
         *
         * Added non persistent cache called WP_Object_Cache, this will be usefull expensive query operation so they're not performed multiple times within a page load.
         * This is used in visibility check if a product belongs to wholesale products.
         *
         * @since 1.23.2
         * @access public
         *
         * @param bool   $only_show_wholesale_products   If "Only Show Wholesale Products To Wholesale Users" setting is enabled.
         * @param string $user_wholesale_role            Wholesale Role of current user.
         * @param array  $args                           WP_Query args.
         * @return array
         */
        public function optimized_meta_query( $only_show_wholesale_products, $user_wholesale_role, $args = array() ) {
            global $wpdb;

            // Override per user.
            $current_user_id                 = get_current_user_id();
            $wwpp_override_discount_per_user = get_user_meta( $current_user_id, 'wwpp_override_wholesale_discount', true );

            // Per user transient cache.
            if ( 'yes' === $wwpp_override_discount_per_user ) {
                $transient_name = 'wwpp_cached_products_ids_' . $current_user_id;
            } else {
                // Per wholesale or just regular visitor transients cache.
                $transient_name  = 'wwpp_cached_products_ids';
                $transient_name .= ! empty( $user_wholesale_role ) ? '_' . $user_wholesale_role : '';
            }

            $restricted_products = array();
            $wholesale_products  = array();

            $wwpp_cached_products_ids  = get_transient( $transient_name );
            $wwpp_product_cache_option = get_option( 'wwpp_enable_product_cache' );

            if ( 'yes' === $wwpp_product_cache_option && ! empty( $wwpp_cached_products_ids ) ) {

                // Non-persistent cache.
                $result = wp_cache_get( 'wwpp_cached_products_ids_non_persistent' );
                if ( false === $result ) {
                    wp_cache_set( 'wwpp_cached_products_ids_non_persistent', $wwpp_cached_products_ids );
                }

                return $this->filter_products( $wwpp_cached_products_ids, $args );

            } else {

                $restricted_args = array(
                    'post_type'      => 'product',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'meta_query'     => array(
                        array(
                            'key'     => WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER,
                            'value'   => array( $user_wholesale_role, 'all' ),
                            'compare' => 'IN',
                        ),
                    ),
                );

                $restricted_products = get_posts( $restricted_args );

                if ( $restricted_products && $only_show_wholesale_products ) {

                    if ( 'yes' === $wwpp_override_discount_per_user ) {

                        $restricted_products = array_merge( $restricted_products, $this->get_variations_ids( $restricted_products ) );

                        // Set wholesale product ids cache. Persistent cache.
                        if ( 'yes' === $wwpp_product_cache_option ) {
                            set_transient( $transient_name, $restricted_products, WEEK_IN_SECONDS );
                        }
                        // Delete after a week.

                        // Non-persistent cache.
                        $result = wp_cache_get( 'wwpp_cached_products_ids_non_persistent' );
                        if ( false === $result ) {
                            wp_cache_set( 'wwpp_cached_products_ids_non_persistent', empty( $wholesale_products ) ? array( 0 ) : $wholesale_products );
                        }

                        return empty( $restricted_products ) ? array( 0 ) : $restricted_products;

                    } else {

                        // Using sql query will cause memory issue
                        // We will just use of wp_query.
                        $wholesale_args = array(
                            'post_type'      => 'product',
                            'post_status'    => 'publish',
                            'posts_per_page' => -1,
                            'fields'         => 'ids',
                            'post__in'       => $restricted_products,
                            'meta_query'     => array(
                                'relation' => 'OR',
                                array(
                                    'key'     => $user_wholesale_role . '_have_wholesale_price',
                                    'value'   => 'yes',
                                    'compare' => '=',
                                ),
                                array( // WWPP-158 : Compatibility with WooCommerce Show Single Variations.
                                    'key'     => $user_wholesale_role . '_wholesale_price',
                                    'value'   => 0,
                                    'compare' => '>',
                                    'type'    => 'NUMERIC',
                                ),
                            ),
                        );

                        $wholesale_products = get_posts( $wholesale_args );
                        $wholesale_products = array_merge( $wholesale_products, $this->get_variations_ids( $wholesale_products ) );

                        // Get grouped products.
                        $grouped_products = wc_get_products(
                            array(
                                'type'    => 'grouped',
                                'status'  => 'publish',
                                'limit'   => -1,
                                'include' => $restricted_products,
                            )
                        );

                        if ( ! empty( $grouped_products ) ) {
                            $grouped_wholesale_products = $this->filter_grouped_products( $grouped_products, $restricted_products, $user_wholesale_role );
                            $wholesale_products         = array_merge( $wholesale_products, $grouped_wholesale_products );
                        }

                        // Set wholesale product ids cache. Persistent cache.
                        if ( 'yes' === $wwpp_product_cache_option ) {
                            set_transient( $transient_name, $wholesale_products, WEEK_IN_SECONDS );
                        }
                        // Delete after a week.

                        // Non-persistent cache.
                        $result = wp_cache_get( 'wwpp_cached_products_ids_non_persistent' );
                        if ( false === $result ) {
                            wp_cache_set( 'wwpp_cached_products_ids_non_persistent', empty( $wholesale_products ) ? array( 0 ) : $wholesale_products );
                        }

                        return empty( $wholesale_products ) ? array( 0 ) : $this->filter_products( $wholesale_products, $args );

                    }
                }

                $restricted_products = array_merge( $restricted_products, $this->get_variations_ids( $restricted_products ) );

                // Set product ids restriction cache. Persistent cache.
                if ( 'yes' === $wwpp_product_cache_option ) {
                    set_transient( $transient_name, $restricted_products, WEEK_IN_SECONDS );
                }
                // Delete after a week.

                // Non-persistent cache.
                $result = wp_cache_get( 'wwpp_cached_products_ids_non_persistent' );
                if ( false === $result ) {
                    wp_cache_set( 'wwpp_cached_products_ids_non_persistent', $restricted_products );
                }

                return empty( $restricted_products ) ? array( 0 ) : $this->filter_products( $restricted_products, $args );

            }
        }

        /**
         * Filter grouped products.
         * If a grouped product has no wholesale children, then it should not be visible to wholesale users.
         *
         * @param array  $grouped_products      Grouped products.
         * @param array  $restricted_products   Restricted products.
         * @param string $user_wholesale_role   User wholesale role.
         *
         * @since 1.23.4
         * @access public
         *
         * @return array
         */
        public function filter_grouped_products( $grouped_products, $restricted_products, $user_wholesale_role ) {
            $wholesale_products = array();

            foreach ( $grouped_products as $grouped_product ) {
                $grouped_product_id = $grouped_product->get_id();

                $grouped_product_meta = get_post_meta( $grouped_product_id );

                if ( isset( $grouped_product_meta[ $user_wholesale_role . '_have_wholesale_price' ] ) && 'yes' === $grouped_product_meta[ $user_wholesale_role . '_have_wholesale_price' ][0] ) {
                    $wholesale_products[] = $grouped_product_id;
                } elseif ( isset( $grouped_product_meta[ $user_wholesale_role . '_wholesale_price' ] ) && $grouped_product_meta[ $user_wholesale_role . '_wholesale_price' ][0] > 0 ) {
                    $wholesale_products[] = $grouped_product_id;
                } else {
                    $grouped_product_children = $grouped_product->get_children();

                    if ( ! empty( $grouped_product_children ) ) {
                        $grouped_product_children = array_intersect( $grouped_product_children, $restricted_products );

                        if ( ! empty( $grouped_product_children ) ) {
                            $wholesale_products[] = $grouped_product_id;
                        }
                    }
                }
            }

            return $wholesale_products;
        }

        /**
         * Get variable variations if WooCommerce Show Single Variations Plugin is active.
         * Integration for Products By Attributes & Variations for WooCommerce plugin.
         *
         * @since 1.23.7
         * @access public
         *
         * @param array $product_ids    Product IDs.
         * @return array
         */
        public function get_variations_ids( $product_ids ) {
            $product_ids = apply_filters( 'wwpp_get_variation_ids_via_product_ids', $product_ids );

            if (
                is_plugin_active( 'show-single-variations-premium/iconic-woo-show-single-variations.php' )
                ||
                is_plugin_active( 'show-products-by-attributes-variations/addify_show_variation_single_product.php' )
            ) {

                $variations = array();

                if ( ! empty( $product_ids ) ) {

                    global $wpdb;

                    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, Squiz.Strings.DoubleQuoteUsage.NotRequired -- Ignored for allowing interpolation in IN query.
                    $variation_ids = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT 
                                ID 
                             FROM 
                                {$wpdb->posts} 
                             WHERE 
                                post_type = 'product_variation' 
                                AND post_status = 'publish'
                                AND post_parent IN ( " . implode( ',', array_fill( 0, count( $product_ids ), '%d' ) ) . " )",
                            $product_ids
                        ),
                        ARRAY_A
                    );
                    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, Squiz.Strings.DoubleQuoteUsage.NotRequired -- Ignored for allowing interpolation in IN query.

                    if ( $variation_ids ) {

                        foreach ( $variation_ids as $variation ) {
                            $variations[] = $variation['ID'];
                        }
                    }
                }

                return ! empty( $variations ) ? $variations : array();

            }

            return array();
        }

        /**
         * Apply wholesale roles filter to shop and archive pages.
         *
         * @since 1.0.0
         * @since 1.7.4
         * There is a bug where is you do 2 separate set->('meta_query', $args)
         * then that meta query becomes an or query not an and, can't figure out why.
         * So we need to set->('meta_query',$args) the 2 filters at the same time
         * The product visibility filter and the show only wholesale products to wholesale users filter
         * @since 1.12.8 Refactor code base for effeciency and maintanability.
         * @since 1.13.1 Prevent query stacking. (Applying the same filter to the same query multiple times).
         * @since 1.15.3 Silence notices thrown by function is_shop.
         * @since 1.16.0 Add support for per category wholesale role filter.
         * @since 1.23.2 Replace the filter used from 'pre_get_posts' to 'woocommerce_product_query'. This is fired after WC set their custom query using the hook 'pre_get_posts'.
         *               Replacing the filter fixes the issue on multiple filter call.
         *               Avoid using multiple meta_query. Doing 2 or more query joins will cause loading issue if customer has very huge database.
         *               The fix was to divide meta queries. 1st query checks the 'wwpp_product_wholesale_visibility_filter' of products, 2nd query checks if the returned products in 1st query has met the meta query condition set for '{wholesale_role}_have_wholesale_price' and '{wholesale_role}_wholesale_price'.
         * @access public
         *
         * @param WP_Query $query WP_Query object.
         */
        public function pre_get_posts( $query ) {
            // Check if user is not an admin, else we don't want to restrict admins in any way.
            if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore.
                // Admin and Shop Manager.

                if ( ! $query->is_main_query() ) {
                    return;
                }

                if ( is_search() && ( ! isset( $_GET['post_type'] ) || $_GET['post_type'] !== 'product' ) ) { //phpcs:ignore

                    // Normal WP search, exclude product related stuff here and terminate function early.

                    $public_post_types = get_post_types(
                        array(
                            'public'              => true,
                            'publicly_queryable'  => true,
                            'exclude_from_search' => false,
                        )
                    );

                    if ( array_key_exists( 'product', $public_post_types ) ) {
                        unset( $public_post_types['product'] );
                    }

                    if ( ! array_key_exists( 'page', $public_post_types ) ) {
                        $public_post_types['page'] = 'page';
                    }

                    $query->set( 'post_type', array_keys( $public_post_types ) );

                    return;

                }

                $user_wholesale_role  = $this->_get_current_user_wholesale_role();
                $front_page_id        = get_option( 'page_on_front' );
                $current_page_id      = $query->get( 'page_id' );
                $shop_page_id         = apply_filters( 'woocommerce_get_shop_page_id', get_option( 'woocommerce_shop_page_id' ) );
                $is_static_front_page = 'page' === get_option( 'show_on_front' );
                $wwpp_products        = array();

                // We do this way in determining the shop page for cases where the shop page is set as the front page.
                if ( $is_static_front_page && $front_page_id === $current_page_id ) {
                    $is_shop_page = ( $current_page_id === $shop_page_id ) ? true : false;
                } else {
                    $is_shop_page = is_shop();
                }

                if ( ! is_admin() && ( $is_shop_page || is_product_category() || is_product_taxonomy() || is_search() ) ) {

                    $filtered_query = $this->get_filtered_products_query();

                    // Set tax query.
                    if ( ! empty( $filtered_query['filtered_term_ids'] ) ) {

                        $tax_query = is_array( $query->get( 'tax_query' ) ) ? $query->get( 'tax_query' ) : array();

                        if ( ! empty( $tax_query ) ) {

                            $tax_query = array_merge(
                                $tax_query,
                                array(
                                    array(
                                        'taxonomy' => 'product_cat',
                                        'field'    => 'term_id',
                                        'terms'    => array_map( 'intval', $filtered_query['filtered_term_ids'] ),
                                        'operator' => 'NOT IN',
                                    ),
                                )
                            );

                        }

                        $query->set( 'tax_query', apply_filters( 'wwpp_pre_get_post_tax_query', $tax_query, $query ) );

                    }

                    $query->set( 'post__in', apply_filters( 'wwpp_pre_get_post__in', $filtered_query['filtered_prod_ids'], $query ) );

                }
            }
        }

        /**
         * Same as pre_get_posts function but only intended for WooCommerce Wholesale Order Form integration,
         * you see the WWOF uses custom query, so unlike the usual way of filter query object, we can't do that with WWOF,
         * but we can filter the query args thus achieving the same effect.
         *
         * @since 1.0.0
         * @since 1.7.4  Apply "Only Show Wholesale Products To Wholesale Users" filter.
         * @since 1.12.8 Refactor code base for effeciency and maintanability.
         * @since 1.16.0 Add support for per category wholesale role filter.
         * @since 1.23.2 Avoid using multiple meta_query. Doing 2 or more query joins will cause loading issue if customer has very huge database.
         *               The fix was to divide meta queries. 1st query checks the 'wwpp_product_wholesale_visibility_filter' of products, 2nd query checks if the returned products in 1st query has met the meta query condition set for '{wholesale_role}_have_wholesale_price' and '{wholesale_role}_wholesale_price'.
         * @access public
         *
         * @param array $query_args         Query args array.
         * @return mixed
         */
        public function pre_get_posts_arg( $query_args ) {
            // Check if user is not an admin, else we don't want to restrict admins in any way.
            if ( ! current_user_can( 'manage_options' ) ) {

                $user_wholesale_role   = $this->_get_current_user_wholesale_role();
                $serialized_query_args = maybe_serialize( $query_args );
                $wwpp_products         = array();

                // Tax query init.
                $filtered_term_ids = array();

                if ( $user_wholesale_role && get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false ) === 'yes' && ! $this->_wholesale_user_have_general_role_discount( $user_wholesale_role ) ) {

                    $wwpp_products = $this->optimized_meta_query( true, $user_wholesale_role, $query_args );

                    if ( ! empty( $this->_product_cat_wholesale_role_filter ) ) {
                        $filtered_term_ids = $this->_get_restricted_product_cat_ids_for_wholesale_user( $user_wholesale_role );
                    }
                } elseif ( ! empty( $user_wholesale_role ) && ! empty( $this->_product_cat_wholesale_role_filter ) ) {
                    $filtered_term_ids = $this->_get_restricted_product_cat_ids_for_wholesale_user( $user_wholesale_role );
                } elseif ( empty( $user_wholesale_role ) && ! empty( $this->_product_cat_wholesale_role_filter ) ) {
                    $filtered_term_ids = array_keys( $this->_product_cat_wholesale_role_filter );
                }
                // Non wholesale user.

                if ( ! empty( $filtered_term_ids ) ) {

                    if ( ! isset( $query_args['tax_query'] ) ) {
                        $query_args['tax_query'] = array();
                    }

                    $serialized_tax_query         = maybe_serialize( $query_args['tax_query'] );
                    $serialized_filtered_term_ids = maybe_serialize( $filtered_term_ids );

                    // The goal here is to not repeatedly add this tax query as pre_get_posts can be called multiple times.
                    if ( strpos( $serialized_tax_query, $serialized_filtered_term_ids ) === false ) {

                        $query_args['tax_query'][] = array(
                            'taxonomy' => 'product_cat',
                            'field'    => 'term_id',
                            'terms'    => array_map( 'intval', $filtered_term_ids ),
                            'operator' => 'NOT IN',
                        );

                    }
                }

                // Check if tax query is set.
                if ( isset( $query_args['tax_query'] ) ) {
                    $query_args['tax_query'] = apply_filters( 'wwpp_pre_get_post_tax_query', $query_args['tax_query'], $query_args );
                }

                if ( empty( $wwpp_products ) ) {
                    $wwpp_products = $this->optimized_meta_query( false, $user_wholesale_role, $query_args );
                }

                // Don't show bundle product if it has no wholesale childrens
                // If current user is wholesale and Bundle Plugin is active and Only Show.. is enabled.
                if (
                    ! empty( $user_wholesale_role ) &&
                    WWP_Helper_Functions::is_plugin_active( 'woocommerce-product-bundles/woocommerce-product-bundles.php' ) &&
                    get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false ) === 'yes'
                ) {
                    global $wc_wholesale_prices_premium;
                    $exclude = $wc_wholesale_prices_premium->wwpp_wc_bundle_product->excluded_bundle_products();

                    $wwpp_products = array_diff( $wwpp_products, $exclude );

                }

                // If current user is wholesale user.
                if ( ! empty( $user_wholesale_role ) ) {

                    // If Disregard Product Category Level Wholesale Discount is enabled and
                    // If Disregard Wholesale Role Level Wholesale Discount is enabled.
                    $disregard = $this->disregard_wholesale_products( $user_wholesale_role );

                    $wwpp_products = array_diff( $wwpp_products, $disregard );

                }

                if ( empty( $wwpp_products ) ) {
                    $wwpp_products = array( 0 );
                }

                $query_args['post__in'] = apply_filters( 'wwpp_pre_get_post__in', $wwpp_products, $query_args );

            }

            return $query_args;
        }

        /**
         * Filter product query. New in WC 3.0.7, they are now trying to implement prepared statements style on their product sql query.
         *
         * @since 1.14.6
         * @access public
         *
         * @param array $query_arr  Query array.
         * @param int   $product_id Product id.
         * @return array Filtered product query.
         */
        public function product_query_filter( $query_arr, $product_id ) { // phpcs:ignore.
            global $wpdb;

            // Check if user is not an admin, else we don't want to restrict admins in any way.
            if ( ! current_user_can( 'manage_options' ) ) {

                $user_wholesale_role  = $this->_get_current_user_wholesale_role();
                $serialized_query_arr = maybe_serialize( $query_arr );

                // Make sure we don't re add this query if it is already added.
                if ( strpos( $serialized_query_arr, WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER ) === false ) {

                    $query_arr['where'] .= " AND p.ID IN (
                                                    SELECT DISTINCT pt.ID
                                                    FROM $wpdb->posts pt
                                                    INNER JOIN $wpdb->postmeta pmt
                                                    ON pt.ID = pmt.post_id
                                                    WHERE pmt.meta_key = '" . WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER . "'
                                                    AND pmt.meta_value IN ( '" . $user_wholesale_role . "' , 'all' )
                                                )";

                }

                if ( $user_wholesale_role &&
                    get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false ) === 'yes' &&
                    ! $this->_wholesale_user_have_general_role_discount( $user_wholesale_role ) ) {

                    // Make sure we don't re add this query if it is already added.
                    if ( strpos( $serialized_query_arr, $user_wholesale_role . '_have_wholesale_price' ) === false ) {

                        // If there is a default general wholesale discount set for this wholesale role then all products are considered wholesale for this dude.
                        // If no mapping is present, then we only show products with wholesale prices specific for this wholesale role.

                        $query_arr['where'] .= " AND p.ID IN (
                                                        SELECT DISTINCT pt.ID
                                                        FROM $wpdb->posts pt
                                                        INNER JOIN $wpdb->postmeta pmt
                                                        ON pt.ID = pmt.post_id
                                                        WHERE ( pmt.meta_key = '" . $user_wholesale_role . "_have_wholesale_price' AND pmt.meta_value = 'yes' )
                                                        OR ( pmt.meta_key = '" . $user_wholesale_role . "_wholesale_price' AND pmt.meta_value > 0 )
                                                    )";

                    }
                }
            }

            return $query_arr;
        }

        /**
         * WC Layer Nav Widget  query is not really optimized well for extension.
         * The widget query alone is fast, however, if it is extended it became very slow.
         * Ticket ID: WWPP-437
         *
         * @param array $query Array of sql query.
         * @return array Filtered array of sql query.
         */
        public function optimize_wwpp_query_for_layer_nav_query( $query ) {
            global $wpdb;

            $user_wholesale_role = $this->_get_current_user_wholesale_role();

            $query = str_replace( "INNER JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id )", '', $query );
            $query = str_replace(
                "( $wpdb->postmeta.meta_key = 'wwpp_product_wholesale_visibility_filter' AND $wpdb->postmeta.meta_value IN ('$user_wholesale_role','all') )",
                "( $wpdb->posts.ID IN ( SELECT DISTINCT pt.ID FROM $wpdb->posts pt INNER JOIN $wpdb->postmeta pmt ON pt.ID = pmt.post_id WHERE pmt.meta_key = '" . WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER . "' AND pmt.meta_value IN ( '$user_wholesale_role' , 'all' ) ) )",
                $query
            );

            return $query;
        }

        /*
        |---------------------------------------------------------------------------------------------------------------
        | Helper Functions
        |---------------------------------------------------------------------------------------------------------------
         */

        /**
         * Get curent user wholesale role.
         *
         * @since 1.12.8
         * @access private
         *
         * @return string User role string or empty string.
         */
        private function _get_current_user_wholesale_role() {
            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            return ( is_array( $user_wholesale_role ) && ! empty( $user_wholesale_role ) ) ? $user_wholesale_role[0] : '';
        }

        /**
         * Check if a wholesale user have an entry on general role discount mapping.
         * WooCommerce > Settings > Wholesale Prices > Discount > General Discount Options
         *
         * @since 1.12.8
         * @since 1.16.0 Refactor code base to get wholesale discount wholesale role level from 'WWPP_Wholesale_Price_Wholesale_Role' model.
         * @access private
         *
         * @param string $user_wholesale_role User Wholesale Role Key.
         * @return boolean Whether wholesale user have mapping entry or not.
         */
        private function _wholesale_user_have_general_role_discount( $user_wholesale_role ) {
            $user_wholesale_discount = $this->_wwpp_wholesale_price_wholesale_role->get_user_wholesale_role_level_discount( get_current_user_id(), $user_wholesale_role );
            return ! empty( $user_wholesale_discount['discount'] );
        }

        /**
         * Get restricted term ids for the current wholesale user.
         *
         * @since 1.16.0
         * @access public
         *
         * @param string $user_wholesale_role User wholesale role.
         * @return array Array of restricted term ids for the current wholesale user.
         */
        public function _get_restricted_product_cat_ids_for_wholesale_user( $user_wholesale_role ) {
            $filtered_terms_ids = array();

            foreach ( $this->_product_cat_wholesale_role_filter as $term_id => $filtered_wholesale_roles ) {
                if ( ! in_array( $user_wholesale_role, $filtered_wholesale_roles, true ) ) {
                    $filtered_terms_ids[] = $term_id;
                }
            }

            return $filtered_terms_ids;
        }

        /**
         * Filter shortcode products to only show wholesale products.
         *
         * @since 1.23.2
         * @access public
         *
         * @param array  $query_args    User wholesale role.
         * @param array  $attributes    Query attributes.
         * @param string $type          Shortcode Type.
         * @return array
         */
        public function wc_shortcode_products_query( $query_args, $attributes, $type = '' ) {
            $wc_product_shortcodes = array(
                'products',
                'recent_products',
                'sale_products',
                'best_selling_products',
                'top_rated_products',
                'featured_products',
                'product_attribute',
                'related_products',
                'product_category',
            );

            return in_array( $type, $wc_product_shortcodes, true ) ? $this->pre_get_posts_arg( $query_args ) : $query_args;
        }

        /**
         * Fetch all disregard global and category wholesale products.
         *
         * @since 1.24.7
         * @access public
         *
         * @param string $user_wholesale_role    Wholesale User.
         * @param array  $product_ids            The product ids to filter.
         * @return array
         */
        public function disregard_wholesale_products( $user_wholesale_role, $product_ids = array() ) {
            if ( get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false ) !== 'yes' ) {
                return array();
            }

            $disregard_wholesale_products = wp_cache_get( 'wwpp_disregard_wholesale_products_non_persistent' );

            if ( false !== $disregard_wholesale_products ) {
                return $disregard_wholesale_products;
            }

            $disregard_products = get_posts(
                array(
                    'post_type'      => 'product',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'post__in'       => $product_ids,
                    'meta_query'     => array(
                        array(
                            'key'     => $user_wholesale_role . '_have_wholesale_price_set_by_product_cat',
                            'value'   => 'yes',
                            'compare' => '=',
                        ),
                        array(
                            'key'     => 'wwpp_ignore_cat_level_wholesale_discount',
                            'value'   => 'yes',
                            'compare' => '=',
                        ),
                        array(
                            'relation' => 'OR',
                            array(
                                'key'     => $user_wholesale_role . '_variations_with_wholesale_price',
                                'value'   => '',
                                'compare' => '=',
                            ),
                            array(
                                'key'     => $user_wholesale_role . '_variations_with_wholesale_price',
                                'value'   => 'gebbirish',
                                'compare' => 'NOT EXISTS',
                            ),
                        ),

                    ),
                )
            );

            // If general discount is set,
            // Get all products with "Disregard Product Category Level Wholesale Discount" is enabled.
            if ( $this->_wholesale_user_have_general_role_discount( $user_wholesale_role ) ) {

                $disregard_general_products = get_posts(
                    array(
                        'post_type'      => 'product',
                        'post_status'    => 'publish',
                        'posts_per_page' => -1,
                        'fields'         => 'ids',
                        'post__in'       => $product_ids,
                        'meta_query'     => array(
                            // Disregard wholesale general / role discount.
                            array(
                                'key'     => 'wwpp_ignore_role_level_wholesale_discount',
                                'value'   => 'yes',
                                'compare' => '=',
                            ),
                            array(
                                'relation' => 'OR',
                                array(
                                    'key'     => $user_wholesale_role . '_variations_with_wholesale_price',
                                    'value'   => '',
                                    'compare' => '=',
                                ),
                                array(
                                    'key'     => $user_wholesale_role . '_variations_with_wholesale_price',
                                    'value'   => 'gebbirish',
                                    'compare' => 'NOT EXISTS',
                                ),
                            ),
                        ),
                    )
                );

                // Both Category and General Discount are Disregarded.
                $category_and_general_disregard = array_intersect( $disregard_general_products, $disregard_products );

                // Disregard General Discount.
                $disregard_products = array_unique( array_merge( $disregard_general_products, $category_and_general_disregard ) );

            }

            wp_cache_set( 'wwpp_disregard_wholesale_products_non_persistent', $disregard_products );

            return apply_filters( 'wwpp_disregard_wholesale_products', $disregard_products );
        }

        /**
         * Filter related products.
         * Dont show restricted products to guests if set via category and product restriction.
         *
         * @since 1.27
         * @access public
         *
         * @param array $related_posts Array of related posts.
         * @param int   $product_id    The product id.
         * @param array $args          Arguments array.
         * @return array
         */
        public function filter_related_products( $related_posts, $product_id, $args ) { // phpcs:ignore.
            global $wc_wholesale_prices;

            if ( ! current_user_can( 'manage_options' ) && $wc_wholesale_prices ) {

                $wholesale_role    = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();
                $wholesale_role    = isset( $wholesale_role[0] ) ? $wholesale_role[0] : '';
                $filtered_term_ids = array();

                if ( ! empty( $wholesale_role ) && ! empty( $this->_product_cat_wholesale_role_filter ) ) {
                    $filtered_term_ids = $this->_get_restricted_product_cat_ids_for_wholesale_user( $wholesale_role );
                } elseif ( empty( $wholesale_role ) && ! empty( $this->_product_cat_wholesale_role_filter ) ) {
                    $filtered_term_ids = array_keys( $this->_product_cat_wholesale_role_filter );
                }

                $related_posts_query_args = array(
                    'post_type'  => 'product',
                    'fields'     => 'ids',
                    'post__in'   => $related_posts,
                    'meta_query' => array(
                        'relation' => 'AND',
                        array(
                            'key'     => WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER,
                            'value'   => array( $wholesale_role, 'all' ),
                            'compare' => 'IN',
                        ),
                    ),
                    'tax_query'  => array(
                        array(
                            'taxonomy' => 'product_cat',
                            'field'    => 'term_id',
                            'terms'    => array_map( 'intval', $filtered_term_ids ),
                            'operator' => 'NOT IN',
                        ),
                    ),
                );

                // Set posts per page to -1 to get all related products.
                $filter_posts_per_page                      = apply_filters( 'wwpp_filter_related_products_per_page', count( $related_posts ) );
                $related_posts_query_args['posts_per_page'] = $filter_posts_per_page;

                if ( get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false ) === 'yes' ) {
                    $related_posts_query_args['meta_query'] = array(
                        'relation' => 'OR',
                        array(
                            'key'     => $wholesale_role . '_have_wholesale_price',
                            'value'   => 'yes',
                            'compare' => '=',
                        ),
                        array( // WWPP-158 : Compatibility with WooCommerce Show Single Variations.
                            'key'     => $wholesale_role . '_wholesale_price',
                            'value'   => 0,
                            'compare' => '>',
                            'type'    => 'NUMERIC',
                        ),
                    );
                }

                $related_posts = get_posts( $related_posts_query_args );
            }

            return $related_posts;
        }

        /**
         * Filter product children when "Only Show Wholesale Products To Wholesale Customers" is enabled.
         *
         * Scenarios:
         * - When the child has Product level wholesale price has both "Disregard Product Category Level Wholesale Discount" and "Disregard Wholesale Role Level Wholesale Discount" enabled then still display the child.
         * - When the child has Category level wholesale discount is set for a product and "Disregard Product Category Level Wholesale Discount" is enabled then dont show the child.
         * - When the child has General level wholesale discount is set for a product and "Disregard Wholesale Role Level Wholesale Discount" is enabled then dont show the child.
         *
         * @since 1.27.3
         * @access public
         *
         * @param array $ids          Product Ids.
         * @return array
         */
        public function filter_children( $ids ) {
            global $wc_wholesale_prices;

            $user_wholesale_role                  = $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole();
            $product_level_wholesale_products     = array();
            $disregard_general_discount_products  = array();
            $disregard_category_discount_products = array();

            if ( ! empty( $ids ) && ! empty( $user_wholesale_role ) && get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false ) === 'yes' ) {

                // Wholesale Product Level should still be displayed.
                $product_level_wholesale_products = get_posts(
                    array(
                        'post_type'      => 'product',
                        'post_status'    => 'publish',
                        'posts_per_page' => -1,
                        'fields'         => 'ids',
                        'meta_query'     => array(
                            array(
                                'key'     => 'wholesale_customer_wholesale_price',
                                'value'   => '0',
                                'compare' => '>',
                            ),
                        ),
                        'post__in'       => $ids,
                    )
                );

                if ( $this->_wholesale_user_have_general_role_discount( $user_wholesale_role[0] ) ) {

                    // Ignore general discount.
                    $disregard_general_discount_products = get_posts(
                        array(
                            'post_type'      => 'product',
                            'post_status'    => 'publish',
                            'posts_per_page' => -1,
                            'fields'         => 'ids',
                            'meta_query'     => array(
                                array(
                                    'key'     => 'wwpp_ignore_role_level_wholesale_discount',
                                    'value'   => 'yes',
                                    'compare' => '!=',
                                ),
                            ),
                            'post__in'       => $ids,
                        )
                    );

                }

                // Ignore category discount.
                $disregard_category_discount_products = get_posts(
                    array(
                        'post_type'      => 'product',
                        'post_status'    => 'publish',
                        'posts_per_page' => -1,
                        'fields'         => 'ids',
                        'meta_query'     => array(
                            array(
                                'key'     => $user_wholesale_role[0] . '_have_wholesale_price_set_by_product_cat',
                                'value'   => 'yes',
                                'compare' => '=',
                            ),
                            array(
                                'key'     => 'wwpp_ignore_cat_level_wholesale_discount',
                                'value'   => 'yes',
                                'compare' => '!=',
                            ),
                        ),
                        'post__in'       => $ids,
                    )
                );

                return array_unique( array_merge( $product_level_wholesale_products, $disregard_general_discount_products, $disregard_category_discount_products ) );

            }

            return $ids;
        }

        /**
         * Filter product query for wholesale customers.
         *
         * @since 2.0.4
         * @access private
         *
         * @return array Filtered query array.
         */
        private function get_filtered_products_query() {
            $user_wholesale_role = $this->_get_current_user_wholesale_role();

            // Tax query init.
            $filtered_term_ids = array();

            // User is wholesale customer AND
            // show wholesale products to wholesale users is enabled AND
            // general discount is not set.
            if ( ! empty( $user_wholesale_role ) &&
                get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false ) === 'yes' &&
                ! $this->_wholesale_user_have_general_role_discount( $user_wholesale_role ) ) {

                // If there is a default general wholesale discount set for this wholesale role then all products are considered wholesale for this dude
                // If no mapping is present, then we only show products with wholesale prices specific for this wholesale role.
                $wwpp_products = $this->optimized_meta_query( true, $user_wholesale_role );

                if ( ! empty( $this->_product_cat_wholesale_role_filter ) ) {
                    $filtered_term_ids = $this->_get_restricted_product_cat_ids_for_wholesale_user( $user_wholesale_role );
                }
            } elseif ( ! empty( $user_wholesale_role ) && ! empty( $this->_product_cat_wholesale_role_filter ) ) {
                $filtered_term_ids = $this->_get_restricted_product_cat_ids_for_wholesale_user( $user_wholesale_role );
            } elseif ( empty( $user_wholesale_role ) && ! empty( $this->_product_cat_wholesale_role_filter ) ) {
                // Non wholesale user.
                $filtered_term_ids = array_keys( $this->_product_cat_wholesale_role_filter );
            }

            if ( empty( $wwpp_products ) ) {
                $wwpp_products = $this->optimized_meta_query( false, $user_wholesale_role );
            }

            // Don't show bundle product if it has no wholesale childrens
            // If current user is wholesale and Bundle Plugin is active and Only Show.. is enabled.
            if (
                ! empty( $user_wholesale_role ) &&
                WWP_Helper_Functions::is_plugin_active( 'woocommerce-product-bundles/woocommerce-product-bundles.php' ) &&
                get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false ) === 'yes'
            ) {
                global $wc_wholesale_prices_premium;
                $exclude = $wc_wholesale_prices_premium->wwpp_wc_bundle_product->excluded_bundle_products();

                $wwpp_products = array_diff( $wwpp_products, $exclude );

            }

            // If current user is wholesale user.
            if ( ! empty( $user_wholesale_role ) ) {

                // If Disregard Product Category Level Wholesale Discount is enabled and
                // If Disregard Wholesale Role Level Wholesale Discount is enabled.
                $disregard = $this->disregard_wholesale_products( $user_wholesale_role );

                $wwpp_products = array_diff( $wwpp_products, $disregard );

            }

            if ( empty( $wwpp_products ) ) {
                $wwpp_products = array( 0 );
            }

            return array(
                'filtered_term_ids' => $filtered_term_ids,
                'filtered_prod_ids' => $wwpp_products,
            );
        }

        /**
         * Filter products collection visibility.
         *
         * @param WC_Product_Query $query object.
         * @param array            $block Block of the query.
         * @param int              $page Page of the query.
         *
         * @since 2.0.3
         * @since 2.0.4 Add check if post type is product.
         *
         * @return WC_Product_Query
         */
        public function filter_query_loop_block_query_vars( $query, $block, $page ) { // phpcs:ignore

            // Pass if post type is product.
            if ( isset( $query['post_type'] ) && 'product' === $query['post_type'] ) {
                $filtered_query = $this->get_filtered_products_query();

                // Set tax query.
                if ( ! empty( $filtered_query['filtered_term_ids'] ) ) {

                    $tax_query = is_array( $query['tax_query'] ) ? $query['tax_query'] : array();

                    if ( ! empty( $tax_query ) ) {

                        $tax_query = array_merge(
                            $tax_query,
                            array(
                                array(
                                    'taxonomy' => 'product_cat',
                                    'field'    => 'term_id',
                                    'terms'    => array_map( 'intval', $filtered_query['filtered_term_ids'] ),
                                    'operator' => 'NOT IN',
                                ),
                            )
                        );

                    }

                    $query['tax_query'] = apply_filters( 'wwpp_pre_get_block_post_tax_query', $tax_query, $query );

                }

                if ( ! empty( $filtered_query['filtered_prod_ids'] ) ) {
                    $query['post__in'] = apply_filters( 'wwpp_pre_get_block_post__in', $filtered_query['filtered_prod_ids'], $query );
                }
            }

            return $query;
        }

        /*
        |---------------------------------------------------------------------------------------------------------------
        | Execute Model
        |---------------------------------------------------------------------------------------------------------------
         */

        /**
         * Execute model.
         *
         * @since 1.12.8
         * @since 1.23.2 We won't be doing meta_query, instead we will just add the result query ids in post__in args and have it cached so it will load faster.
         *               Removed 'woocommerce_grouped_children_args' filter as it was already removed by WC on version 3.
         *               Removed 'woocommerce_related_products_args' filter as it was already removed by WC on version 3.
         *               Removed 'wwof_filter_product_listing_query_arg' filter, we will just do it in wwof plugin.
         *               Removed 'woocommerce_product_query_meta_query' filter. We will just use 'woocommerce_shortcode_products_query'.
         * @since 1.23.3 Re-add 'wwof_filter_product_listing_query_arg' filter.
         * @access public
         */
        public function run() {
            // This hook is called after the wc query variable object is created, but before the actual query is run.
            // The pre_get_posts action gives developers access to the $query object by reference (any changes you make to $query are made directly to the original object - no return value is necessary).
            add_action( 'woocommerce_product_query', array( $this, 'pre_get_posts' ), 10, 1 );

            // For WC Widgets.
            add_filter( 'woocommerce_products_widget_query_args', array( $this, 'pre_get_posts_arg' ), 10, 1 );

            // For shortcode products. Redundant with 'woocommerce_product_query_meta_query' filter. Both fired in the same function so we just pick one.
            add_filter( 'woocommerce_shortcode_products_query', array( $this, 'wc_shortcode_products_query' ), 10, 3 );

            // Fix slow query on wc layer nav query.
            add_filter( 'woocommerce_get_filtered_term_product_counts_query', array( $this, 'optimize_wwpp_query_for_layer_nav_query' ), 10, 1 );

            // Filter product query in wwof.
            add_filter( 'wwof_filter_product_listing_query_arg', array( $this, 'pre_get_posts_arg' ), 10, 1 );

            // For related products.
            add_filter( 'woocommerce_product_related_posts_query', array( $this, 'product_query_filter' ), 10, 2 ); // WC 3.0.7.
            add_filter( 'woocommerce_related_products', array( $this, 'filter_related_products' ), 10, 3 );

            // Filter children.
            add_filter( 'woocommerce_product_get_children', array( $this, 'filter_children' ) );

            // Filter product block.
            add_filter( 'query_loop_block_query_vars', array( $this, 'filter_query_loop_block_query_vars' ), 11, 3 );
        }
    }

}
