<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_API_Wholesale_Products_Controller' ) ) {

    /**
     * Model that houses the logic of WWPP integration with WC API WPP Wholesale Products.
     *
     * @since 1.18
     */
    class WWPP_API_Wholesale_Products_Controller extends WC_REST_Products_Controller {

        /**
         * Class Properties
         */

        /**
         * Endpoint namespace.
         *
         * @var string
         */
        protected $namespace = 'wc/v3';

        /**
         * Route base.
         *
         * @var string
         */
        protected $rest_base = 'wholesale/products';

        /**
         * Post type.
         *
         * @var string
         */
        protected $post_type = 'product';

        /**
         * Wholesale role.
         *
         * @var string
         */
        protected $wholesale_role = '';

        /**
         * Wholesale Roles.
         *
         * @var array
         */
        protected $registered_wholesale_roles = array();

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWPP_API_Wholesale_Products_Controller constructor.
         *
         * @since 1.18
         * @access public
         */
        public function __construct() {

            // Filter the query arguments of the request.
            add_filter( "woocommerce_rest_{$this->post_type}_object_query", array( $this, 'query_args' ), 10, 2 );

            // include wholesale data into the response.
            add_filter( "woocommerce_rest_prepare_{$this->post_type}_object", array( $this, 'add_wholesale_data_on_response' ), 10, 3 );

            // Fires after a single object is created or updated via the REST API.
            add_action( "woocommerce_rest_insert_{$this->post_type}_object", array( $this, 'create_update_wholesale_product' ), 10, 3 );

            // Misc stuff on api init.
            add_action( 'rest_api_init', array( $this, 'api_init' ) );
        }

        /**
         * Ensure that only one instance of WWPP_API_Wholesale_Products_Controller is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.18
         * @access public
         *
         * @return WWPP_API_Wholesale_Products_Controller
         */
        public static function instance() {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        /**
         * On API init.
         *
         * @since 1.18
         * @access public
         */
        public function api_init() {

            global $wc_wholesale_prices_premium;

            $this->registered_wholesale_roles = $wc_wholesale_prices_premium->wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
        }

        /**
         * Query args.
         *
         * @param array           $args    Request args.
         * @param WP_REST_Request $request Request data.
         *
         * @since 1.18
         * @access public
         * @return array
         */
        public function query_args( $args, $request ) {

            // Check if not wholesale endpoint.
            if ( ! $this->is_wholesale_endpoint( $request ) ) {
                return $args;
            }

            // Get request role type.
            $this->wholesale_role = ! empty( $request['wholesale_role'] ) ? sanitize_text_field( $request['wholesale_role'] ) : sanitize_text_field( $this->wholesale_role );

            // If there's a global wholesale discount set then just return the args ( will use the default args which return all products ).
            if ( $this->has_wholesale_general_discount( $this->wholesale_role ) ) {
                return $args;
            }

            // Fetch wholesale products and include in post__in.
            $args['post__in'] = array_values( array_unique( array_merge( $args['post__in'], $this->get_wholesale_products( $this->wholesale_role ) ) ) );

            if ( empty( $args['post__in'] ) ) {
                $args['post__in'] = array( 0 );
            }

            return $args;
        }

        /**
         * Get simple and variable Wholesale Products.
         *
         * @param string $wholesale_role The wholesale role.
         *
         * @since 1.18
         * @access public
         * @return array
         */
        public function get_wholesale_products( $wholesale_role ) {

            global $wpdb;

            $have_wholesale_price_meta_list            = array();
            $variations_with_wholesale_price_meta_list = array();
            $have_wholesale_price_set_via_category     = array();
            $wholesale_role                            = sanitize_text_field( $wholesale_role );

            // Used to check if variable has wholesale variations.
            // OR wholesale discount set via the category.
            // Also this prevents fetching non-existing wholesale roles in case it was removed but the meta still exist in the product.
            $wholesale_roles_list = ! empty( $wholesale_role ) ? array( $wholesale_role => 1 ) : $this->registered_wholesale_roles;

            foreach ( $wholesale_roles_list as $role => $data ) {
                array_push( $have_wholesale_price_meta_list, "'" . $role . "_have_wholesale_price'" );
                array_push( $variations_with_wholesale_price_meta_list, "'" . $role . "_variations_with_wholesale_price'" );
                array_push( $have_wholesale_price_set_via_category, "'" . $role . "_have_wholesale_price_set_by_product_cat'" );
            }

            $have_wholesale_price_meta_list            = "'" . implode( ',', $have_wholesale_price_meta_list ) . "'";
            $variations_with_wholesale_price_meta_list = "'" . implode( ',', $variations_with_wholesale_price_meta_list ) . "'";
            $have_wholesale_price_set_via_category     = "'" . implode( ',', $have_wholesale_price_set_via_category ) . "'";

            $wholesale_products = array();

            // Allow deletion of wholesale products with status of draft only if request method is DELETE.
            if ( isset( $_REQUEST['request'] ) && $_REQUEST['request']->get_method() === 'DELETE' ) { // phpcs:ignore.
                $post_status = "IN ( 'publish' , 'draft' , 'trash' )";
            } else {
                $post_status = "= 'publish'";
            }

            // phpcs:disable.
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT 
                        DISTINCT p.ID 
                     FROM 
                        $wpdb->posts p 
                        INNER JOIN $wpdb->postmeta pm1 ON (p.ID = pm1.post_id) 
                        INNER JOIN $wpdb->postmeta pm2 ON (p.ID = pm2.post_id) 
                     WHERE 
                        p.post_status " . $post_status . " 
                        AND p.post_type = 'product' 
                        AND (
                            (
                                pm1.meta_key IN ( " . implode( ',', array_fill( 0, count( $have_wholesale_price_meta_list ), '%d' ) ) . " ) 
                                AND pm1.meta_value = 'yes'
                            ) 
                            AND (
                                    (
                                        pm2.meta_key LIKE %s
                                        AND CAST(pm2.meta_value AS SIGNED) > 0
                                    ) 
                                    OR (
                                        pm2.meta_key IN ( " . implode( ',', array_fill( 0, count( $variations_with_wholesale_price_meta_list ), '%d' ) ) . " ) 
                                        AND pm2.meta_value = 'yes'
                                    ) 
                                    OR (
                                        pm2.meta_key IN ( " . implode( ',', array_fill( 0, count( $variations_with_wholesale_price_meta_list ), '%d' ) ) . " ) 
                                        AND CAST(pm2.meta_value AS SIGNED) > 0
                                    ) 
                                    OR (
                                        pm2.meta_key IN ( " . implode( ',', array_fill( 0, count( $have_wholesale_price_set_via_category ), '%d' ) ) . " ) 
                                        AND pm2.meta_value = 'yes'
                                    )
                            )
                        )",
                    $have_wholesale_price_meta_list,
                    '%' . $wpdb->esc_like( $wholesale_role . '_wholesale_price.' ) . '%',
                    $variations_with_wholesale_price_meta_list,
                    $variations_with_wholesale_price_meta_list,
                    $have_wholesale_price_set_via_category,
                ),
                ARRAY_A
            );
            // phpcs:enable.

            if ( $results ) {

                foreach ( $results as $product ) {
                    $wholesale_products[] = $product['ID'];
                }
            }

            return $wholesale_products;
        }

        /**
         * Check if there is a wholesale percentage discount set via the General Discount options.
         *
         * @param string $wholesale_role The wholesale role.
         *
         * @since 1.18
         * @access public
         * @return bool|int
         */
        public function has_wholesale_general_discount( $wholesale_role ) {

            $wholesale_role_discount = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, array() );

            if ( ! empty( $wholesale_role_discount ) && empty( $wholesale_role ) ) {
                return true;
            }

            if ( array_key_exists( $wholesale_role, $this->registered_wholesale_roles ) && array_key_exists( $wholesale_role, $wholesale_role_discount ) ) {
                return $wholesale_role_discount[ $wholesale_role ];
            }

            return false;
        }

        /**
         * Check if there is a wholesale percentage discount set via the Category Discount options
         *
         * @param string $wholesale_role The wholesale role.
         * @param array  $category_ids   The category ids.
         *
         * @since 1.20
         * @access public
         * @return bool|int
         */
        public function has_wholesale_category_discount( $wholesale_role, $category_ids ) {

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
                        if ( ! empty( $this->registered_wholesale_roles ) ) {

                            foreach ( $this->registered_wholesale_roles as $role => $data ) {

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
         * Modify the response to include WWPP wholesale data.
         *
         * @param WP_REST_Response $response The response object.
         * @param WC_Product       $product The object.
         * @param WP_REST_Request  $request The request object.
         *
         * @since 1.18
         * @access public
         * @return array
         */
        public function add_wholesale_data_on_response( $response, $product, $request ) {

            // Check if not wholesale endpoint.
            if ( ! $this->is_wholesale_endpoint( $request ) ) {
                return $response;
            }

            $context = ! empty( $request['context'] ) ? $request['context'] : 'view';
            $data    = $this->get_product_data( $product, $context, $request );

            // Add variations to variable products.
            if ( $product->is_type( 'variable' ) && $product->has_child() ) {
                $data['variations'] = $product->get_children();
            }

            // Add grouped products data.
            if ( $product->is_type( 'grouped' ) && $product->has_child() ) {
                $data['grouped_products'] = $product->get_children();
            }

            // Add wholesale data. Add also WWPP meta data.
            // NOTE: We will need to merge the Product Calculated Wholesale Prices Data later on. $this->get_wholesale_data( $product ).
            $data['wholesale_data'] = $this->get_wwpp_meta_data( $product );

            // Remove WWPP meta in meta data.
            $data['meta_data'] = $this->remove_wwpp_meta( $data['meta_data'] );

            $data     = $this->add_additional_fields_to_object( $data, $request );
            $data     = $this->filter_response_by_context( $data, $context );
            $response = rest_ensure_response( $data );
            $response->add_links( $this->prepare_links( $product, $request ) );

            return apply_filters( "wwpp_rest_prepare_{$this->post_type}_object", $response, $product, $request );
        }

        /**
         * Get Product Calculated Wholesale Prices Data.
         *
         * @param WC_Product $product The product.
         *
         * @since 1.18
         * @access public
         * @return array
         */
        public function get_wholesale_data( $product ) {

            global $wc_wholesale_prices;

            $wholesale_data['calculated_wholesale_prices'] = array();

            $product_id   = $product->get_id();
            $product_type = $product->get_type();

            if ( 'simple' === $product_type ) {

                if ( ! empty( $this->wholesale_role ) ) {

                    $price_arr = $wc_wholesale_prices->wwp_wholesale_prices->get_product_wholesale_price_on_shop_v3( $product_id, array( $this->wholesale_role ) );

                    if ( ! empty( $price_arr['wholesale_price'] ) ) {

                        $wholesale_data['calculated_wholesale_prices'] = array_merge(
                            $wholesale_data['calculated_wholesale_prices'],
                            array(
                                $this->wholesale_role => array(
                                    'wholesale_price' => (float) $price_arr['wholesale_price'],
                                    'discount_source' => $price_arr['source'],
                                ),
                            )
                        );

                    }
                } else {

                    foreach ( $this->registered_wholesale_roles as $role => $data ) {

                        $price_arr = $wc_wholesale_prices->wwp_wholesale_prices->get_product_wholesale_price_on_shop_v3( $product_id, array( $role ) );

                        if ( ! empty( $price_arr['wholesale_price'] ) ) {

                            $wholesale_data['calculated_wholesale_prices'] = array_merge(
                                $wholesale_data['calculated_wholesale_prices'],
                                array(
                                    $role => array(
                                        'wholesale_price' => (float) $price_arr['wholesale_price'],
                                        'discount_source' => $price_arr['source'],
                                    ),
                                )
                            );

                        }
                    }
                }
            } elseif ( 'variable' === $product_type ) {

                $wholesale_variations = array();

                if ( empty( $wholesale_variations ) ) {

                    $wholesale_variations = array();
                    foreach ( $product->get_available_variations() as $variation ) {
                        $wholesale_variations[] = $variation['variation_id'];
                    }
                }

                if ( ! empty( $this->wholesale_role ) ) {

                    foreach ( $wholesale_variations as $variation_id ) {

                        if ( get_post_status( $variation_id ) ) {

                            $price_arr = $wc_wholesale_prices->wwp_wholesale_prices->get_product_wholesale_price_on_shop_v3( $variation_id, array( $this->wholesale_role ) );
                            $_product  = wc_get_product( $variation_id );

                            if ( ! empty( $price_arr['wholesale_price'] ) ) {

                                if ( ! isset( $wholesale_data['calculated_wholesale_prices'][ $variation_id ] ) ) {
                                    $wholesale_data['calculated_wholesale_prices'][ $variation_id ] = array();
                                }

                                $wholesale_data['calculated_wholesale_prices'][ $variation_id ] = array_merge(
                                    $wholesale_data['calculated_wholesale_prices'][ $variation_id ],
                                    array(
                                        $this->wholesale_role => array(
                                            'wholesale_price' => (float) $price_arr['wholesale_price'],
                                            'discount_source' => $price_arr['source'],
                                        ),
                                    )
                                );

                            }
                        }
                    }
                } else {

                    foreach ( $this->registered_wholesale_roles as $role => $data ) {

                        foreach ( $wholesale_variations as $variation_id ) {

                            if ( get_post_status( $variation_id ) ) {

                                $price_arr = $wc_wholesale_prices->wwp_wholesale_prices->get_product_wholesale_price_on_shop_v3( $variation_id, array( $role ) );

                                $_product = wc_get_product( $variation_id );

                                if ( ! empty( $price_arr['wholesale_price'] ) ) {

                                    if ( ! isset( $wholesale_data['calculated_wholesale_prices'][ $variation_id ] ) ) {
                                        $wholesale_data['calculated_wholesale_prices'][ $variation_id ] = array();
                                    }

                                    $wholesale_data['calculated_wholesale_prices'][ $variation_id ] = array_merge(
                                        $wholesale_data['calculated_wholesale_prices'][ $variation_id ],
                                        array(
                                            $role => array(
                                                'wholesale_price' => (float) $price_arr['wholesale_price'],
                                                'discount_source' => $price_arr['source'],
                                            ),
                                        )
                                    );

                                }
                            }
                        }
                    }
                }
            } elseif ( 'variation' === $product_type ) {

                if ( ! empty( $this->wholesale_role ) ) {

                    $price_arr = $wc_wholesale_prices->wwp_wholesale_prices->get_product_wholesale_price_on_shop_v3( $product_id, array( $this->wholesale_role ) );

                    if ( ! empty( $price_arr['wholesale_price'] ) ) {

                        $wholesale_data['calculated_wholesale_prices'] = array_merge(
                            $wholesale_data['calculated_wholesale_prices'],
                            array(
                                $this->wholesale_role => array(
                                    'wholesale_price' => (float) $price_arr['wholesale_price'],
                                    'discount_source' => $price_arr['source'],
                                ),
                            )
                        );

                    }
                } else {

                    foreach ( $this->registered_wholesale_roles as $role => $data ) {

                        $price_arr = $wc_wholesale_prices->wwp_wholesale_prices->get_product_wholesale_price_on_shop_v3( $product_id, array( $role ) );

                        if ( ! empty( $price_arr['wholesale_price'] ) ) {

                            $wholesale_data['calculated_wholesale_prices'] = array_merge(
                                $wholesale_data['calculated_wholesale_prices'],
                                array(
                                    $role => array(
                                        'wholesale_price' => (float) $price_arr['wholesale_price'],
                                        'discount_source' => $price_arr['source'],
                                    ),
                                )
                            );

                        }
                    }
                }
            }

            return empty( $wholesale_data ) ? array() : $wholesale_data;
        }

        /**
         * Check if the request coming from wholesale endpoint.
         *
         * @param WC_Product $product The product.
         *
         * @since 1.18
         * @access public
         * @return array
         */
        public function get_wwpp_meta_data( $product ) {

            $meta_data = array(
                'wholesale_price'                          => array(),
                'wholesale_minimum_order_quantity'         => array(),
                'wholesale_order_quantity_step'            => array(),
                'wwpp_product_wholesale_visibility_filter' => array(),
                'variable_level_wholesale_minimum_order_quantity' => array(),
                'variable_level_wholesale_order_quantity_step' => array(),
            );

            $ignore_cat_level_discount  = $product->get_meta( 'wwpp_ignore_cat_level_wholesale_discount', true );
            $ignore_role_level_discount = $product->get_meta( 'wwpp_ignore_role_level_wholesale_discount', true );
            $qty_discount_rule_mapping  = $product->get_meta( 'wwpp_post_meta_quantity_discount_rule_mapping', true );
            $product_visibility_filter  = WWP_Helper_Functions::get_formatted_meta_data( $product, 'wwpp_product_wholesale_visibility_filter' );
            $enable_rule_mapping        = $product->get_meta( 'wwpp_post_meta_enable_quantity_discount_rule', true );

            if ( ! empty( $ignore_cat_level_discount ) ) {
                $meta_data['wwpp_ignore_cat_level_wholesale_discount'] = $ignore_cat_level_discount;
            }

            if ( ! empty( $ignore_role_level_discount ) ) {
                $meta_data['wwpp_ignore_role_level_wholesale_discount'] = $ignore_role_level_discount;
            }

            if ( ! empty( $qty_discount_rule_mapping ) && 'yes' === $enable_rule_mapping ) {
                $meta_data['wwpp_quantity_discount_rule_mapping'] = $qty_discount_rule_mapping;
            }

            if ( ! empty( $product_visibility_filter ) ) {
                $meta_data['wwpp_product_wholesale_visibility_filter'] = array_unique( $product_visibility_filter );
            }

            if ( ! empty( $enable_rule_mapping ) ) {
                $meta_data['wwpp_enable_quantity_discount_rule'] = $enable_rule_mapping;
            }

            foreach ( $this->registered_wholesale_roles as $role => $data ) {

                $wholesale_price    = $product->get_meta( $role . '_wholesale_price', true );
                $wholesale_min_qty  = $product->get_meta( $role . '_wholesale_minimum_order_quantity', true );
                $wholesale_qty_step = $product->get_meta( $role . '_wholesale_order_quantity_step', true );

                if ( ! empty( $wholesale_price ) ) {
                    $meta_data['wholesale_price'] = array_merge( $meta_data['wholesale_price'], array( $role => $wholesale_price ) );
                }

                if ( ! empty( $wholesale_min_qty ) ) {
                    $meta_data['wholesale_minimum_order_quantity'] = array_merge( $meta_data['wholesale_minimum_order_quantity'], array( $role => $wholesale_min_qty ) );
                }

                if ( ! empty( $wholesale_qty_step ) ) {
                    $meta_data['wholesale_order_quantity_step'] = array_merge( $meta_data['wholesale_order_quantity_step'], array( $role => $wholesale_qty_step ) );
                }

                if ( $product->is_type( 'variable' ) ) {

                    $variable_order_qty = $product->get_meta( $role . '_variable_level_wholesale_minimum_order_quantity', true );
                    $variable_qty_step  = $product->get_meta( $role . '_variable_level_wholesale_order_quantity_step', true );

                    if ( ! empty( $variable_order_qty ) ) {
                        $meta_data['variable_level_wholesale_minimum_order_quantity'] = array_merge( $meta_data['variable_level_wholesale_minimum_order_quantity'], array( $role => $variable_order_qty ) );
                    }

                    if ( ! empty( $variable_qty_step ) ) {
                        $meta_data['variable_level_wholesale_order_quantity_step'] = array_merge( $meta_data['variable_level_wholesale_order_quantity_step'], array( $role => $variable_qty_step ) );
                    }
                }
            }

            return array_filter( $meta_data );
        }

        /**
         * Unset WWPP meta in meta_data property. WWPP meta will be transfered to its own property called wholesale_data.
         *
         * @param array $meta_data The meta data.
         *
         * @since 1.18
         * @access public
         * @return array
         */
        public function remove_wwpp_meta( $meta_data ) {

            $meta_to_remove = array(
                'wwpp_ignore_cat_level_wholesale_discount',
                'wwpp_ignore_role_level_wholesale_discount',
                'wwpp_post_meta_quantity_discount_rule_mapping',
                'wwpp_product_wholesale_visibility_filter',
                'wwpp_post_meta_enable_quantity_discount_rule',
            );

            if ( ! empty( $meta_data ) ) {

                foreach ( $meta_data as $key => $data ) {

                    if ( in_array( $data->key, $meta_to_remove, true ) ) {
                        unset( $meta_data[ $key ] );
                    } elseif ( strpos( $data->key, '_wholesale_price' ) !== false ||
                        strpos( $data->key, '_have_wholesale_price' ) !== false ||
                        strpos( $data->key, '_wholesale_minimum_order_quantity' ) !== false ||
                        strpos( $data->key, '_wholesale_order_quantity_step' ) !== false ) {
                        unset( $meta_data[ $key ] );
                    }
                }
            }

            return array_values( $meta_data );
        }

        /**
         * Check if the request coming from wholesale endpoint.
         *
         * @param WP_REST_Request $request The request.
         *
         * @since 1.18
         * @access public
         * @return bool
         */
        public function is_wholesale_endpoint( $request ) {

            if ( ! is_a( $request, 'WP_REST_Request' ) ) {
                return false;
            }

            if ( ! method_exists( $request, 'get_route' ) ) {
                return false;
            }

            $route = explode( '/', $request->get_route() );

            // Check if wholesale endpoint.
            return strpos( $request->get_route(), 'wc/v3/wholesale' ) !== false ? true : false;
        }

        /**
         * Fires after a single object is created or updated via the REST API.
         *
         * @param WC_Product      $product           The product object.
         * @param WP_REST_Request $request          The request object.
         * @param Boolean         $create_product     True is creating, False is updating.
         *
         * @since 1.18.0
         * @access public
         */
        public function create_update_wholesale_product( $product, $request, $create_product ) {

            // Note: This function seems to be firing 3x. Need to optimize in the future.

            // Check if not wholesale endpoint then dont proceed.
            if ( ! $this->is_wholesale_endpoint( $request ) ) {
                return;
            }

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
                        if ( array_key_exists( $role, $this->registered_wholesale_roles ) && ! in_array( $role, $visibility_list, true ) ) {
                            $product->add_meta_data( 'wwpp_product_wholesale_visibility_filter', $role, false );
                            $wholesale_role_exist = true;
                        }
                    }

                    if ( false === $wholesale_role_exist ) {
                        $product->delete_meta_data( 'wwpp_product_wholesale_visibility_filter' );
                        $product->update_meta_data( 'wwpp_product_wholesale_visibility_filter', 'all' );
                    }
                } elseif ( array_key_exists( $wholesale_visibility_filter, $this->registered_wholesale_roles ) ) {
                    $product->update_meta_data( 'wwpp_product_wholesale_visibility_filter', $wholesale_visibility_filter );
                } else {
                    $product->update_meta_data( 'wwpp_product_wholesale_visibility_filter', 'all' );
                }
            } elseif ( $create_product ) {
                $product->update_meta_data( 'wwpp_product_wholesale_visibility_filter', 'all' );
            }

            // Check if Disregard Product Category Level Wholesale Discount is set.
            if ( isset( $ignore_cat_level_wholesale_discount ) && in_array( $product_type, array( 'simple', 'variable' ), true ) ) {

                if ( in_array( strtolower( $ignore_cat_level_wholesale_discount ), array( 'yes', 'no' ), true ) ) {
                    $product->update_meta_data( 'wwpp_ignore_cat_level_wholesale_discount', strtolower( $ignore_cat_level_wholesale_discount ) );
                }
            }

            // Check if Disregard Wholesale Role Level Wholesale Discount is set.
            if ( isset( $ignore_role_level_wholesale_discount ) && in_array( $product_type, array( 'simple', 'variable' ), true ) ) {

                if ( in_array( strtolower( $ignore_role_level_wholesale_discount ), array( 'yes', 'no' ), true ) ) {
                    $product->update_meta_data( 'wwpp_ignore_role_level_wholesale_discount', strtolower( $ignore_role_level_wholesale_discount ) );
                }
            }

            // Check if wholesale price is set.
            if ( isset( $wholesale_price ) && in_array( $product_type, array( 'simple', 'variation' ), true ) ) {

                // Multiple wholesale price is set.
                if ( is_array( $wholesale_price ) ) {

                    foreach ( $wholesale_price as $role => $price ) {

                        // Validate if wholesale role exist.
                        if ( is_numeric( $price ) && array_key_exists( $role, $this->registered_wholesale_roles ) ) {

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
            if ( isset( $wholesale_minimum_order_quantity ) && in_array( $product_type, array( 'simple', 'variable', 'variation' ), true ) ) {

                // Multiple order quantity is set.
                if ( is_array( $wholesale_minimum_order_quantity ) ) {

                    foreach ( $wholesale_minimum_order_quantity as $role => $quantity ) {

                        // Validate if wholesale role exist.
                        if ( is_numeric( $quantity ) && array_key_exists( $role, $this->registered_wholesale_roles ) ) {

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
            if ( isset( $wholesale_order_quantity_step ) && in_array( $product_type, array( 'simple', 'variable', 'variation' ), true ) ) {

                // Multiple order quantity step is set.
                if ( is_array( $wholesale_order_quantity_step ) ) {

                    foreach ( $wholesale_order_quantity_step as $role => $qty_step ) {

                        // Validate if wholesale role exist.
                        if ( is_numeric( $qty_step ) && array_key_exists( $role, $this->registered_wholesale_roles ) ) {

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

                if ( is_array( $wholesale_quantity_discount_rule_mapping ) && in_array( $product_type, array( 'simple', 'variable', 'variation' ), true ) ) {

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

                            if ( ! array_key_exists( $discount_rule['wholesale_role'], $this->registered_wholesale_roles ) ) {
                                unset( $wholesale_quantity_discount_rule_mapping[ $key ] );
                            }
                        }

                        if ( isset( $discount_rule['start_qty'] ) || isset( $discount_rule['end_qty'] ) ) {

                            if ( ! is_numeric( $discount_rule['start_qty'] ) ||
                                ( is_numeric( $discount_rule['start_qty'] ) && $discount_rule['start_qty'] <= 0 ) ) {
                                unset( $wholesale_quantity_discount_rule_mapping[ $key ] );
                            }

                            if ( ( ! empty( $discount_rule['end_qty'] ) && ! is_numeric( $discount_rule['end_qty'] ) ) ||
                                ( isset( $discount_rule['end_qty'] ) && is_numeric( $discount_rule['start_qty'] ) && is_numeric( $discount_rule['end_qty'] ) && $discount_rule['end_qty'] < $discount_rule['start_qty'] ) ) {
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
                                ( is_numeric( $discount_rule['wholesale_price'] ) && $discount_rule['wholesale_price'] <= 0 ) ) {
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
         * Check if a given request has access to delete an item.
         *
         * @param  WP_REST_Request $request Full details about the request.
         *
         * @since 1.18.0
         * @access public
         * @return bool|WP_Error
         */
        public function delete_item( $request ) {

            global $wc_wholesale_prices;

            $wholesale_role = isset( $request['wholesale_role'] ) ? sanitize_text_field( $request['wholesale_role'] ) : '';
            $object         = $this->get_object( (int) $request['id'] );

            if ( ! $this->has_wholesale_general_discount( $wholesale_role ) && ( $object && 0 !== $object->get_id() ) ) {

                $_REQUEST['request'] = $request;
                $wholesale_products  = $this->get_wholesale_products( $wholesale_role );

                if ( ! in_array( $request['id'], $wholesale_products, true ) ) {
                    return new WP_Error( 'woocommerce_rest_cannot_delete', sprintf( __( 'Not a wholesale product.', 'woocommerce-wholesale-prices-premium' ), $this->post_type ), array( 'status' => rest_authorization_required_code() ) );
                }
            }

            $response = parent::delete_item( $request );

            return $response;
        }

        /**
         * Check if the request coming from wholesale endpoint.
         *
         * @param WP_REST_Request $request The request.
         *
         * @since 1.18
         * @since 1.20 Allow product creation if wholesale discount is set via the Category or General Discount Options
         * @access public
         * @return WP_REST_Response|WP_Error
         */
        public function create_item( $request ) {

            $wholesale_role = isset( $request['wholesale_role'] ) ? sanitize_text_field( $request['wholesale_role'] ) : '';
            $categories     = isset( $request['categories'] ) ? $request['categories'] : array();

            if ( ! $this->has_wholesale_category_discount( $wholesale_role, $categories ) && ! $this->has_wholesale_general_discount( $wholesale_role ) ) {

                if ( ! isset( $request['wholesale_price'] ) && ( isset( $request['type'] ) ) && 'variable' !== $request['type'] ) {
                    return new WP_Error( 'woocommerce_rest_cannot_create', __( 'Unable to create. Please provide "wholesale_price" in the request paremeter.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
                }
            }

            // Check if wholesale price is set. Make wholesale price as the basis to create wholesale product.
            if ( isset( $request['wholesale_price'] ) ) {

                if ( is_array( $request['wholesale_price'] ) ) {

                    $total_valid_wholesale_price = 0;

                    foreach ( $request['wholesale_price'] as $role => $price ) {

                        // Validate if wholesale role exist.
                        if ( is_numeric( $price ) && array_key_exists( $role, $this->registered_wholesale_roles ) ) {
                            ++$total_valid_wholesale_price;
                        }
                    }

                    if ( empty( $total_valid_wholesale_price ) ) {
                        return new WP_Error( 'woocommerce_rest_cannot_create', __( 'Unable to create. Invalid wholesale price.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
                    }
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
                        return new WP_Error( 'woocommerce_rest_cannot_create', __( 'Unable to create. Make sure the quantity discount rule mapping is using "percent-price" for price type.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
                    } else {
                        $request['wholesale_quantity_discount_rule_mapping'] = $qty_mapping;
                    }
                }
            }

            $response = parent::create_item( $request );

            return $response;
        }

        /**
         * Validate if fetched item is wholesale product
         *
         * @param WP_REST_Request $request The request.
         *
         * @since 1.18
         * @access public
         * @return WP_REST_Response|WP_Error
         */
        public function get_item( $request ) {

            $wholesale_role     = isset( $request['wholesale_role'] ) ? $request['wholesale_role'] : '';
            $wholesale_products = $this->get_wholesale_products( $wholesale_role );
            $product_id         = (int) $request['id'];

            if ( ! empty( $wholesale_role ) ) {

                // If wholesale role does not exist return error response.
                if ( ! isset( $this->registered_wholesale_roles[ $wholesale_role ] ) ) {
                    return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Invalid wholesale role.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
                }
            }

            if ( ! $this->has_wholesale_general_discount( $wholesale_role ) ) {

                // If just a regular product ( without wholesale price ) then show an error.
                if ( ! in_array( $product_id, $wholesale_products, true ) ) {
                    return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Not a wholesale product.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
                }
            }

            $response = parent::get_item( $request );

            return $response;
        }

        /**
         * Validate if the request wholesale role is valid
         *
         * @param WP_REST_Request $request The request.
         *
         * @since 1.18
         * @access public
         * @return WP_REST_Response|WP_Error
         */
        public function get_items( $request ) {

            if ( isset( $request['wholesale_role'] ) && ! isset( $this->registered_wholesale_roles[ $request['wholesale_role'] ] ) ) {
                return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Invalid wholesale role.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
            }

            $response = parent::get_items( $request );

            return $response;
        }
    }

}

return new WWPP_API_Wholesale_Products_Controller();
