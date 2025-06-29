<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_REST_Wholesale_Products_V1_Controller' ) ) {

    /**
     * Model that houses the logic of WWPP integration with WC API WPP Wholesale Products.
     *
     * @since 1.18
     */
    class WWPP_REST_Wholesale_Products_V1_Controller extends WWP_REST_Wholesale_Products_V1_Controller {

        /**
         * WWPP_REST_Wholesale_Products_V1_Controller constructor.
         *
         * @since  1.18
         * @access public
         */
        public function __construct() {

            // Filter the query arguments of the request.
            add_filter(
                "wwp_rest_wholesale_{$this->post_type}_meta_query",
                array( $this, 'rest_meta_query_args' ),
                10,
                4
            );

            // Filter the query arguments of the request.
            add_filter(
                "wwp_rest_wholesale_{$this->post_type}_tax_query",
                array( $this, 'rest_tax_query_args' ),
                10,
                4
            );

            // Disregard category/general/user wholesale discounts.
            add_filter(
                "wwp_rest_wholesale_{$this->post_type}_query_args",
                array( $this, 'rest_disregard_discounts' ),
                10,
                3
            );

            // Allow backorders - Items.
            add_filter(
                "wwp_rest_wholesale_{$this->post_type}_query_args",
                array( $this, 'rest_allow_backorders_check' ),
                10,
                4
            );

            // Include WWPP wholesale data into the response.
            add_filter( 'wwp_meta_data', array( $this, 'get_wwpp_meta_data' ), 10, 3 );

            // Include Wholesale sale data to WWPP wholesale data into the response.
            add_filter( 'wwp_meta_data', array( $this, 'get_wwpp_wholesale_sale_meta_data' ), 11, 3 );

            // Fires after a single object is created or updated via the REST API.
            add_action(
                "woocommerce_rest_insert_{$this->post_type}_object",
                array( $this, 'create_update_wholesale_product' ),
                10,
                3
            );

            // Only show wholesale products to wholesale users.
            add_filter(
                'wwp_only_show_wholesale_products_to_wholesale_users',
                array( $this, 'only_show_wholesale_products_to_wholesale_users' ),
                10,
                2
            );

            // Check if general discount is set.
            add_filter( 'wwp_general_discount_is_set', array( $this, 'is_general_discount_set' ), 10, 2 );

            // Set necessary hooks for getting wholesale data.
            add_action( 'wwp_before_adding_wholesale_data_on_response', array( $this, 'set_hooks' ), 1, 3 );

            // Add a restriction error message.
            add_action(
                "wwp_before_get_item_{$this->post_type}_extra_check",
                array( $this, 'before_product_get_item_extra_check' ),
                10,
                2
            );
        }

        /**
         * "Disregard Product Category Level Wholesale Discount" and "Disregard Wholesale Role Level Wholesale Discount" features
         *
         * @param array           $args_copy Request args copy.
         * @param array           $args      Request args orig.
         * @param WP_REST_Request $request   Request data.
         *
         * @return array
         * @since  1.27
         * @access public
         */
        public function rest_disregard_discounts( $args_copy, $args, $request ) {

            $wholesale_role = isset( $request['wholesale_role'] ) ? sanitize_text_field(
                $request['wholesale_role']
            ) : '';

            if ( empty( $wholesale_role ) ) {
                return $args_copy;
            }

            global $wc_wholesale_prices_premium;

            $disregard_products = $wc_wholesale_prices_premium->wwpp_query->disregard_wholesale_products(
                $wholesale_role
            );

            $args_copy['post__not_in'] = array_merge( $args_copy['post__not_in'], $disregard_products );

            return apply_filters(
                'wwp_rest_wholesale_' . $this->post_type . '_disregard_discounts',
                $args_copy,
                $args,
                $request
            );
        }

        /**
         * "Always Allow Backorders" feature.
         *
         * @param array           $args_copy Request args copy.
         * @param array           $args      Request args orig.
         * @param WP_REST_Request $request   Request data.
         *
         * @return array
         * @since  1.27
         * @access public
         */
        public function rest_allow_backorders_check( $args_copy, $args, $request ) {

            $this->rest_allow_backorders( $request );

            return $args_copy;
        }

        /**
         * Override backorders if "Always Allow Backorders" feature is enabled.
         *
         * @param WP_REST_Request $request Request data.
         * @param string          $post_type Post type.
         *
         * @since  1.27
         * @access public
         */
        public function rest_allow_backorders( $request, $post_type = 'product' ) {

            global $wc_wholesale_prices;

            $wholesale_role  = isset( $request['wholesale_role'] ) ? sanitize_text_field(
                $request['wholesale_role']
            ) : '';
            $wholesale_roles = $wc_wholesale_prices->wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

            add_filter( 'woocommerce_product_get_backorders', array( $this, 'maybe_set_backorders_to_notify' ), 10, 2 );
            add_filter(
                'woocommerce_product_variation_get_backorders',
                array( $this, 'maybe_set_backorders_to_notify' ),
                10,
                2
            );

            if ( ! empty( $wholesale_role ) && in_array( $wholesale_role, array_keys( $wholesale_roles ), true ) &&
                'yes' === get_option( 'wwpp_settings_always_allow_backorders_to_wholesale_users', false ) ) {

                // Force product to be in stock if we are overriding backorders.
                add_filter(
                    'woocommerce_product_is_in_stock',
                    function () {

                        return true;
                    },
                    10,
                    0
                );

                if ( 'yes' === get_option( 'wwpp_settings_show_back_order_notice_wholesale_users', false ) ) {
                    // When "Show Backorders Notice When Allowed" option is enabled then set "backorders" to "notify".
                    add_filter(
                        'woocommerce_product_get_backorders',
                        function () {

                            return 'notify';
                        },
                        10,
                        0
                    );
                    add_filter(
                        'woocommerce_product_variation_get_backorders',
                        function () {

                            return 'notify';
                        },
                        10,
                        0
                    );

                }

                // Updates "backorders_allowed" property.
                add_filter(
                    'woocommerce_product_backorders_allowed',
                    function () {

                        return true;
                    },
                    10,
                    0
                );
                add_filter(
                    'woocommerce_product_variation_backorders_allowed',
                    function () {

                        return true;
                    },
                    10,
                    0
                );

                // Updates "backordered" property.
                add_filter(
                    'woocommerce_product_get_stock_status',
                    function ( $value ) {

                        if ( 'outofstock' === $value ) {
                            $value = 'onbackorder';
                        }

                        return $value;
                    },
                    10,
                    2
                );
                add_filter(
                    'woocommerce_product_variation_get_stock_status',
                    function ( $value ) {

                        if ( 'outofstock' === $value ) {
                            $value = 'onbackorder';
                        }

                        return $value;
                    },
                    10,
                    2
                );

            }
        }

        /**
         * Maybe set backorders to "notify" when "Show Backorders Notice When Allowed" option is enabled or for
         * products that has unmanaged stock but has stock status set to "onbackorder".
         *
         * @param string      $value Backorders value.
         * @param \WC_Product $product Product object.
         *
         * @return string
         */
        public function maybe_set_backorders_to_notify( $value, $product ) {

            /**
             * We pass 'edit' as context here to retrieve the original value of 'stock_status' property (skip applying filters).
             */
            $original_stock_status = $product->get_stock_status( 'edit' );
            if ( ! $product->managing_stock() && 'onbackorder' === $product->get_stock_status() && 'outofstock' !== $original_stock_status ) {
                $value = 'notify';
            }

            return $value;
        }

        /**
         * Wholesale product restriction/visibility.
         *
         * @param array           $meta_query Meta query.
         * @param string          $wholesale_role Wholesale role.
         * @param array           $args_copy Request args copy.
         * @param WP_REST_Request $request Request data.
         *
         * @since  1.27.10 Tweak api meta query, add check if meta_query variable is already defined.
         * @access public
         * @return array
         */
        public function rest_meta_query_args( $meta_query, $wholesale_role, $args_copy, $request ) {

            if ( ! empty( $meta_query ) ) {
                $meta_query = array(
                    $meta_query,
                    array(
                        'key'     => WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER,
                        'value'   => array( $wholesale_role, 'all' ),
                        'compare' => 'IN',
                    ),
                );
            } else {
                $meta_query = array(
                    array(
                        'key'     => WWPP_PRODUCT_WHOLESALE_VISIBILITY_FILTER,
                        'value'   => array( $wholesale_role, 'all' ),
                        'compare' => 'IN',
                    ),
                );
            }

            return apply_filters(
                "wwpp_rest_wholesale_{$this->post_type}_meta_query",
                $meta_query,
                $wholesale_role,
                $args_copy,
                $request
            );
        }

        /**
         * Category wholesale role filter/visibility.
         *
         * @param array           $tax_query Tax query.
         * @param string          $wholesale_role Wholesale role.
         * @param array           $args_copy Request args copy.
         * @param WP_REST_Request $request Request data.
         *
         * @since  1.27.10 Tweak api tax query, add check if filtered_term_ids variable is not empty
         * @access public
         *
         * @return array
         */
        public function rest_tax_query_args( $tax_query, $wholesale_role, $args_copy, $request ) {

            global $wc_wholesale_prices_premium;

            $filtered_term_ids = array();

            $product_cat_wholesale_role_filter = get_option( WWPP_OPTION_PRODUCT_CAT_WHOLESALE_ROLE_FILTER );
            if ( ! is_array( $product_cat_wholesale_role_filter ) ) {
                $product_cat_wholesale_role_filter = array();
            }

            if ( ! empty( $wholesale_role ) && ! empty( $product_cat_wholesale_role_filter ) ) {
                $filtered_term_ids = $wc_wholesale_prices_premium->wwpp_query->_get_restricted_product_cat_ids_for_wholesale_user(
                    $wholesale_role
                );
            } elseif ( empty( $wholesale_role ) && ! empty( $product_cat_wholesale_role_filter ) ) {
                $filtered_term_ids = array_keys( $product_cat_wholesale_role_filter );
            }

            if ( ! empty( $filtered_term_ids ) ) {
                $tax_query = array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => array_map( 'intval', $filtered_term_ids ),
                        'operator' => 'NOT IN',
                    ),
                );
            }

            return $tax_query;
        }

        /**
         * Custom method that check if there is a wholesale percentage discount set via the Category Discount options.
         *
         * @param string $wholesale_role The wholesale role key.
         * @param array  $category_ids   The category ids.
         *
         * @return bool|int
         * @since  1.20
         * @access public
         */
        public function has_wholesale_category_discount( $wholesale_role, $category_ids ) {

            global $wc_wholesale_prices;

            $registered_wholesale_roles = $wc_wholesale_prices->wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

            if ( ! empty( $category_ids ) ) {

                foreach ( $category_ids as $key => $category ) {

                    $category_wholesale_discount = get_option( 'taxonomy_' . $category['id'] );

                    // If wholesale role key is provided in the request.
                    if ( ! empty( $wholesale_role ) ) {

                        if ( ! empty( $category_wholesale_discount[ $wholesale_role . '_wholesale_discount' ] ) ) {
                            return true;
                        } else {
                            return false;
                        }
                    } else {

                        // If no rule key provided but have atleast 1 discount set in the category.
                        if ( ! empty( $registered_wholesale_roles ) ) {

                            foreach ( $registered_wholesale_roles as $role => $data ) {

                                if ( ! empty( $category_wholesale_discount[ $role . '_wholesale_discount' ] ) ) {
                                    return true;
                                }
                            }
                        }

                        return false;

                    }
                }
            }
        }

        /**
         * Custom method that check if the request coming from wholesale endpoint.
         *
         * @param array           $meta_data Meta data value.
         * @param WC_Product      $product Producct object.
         * @param WP_REST_Request $request Request parameters.
         *
         * @since 1.08
         * @access public
         *
         * @return array
         */
        public function get_wwpp_meta_data( $meta_data, $product, $request ) {

            global $wc_wholesale_prices;

            $registered_wholesale_roles = $wc_wholesale_prices->wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

            do_action( 'before_getting_meta_data', $product, $request );

            // Override Regular Price Suffix option.
            add_filter( 'woocommerce_get_price_suffix', array( $this, 'wwpp_regular_price_suffix_override' ), 10, 4 );

            $product_id = $product->get_id();

            // WWPP Meta Data.
            $meta = array(
                'wholesale_price',
                'wholesale_minimum_order_quantity',
                'wholesale_order_quantity_step',
                'wwpp_product_wholesale_visibility_filter',
                'variable_level_wholesale_minimum_order_quantity',
                'variable_level_wholesale_order_quantity_step',
            );

            // Fix undefined index.
            foreach ( $meta as $m ) {
                if ( ! isset( $meta_data[ $m ] ) ) {
                    $meta_data[ $m ] = array();
                }
            }

            $ignore_cat_level_discount  = $product->get_meta(
                'wwpp_ignore_cat_level_wholesale_discount',
                true
            );
            $ignore_role_level_discount = $product->get_meta(
                'wwpp_ignore_role_level_wholesale_discount',
                true
            );
            $product_visibility_filter  = WWP_Helper_Functions::get_formatted_meta_data(
                $product,
                'wwpp_product_wholesale_visibility_filter'
            );

            // "Disregard Product Category Level Wholesale Discount" Option.
            if ( ! empty( $ignore_cat_level_discount ) ) {
                $meta_data['wwpp_ignore_cat_level_wholesale_discount'] = $ignore_cat_level_discount;
            }

            // "Disregard Wholesale Role Level Wholesale Discount" Option.
            if ( ! empty( $ignore_role_level_discount ) ) {
                $meta_data['wwpp_ignore_role_level_wholesale_discount'] = $ignore_role_level_discount;
            }

            // "Restrict To Wholesale Roles" Option.
            if ( ! empty( $product_visibility_filter ) ) {
                $meta_data['wwpp_product_wholesale_visibility_filter'] = array_unique( $product_visibility_filter );
            }

            if ( isset( $request['wholesale_role'] ) && '' !== $request['wholesale_role'] ) {

                // Get wholesale price data.
                $wholesale_price_data = $wc_wholesale_prices->wwp_wholesale_prices->get_product_wholesale_price_on_shop_v3(
                    $product_id,
                    array( $request['wholesale_role'] )
                );

                // Return product level data.
                if ( $request->get_method() === 'POST' ) {

                    $enable_rule_mapping       = $product->get_meta(
                        'wwpp_post_meta_enable_quantity_discount_rule',
                        true
                    );
                    $qty_discount_rule_mapping = $product->get_meta(
                        'wwpp_post_meta_quantity_discount_rule_mapping',
                        true
                    );

                } else {

                    $enable_rule_mapping       = WWPP_API_Helpers::is_quantity_discount_rule_enabled(
                        $product_id,
                        $wholesale_price_data,
                        $product,
                        $request
                    );
                    $enable_rule_mapping       = true === $enable_rule_mapping ? 'yes' : 'no';
                    $qty_discount_rule_mapping = WWPP_API_Helpers::get_quantity_discount_mapping(
                        $product_id,
                        $wholesale_price_data,
                        $product,
                        $request
                    );

                }

                // Quantity Table Mapping.
                if ( ! empty( $qty_discount_rule_mapping ) && 'yes' === $enable_rule_mapping ) {
                    $qty_discount_rule_desc                           = WWPP_API_Helpers::get_quantity_discount_description(
                        $product_id,
                        $wholesale_price_data,
                        $product,
                        $request
                    );
                    $meta_data['wwpp_quantity_discount_rule_mapping'] = array(
                        'desc'    => $qty_discount_rule_desc,
                        'mapping' => $qty_discount_rule_mapping,
                    );
                }

                // "Product Quantity Based Wholesale Pricing" option.
                if ( $enable_rule_mapping ) {
                    $meta_data['wwpp_enable_quantity_discount_rule'] = $enable_rule_mapping;
                }
            }

            // Filter By Wholesale Role.
            foreach ( $registered_wholesale_roles as $role => $data ) {

                $wholesale_price    = $wc_wholesale_prices->wwp_wholesale_prices->get_product_wholesale_price_on_shop(
                    $product_id,
                    array( $role )
                );
                $wholesale_min_qty  = $product->get_meta( $role . '_wholesale_minimum_order_quantity', true );
                $wholesale_qty_step = $product->get_meta( $role . '_wholesale_order_quantity_step', true );

                if ( ! empty( $wholesale_price ) ) {
                    $meta_data['wholesale_price'] = array_merge(
                        $meta_data['wholesale_price'],
                        array( $role => $wholesale_price )
                    );
                }

                if ( ! empty( $wholesale_min_qty ) ) {
                    $meta_data['wholesale_minimum_order_quantity'] = array_merge(
                        $meta_data['wholesale_minimum_order_quantity'],
                        array( $role => $wholesale_min_qty )
                    );
                }

                if ( ! empty( $wholesale_qty_step ) ) {
                    $meta_data['wholesale_order_quantity_step'] = array_merge(
                        $meta_data['wholesale_order_quantity_step'],
                        array( $role => $wholesale_qty_step )
                    );
                }

                if ( $product->is_type( 'variable' ) ) {

                    $variable_order_qty = $product->get_meta(
                        $role . '_variable_level_wholesale_minimum_order_quantity',
                        true
                    );
                    $variable_qty_step  = $product->get_meta(
                        $role . '_variable_level_wholesale_order_quantity_step',
                        true
                    );

                    if ( ! empty( $variable_order_qty ) ) {
                        $meta_data['variable_level_wholesale_minimum_order_quantity'] = array_merge(
                            $meta_data['variable_level_wholesale_minimum_order_quantity'],
                            array( $role => $variable_order_qty )
                        );
                    }

                    if ( ! empty( $variable_qty_step ) ) {
                        $meta_data['variable_level_wholesale_order_quantity_step'] = array_merge(
                            $meta_data['variable_level_wholesale_order_quantity_step'],
                            array( $role => $variable_qty_step )
                        );
                    }
                }
            }

            return apply_filters( 'wwpp_meta_data', array_filter( $meta_data ), $product, $request );
        }

        /**
         * Include wholesale sale prices data to wholesale data
         *
         * @since  1.30.1
         * @access public
         *
         * @param array           $meta_data Meta data value.
         * @param WC_Product      $product   Producct object.
         * @param WP_REST_Request $request   Request parameters.
         *
         * @return array
         */
        public function get_wwpp_wholesale_sale_meta_data( $meta_data, $product, $request ) {
            global $wc_wholesale_prices;

            $registered_wholesale_roles = $wc_wholesale_prices->wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

            $product_id     = $product->get_id();
            $sale_price_arr = array();

            foreach ( $registered_wholesale_roles as $role => $data ) {

                $wholesale_sale_price_arr = WWPP_Wholesale_Prices::get_product_wholesale_sale_price( $product_id, array( $role ) );

                if ( null !== $wholesale_sale_price_arr ) {
                    if ( true === $wholesale_sale_price_arr['is_on_sale'] ) {
                        foreach ( $wholesale_sale_price_arr as $key => $value ) {
                            $array_key                    = str_replace( 'wholesale_sale_', '', $key );
                            $sale_price_arr[ $array_key ] = $value;
                        }
                        $meta_data['wholesale_sale_price'][ $role ] = $sale_price_arr;
                    }
                }
            }

            return $meta_data;
        }

        /**
         * Custom method that add or update wholesale data.
         * Fires after a single object is created or updated via the REST API.
         *
         * @param WC_Product      $product      The product object.
         * @param WP_REST_Request $request     Full details about the request.
         * @param Boolean         $create_product True is creating, False is updating.
         *
         * @since  1.18.0
         * @access public
         */
        public function create_update_wholesale_product( $product, $request, $create_product ) {

            global $wc_wholesale_prices;

            $registered_wholesale_roles = $wc_wholesale_prices->wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

            // Import variables into the current symbol table from an array.
            extract( $request->get_params() ); // phpcs:ignore.

            // Get product type.
            $product_type = WWP_Helper_Functions::wwp_get_product_type( $product );

            // The product id.
            $product_id = $product->get_id();

            // Check if wholesale role visibility filter is set.
            if ( isset( $wholesale_visibility_filter ) ) {

                // Update with new values.
                $product->delete_meta_data( 'wwpp_product_wholesale_visibility_filter' );

                // Multiple visibility role.
                if ( is_array( $wholesale_visibility_filter ) ) {

                    $wholesale_role_exist = false; // atleast 1 role exist to make this true.

                    $visibility_list = WWP_Helper_Functions::get_formatted_meta_data( $product, 'wwpp_product_wholesale_visibility_filter' );
                    foreach ( $wholesale_visibility_filter as $role ) {

                        // Validate if wholesale role exist.
                        if ( array_key_exists( $role, $registered_wholesale_roles ) && ! in_array( $role, $visibility_list, true ) ) {
                            $product->add_meta_data( 'wwpp_product_wholesale_visibility_filter', $role, false );
                            $wholesale_role_exist = true;
                        }
                    }

                    if ( false === $wholesale_role_exist ) {
                        $product->delete_meta_data( 'wwpp_product_wholesale_visibility_filter' );
                        $product->update_meta_data( 'wwpp_product_wholesale_visibility_filter', 'all' );
                    }
                } elseif ( array_key_exists( $wholesale_visibility_filter, $registered_wholesale_roles ) ) {
                    $product->update_meta_data( 'wwpp_product_wholesale_visibility_filter', $wholesale_visibility_filter );
                } else {
                    $product->update_meta_data( 'wwpp_product_wholesale_visibility_filter', 'all' );
                }
            } elseif ( $create_product ) {

                    $product->update_meta_data( 'wwpp_product_wholesale_visibility_filter', 'all' );
            }

            // Check if Disregard Product Category Level Wholesale Discount is set.
            if ( isset( $ignore_cat_level_wholesale_discount ) && ! in_array( $product_type, array( 'variation' ), true ) ) {

                if ( in_array( strtolower( $ignore_cat_level_wholesale_discount ), array( 'yes', 'no' ), true ) ) {
                    $product->update_meta_data( 'wwpp_ignore_cat_level_wholesale_discount', strtolower( $ignore_cat_level_wholesale_discount ) );
                }
            }

            // Check if Disregard Wholesale Role Level Wholesale Discount is set.
            if ( isset( $ignore_role_level_wholesale_discount ) && ! in_array( $product_type, array( 'variation' ), true ) ) {

                if ( in_array( strtolower( $ignore_role_level_wholesale_discount ), array( 'yes', 'no' ), true ) ) {
                    $product->update_meta_data( 'wwpp_ignore_role_level_wholesale_discount', strtolower( $ignore_role_level_wholesale_discount ) );
                }
            }

            // Check if wholesale price is set.
            if ( isset( $wholesale_price ) && ! in_array( $product_type, array( 'variable' ), true ) ) {

                // Multiple wholesale price is set.
                if ( is_array( $wholesale_price ) ) {

                    foreach ( $wholesale_price as $role => $price ) {

                        // Validate if wholesale role exist.
                        if ( is_numeric( $price ) && array_key_exists( $role, $registered_wholesale_roles ) ) {
                            $product->update_meta_data( $role . '_wholesale_price', $price );
                            $product->update_meta_data( $role . '_have_wholesale_price', 'yes' );

                        }

                        // If user updates the wholesale and if its empty still do update the meta.
                        if ( ! $create_product && empty( $price ) ) {
                            $product->update_meta_data( $role . '_wholesale_price', $price );
                        }
                    }
                }
            }

            // Check if wholesale minimum order quantity is set.
            if ( isset( $wholesale_minimum_order_quantity ) ) {

                // Multiple order quantity is set.
                if ( is_array( $wholesale_minimum_order_quantity ) ) {

                    foreach ( $wholesale_minimum_order_quantity as $role => $quantity ) {

                        // Validate if wholesale role exist.
                        if ( is_numeric( $quantity ) && array_key_exists( $role, $registered_wholesale_roles ) ) {

                            if ( 'variable' === $product_type ) {
                                $product->update_meta_data( $role . '_variable_level_wholesale_minimum_order_quantity', $quantity );
                            } else {
                                $product->update_meta_data( $role . '_wholesale_minimum_order_quantity', $quantity );
                            }
                        }

                        // If user updates the wholesale order quantity and if its empty still do update the meta.
                        if ( ! $create_product && empty( $quantity ) && 'variable' === $product_type ) {
                            $product->update_meta_data( $role . '_variable_level_wholesale_minimum_order_quantity', $quantity );
                        } elseif ( ! $create_product && empty( $quantity ) ) {
                            $product->update_meta_data( $role . '_wholesale_minimum_order_quantity', $quantity );
                        }
                    }
                }
            }

            // Check if wholesale order quantity step is set.
            if ( isset( $wholesale_order_quantity_step ) ) {

                // Multiple order quantity step is set.
                if ( is_array( $wholesale_order_quantity_step ) ) {

                    foreach ( $wholesale_order_quantity_step as $role => $qty_step ) {

                        // Validate if wholesale role exist.
                        if ( is_numeric( $qty_step ) && array_key_exists( $role, $registered_wholesale_roles ) ) {

                            if ( 'variable' === $product_type ) {
                                $product->update_meta_data( $role . '_variable_level_wholesale_order_quantity_step', $qty_step );
                            } else {
                                $product->update_meta_data( $role . '_wholesale_order_quantity_step', $qty_step );
                            }
                        }

                        // If user updates the wholesale order quantity step and if its empty still do update the meta.
                        if ( ! $create_product && empty( $qty_step ) && 'variable' === $product_type ) {
                            $product->update_meta_data( $role . '_variable_level_wholesale_order_quantity_step', $qty_step );
                        } elseif ( ! $create_product && empty( $qty_step ) ) {
                            $product->update_meta_data( $role . '_wholesale_order_quantity_step', $qty_step );
                        }
                    }
                }
            }

            // Check if Product Quantity Based Wholesale Pricing is set.
            if ( isset( $wholesale_quantity_discount_rule_mapping ) ) {

                if ( is_array( $wholesale_quantity_discount_rule_mapping ) ) {

                    // Validate the values.
                    foreach ( $wholesale_quantity_discount_rule_mapping as $key => $discount_rule ) {

                        // Remove rule if missing required values.
                        if ( ! isset( $discount_rule['wholesale_role'] ) ||
                            ! isset( $discount_rule['start_qty'] ) ||
                            ! isset( $discount_rule['price_type'] ) ||
                            ! isset( $discount_rule['wholesale_price'] ) ) {
                            unset( $wholesale_quantity_discount_rule_mapping[ $key ] );
                        }

                        // Check if rules have valid values.
                        if ( isset( $discount_rule['wholesale_role'] ) ) {

                            if ( ! array_key_exists( $discount_rule['wholesale_role'], $registered_wholesale_roles ) ) {
                                unset( $wholesale_quantity_discount_rule_mapping[ $key ] );
                            }
                        }

                        if ( isset( $discount_rule['start_qty'] ) || isset( $discount_rule['end_qty'] ) ) {

                            if ( ! is_numeric( $discount_rule['start_qty'] ) ||
                                ( is_numeric( $discount_rule['start_qty'] ) && $discount_rule['start_qty'] <= 0 ) ) {
                                unset( $wholesale_quantity_discount_rule_mapping[ $key ] );
                            }

                            if ( ( ! empty( $discount_rule['end_qty'] ) && ! is_numeric(
                                $discount_rule['end_qty']
                            ) ) ||
                                ( isset( $discount_rule['end_qty'] ) && is_numeric(
                                    $discount_rule['start_qty']
                                ) && is_numeric(
                                    $discount_rule['end_qty']
                                ) && $discount_rule['end_qty'] < $discount_rule['start_qty'] ) ) {
                                unset( $wholesale_quantity_discount_rule_mapping[ $key ] );
                            }
                        }

                        if ( isset( $discount_rule['price_type'] ) ) {

                            if ( ! in_array( $discount_rule['price_type'], array( 'fixed-price', 'percent-price' ), true ) ) {
                                unset( $wholesale_quantity_discount_rule_mapping[ $key ] );
                            }
                        }

                        if ( isset( $discount_rule['wholesale_price'] ) ) {

                            if ( ! is_numeric( $discount_rule['wholesale_price'] ) ||
                                ( is_numeric(
                                    $discount_rule['wholesale_price']
                                ) && $discount_rule['wholesale_price'] <= 0 ) ) {
                                unset( $wholesale_quantity_discount_rule_mapping[ $key ] );
                            }
                        }

                        if ( ! isset( $discount_rule['end_qty'] ) ) {
                            $wholesale_quantity_discount_rule_mapping[ $key ]['end_qty'] = '';
                        }
                    }

                    if ( ! empty( $wholesale_quantity_discount_rule_mapping ) ) {
                        $product->update_meta_data( 'wwpp_post_meta_enable_quantity_discount_rule', 'yes' );
                        $product->update_meta_data( 'wwpp_post_meta_quantity_discount_rule_mapping', $wholesale_quantity_discount_rule_mapping );
                    }
                } elseif ( empty( $wholesale_quantity_discount_rule_mapping ) ) {
                    $product->update_meta_data( 'wwpp_post_meta_enable_quantity_discount_rule', 'no' );
                    $product->update_meta_data( 'wwpp_post_meta_quantity_discount_rule_mapping', $wholesale_quantity_discount_rule_mapping );
                }
            }

            // Save the product.
            $product->save();
        }

        /**
         * Custom method that check if there's general discount set. If true will return all products.
         *
         * @param bool            $value  The value to return - true or false.
         * @param WP_REST_Request $request Full details about the request.
         *
         * @return bool
         * @since  1.25.0
         * @access public
         */
        public function is_general_discount_set( $value, $request ) {

            $wholesale_role = isset( $request['wholesale_role'] ) ? sanitize_text_field(
                $request['wholesale_role']
            ) : '';

            return WWPP_API_Helpers::has_wholesale_general_discount( $wholesale_role ) ? true : $value;
        }

        /**
         * Override the parent method.
         * Check if the request coming from wholesale endpoint
         *
         * @param WP_REST_Request $request Request data.
         *
         * @return WP_REST_Response|WP_Error
         * @since  1.20 Allow product creation if wholesale discount is set via the Category or General Discount Options
         * @access public
         * @since  1.18
         */
        public function create_item( $request ) {

            $wholesale_role = isset( $request['wholesale_role'] ) ? sanitize_text_field(
                $request['wholesale_role']
            ) : '';
            $categories     = isset( $request['categories'] ) ? $request['categories'] : array();

            // Category or General discount are not set.
            if ( ! $this->has_wholesale_category_discount(
                $wholesale_role,
                $categories
            ) && ! WWPP_API_Helpers::has_wholesale_general_discount( $wholesale_role ) ) {

                // Wholesale price is not set.
                if ( ! isset( $request['wholesale_price'] ) && ( isset( $request['type'] ) ) && 'variable' !== $request['type'] ) {
                    return new WP_Error(
                        'wholesale_rest_product_cannot_create',
                        __(
                            'Unable to create. Please provide "wholesale_price" in the request paremeter.',
                            'woocommerce-wholesale-prices-premium'
                        ),
                        array( 'status' => 400 )
                    );
                }
            }

            // Validate if all quantity mapping is valid. Only allowed price type is percentage.
            if ( isset( $request['type'] ) && 'variable' === $request['type'] ) {

                if ( ! empty( $request['wholesale_quantity_discount_rule_mapping'] ) ) {

                    $qty_mapping      = $request['wholesale_quantity_discount_rule_mapping'];
                    $qty_mapping_temp = $qty_mapping;

                    foreach ( $qty_mapping_temp as $key => $map ) {

                        if ( 'percent-price' !== $map['price_type'] ) {
                            unset( $qty_mapping[ $key ] );
                        }
                    }

                    if ( empty( $qty_mapping ) ) {
                        return new WP_Error(
                            'wholesale_rest_product_cannot_create',
                            __(
                                'Unable to create. Make sure the quantity discount rule mapping is using "percent-price" for price type.',
                                'woocommerce-wholesale-prices-premium'
                            ),
                            array( 'status' => 400 )
                        );
                    } else {
                        $request['wholesale_quantity_discount_rule_mapping'] = $qty_mapping;
                    }
                }
            }

            $response = parent::create_item( $request );

            return $response;
        }

        /**
         * Custom method that override the price suffix for regular prices viewed by wholesale customers.
         *
         * @param string     $price_suffix_html Price suffix markup.
         * @param WC_Product $product           WC Product instance.
         * @param string     $price             Product price.
         * @param int        $qty               Quantity.
         *
         * @return string Filtered price suffix markup.
         * @since  1.24.4
         *
         * @access public
         */
        public function wwpp_regular_price_suffix_override( $price_suffix_html, $product, $price = null, $qty = 1 ) {

            if ( empty( $price_suffix_html ) ) {
                return $price_suffix_html;
            }
            // Called on a variable product price range.

            if ( is_null( $price ) ) {
                $price = $product->get_price();
            }

            $wholesale_role = isset( $_REQUEST['wholesale_role'] ) ? sanitize_text_field( $_REQUEST['wholesale_role'] ) : ''; // phpcs:ignore.

            if ( ! empty( $wholesale_role ) ) {

                $price_suffix_option = get_option( 'wwpp_settings_override_price_suffix_regular_price' );
                if ( empty( $price_suffix_option ) ) {
                    $price_suffix_option = get_option( 'woocommerce_price_display_suffix' );
                }

                $wholesale_suffix_for_regular_price = $price_suffix_option;
                $has_match                          = false;

                if ( strpos( $wholesale_suffix_for_regular_price, '{price_including_tax}' ) !== false ) {

                    $product_price_incl_tax             = WWP_Helper_Functions::wwp_formatted_price(
                        WWP_Helper_Functions::wwp_get_price_including_tax(
                            $product,
                            array(
                                'qty'   => 1,
                                'price' => $price,
                            )
                        )
                    );
                    $wholesale_suffix_for_regular_price = str_replace(
                        '{price_including_tax}',
                        $product_price_incl_tax,
                        $wholesale_suffix_for_regular_price
                    );
                    $has_match                          = true;

                }

                if ( strpos( $wholesale_suffix_for_regular_price, '{price_excluding_tax}' ) !== false ) {

                    $product_price_excl_tax             = WWP_Helper_Functions::wwp_formatted_price(
                        WWP_Helper_Functions::wwp_get_price_excluding_tax(
                            $product,
                            array(
                                'qty'   => 1,
                                'price' => $price,
                            )
                        )
                    );
                    $wholesale_suffix_for_regular_price = str_replace(
                        '{price_excluding_tax}',
                        $product_price_excl_tax,
                        $wholesale_suffix_for_regular_price
                    );
                    $has_match                          = true;

                }

                return $has_match ? ' <small class="woocommerce-price-suffix wholesale-user-regular-price-suffix">' . $wholesale_suffix_for_regular_price . '</small>' : ' <small class="woocommerce-price-suffix">' . $price_suffix_option . '</small>';

            }

            return $price_suffix_html;
        }

        /**
         * Custom method that check whether "Only show.." option is enabled if true then only return wholesale products when performing request.
         *
         * @param bool            $value   "Only Show.." option.
         * @param WP_REST_Request $request Request data.
         *
         * @return bool
         * @since  1.25
         *
         * @access public
         */
        public function only_show_wholesale_products_to_wholesale_users( $value, $request ) {

            $wholesale_role = sanitize_text_field( $request['wholesale_role'] );

            $restrict_products = ! empty( $wholesale_role ) && get_option(
                'wwpp_settings_only_show_wholesale_products_to_wholesale_users'
            ) === 'yes' ? true : false;

            return apply_filters(
                'wwpp_products_controller_only_show_wholesale_products_to_wholesale_users',
                $restrict_products,
                $request
            );
        }

        /**
         * Custom method that set proper hooks and filter when getting wholesale data
         *
         * @param WP_REST_Response $response WP REST Response.
         * @param WC_Product       $product  WC Product Object.
         * @param WP_REST_Request  $request  WP REST Request.
         *
         * @return string|void
         * @since  1.25.2
         *
         * @access public
         */
        public function set_hooks( $response, $product, $request ) { // phpcs:ignore.

            if ( isset( $request['wholesale_role'] ) ) {

                // Properly set proper wholesale role.
                add_filter(
                    'wwpp_get_current_wholesale_role',
                    function ( $wholesale_role ) {

                        return isset( $_REQUEST['wholesale_role'] ) ? $_REQUEST['wholesale_role'] : $wholesale_role; // phpcs:ignore.
                    }
                );

                // Properly get "Per User Override Options".
                if ( isset( $request['uid'] ) && $request['uid'] > 0 ) {

                    // Under WWPP_Wholesale_Price_Variable_Product class.
                    add_filter(
                        'user_is_not_admin_check',
                        function ( $is_not_admin ) {

                            return ( isset( $_REQUEST['uid'] ) && $_REQUEST['uid'] > 0 ) ? ! user_can( $_REQUEST['uid'], 'manage_options' ) : $is_not_admin; // phpcs:ignore.
                        }
                    );

                    // For WWP variable cached prices.
                    add_filter(
                        'wwp_wholesale_price_current_user_id',
                        function ( $uid ) {

                            return ( isset( $_REQUEST['uid'] ) && $_REQUEST['uid'] > 0 ) ? $_REQUEST['uid'] : $uid; // phpcs:ignore.
                        },
                        10,
                        1
                    );

                    // For WWPP override per user options.
                    add_filter(
                        'wwpp_get_current_user_id',
                        function ( $uid ) {

                            return ( isset( $_REQUEST['uid'] ) && $_REQUEST['uid'] > 0 ) ? $_REQUEST['uid'] : $uid; // phpcs:ignore.
                        },
                        10,
                        1
                    );

                    // WWPP Tax display settings.
                    add_filter(
                        'option_woocommerce_tax_display_shop',
                        function ( $tax_display ) {

                            $wholesale_role = isset( $_REQUEST['wholesale_role'] ) ? $_REQUEST['wholesale_role'] : ''; // phpcs:ignore.

                            // User Level.
                            $tax_exemption = WWPP_Helper_Functions::is_user_wwpp_tax_exempted( ( isset( $_REQUEST['uid'] ) && $_REQUEST['uid'] > 0 ) ? $_REQUEST['uid'] : 0, $wholesale_role ); // phpcs:ignore.

                            if ( 'yes' === $tax_exemption ) {
                                return 'excl';
                            }

                            // WWPP Level.
                            $wholesale_tax_display_shop = get_option(
                                'wwpp_settings_incl_excl_tax_on_wholesale_price',
                                false
                            );
                            if ( in_array( $wholesale_tax_display_shop, array( 'incl', 'excl' ), true ) ) {
                                return $wholesale_tax_display_shop;
                            }

                            // WC Default.
                            return $tax_display;
                        },
                        10,
                        1
                    );

                }
            }

            // Override Regular Price Suffix option.
            add_filter( 'woocommerce_get_price_suffix', array( $this, 'wwpp_regular_price_suffix_override' ), 10, 4 );
        }

        /**
         * Override the parent method.
         * Wholesale product additional query params.
         *
         * @return array
         * @since 1.27
         */
        public function get_collection_params() {

            $wholesale_roles                             = array();
            $wholesale_minimum_order_quantity_properties = array();
            $wholesale_order_quantity_step_properties    = array();

            foreach ( $this->registered_wholesale_roles as $role => $data ) {
                $wholesale_roles[] = $role;

                // translators: %s: Role Name.
                $wholesale_minimum_order_quantity_properties_text     = sprintf( __( 'Wholesale minimum order quantity for %s', 'woocommerce-wholesale-prices-premium' ), $data['roleName'] );
                $wholesale_minimum_order_quantity_properties[ $role ] = array(
                    'description'       => $wholesale_minimum_order_quantity_properties_text,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                );

                // translators: %s: Role Name.
                $wholesale_order_quantity_step_properties_text     = sprintf( __( 'Wholesale order quantity step for %s', 'woocommerce-wholesale-prices-premium' ), $data['roleName'] );
                $wholesale_order_quantity_step_properties[ $role ] = array(
                    'description'       => $wholesale_order_quantity_step_properties_text,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                );
            }

            $params = array(
                'wholesale_visibility_filter'              => array(
                    'description' => __(
                        'The wholesale product visibility filter.',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    'type'        => 'string',
                    'enum'        => $wholesale_roles,
                ),
                'wholesale_minimum_order_quantity'         => array(
                    'description' => __(
                        'The wholesale product minimum order quantity.',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    'type'        => 'object',
                    'properties'  => $wholesale_minimum_order_quantity_properties,
                ),
                'wholesale_order_quantity_step'            => array(
                    'description' => __(
                        'The wholesale product order quantity step.',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    'type'        => 'object',
                    'properties'  => $wholesale_order_quantity_step_properties,
                ),
                'ignore_cat_level_wholesale_discount'      => array(
                    'default'     => 'no',
                    'enum'        => array( 'no', 'yes' ),
                    'description' => __(
                        'Ignore category wholesale discount.',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    'type'        => 'string',
                ),
                'ignore_role_level_wholesale_discount'     => array(
                    'default'     => 'no',
                    'enum'        => array( 'no', 'yes' ),
                    'description' => __( 'Ignore role wholesale discount.', 'woocommerce-wholesale-prices-premium' ),
                    'type'        => 'string',
                ),
                'wholesale_quantity_discount_rule_mapping' => array(
                    'description' => __(
                        'The wholesale product quantity discount rule mapping.',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    'type'        => 'array',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'wholesale_role'  => array(
                                'description' => __( 'Wholesale role.', 'woocommerce-wholesale-prices-premium' ),
                                'type'        => 'string',
                                'enum'        => $wholesale_roles,
                            ),
                            'start_qty'       => array(
                                'description'       => __( 'Start quantity.', 'woocommerce-wholesale-prices-premium' ),
                                'type'              => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                            ),
                            'end_qty'         => array(
                                'description'       => __( 'End quantity.', 'woocommerce-wholesale-prices-premium' ),
                                'type'              => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                            ),
                            'price_type'      => array(
                                'description' => __( 'Price type.', 'woocommerce-wholesale-prices-premium' ),
                                'type'        => 'string',
                                'enum'        => array( 'percent-price', 'fixed-price' ),
                            ),
                            'wholesale_price' => array(
                                'description'       => __( 'Wholesale price.', 'woocommerce-wholesale-prices-premium' ),
                                'type'              => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                            ),
                        ),
                    ),
                ),
            );

            $params = array_merge( parent::get_collection_params(), $params );

            return apply_filters( 'wwpp_rest_wholesale_product_get_collection_params', $params, $this );
        }

        /**
         * If the product is restricted then display an error message.
         *
         * @param array           $extra   Extra checks array. Contains is_valid and message.
         * @param WP_REST_Request $request WP REST Request Object.
         *
         * @return array
         * @since 1.27
         */
        public function before_product_get_item_extra_check( $extra, $request ) {

            // Always Allow Backorders feature - Per item data.
            $this->rest_allow_backorders( $request );

            $product_id                = $request['id'];
            $product                   = wc_get_product( $product_id );
            $product_visibility_filter = WWP_Helper_Functions::get_formatted_meta_data(
                $product,
                'wwpp_product_wholesale_visibility_filter'
            );
            $wholesale_role            = isset( $request['wholesale_role'] ) ? sanitize_text_field(
                $request['wholesale_role']
            ) : '';

            $product_is_restricted_in_category = WWPP_Helper_Functions::is_product_restricted_in_category(
                $product_id,
                $wholesale_role
            );

            // Return immediately since not a product.
            // Probably invalid id.
            if ( get_post_type( $product_id ) !== $this->post_type ) {
                return $extra;
            }

            if (
                $product_is_restricted_in_category
                ||
                ( ! in_array( 'all', $product_visibility_filter, true ) && ! in_array(
                    $wholesale_role,
                    $product_visibility_filter,
                    true
                ) )
            ) {
                $extra['is_valid'] = false;
                $extra['message']  = new WP_Error(
                    'wholesale_rest_product_cannot_view',
                    __(
                        'The product is restricted. Please provide the correct wholesale_role parameter for this product.',
                        'woocommerce-wholesale-prices-premium'
                    ),
                    array( 'status' => 401 )
                );
            }

            return $extra;
        }
}

}
