<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_REST_Wholesale_Category_Discount_V1_Controller' ) ) {

    /**
     * Model that houses the logic of WWPP integration with WC API WPP Wholesale General Discount.
     *
     * @since 1.27
     */
    class WWPP_REST_Wholesale_Category_Discount_V1_Controller extends WC_REST_Controller {


        /**
         * Endpoint namespace.
         *
         * @var string
         */
        protected $namespace = 'wholesale/v1';

        /**
         * Route base.
         *
         * @var string
         */
        protected $rest_base = 'category-discount';

        /**
         * WWPP object.
         *
         * @var object
         */
        protected $wc_wholesale_prices_premium;

        /**
         * WWP_REST_Wholesale_Roles_V1_Controller constructor.
         *
         * @since 1.27
         * @access public
         */
        public function __construct() {

            global $wc_wholesale_prices_premium;

            $this->wc_wholesale_prices_premium = $wc_wholesale_prices_premium;

            // Fires when preparing to serve an API request.
            add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        }

        /**
         * Register routes for wholesale roles API.
         *
         * @since 1.27
         * @access public
         */
        public function register_routes() {

            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base,
                array(
                    array(
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => array( $this, 'get_items' ),
                        'permission_callback' => array( $this, 'permissions_check' ),
                        'args'                => $this->get_collection_params(),
                    ),
                )
            );

            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/(?P<category_id>[a-z0-9_]*)',
                array(
                    array(
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => array( $this, 'get_item' ),
                        'permission_callback' => array( $this, 'permissions_check' ),
                        'args'                => $this->get_collection_params(),
                    ),
                    array(
                        'methods'             => WP_REST_Server::EDITABLE,
                        'callback'            => array( $this, 'modify_item' ),
                        'permission_callback' => array( $this, 'permissions_check' ),
                        'args'                => $this->get_collection_params(),
                    ),
                    array(
                        'methods'             => WP_REST_Server::DELETABLE,
                        'callback'            => array( $this, 'delete_item' ),
                        'permission_callback' => array( $this, 'permissions_check' ),
                        'args'                => $this->get_collection_params(),
                    ),
                )
            );
        }

        /**
         * Check if a given request has permissions.
         * Authenticated vai WC API.
         *
         * @since 1.27
         * @param  WP_REST_Request $request Full details about the request.
         * @return WP_Error|boolean
         */
        public function permissions_check( $request ) {

            // Grant permission if admin or shop manager.
            if ( current_user_can( 'administrator' ) || current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore.
                return true;
            }

            return new WP_Error( 'wholesale_rest_category_discount_permission_failed', __( "You don't have permission.", 'woocommerce-wholesale-order-form' ), array( 'status' => rest_authorization_required_code() ) );
        }

        /**
         * Get the query params for collections of attachments.
         *
         * @since 1.27
         * @return array
         */
        public function get_collection_params() {

            $params = array(
                'category_id' => array(
                    'required'          => false,
                    'description'       => __( 'The category ID.', 'woocommerce-wholesale-prices-premium' ),
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'rest_validate_request_arg',
                ),
            );

            return apply_filters( 'wholesale_rest_category_discount_collection_params', $params );
        }

        /**
         * Get all items.
         *
         * @param WP_REST_Request $request Full details about the request.
         *
         * @since 1.27
         * @access public
         * @return WP_REST_Request
         */
        public function get_items( $request ) {

            global $wpdb;

            $results = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE 'taxonomy_%'" );

            $category_discounts = array();
            if ( ! empty( $results ) ) {
                foreach ( $results as $result ) {
                    $cat_id = intval( str_replace( 'taxonomy_', '', $result->option_name ) );
                    $term   = get_term( $cat_id );
                    if ( $term ) {
                        $category_discounts[] = array(
                            'id'                  => $cat_id,
                            'name'                => $term_name = get_term( $cat_id )->name,
                            'wholesale_discounts' => array_map( 'floatval', maybe_unserialize( $result->option_value ) ),
                        );
                    }
                }
            }

            return rest_ensure_response( apply_filters( 'wholesale_rest_category_discount_get_items', $category_discounts, $request ) );
        }

        /**
         * Get an item.
         * Only return if:
         * - Category ID is valid
         *
         * @param WP_REST_Request $request Full details about the request.
         *
         * @since 1.27
         * @access public
         * @return WP_REST_Request
         */
        public function get_item( $request ) {

            if ( isset( $request['category_id'] ) && is_int( $request['category_id'] ) ) {

                $term = get_term( $request['category_id'] );

                if ( $term ) {

                    $wholesale_discounts = get_option( 'taxonomy_' . $request['category_id'], array() );

                    if ( ! is_array( $wholesale_discounts ) ) {
                        $wholesale_discounts = array();
                    }

                    $category_discount = array(
                        'id'                  => intval( $request['category_id'] ),
                        'name'                => $term->name,
                        'wholesale_discounts' => array_map( 'floatval', $wholesale_discounts ),
                    );

                    return rest_ensure_response( apply_filters( 'wholesale_rest_category_discount_get_item', $category_discount, $request ) );

                }
            }

            return new WP_Error( 'wholesale_rest_category_discount_cannot_view', __( 'Category ID is invalid.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
        }

        /**
         * Create, update a wholesale category discounts.
         * All have same behavior so decided to use same callback for all 3.
         * Only create, updte, delete when:
         * - Category ID is valid
         * - Wholesale Role exists
         *
         * @param WP_REST_Request $request Full details about the request.
         *
         * @since 1.27
         * @access public
         * @return WP_REST_Request
         */
        public function modify_item( $request ) {
            $method = $request->get_method();
            switch ( $method ) {
                case 'PUT':
                    $method = 'update';
                    break;
                case 'POST':
                    $method = 'create';
                    break;
            }

            $invalid_roles           = array();
            $invalid_wholesale_price = array();

            if ( isset( $request['category_id'] ) && is_int( $request['category_id'] ) ) {

                $term            = get_term( $request['category_id'] );
                $parameters      = $request->get_json_params();
                $wholesale_roles = $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

                if ( empty( $term ) ) {
                    return new WP_Error( 'wholesale_rest_category_discount_cannot_' . $method . '', __( 'Category ID is invalid.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
                }

                if ( empty( $parameters ) ) {
                    return new WP_Error( 'wholesale_rest_category_discount_cannot_' . $method . '', __( 'Please provide discount for each wholesale roles.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
                }

                // Validate if wholesale role does exist.
                foreach ( $parameters as $role => $discount ) {

                    if ( ! isset( $wholesale_roles[ $role ] ) ) {
                        $invalid_roles[] = $role;
                    }

                    if ( ! is_numeric( $discount ) ) {
                        $invalid_wholesale_price[ $role ] = $discount;
                    }
                }

                if ( ! empty( $invalid_roles ) || ! empty( $invalid_wholesale_price ) ) {
                    return new WP_Error(
                        'wholesale_rest_category_discount_cannot_' . $method . '',
                        // Translators: %s is the method.
                        sprintf( __( 'Unable to %s. Invalid wholesale price.', 'woocommerce-wholesale-prices' ), $method ),
                        array(
                            'status'                  => 400,
                            'invalid_roles'           => $invalid_roles,
                            'invalid_wholesale_price' => $invalid_wholesale_price,
                        )
                    );
                }

                // Passed.
                if ( empty( $invalid_roles ) && empty( $invalid_wholesale_price ) ) {

                    $wholesale_discounts = get_option( 'taxonomy_' . $request['category_id'], array() );

                    if ( empty( $wholesale_discounts ) ) {
                        $wholesale_discounts = array();
                    }

                    foreach ( $parameters as $key => $discount ) {
                        $wholesale_discounts[ $key . '_wholesale_discount' ] = $discount;
                    }

                    update_option( 'taxonomy_' . $request['category_id'], $wholesale_discounts, 'no' );

                    $method1 = 'update' === $method ? __( 'updated', 'woocommerce-wholesale-prices-premium' ) : __( 'created', 'woocommerce-wholesale-prices-premium' );
                    $method2 = 'update' === $method ? 'updated' : 'created';

                    $result = array(
                        // Translators: %1s is the method.
                        'message' => sprintf( __( 'Successfully %1s category discount.', 'woocommerce-wholesale-prices-premium' ), $method1 ),
                        'data'    => array_map( 'floatval', $wholesale_discounts ),
                    );

                    return new WP_REST_Response( apply_filters( 'wholesale_rest_category_discount_' . $method2 . '_item', $result, $request ), 200 );

                }
            }

            return new WP_Error( 'wholesale_rest_category_discount_cannot_' . $method2 . '', __( 'The parameter category_id is missing.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
        }

        /**
         * Unset the wholesale discount set in category.
         * Must pass category id and the wholesale roles you want to unset.
         * Only remove when:
         * - Category ID is valid
         * - Wholesale Role exists
         *
         * @param WP_REST_Request $request Full details about the request.
         *
         * @since 1.27
         * @access public
         * @return WP_REST_Request
         */
        public function delete_item( $request ) {

            $invalid_roles = array();

            if ( isset( $request['category_id'] ) && is_int( $request['category_id'] ) ) {

                $term            = get_term( $request['category_id'] );
                $parameters      = $request->get_json_params();
                $wholesale_roles = $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

                if ( empty( $term ) ) {
                    return new WP_Error( 'wholesale_rest_category_discount_cannot_delete', __( 'Category ID is invalid.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
                }

                if ( empty( $parameters ) ) {
                    return new WP_Error( 'wholesale_rest_category_discount_cannot_delete', __( 'Please provide wholesale role keys you want to remove.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
                }

                // Validate if wholesale role does exist.
                foreach ( $parameters as $role ) {
                    if ( ! isset( $wholesale_roles[ $role ] ) ) {
                        $invalid_roles[] = $role;
                    }
                }

                if ( ! empty( $invalid_roles ) ) {
                    return new WP_Error(
                        'wholesale_rest_category_discount_cannot_delete',
                        __( 'Make sure wholesale role are valid. Please indicate the wholesale roles you want to remove the discount.', 'woocommerce-wholesale-prices-premium' ),
                        array(
                            'status'        => 400,
                            'invalid_roles' => $invalid_roles,
                        )
                    );
                }

                // Passed.
                if ( empty( $invalid_roles ) ) {

                    $wholesale_discounts = get_option( 'taxonomy_' . $request['category_id'], array() );

                    if ( empty( $wholesale_discounts ) ) {
                        $wholesale_discounts = array();
                    }

                    foreach ( $parameters as $role ) {
                        unset( $wholesale_discounts[ $role . '_wholesale_discount' ] );
                    }

                    update_option( 'taxonomy_' . $request['category_id'], $wholesale_discounts, 'no' );

                    $result = array(
                        'message' => __( 'Successfully deleted category discount.', 'woocommerce-wholesale-prices-premium' ),
                        'data'    => array_map( 'floatval', $wholesale_discounts ),
                    );

                    return new WP_REST_Response( apply_filters( 'wholesale_rest_category_discount_delete_item', $result, $request ), 200 );

                }
            }

            return new WP_Error( 'wholesale_rest_category_discount_cannot_delete', __( 'Category ID is invalid.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
        }
    }

}
