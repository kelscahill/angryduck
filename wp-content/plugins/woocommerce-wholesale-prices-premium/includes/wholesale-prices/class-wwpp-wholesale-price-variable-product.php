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
     */
    class WWPP_Wholesale_Price_Variable_Product {
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

                        if ( strpos( $price_suffix, '{price_including_tax}' ) === false && strpos( $price_suffix, '{price_excluding_tax}' ) === false && ! $args['return_wholesale_price_only'] ) {

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
                    // Get all children of the variable product.
                    // @since 2.0.0.6 Changes for load issues on variable products with many variations.
                    // @since 2.0.1 Change back to get_post_meta to avoid performance issue.
                    foreach ( $children as $variation_id ) {
                        $roles_variation_is_visible = get_post_meta( $variation_id, WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER );

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
                $children            = $product->get_children();
                $user_id             = apply_filters( 'wwpp_get_current_user_id', get_current_user_id() );

                if ( ! empty( $children ) ) {
                    // Get all children of the variable product.
                    // @since 2.0.0.6 Changes for load issues on variable products with many variations.
                    // @since 2.0.1 Change back to get_post_meta to avoid performance issue.
                    foreach ( $children as $variation_id ) {

                        $roles_variation_is_visible = get_post_meta( $variation_id, WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER );
                        if ( ! is_array( $roles_variation_is_visible ) ) {
                            $roles_variation_is_visible = array();
                        }

                        if ( ! empty( $roles_variation_is_visible ) && ( ! in_array( 'all', $roles_variation_is_visible, true ) || ! in_array( $user_wholesale_role, $roles_variation_is_visible, true ) ) ) {

                            // Has a variation that is not accessible to the current user, therefore we have to update the product hash
                            // so WooCommerce will need to get the price data fresh from the db and not from the hash.
                            // TODO: Investigate the possible performance penalty of this codebase. Specially on variable products with lots of variations.
                            $price_hash[] = 'wwpp_' . $user_id . '_' . $product->get_id();

                            break;

                        }
                    }
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
         * Filter variable variation prices when "Only Show Wholesale Products To Wholesale Customers" is enabled.
         * Only list variation price if the variation has wholesale price.
         *
         * @since 1.25.1
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

            foreach ( $transient_cached_prices_array as $key => $values ) {

                foreach ( $values as $variation_id => $price ) {

                    // Wholesale Exclusive Variation
                    // Array of wholesale role(s) in which this variation is exclusive to show.
                    $roles_variation_is_visible = get_post_meta( $variation_id, WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER );

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

                // phpcs:disable
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

            // Filter variable variation price ranges when "Only Show Wholesale Products To Wholesale Customers" is enabled.
            add_filter( 'woocommerce_variation_prices', array( $this, 'filter_wholesale_variation_prices' ), 10, 3 );
            add_filter( 'wwpp_pre_get_post__in', array( $this, 'filter_variable_simple_pre_get_post__in' ), 10, 2 );
        }
    }
}
