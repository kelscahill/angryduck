<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_API_Wholesale_Products_Variations_Controller' ) ) {

    /**
     * Model that houses the logic of WWPP integration with WC API WPP Wholesale Products Variations.
     *
     * @since 1.18
     */
    class WWPP_API_Wholesale_Products_Variations_Controller extends WC_REST_Product_Variations_Controller {

        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWPP_API_Wholesale_Products_Variations_Controller.
         *
         * @var WWPP_API_Wholesale_Products_Variations_Controller
         */
        private static $_instance;

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
        protected $rest_base = 'wholesale/products/(?P<product_id>[\d]+)/variations';

        /**
         * Post type.
         *
         * @var string
         */
        protected $post_type = 'product_variation';

        /**
         * Wholesale role.
         *
         * @var string
         */
        protected $wholesale_role = '';

        /**
         * WWPP_API_Wholesale_Products_Controller.
         *
         * @var object
         */
        protected $wwpp_api_wholesale_products_controller;

        /**
         * Wholesale Roles.
         *
         * @var array
         */
        protected $registered_wholesale_roles;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWPP_API_Wholesale_Products_Variations_Controller constructor.
         *
         * @since 1.18
         * @access public
         */
        public function __construct() {

            $this->wwpp_api_wholesale_products_controller = new WWPP_API_Wholesale_Products_Controller();

            // Fires when preparing to serve an API request.
            add_action( 'rest_api_init', array( $this, 'register_routes' ) );

            // Include wholesale data into the response.
            add_filter( "woocommerce_rest_prepare_{$this->post_type}_object", array( $this->wwpp_api_wholesale_products_controller, 'add_wholesale_data_on_response' ), 10, 3 );

            // Filter the query arguments of the request.
            add_filter( "woocommerce_rest_{$this->post_type}_object_query", array( $this, 'query_args' ), 10, 2 );

            // Misc stuff on api init.
            add_action( 'rest_api_init', array( $this, 'api_init' ) );

            // Fires after a single object is created or updated via the REST API.
            add_action( "woocommerce_rest_insert_{$this->post_type}_object", array( $this->wwpp_api_wholesale_products_controller, 'create_update_wholesale_product' ), 10, 3 );
        }

        /**
         * Ensure that only one instance of WWPP_API_Wholesale_Products_Variations_Controller is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.18
         * @access public
         *
         * @return WWPP_API_Wholesale_Products_Variations_Controller
         */
        public static function instance() {

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
            if ( ! $this->wwpp_api_wholesale_products_controller->is_wholesale_endpoint( $request ) ) {
                return $args;
            }

            // Get request role type.
            $this->wholesale_role = ! empty( $request['wholesale_role'] ) ? sanitize_text_field( $request['wholesale_role'] ) : sanitize_text_field( $this->wholesale_role );

            // If there's a global wholesale discount set then just return the args ( will use the default args which return all products ).
            if ( $this->wwpp_api_wholesale_products_controller->has_wholesale_general_discount( $this->wholesale_role ) ) {
                return $args;
            }

            // Fetch wholesale products and include in post__in.
            $args['post__in'] = array_values( array_unique( array_merge( $args['post__in'], $this->get_wholesale_variations( $this->wholesale_role, $request ) ) ) );

            if ( empty( $args['post__in'] ) ) {
                $args['post__in'] = array( 0 );
            }

            return $args;
        }

        /**
         * Get Variations with Wholesale Prices.
         *
         * @param string          $wholesale_role Wholesale role.
         * @param WP_REST_Request $request Request data.
         *
         * @since 1.18
         * @access public
         * @return array
         */
        public function get_wholesale_variations( $wholesale_role, $request ) {

            global $wpdb;

            $have_wholesale_price_meta_list = array();
            $wholesale_role                 = sanitize_text_field( $wholesale_role );

            $wholesale_roles_list = ! empty( $wholesale_role ) ? array( $wholesale_role => 1 ) : $this->registered_wholesale_roles;

            foreach ( $wholesale_roles_list as $role => $data ) {
                array_push( $have_wholesale_price_meta_list, "'" . $role . "_wholesale_price'" );
            }

            $have_wholesale_price_meta_list = "'" . implode( ', ', $have_wholesale_price_meta_list ) . "'";

            $wholesale_products = array();
            $product_id         = intval( $request['product_id'] ); // cast as Integer value, returns 0 if not int, returns whole number if value is float.

            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Ignored for allowing interpolation in IN query.
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT 
                        DISTINCT p.ID 
                    FROM 
                        $wpdb->posts p 
                        INNER JOIN $wpdb->postmeta pm1 ON (p.ID = pm1.post_id) 
                        INNER JOIN $wpdb->postmeta pm2 ON (p.ID = pm2.post_id) 
                    WHERE 
                        p.post_status = 'publish' 
                        AND p.post_type = %s
                        AND p.post_parent = %d
                        AND (
                            pm1.meta_key IN ( $have_wholesale_price_meta_list ) 
                            AND CAST(pm1.meta_value AS SIGNED) > 0
                        )",
                    $this->post_type,
                    $product_id
                ),
                ARRAY_A
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Ignored for allowing interpolation in IN query.

            if ( $results ) {

                foreach ( $results as $product ) {
                    $wholesale_products[] = $product['ID'];
                }
            }

            return $wholesale_products;
        }

        /**
         * Add checking on the response when fetching variations.
         *
         * @param WP_REST_Request $request Request data.
         *
         * @since 1.18
         * @access public
         * @return WP_REST_Response|WP_Error
         */
        public function get_items( $request ) {

            $response = parent::get_items( $request );

            if ( isset( $request['wholesale_role'] ) && ! isset( $this->registered_wholesale_roles[ $request['wholesale_role'] ] ) ) {
                return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Invalid wholesale role.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
            }

            if ( empty( $response->data ) ) {
                return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Not a wholesale product.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
            }

            return $response;
        }

        /**
         * Override WC Delete variation. Check first if variation is has wholesale price for it to be deleted.
         *
         * @param WP_REST_Request $request Request data.
         *
         * @since 1.18
         * @access public
         * @return array
         */
        public function delete_item( $request ) {

            global $wc_wholesale_prices;

            $wholesale_role = isset( $request['wholesale_role'] ) ? sanitize_text_field( $request['wholesale_role'] ) : '';
            $object         = $this->get_object( (int) $request['id'] );

            if ( ! $this->wwpp_api_wholesale_products_controller->has_wholesale_general_discount( $wholesale_role ) && ( $object && 0 !== $object->get_id() ) ) {

                $_REQUEST['request'] = $request;
                $wholesale_products  = $this->wwpp_api_wholesale_products_controller->get_wholesale_products( $wholesale_role );

                // If just a regular product ( without wholesale price ) then show an error.
                if ( ! in_array( $request['product_id'], $wholesale_products, true ) ) {
                    return new WP_Error( 'woocommerce_rest_cannot_delete', sprintf( __( 'Not a wholesale product.', 'woocommerce-wholesale-prices-premium' ), $this->post_type ), array( 'status' => rest_authorization_required_code() ) );
                }
            }

            // Force Delete Variation.
            $request->set_param( 'force', true );

            $response = parent::delete_item( $request );

            $this->update_variable_wholesale_price_meta( $response );

            return $response;
        }

        /**
         * Validate if fetched item is wholesale product
         *
         * @param WP_REST_Request $request Request data.
         *
         * @since 1.18
         * @access public
         * @return WP_REST_Response|WP_Error
         */
        public function get_item( $request ) {

            $wholesale_role       = isset( $request['wholesale_role'] ) ? sanitize_text_field( $request['wholesale_role'] ) : '';
            $wholesale_variations = $this->get_wholesale_variations( $wholesale_role, $request );
            $variation_id         = (int) $request['id'];

            if ( ! empty( $wholesale_role ) ) {

                // If wholesale role does not exist return error response.
                if ( ! isset( $this->registered_wholesale_roles[ $wholesale_role ] ) ) {
                    return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Invalid wholesale role.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
                }
            }

            if ( ! $this->wwpp_api_wholesale_products_controller->has_wholesale_general_discount( $wholesale_role ) ) {

                // If just a regular product ( without wholesale price ) then show an error.
                if ( ! in_array( $variation_id, $wholesale_variations, true ) ) {
                    return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Not a wholesale product.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
                }
            }

            $response = parent::get_item( $request );

            return $response;
        }

        /**
         * Extra validation on variation creation.
         *
         * @param WP_REST_Request $request Request data.
         *
         * @since 1.18
         * @access public
         * @return WP_REST_Response|WP_Error
         */
        public function create_item( $request ) {

            $wholesale_role = isset( $request['wholesale_role'] ) ? sanitize_text_field( $request['wholesale_role'] ) : '';
            $terms          = get_the_terms( $request['product_id'], 'product_cat' );
            $categories     = array();

            if ( $terms ) {
                foreach ( $terms as $term ) {
                    $categories[] = array( 'id' => $term->term_id );
                }
            }

            if ( ! $this->wwpp_api_wholesale_products_controller->has_wholesale_category_discount( $wholesale_role, $categories ) && ! $this->wwpp_api_wholesale_products_controller->has_wholesale_general_discount( $wholesale_role ) ) {

                if ( ! isset( $request['wholesale_price'] ) ) {
                    return new WP_Error( 'woocommerce_rest_cannot_create', __( 'Unable to create. Please provide "wholesale_price" in the request paremeter.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
                }
            }

            // Check if wholesale price is set. Make wholesale price as the basis to create wholesale product.
            if ( isset( $request['wholesale_price'] ) ) {

                if ( ! is_array( $request['wholesale_price'] ) || empty( $request['wholesale_price'] ) ) {
                    return new WP_Error( 'woocommerce_rest_cannot_create', __( 'Unable to create. Invalid wholesale price.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
                }

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

            $response = parent::create_item( $request );

            $this->set_variable_wholesale_price_meta( $response );

            return $response;
        }

        /**
         * Set _have_wholesale_price and _variations_with_wholesale_price meta in variable level if the created variation has wholesale price set.
         *
         * @param WP_REST_Response $response Response data.
         *
         * @since 1.21
         * @access public
         * @return void
         */
        public function set_variable_wholesale_price_meta( $response ) {

            $variable_id  = $response->data['parent_id'];
            $variation_id = $response->data['id'];

            $wholesale_role_dicounts = $response->data['wholesale_data']['wholesale_price'];

            if ( $wholesale_role_dicounts ) {

                // Get variation product object.
                $product = wc_get_product( $variable_id );

                foreach ( $wholesale_role_dicounts as $role => $discount ) {

                    $product->update_meta_data( $role . '_have_wholesale_price', 'yes' );
                    $product->add_meta_data( $role . '_variations_with_wholesale_price', $variation_id );

                }

                // Save the product object.
                $product->save();
            }
        }

        /**
         * Update _have_wholesale_price and _variations_with_wholesale_price meta in variable level if the variation is deleted.
         *
         * @param WP_REST_Response $response Response data.
         *
         * @since 1.21
         * @access public
         * @return void
         */
        public function update_variable_wholesale_price_meta( $response ) {

            global $wc_wholesale_prices;

            $variable_id     = $response->data['parent_id'];
            $variation_id    = $response->data['id'];
            $wholesale_roles = $this->registered_wholesale_roles;
            $product         = wc_get_product( $variable_id );
            $variations      = $product->get_available_variations();

            if ( $wholesale_roles ) {

                foreach ( $wholesale_roles as $role => $data ) {

                    $product->delete_meta_data_value( $role . '_variations_with_wholesale_price', $variation_id );

                    $price_arr = $wc_wholesale_prices->wwp_wholesale_prices->get_product_wholesale_price_on_shop_v3( $variable_id, array( $role ) );

                    if ( ! empty( $price_arr['wholesale_price'] ) ) {
                        $product->update_meta_data( $role . '_have_wholesale_price', 'yes' );
                    } else {
                        $product->delete_meta_data( $role . '_have_wholesale_price' );
                    }
                }
            }

            // If all variations are removed then set stock status to outofstock.
            if ( empty( $variations ) ) {
                $product->update_meta_data( '_stock_status', 'outofstock' );
            }

            // Save the product object.
            $product->save();
        }
    }

}

return new WWPP_API_Wholesale_Products_Variations_Controller();
