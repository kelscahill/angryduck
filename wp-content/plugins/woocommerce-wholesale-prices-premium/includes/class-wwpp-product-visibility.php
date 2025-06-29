<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Product_Visibility' ) ) {

    /**
     * Model that houses the logic of filtering the products and only showing them to the proper recipient.
     *
     * @since 1.12.8
     * @see WWPP_Query They are related in a way that WWPP_Query also filter products but via query.
     */
    class WWPP_Product_Visibility {

        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWPP_Product_Visibility.
         *
         * @since 1.12.8
         * @access private
         * @var WWPP_Product_Visibility
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
         * Product category wholesale role filter.
         *
         * @since 1.16.0
         * @access public
         * @var array
         */
        private $_product_cat_wholesale_role_filter;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWPP_Product_Visibility constructor.
         *
         * @since 1.12.8
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Product_Visibility model.
         */
        public function __construct( $dependencies ) {

            $this->_wwpp_wholesale_roles                = $dependencies['WWPP_Wholesale_Roles'];
            $this->_wwpp_wholesale_price_wholesale_role = $dependencies['WWPP_Wholesale_Price_Wholesale_Role'];

            $this->_product_cat_wholesale_role_filter = get_option( WWPP_OPTION_PRODUCT_CAT_WHOLESALE_ROLE_FILTER );
            if ( ! is_array( $this->_product_cat_wholesale_role_filter ) ) {
                $this->_product_cat_wholesale_role_filter = array();
            }
        }

        /**
         * Ensure that only one instance of WWPP_Product_Visibility is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.12.8
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Product_Visibility model.
         * @return WWPP_Product_Visibility
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
         * @since 1.12.8
         * @access private
         *
         * @return mixed String of user wholesale role, False otherwise.
         */
        private function _get_current_user_wholesale_role() {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();

            return ( is_array( $user_wholesale_role ) && ! empty( $user_wholesale_role ) ) ? $user_wholesale_role[0] : false;
        }

        /*
        |--------------------------------------------------------------------------
        | Wholesale Role Visibility Filter On Single Product Admin Page
        |--------------------------------------------------------------------------
         */

        /**
         * Embed custom metabox with fields relating to wholesale role filter into the single product admin page.
         *
         * @since 1.0.0
         * @since 1.12.8 Refactor code base.
         * @since 1.16.0 Add ignore role/cat level wholesale pricing feature.
         * @access public
         */
        public function add_product_wholesale_role_visibility_filter_fields() {

            global $post;

            /**
             * Allows to hide role restriction filter field
             *
             * @param boolean $show Whether to show the role restriction filter field. Default true
             * @return boolean
             */
            if ( ! apply_filters( 'wwpp_product_editor_show_role_visibility_filter', true ) ) {
                return;
            }

            $all_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

            if ( 'product' === $post->post_type ) {
                $product_wholesale_role_filter = get_post_meta( $post->ID, WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER );

                if ( ! is_array( $product_wholesale_role_filter ) ) {
                    $product_wholesale_role_filter = array();
                }

                $ignore_cat_level_wp  = get_post_meta( $post->ID, 'wwpp_ignore_cat_level_wholesale_discount', true );
                $ignore_role_level_wp = get_post_meta( $post->ID, 'wwpp_ignore_role_level_wholesale_discount', true );

                require_once WWPP_VIEWS_PATH . 'backend/product/single/view-wwpp-product-wholesale-role-visibility-filter.php';

            }
        }

        /**
         * Save custom embeded fields relating to wholesale role visibility filter.
         *
         * @since 1.0.0
         * @since 1.12.8 Refactor code base to be more efficient and secure.
         * @since 1.16.0 Add ignore role/cat level wholesale pricing feature.
         * @access public
         *
         * @param int $post_id Post ( Product ) Id.
         */
        public function save_product_wholesale_role_visibility_filter( $post_id ) {

            // Check if this is an inline edit. If true then return.
            if ( isset( $_POST['_inline_edit'] ) && wp_verify_nonce( $_POST['_inline_edit'], 'inlineeditnonce' ) ) {
                return;
            }

            // Check if valid save post action.
            if ( WWP_Helper_Functions::check_if_valid_save_post_action( $post_id, 'product' ) ) {

                // Get product object.
                $product = wc_get_product( $post_id );

                if ( ! is_a( $product, 'WC_Product' ) ) {
                    return;
                }

                // Security check.
                if ( isset( $_POST['wwpp_nonce_save_product_wholesale_role_visibility_filter'] ) && wp_verify_nonce( $_POST['wwpp_nonce_save_product_wholesale_role_visibility_filter'], 'wwpp_action_save_product_wholesale_role_visibility_filter' ) ) {

                    // Because we are adding post meta via add_meta_data
                    // We make sure to delete old post meta so the meta won't contains duplicate values.
                    $product->delete_meta_data( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER );

                    if ( isset( $_POST['wholesale-visibility-select'] ) && is_array( $_POST['wholesale-visibility-select'] ) && ! empty( $_POST['wholesale-visibility-select'] ) ) {

                        foreach ( $_POST['wholesale-visibility-select'] as $wholesaleRole ) {
                            $product->add_meta_data( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER, $wholesaleRole );
                        }
                    } else {
                        $product->add_meta_data( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER, 'all' );
                    }
                }

                if ( isset( $_POST['wwpp_nonce_save_product_wholesale_price_options'] ) && wp_verify_nonce( $_POST['wwpp_nonce_save_product_wholesale_price_options'], 'wwpp_action_save_product_wholesale_price_options' ) ) {

                    $ignore_cat_level_wp  = isset( $_POST['void-cat-level-wholesale-discount'] ) ? 'yes' : 'no';
                    $ignore_role_level_wp = isset( $_POST['void-wholesale-role-level-wholesale-discount'] ) ? 'yes' : 'no';

                    $product->update_meta_data( 'wwpp_ignore_cat_level_wholesale_discount', $ignore_cat_level_wp );
                    $product->update_meta_data( 'wwpp_ignore_role_level_wholesale_discount', $ignore_role_level_wp );

                }

                // Save the product.
                $product->save();
            }
        }

        /**
         * Apply wholesale role visibility filter to each single product page.
         * If single product page is loaded, check the filter and the current user if he/she is authorized to view the product.
         * If yes then continue loading the single product page. Else redirect to the shop page.
         *
         * @since 1.0.0
         * @since 1.12.8 Refactor code base to be more efficient and maintainable.
         * @since 1.16.0 Add support for per category wholesale role filter.
         * @since 1.26.3 shop manager and admin is not restricted
         * @since 1.27   Separate the logic that checks if the product has wholesale role restriction set in the category.
         *               Transfered it to the helper class coz this logic is also used in the API.
         * @access public
         */
        public function check_product_wholesale_role_visibility_filter() {

            // Check if user is not an admin or shop manager, else we don't want to restrict admins in any way.
            if ( ! current_user_can( 'manage_woocommerce' ) ) {

                $user_wholesale_role = $this->_get_current_user_wholesale_role();
                $redirect_link       = apply_filters( 'wwpp_wholesale_role_visibility_filter_redirect_link', get_permalink( wc_get_page_id( 'shop' ) ) );

                if ( is_product() ) {

                    global $post;

                    // Get product object.
                    $product    = wc_get_product( $post->ID );
                    $product_id = $product->get_id();

                    $product_cat_terms = get_the_terms( $product_id, 'product_cat' );

                    // Wholesale role product category filter.
                    $product_is_restricted_in_category = WWPP_Helper_Functions::is_product_restricted_in_category( $product_id, $user_wholesale_role );

                    // One of the cats this product is under have a wholesale role filter.
                    if ( $product_is_restricted_in_category ) {
                        wp_safe_redirect( $redirect_link );
                        exit();
                    }

                    $post_wholesale_filter = WWP_Helper_Functions::get_formatted_meta_data( $product, WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER );

                    if ( ! is_array( $post_wholesale_filter ) || empty( $post_wholesale_filter ) ) {
                        // If no filter then meaning this product is accessible to all users.
                        $post_wholesale_filter = array( 'all' );
                    }

                    if ( ! in_array( $user_wholesale_role, $post_wholesale_filter, true ) && ! in_array( 'all', $post_wholesale_filter, true ) ) {
                        wp_safe_redirect( $redirect_link );
                        exit();
                    }
                } elseif ( is_product_category() ) {

                    $cat_id = get_queried_object_id();

                    if ( ! empty( $this->_product_cat_wholesale_role_filter ) && array_key_exists( $cat_id, $this->_product_cat_wholesale_role_filter ) && ! in_array( $user_wholesale_role, $this->_product_cat_wholesale_role_filter[ $cat_id ], true ) ) {

                        wp_safe_redirect( $redirect_link );
                        exit();

                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Only Show Wholesale Products To Wholesale Users
        |--------------------------------------------------------------------------
         */

        /**
         * Only show wholesale products to wholesale users if specified by admin. (Single product page).
         *
         * @since 1.0.3
         * @since 1.13.0 Refactor codebase and move to its correct model.
         * @since 1.16.0 Refactor code base to get wholesale discount wholesale role level from 'WWPP_Wholesale_Price_Wholesale_Role' model.
         */
        public function only_show_wholesale_products_to_wholesale_users() {

            $user_wholesale_role = $this->_get_current_user_wholesale_role();

            // Check if user is not an admin, else we don't want to restrict admins in any way.
            // And also check if settings for "Only Showing Wholesale Products To Wholesale Users" option is checked.
            if ( ! empty( $user_wholesale_role ) && ! current_user_can( 'manage_options' ) && get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false ) === 'yes' && is_product() ) {

                global $post, $wc_wholesale_prices_premium;

                $disregard_products = $wc_wholesale_prices_premium->wwpp_query->disregard_wholesale_products( $user_wholesale_role );
                $redirect_link      = apply_filters( 'wwpp_only_show_wholesale_products_to_wholesale_users_redirect_link', get_permalink( wc_get_page_id( 'shop' ) ) );

                // Disregard wholesale products.
                if ( in_array( $post->ID, $disregard_products, true ) ) {

                    wp_safe_redirect( $redirect_link );
                    exit();

                }

                $user_wholesale_discount = $this->_wwpp_wholesale_price_wholesale_role->get_user_wholesale_role_level_discount( get_current_user_id(), $user_wholesale_role );

                // If the current user have no ( either empty or zero or false ) wholesale discount, we check the 'have_wholesale_price' flag.
                // Else ( It has valid value ) then all products for this customer is considered as having wholesale price.
                if ( $user_wholesale_role && empty( $user_wholesale_discount['discount'] ) ) {

                    $wholesale_price      = get_post_meta( $post->ID, $user_wholesale_role . '_wholesale_price', true );
                    $have_wholesale_price = get_post_meta( $post->ID, $user_wholesale_role . '_have_wholesale_price', true );

                    // Possible that the product is group.
                    if ( 'product' === $post->post_type && 'yes' !== $have_wholesale_price ) {
                        // Get product object.
                        $product = wc_get_product( $post->ID );

                        // Check if Grouped product.
                        if ( $product->is_type( 'grouped' ) ) {
                            $grouped_products = $product->get_children();
                            $not_allowed      = true;
                            if ( ! empty( $grouped_products ) ) {
                                $child_products = array();
                                foreach ( $grouped_products as $grouped_product_id ) {
                                    $grouped_product_have_wholesale_price = get_post_meta( $grouped_product_id, $user_wholesale_role . '_have_wholesale_price', true );

                                    if ( 'yes' !== $grouped_product_have_wholesale_price ) {
                                        $child_products[] = $grouped_product_id;
                                    }
                                }

                                if ( empty( $child_products ) ) {
                                    $not_allowed = false;
                                }
                            }

                            if ( $not_allowed ) {
                                wp_safe_redirect( $redirect_link );
                                exit();
                            }
                        } elseif ( empty( $wholesale_price ) ) {

                            wp_safe_redirect( $redirect_link );
                            exit();

                        }
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Cross/Inter Sells Products
        |--------------------------------------------------------------------------
         */

        /**
         * Filter cross-sells and up-sells ids. Remove restricted product ids, only show products that is allowed the the current user.
         *
         * @since 1.23.4
         * @since 1.28   Add 'product_variation' in 'post_type' query arguments to show variation as upsells product
         *
         * @param string $wholesale_role             Wholesale Role.
         * @param array  $ids                        Cross-sells and Uup-sells ids.
         * @param array  $has_wholesale_discount     Value is true if override per user is set or general discount is set else value is false.
         *
         * @return array
         */
        public function visibility_filter_upsell_crossell_ids( $wholesale_role, $ids, $has_wholesale_discount ) {
            global $wc_wholesale_prices_premium;

            $show_wholesale_products_to_wholesale_users = get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users', false );
            $restricted_cat_ids                         = array();

            if ( ! empty( $wholesale_role ) ) {
                $restricted_cat_ids = $wc_wholesale_prices_premium->wwpp_query->_get_restricted_product_cat_ids_for_wholesale_user( $wholesale_role );
            } else {
                // For non-wholesale users, if "Only Show Wholesale Products" is enabled, return empty array.
                if ( 'yes' === $show_wholesale_products_to_wholesale_users ) {
                    return array();
                }

                $restricted_categories = get_option( WWPP_OPTION_PRODUCT_CAT_WHOLESALE_ROLE_FILTER, array() );
                if ( ! empty( $restricted_categories ) ) {
                    foreach ( $restricted_categories as $cat_key => $restricted_cat ) {
                        $restricted_cat_ids[] = $cat_key;
                    }
                }
            }

            $restricted_args = array(
                'post_type'      => array( 'product', 'product_variation' ),
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'post__in'       => $ids,
                'orderby'        => 'post__in',
                'meta_query'     => array(
                    array(
                        'key'     => WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER,
                        'value'   => array( $wholesale_role, 'all' ),
                        'compare' => 'IN',
                    ),
                ),
                'tax_query'      => empty( $restricted_cat_ids ) ? array() : array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => array_map( 'intval', $restricted_cat_ids ),
                        'operator' => 'NOT IN',
                    ),
                ),
            );

            if ( 'yes' === $show_wholesale_products_to_wholesale_users ) {
                if ( empty( $wholesale_role ) ) {
                    // Non-wholesale user should not see any products when this setting is enabled.
                    return array();
                } elseif ( ! $has_wholesale_discount ) {
                    $restricted_args['meta_query'][] = array(
                        'relation' => 'OR',
                        array(
                            'key'     => $wholesale_role . '_have_wholesale_price',
                            'value'   => 'yes',
                            'compare' => '=',
                        ),
                        array(
                            'key'     => $wholesale_role . '_wholesale_price',
                            'value'   => 0,
                            'compare' => '>',
                            'type'    => 'NUMERIC',
                        ),
                    );
                }
            }

            $restricted_query = new WP_Query( $restricted_args );
            return empty( $restricted_query->posts ) ? array() : $restricted_query->posts;
        }

        /**
         * Filter inter sells products ( cross-sells, up-sells ).
         *
         * @since 1.7.3
         * @since 1.12.8 Refactor codebase for effeciency and maintainability.
         * @since 1.16.0 Refactor code base to get wholesale discount wholesale role level from 'WWPP_Wholesale_Price_Wholesale_Role' model.
         * @since 1.23.2 Fixes the issue when Shop page display is set to Show categories it will print an error or time out. Tested on 1,375 categories and 12,000 products.
         *               Git rid of using get_meta that checks on individual product ids if its wholesale, this is the cause of the error.
         *               Fixed it by setting a non-persistent cache of wholesale product ids then compare the product id if it really is a wholesale product.
         *
         * @since 1.23.4 Refactor code: Make sure visibility is addressed incase the products are restricted to speicifc wholesale users.
         *
         * @param array      $product_ids Arrays of product ids.
         * @param WC_Product $product     Product object.
         *
         * @return array Filtered array of product ids.
         */
        public function filter_cross_and_up_sell_products( $product_ids, $product ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
            // Check if user is not an admin, else we don't want to restrict admins in any way.
            if ( ! current_user_can( 'manage_options' ) && ! empty( $product_ids ) ) {
                $user_id               = get_current_user_id();
                $user_wholesale_role   = $this->_get_current_user_wholesale_role();
                $override_per_user     = '';
                $user_general_discount = '';

                if ( $user_id ) {
                    $override_per_user     = get_user_meta( $user_id, 'wwpp_override_wholesale_discount', true );
                    $user_general_discount = $this->_wwpp_wholesale_price_wholesale_role->get_user_wholesale_role_level_discount( $user_id, $user_wholesale_role );
                }

                // If override discount per user is set.
                if ( 'yes' === $override_per_user ) {
                    $wholesale_role_discount = get_user_meta( $user_id, 'wwpp_wholesale_discount', true );
                    if ( is_numeric( $wholesale_role_discount ) ) {
                        return $this->visibility_filter_upsell_crossell_ids( $user_wholesale_role, $product_ids, true );
                    } else {
                        return $this->visibility_filter_upsell_crossell_ids( $user_wholesale_role, $product_ids, false );
                    }
                } elseif ( ! empty( $user_general_discount['discount'] ) ) {
                    // If general discount is set.
                    return $this->visibility_filter_upsell_crossell_ids( $user_wholesale_role, $product_ids, true );
                } else {
                    return $this->visibility_filter_upsell_crossell_ids( $user_wholesale_role, $product_ids, false );
                }
            } else {
                return $product_ids;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Product Category Items Count
        |--------------------------------------------------------------------------
         */

        /**
         * Filter product category product items count.
         *
         * @since 1.7.3
         * @since 1.14.0 Refactor codebase and move to its proper model.
         * @since 1.23.4 Remove using filter_cross_and_up_sell_products function to count how many wholesale products. Revert changes made in filter_cross_and_up_sell_products function which fixes the load time issue.
         *               Perform a query to count the wholesale products under this category,
         *               Only if Only Show Wholesale Products is enabled and override per user and the general discount is not set for the current user.
         *
         * @access public
         *
         * @param string $count_markup Category product count html markup.
         * @param object $category     Category object.
         * @return string Filtered category product count html markup.
         */
        public function filter_product_category_post_count( $count_markup, $category ) {

            global $wc_wholesale_prices_premium;

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();
            $wholesale_role      = isset( $user_wholesale_role[0] ) ? $user_wholesale_role[0] : '';

            if ( ! empty( $wholesale_role ) && get_option( 'wwpp_settings_hide_product_categories_product_count', false ) === 'yes' ) {
                return '';
            }

            $product_ids = array();
            $products    = WWPP_WPDB_Helper::get_products_by_category( $category->term_id ); // WP_Post.

            foreach ( $products as $product ) {
                $product_ids[] = $product->ID;
            }

            if ( ! empty( $wholesale_role ) ) {
                $restricted_cat_ids = $wc_wholesale_prices_premium->wwpp_query->_get_restricted_product_cat_ids_for_wholesale_user( $wholesale_role );
            } else {
                $restricted_cat_ids = get_option( WWPP_OPTION_PRODUCT_CAT_WHOLESALE_ROLE_FILTER, array() );
            }

            $args = array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'post__in'       => $product_ids,
                'meta_query'     => array(
                    array(
                        'key'     => WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER,
                        'value'   => array( $wholesale_role, 'all' ),
                        'compare' => 'IN',
                    ),
                ),
                'tax_query'      => empty( $restricted_cat_ids ) ? array() : array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => array_map( 'intval', $restricted_cat_ids ),
                        'operator' => 'NOT IN',
                    ),
                ),
            );

            if ( ! empty( $user_wholesale_role ) &&
                'yes' === get_option( 'wwpp_settings_only_show_wholesale_products_to_wholesale_users' ) &&
                ! WWPP_Helper_Functions::_wholesale_user_have_override_per_user_discount( $user_wholesale_role ) &&
                ! WWPP_Helper_Functions::_wholesale_user_have_general_role_discount( $wholesale_role ) ) {

                $args['meta_query'][] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => $wholesale_role . '_have_wholesale_price',
                        'value'   => 'yes',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => $wholesale_role . '_wholesale_price',
                        'value'   => 0,
                        'compare' => '>',
                        'type'    => 'NUMERIC',
                    ),
                );

            }

            $wholesale_query = new WP_Query( $args );

            return ' <mark class="count">(' . $wholesale_query->post_count . ')</mark>';
        }

        /**
         * Display wholesale product visibility field in quick edit. Hooked into 'wwp_after_quick_edit_wholesale_price_fields'.
         *
         * @since 1.14.4
         * @access public
         *
         * @param Array $all_wholesale_roles    list of wholesale roles.
         */
        public function quick_edit_display_product_visibility_field( $all_wholesale_roles ) { // phpcs:ignore.

            $all_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

            ?>
            <div class="quick_edit_product_visibility_field" style="float: none; clear: both; display: block;">
                <div style="height: 1px;"></div>
                <!--To Prevent Heading From Bumping Up-->
                <h4><?php esc_html_e( 'Restrict To Wholesale Roles', 'woocommerce-wholesale-prices-premium' ); ?></h4>
                <select style="width: 100%;"
                    data-placeholder="<?php esc_attr_e( 'Choose wholesale users...', 'woocommerce-wholesale-prices-premium' ); ?>"
                    name="wholesale-visibility-select[]" id="wholesale-visibility-select" multiple>

                    <?php foreach ( $all_registered_wholesale_roles as $role_key => $role ) : ?>
                    <option value="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( $role['roleName'] ); ?></option>
                    <?php endforeach; ?>

                </select>
                <!--#wholesale-visibility-select-->
            </div>
            <?php
        }

        /**
         * Add the product visibility data on the product listing column so it can be used to populate the
         * current values of the quick edit fields via javascript.
         *
         * @since 1.14.4
         * @access public
         *
         * @param Array $all_wholesale_roles   list of wholesale roles.
         * @param int   $product_id            Product ID.
         */
        public function add_product_visibility_data_to_product_listing_column( $all_wholesale_roles, $product_id ) {

            // Get product object.
            $product = wc_get_product( $product_id );

            $product_wholesale_role_filter = WWP_Helper_Functions::get_formatted_meta_data( $product, WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER );

            if ( ! is_array( $product_wholesale_role_filter ) ) {
                $product_wholesale_role_filter = array();
            }

            ?>

            <div class="wholesale_product_visibility_data" data-selected_roles='<?php echo wp_json_encode( $product_wholesale_role_filter ); ?>'></div>
            <?php
        }

        /**
         * Save wholesale custom fields on the quick edit option.
         *
         * @since 1.14.4
         * @access public
         *
         * @param WC_Product $product               Product object.
         * @param int        $product_id            Product ID.
         */
        public function save_product_visibility_on_quick_edit_screen( $product, $product_id ) {

            // Get product object.
            $product_obj = wc_get_product( $product_id );

            if ( ! is_a( $product_obj, 'WC_Product' ) ) {
                return;
            }

            // Because we are adding post meta via add_meta_data
            // We make sure to delete old post meta so the meta won't contains duplicate values.
            $product_obj->delete_meta_data( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER );

            if ( isset( $_POST['wholesale-visibility-select'] ) && is_array( $_POST['wholesale-visibility-select'] ) && ! empty( $_POST['wholesale-visibility-select'] ) ) { // phpcs:ignore.

                foreach ( $_POST['wholesale-visibility-select'] as $wholesaleRole ) { // phpcs:ignore.
                    $product_obj->add_meta_data( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER, $wholesaleRole );
                }
            } else {
                $product_obj->add_meta_data( WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER, 'all' );
            }

            // Save the product.
            $product_obj->save();
        }

        /*
        |--------------------------------------------------------------------------
        | Product Category Wholesale Role Filter
        |--------------------------------------------------------------------------
         */

        /**
         * Filter 'get_terms' of 'product_cat' and pass it through the wholesale role product category filter.
         * This feature will show non-restricted category to regular users. If restricted to wholesale role then don't show on regular users.
         * For wholesale users, only show categories that don't have restrictions or restricted only to show to them.
         *
         * @since 1.16.0
         * @access public
         *
         * @param array  $terms      Array of terms object.
         * @param array  $taxonomy   Array of list of taxonomy involve in this current 'get_term' query.
         * @param object $query_vars Query vars object.
         * @param object $term_query Term query object.
         * @return array Filtered array of terms object.
         */
        public function filter_product_cat_by_wholesale_role( $terms, $taxonomy, $query_vars, $term_query ) { // phpcs:ignore.

            if ( ! is_admin() && ! current_user_can( 'manage_options' ) && ! empty( $terms ) && isset( $terms[0] ) && is_object( $terms[0] ) && property_exists( $terms[0], 'taxonomy' ) && 'product_cat' === $terms[0]->taxonomy ) {

                if ( ! empty( $this->_product_cat_wholesale_role_filter ) ) {

                    $user_wholesale_role = $this->_get_current_user_wholesale_role();
                    $filtered_terms      = array();

                    if ( empty( $user_wholesale_role ) ) {
                        // Non wholesale user.
                        foreach ( $terms as $t ) {
                            if ( ! array_key_exists( $t->term_id, $this->_product_cat_wholesale_role_filter ) ) {
                                $filtered_terms[] = $t;
                            }
                        }
                    } else {
                        // Wholesale user.
                        $restricted_term_ids = array();

                        foreach ( $this->_product_cat_wholesale_role_filter as $term_id => $restricted_wholesale_roles ) {
                            if ( ! in_array( $user_wholesale_role, $restricted_wholesale_roles, true ) ) {
                                $restricted_term_ids[] = $term_id;
                            }
                        }

                        foreach ( $terms as $t ) {
                            if ( ! in_array( $t->term_id, $restricted_term_ids, true ) ) {
                                $filtered_terms[] = $t;
                            }
                        }
                    }

                    $terms = $filtered_terms;

                }
            }

            return $terms;
        }

        /*
        |--------------------------------------------------------------------------
        | Product Meta Wholesale Visibility Export and Import
        |--------------------------------------------------------------------------
         */

        /**
         * Fix for multiple wwpp_product_wholesale_visibility_filter meta EXPORT
         *
         * @since 1.17
         * @access public
         *
         * @param mixed  $value      Mixed value.
         * @param object $meta       WC_Meta_Data Object.
         * @param object $product    WC_Product_Simple | WC_Product_Variable object. etc.
         * @param array  $row        Array of exported product data.
         * @return mixed String|Int|Object
         */
        public function wc_export_meta_value_filter( $value, $meta, $product, $row ) { // phpcs:ignore.

            if ( 'wwpp_product_wholesale_visibility_filter' === $meta->key ) {

                $visibility_filter = WWP_Helper_Functions::get_formatted_meta_data( $product, 'wwpp_product_wholesale_visibility_filter' );
                $visibility_filter = array_unique( $visibility_filter ); // remove duplicate visibility value.

                // If more than 1 role then concatenate them with comma in 1 string.
                if ( count( $visibility_filter ) >= 2 ) {
                    return implode( ',', $visibility_filter );
                }
            }

            return $value;
        }

        /**
         * Fix for multiple wwpp_product_wholesale_visibility_filter meta IMPORT
         *
         * @since 1.17
         * @access public
         *
         * @param object $product    WC_Product_Simple | WC_Product_Variable object. etc.
         * @param array  $data       Array of imported product data.
         */
        public function wc_import_product( $product, $data ) { // phpcs:ignore.

            if ( ! is_a( $product, 'WC_Product' ) ) {
                return;
            }

            $visibility_filters = $product->get_meta( 'wwpp_product_wholesale_visibility_filter', true );

            if ( '' !== $visibility_filters && false !== strpos( $visibility_filters, ',' ) ) {

                $visibility_filters = explode( ',', $visibility_filters );

                // Remove wwpp_product_wholesale_visibility_filter meta.
                $product->delete_meta_data( 'wwpp_product_wholesale_visibility_filter' );

                // Re-add wwpp_product_wholesale_visibility_filter metas in separate row.
                foreach ( $visibility_filters as $wholesale_role ) {
                    $product->add_meta_data( 'wwpp_product_wholesale_visibility_filter', sanitize_text_field( $wholesale_role ) );
                }

                // Save the product.
                $product->save();
            }
        }

        /**
         * Insert <wholesale_role>_variations_with_wholesale_price on parent variable meta IMPORT if variation has wholesale price (Product Level).
         * This will only work for product level wholesale price.
         *
         * @since 1.24.7
         * @access public
         *
         * @param object $product    WC_Product_Simple | WC_Product_Variable object. etc.
         * @param array  $data       Array of imported product data.
         */
        public function wc_import_product_set_variations_with_wholesale_price_meta( $product, $data ) { // phpcs:ignore.

            $all_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

            if ( 'variation' === $product->get_type() ) {

                foreach ( $all_registered_wholesale_roles as $wholesale_role => $role ) {
                    $this->set_variations_with_wholesale_price_meta( $product->get_id(), $product->get_parent_id(), $wholesale_role );
                }
            }
        }

        /**
         * Helper function that insert <wholesale_role>_variations_with_wholesale_price if variation has wholesale price (Product Level).
         * This will only work for product level wholesale price.
         *
         * @since 1.26.1
         * @access public
         *
         * @param int    $variation_id       The variation ID.
         * @param int    $parent_id          The variable ID.
         * @param string $wholesale_role     The wholesale role.
         */
        public function set_variations_with_wholesale_price_meta( $variation_id, $parent_id, $wholesale_role ) {

            // Variation product object.
            $variation_product = wc_get_product( $variation_id );

            // Get product object.
            $product = wc_get_product( $parent_id );

            if ( ! is_a( $product, 'WC_Product' ) || ! is_a( $variation_product, 'WC_Product' ) ) {
                return;
            }

            $wholesale_price                = $variation_product->get_meta( $wholesale_role . '_wholesale_price', true );
            $variation_with_wholesale_price = WWP_Helper_Functions::get_formatted_meta_data( $product, $wholesale_role . '_variations_with_wholesale_price' );

            if (
                (
                    empty( $variation_with_wholesale_price ) &&
                    ! empty( $wholesale_price ) && $wholesale_price > 0
                ) ||
                (
                    ! empty( $variation_with_wholesale_price ) &&
                    ! in_array( $variation_id, $variation_with_wholesale_price, true ) &&
                    ! empty( $wholesale_price ) && $wholesale_price > 0
                )
            ) {
                $product->add_meta_data( $wholesale_role . '_variations_with_wholesale_price', $variation_id );
                $product->update_meta_data( $wholesale_role . '_have_wholesale_price', 'yes' );
            }

            // Delete Variable postmeta _wholesale_price since this is not needed in variable level and can cause issue.
            $wholesale_price = $product->get_meta( $wholesale_role . '_wholesale_price', true );
            if ( ! empty( $wholesale_price ) ) {
                $product->delete_meta_data( $wholesale_role . '_wholesale_price' );
            }

            // Save the product.
            $product->save();
        }

        /**
         * On page load, check if parent category have restrictions it has sub-categories then add them to restrictions too (Fix for WWPP-728).
         * Moved the filter get_terms from function run() to here ( Real fix for WWPP-706 ).
         *
         * @since 1.21
         * @access public
         */
        public function include_sub_categories_from_restriction() {

            // Filter 'product_cat' get_terms query on page load.
            add_filter( 'get_terms', array( $this, 'filter_product_cat_by_wholesale_role' ), 99, 4 );

            if ( ! empty( $this->_product_cat_wholesale_role_filter ) ) {

                foreach ( $this->_product_cat_wholesale_role_filter as $term_id => $restricted_wholesale_roles ) {

                    $children = get_term_children( $term_id, 'product_cat' );

                    if ( ! empty( $children ) ) {

                        // Include sub categories.
                        foreach ( $children as $child ) {
                            $this->_product_cat_wholesale_role_filter[ $child ] = $restricted_wholesale_roles;
                        }
                    }
                }
            }
        }

        /**
         * When using WC Shortcodes product_add_to_cart, product_add_to_cart_url and product_page, only display it to respective users.
         *
         * @since 1.23.5
         * @access public
         *
         * @param array  $atts       Shortcode attributes.
         * @param string $content    Content.
         * @param string $tag        Current shortcode tag name.
         *
         * @return string
         */
        public function wc_shortcodes_visibility_checks( $atts, $content, $tag ) {
            $return_string = '';
            $atts          = shortcode_atts(
                array(
                    'id'  => '',
                    'sku' => '',
                ),
                $atts,
                'product_add_to_cart'
            );

            if ( ! empty( $atts['id'] ) ) {
                $product_data = get_post( $atts['id'] );
                unset( $atts['sku'] );
            } elseif ( ! empty( $atts['sku'] ) ) {
                $product_id   = wc_get_product_id_by_sku( $atts['sku'] );
                $product_data = get_post( $product_id );
                unset( $atts['id'] );
            } else {
                $return_string = '';
            }
            $product = is_object( $product_data ) && in_array( $product_data->post_type, array( 'product', 'product_variation' ), true ) ? wc_setup_product_data( $product_data ) : false;

            if ( ! $product ) {
                $return_string = '';
            }

            // Returns the id back if visible for this current user.
            $product_id = $this->filter_cross_and_up_sell_products( array( WWP_Helper_Functions::wwp_get_product_id( $product ) ), $product );

            if ( empty( $product_id ) ) {
                $return_string = '';
            }

            switch ( $tag ) {
                case 'add_to_cart':
                    $return_string = WC_Shortcodes::product_add_to_cart( $atts );
                    break;
                case 'add_to_cart_url':
                    $return_string = WC_Shortcodes::product_add_to_cart_url( $atts );
                    break;
                case 'product_page':
                    $return_string = WC_Shortcodes::product_page( $atts );
                    break;
                default:
                    $return_string = '';
            }

            return $return_string;
        }

        /**
         * Don't export not needed meta
         *
         * @since 1.24.7
         * @access public
         *
         * @param array      $excluded Excluded meta keys.
         * @param WC_Product $product Product object.
         *
         * @return array
         */
        public function exclude_meta_keys_from_export( $excluded, $product ) {

            $all_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

            switch ( $product->get_type() ) {
                case 'variable':
                    foreach ( $all_registered_wholesale_roles as $role_key => $role ) {
                        $excluded[] = $role_key . '_variations_with_wholesale_price';
                    }
            }

            return $excluded;
        }

        /*
        |--------------------------------------------------------------------------
        | Execute Model
        |--------------------------------------------------------------------------
         */

        /**
         * Execute model.
         *
         * @since 1.12.8
         * @since 1.13.0 Move the logic of only showing wholesale products to wholesale users here
         * @since 1.23.4 Update crosssell and upsell hook prefix to 'woocommerce_product_get_' from 'woocommerce_product_'
         * @access public
         */
        public function run() {

            // Wholesale role visibility filter on single product admin page.
            add_action( 'post_submitbox_misc_actions', array( $this, 'add_product_wholesale_role_visibility_filter_fields' ), 100 );
            add_action( 'save_post', array( $this, 'save_product_wholesale_role_visibility_filter' ), 10, 1 );
            add_action( 'template_redirect', array( $this, 'check_product_wholesale_role_visibility_filter' ), 10 );

            // Only show wholesale products to wholesale users.
            add_filter( 'template_redirect', array( $this, 'only_show_wholesale_products_to_wholesale_users' ), 100 );

            // Filter cross and up sell products.
            add_filter( 'woocommerce_product_get_crosssell_ids', array( $this, 'filter_cross_and_up_sell_products' ), 10, 2 );
            add_filter( 'woocommerce_product_get_upsell_ids', array( $this, 'filter_cross_and_up_sell_products' ), 10, 2 );
            add_filter( 'woocommerce_product_get_children', array( $this, 'filter_cross_and_up_sell_products' ), 10, 2 );
            add_filter( 'woocommerce_cart_crosssell_ids', array( $this, 'filter_cross_and_up_sell_products' ), 10, 2 );

            // Filter product category product items count.
            add_filter( 'woocommerce_subcategory_count_html', array( $this, 'filter_product_category_post_count' ), 10, 2 );

            // Quick edit support.
            add_action( 'wwp_after_quick_edit_wholesale_price_fields', array( $this, 'quick_edit_display_product_visibility_field' ), 10, 1 );
            add_action( 'wwp_add_wholesale_price_fields_data_to_product_listing_column', array( $this, 'add_product_visibility_data_to_product_listing_column' ), 10, 2 );
            add_action( 'wwp_save_wholesale_price_fields_on_quick_edit_screen', array( $this, 'save_product_visibility_on_quick_edit_screen' ), 10, 2 );

            // Properly import/export wwpp_product_wholesale_visibility_filter meta.
            add_filter( 'woocommerce_product_export_meta_value', array( $this, 'wc_export_meta_value_filter' ), 10, 4 );
            add_action( 'woocommerce_product_import_inserted_product_object', array( $this, 'wc_import_product' ), 10, 2 );
            add_action( 'woocommerce_product_import_inserted_product_object', array( $this, 'wc_import_product_set_variations_with_wholesale_price_meta' ), 10, 2 );

            // Exclude meta to export.
            add_filter( 'woocommerce_product_export_skip_meta_keys', array( $this, 'exclude_meta_keys_from_export' ), 10, 2 );

            // If parent is restricted then add restriction to sub categories too.
            add_action( 'template_redirect', array( $this, 'include_sub_categories_from_restriction' ) );

            // Filter products from WC Shortcodes.
            add_action(
                'init',
                function () {

                // Make sure WooCommerce Class Exists and WooCommerce Plugin is installed and activated to avoid PHP Fatal error:  Uncaught Error: Class 'WC_Shortcodes' fix for issue #381.

                if ( class_exists( 'WooCommerce' ) ) {

                    // Remove shortcodes.
                    remove_shortcode( 'add_to_cart', array( new WC_Shortcodes(), 'product_add_to_cart' ) );
                    remove_shortcode( 'add_to_cart_url', array( new WC_Shortcodes(), 'product_add_to_cart_url' ) );
                    remove_shortcode( 'product_page', array( new WC_Shortcodes(), 'product_page' ) );

                }

                // Add shortcode for custom product check.
                add_shortcode( 'add_to_cart_url', array( $this, 'wc_shortcodes_visibility_checks' ) );
                add_shortcode( 'add_to_cart', array( $this, 'wc_shortcodes_visibility_checks' ) );
                add_shortcode( 'product_page', array( $this, 'wc_shortcodes_visibility_checks' ) );
                },
                20
            );
        }
    }

}
