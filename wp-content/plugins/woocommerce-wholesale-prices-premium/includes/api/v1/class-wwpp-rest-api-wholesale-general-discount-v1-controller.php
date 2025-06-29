<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_REST_Wholesale_General_Discount_V1_Controller' ) ) {

    /**
     * Model that houses the logic of WWPP integration with WC API WPP Wholesale General Discount.
     *
     * @since 1.27
     */
    class WWPP_REST_Wholesale_General_Discount_V1_Controller extends WC_REST_Controller {


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
        protected $rest_base = 'general-discount';

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
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => array( $this, 'create_item' ),
                        'permission_callback' => array( $this, 'permissions_check' ),
                        'args'                => $this->get_collection_params(),
                    ),
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
                '/' . $this->rest_base . '/(?P<wholesale_role>[a-z0-9_]*)',
                array(
                    array(
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => array( $this, 'get_item' ),
                        'permission_callback' => array( $this, 'permissions_check' ),
                        'args'                => $this->get_collection_params(),
                    ),
                    array(
                        'methods'             => WP_REST_Server::EDITABLE,
                        'callback'            => array( $this, 'update_item' ),
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

            return new WP_Error( 'wholesale_rest_general_discount_permission_failed', __( "You don't have permission.", 'woocommerce-wholesale-order-form' ), array( 'status' => rest_authorization_required_code() ) );
        }

        /**
         * Get the query params for collections of attachments.
         *
         * @since 1.27
         * @return array
         */
        public function get_collection_params() {

            $params = array(
                'wholesale_role'     => array(
                    'required'          => false,
                    'description'       => __( 'The wholesale role.', 'woocommerce-wholesale-prices-premium' ),
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => 'rest_validate_request_arg',
                ),
                'wholesale_discount' => array(
                    'description'       => __( 'The wholesale discount.', 'woocommerce-wholesale-prices-premium' ),
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => 'rest_validate_request_arg',
                ),
            );

            return apply_filters( 'wholesale_rest_general_discount_collection_params', $params );
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

            $general_discount = array_map( 'floatval', get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, array() ) );

            return rest_ensure_response( apply_filters( 'wholesale_rest_general_discount_get_items', $general_discount, $request ) );
        }

        /**
         * Get an item.
         *
         * @param WP_REST_Request $request Full details about the request.
         *
         * @since 1.27
         * @access public
         * @return WP_REST_Request
         */
        public function get_item( $request ) {

            $wholesale_roles = $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $wholesale_role  = isset( $request['wholesale_role'] ) ? $request['wholesale_role'] : '';

            if ( ! empty( $wholesale_role ) && isset( $wholesale_roles[ $wholesale_role ] ) ) {

                $general_discounts = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, array() );

                if ( isset( $general_discounts[ $wholesale_role ] ) ) {

                    $result = array(
                        $wholesale_role => floatval( $general_discounts[ $wholesale_role ] ),
                    );

                    return new WP_REST_Response( apply_filters( 'wholesale_rest_general_discount_get_item', $result, $request ), 200 );

                } else {

                    $result = array(
                        'message' => __( 'No wholesale discount set for this wholesale role.', 'woocommerce-wholesale-prices-premium' ),
                    );

                    return new WP_REST_Response( apply_filters( 'wholesale_rest_general_discount_get_item', $result, $request ), 200 );

                }
            }

            return new WP_Error( 'wholesale_rest_general_discount_cannot_view', __( 'Invalid Wholesale Role.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
        }

        /**
         * Create an item.
         * Only create when:
         * - wholesale_role and wholesale_discount parameter is provided
         * - General discount is not yet created for the wholesale customer
         * - The wholesle role exist in the mapping
         *
         * @param WP_REST_Request $request Full details about the request.
         *
         * @since 1.27
         * @access public
         * @return WP_REST_Request
         */
        public function create_item( $request ) {

            if ( ! isset( $request['wholesale_role'] ) || ! isset( $request['wholesale_discount'] ) ) {
                return new WP_Error( 'wholesale_rest_general_discount_cannot_create', __( 'Please provide wholesale_role and wholesale_discount parameter.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
            }

            if ( ! is_numeric( $request['wholesale_discount'] ) ) {
                return new WP_Error( 'wholesale_rest_general_discount_cannot_create', __( 'The parameter wholesale_discount must be numeric.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
            }

            $wholesale_roles = $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

            // Validate wholesale role exist.
            if ( ! isset( $wholesale_roles[ $request['wholesale_role'] ] ) ) {
                return new WP_Error( 'wholesale_rest_general_discount_cannot_create', __( 'The wholesale role does not exist.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
            }

            // The wholesale role already exist in the general discount.
            $general_discounts = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, array() );
            if ( empty( $general_discounts ) ) {
                $general_discounts = array();
            }

            if ( isset( $general_discounts[ $request['wholesale_role'] ] ) ) {
                return new WP_Error( 'wholesale_rest_general_discount_cannot_create', __( 'General discount is already created for this wholesale role.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
            }

            // If all passed then create the general discount.
            $wholesale_role     = sanitize_text_field( $request['wholesale_role'] );
            $wholesale_discount = sanitize_text_field( $request['wholesale_discount'] );

            $general_discounts[ $wholesale_role ] = $wholesale_discount;

            update_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, $general_discounts, 'no' );

            $result = array(
                'message' => __( 'Successfully created new general discount.', 'woocommerce-wholesale-prices-premium' ),
                'data'    => array_map( 'floatval', $general_discounts ),
            );

            return new WP_REST_Response( apply_filters( 'wholesale_rest_general_discount_create_item', $result, $request ), 200 );
        }

        /**
         * Update item from the collection.
         * Only update when:
         * - wholesale_role and wholesale_discount parameter is provided
         * - General discount exist for the wholesale customer
         * - The wholesle role exist in the mapping
         *
         * @param WP_REST_Request $request Full details about the request.
         *
         * @since 1.27
         * @access public
         * @return WP_REST_Request
         */
        public function update_item( $request ) {

            if ( ! isset( $request['wholesale_role'] ) || ! isset( $request['wholesale_discount'] ) ) {
                return new WP_Error( 'wholesale_rest_general_discount_cannot_update', __( 'Please provide wholesale_role and wholesale_discount parameter.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
            }

            if ( ! is_numeric( $request['wholesale_discount'] ) ) {
                return new WP_Error( 'wholesale_rest_general_discount_cannot_update', __( 'The parameter wholesale_discount must be numeric.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
            }

            $wholesale_roles = $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

            // Validate wholesale role exist.
            if ( ! isset( $wholesale_roles[ $request['wholesale_role'] ] ) ) {
                return new WP_Error( 'wholesale_rest_general_discount_cannot_update', __( 'The wholesale role does not exist.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
            }

            // The wholesale role already exist in the general discount.
            $general_discounts = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, array() );

            if ( empty( $general_discounts ) ) {
                $general_discounts = array();
            }

            if ( ! isset( $general_discounts[ $request['wholesale_role'] ] ) ) {
                return new WP_Error( 'wholesale_rest_general_discount_cannot_update', __( 'General discount does not exist.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
            }

            // If all passed then update the general discount.
            $wholesale_role                       = sanitize_text_field( $request['wholesale_role'] );
            $wholesale_discount                   = sanitize_text_field( $request['wholesale_discount'] );
            $general_discounts[ $wholesale_role ] = $wholesale_discount;

            update_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, $general_discounts, 'no' );

            $result = array(
                'message' => __( 'Successfully updated general discount.', 'woocommerce-wholesale-prices-premium' ),
                'data'    => array_map( 'floatval', $general_discounts ),
            );

            return new WP_REST_Response( apply_filters( 'wholesale_rest_general_discount_update_item', $result, $request ), 200 );
        }

        /**
         * Delete item from the collection.
         * Only delete when:
         * - wholesale_role parameter is provided
         * - General discount is set for the wholesale customer
         * - The wholesle role exist in the mapping
         *
         * @param WP_REST_Request $request Full details about the request.
         *
         * @since 1.27
         * @access public
         * @return WP_REST_Request
         */
        public function delete_item( $request ) {

            if ( ! isset( $request['wholesale_role'] ) ) {
                return new WP_Error( 'wholesale_rest_general_discount_cannot_delete', __( 'Missing parameter wholesale role.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
            }

            $wholesale_roles = $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

            // Validate wholesale role exist.
            if ( ! isset( $wholesale_roles[ $request['wholesale_role'] ] ) ) {
                return new WP_Error( 'wholesale_rest_general_discount_cannot_delete', __( 'The wholesale role does not exist.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
            }

            $general_discounts = get_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, array() );
            if ( empty( $general_discounts ) ) {
                $general_discounts = array();
            }

            if ( ! isset( $general_discounts[ $request['wholesale_role'] ] ) ) {
                return new WP_Error( 'wholesale_rest_general_discount_cannot_delete', __( 'Unable to delete. General discount does not exist.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 400 ) );
            }

            // Remove general discount.
            $wholesale_role = sanitize_text_field( $request['wholesale_role'] );
            unset( $general_discounts[ $wholesale_role ] );

            update_option( WWPP_OPTION_WHOLESALE_ROLE_GENERAL_DISCOUNT_MAPPING, $general_discounts, 'no' );

            $result = array(
                'message' => __( 'Successfully deleted general discount.', 'woocommerce-wholesale-prices-premium' ),
                'data'    => $general_discounts,
            );

            return new WP_REST_Response( apply_filters( 'wholesale_rest_general_discount_delete_item', $result, $request ), 200 );
        }
    }

}
