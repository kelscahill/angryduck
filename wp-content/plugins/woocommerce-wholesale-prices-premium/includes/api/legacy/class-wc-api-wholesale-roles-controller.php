<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_API_Wholesale_Roles_Controller' ) ) {

    /**
     * Model that houses the logic of WWPP integration with WC API WPP Wholesale Products.
     *
     * @since 1.19
     */
    class WWPP_API_Wholesale_Roles_Controller extends WC_REST_Controller {

        /**
         * Property that holds the single main instance of WWPP_API_Wholesale_Roles_Controller.
         *
         * @var WWPP_API_Wholesale_Roles_Controller
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
        protected $rest_base = 'wholesale/roles';

        /**
         * WWPP object.
         *
         * @var object
         */
        protected $wc_wholesale_prices_premium;

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWPP_API_Wholesale_Roles_Controller constructor.
         *
         * @since 1.19
         * @access public
         */
        public function __construct() {

            global $wc_wholesale_prices_premium;

            $this->wc_wholesale_prices_premium = $wc_wholesale_prices_premium;

            // Fires when preparing to serve an API request.
            add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        }

        /**
         * Ensure that only one instance of WWPP_API_Wholesale_Roles_Controller is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.19
         * @access public
         *
         * @return WWPP_API_Wholesale_Roles_Controller
         */
        public static function instance() {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        /**
         * Register routes for wholesale roles API.
         *
         * @since 1.19
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
                    ),
                    array(
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => array( $this, 'create_item' ),
                        'permission_callback' => array( $this, 'permissions_check' ),
                    ),
                )
            );

            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/(?P<role_key>[a-z0-9_]*)',
                array(
                    array(
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => array( $this, 'get_item' ),
                        'permission_callback' => array( $this, 'permissions_check' ),
                    ),
                    array(
                        'methods'             => WP_REST_Server::EDITABLE,
                        'callback'            => array( $this, 'update_item' ),
                        'permission_callback' => array( $this, 'permissions_check' ),
                    ),
                    array(
                        'methods'             => WP_REST_Server::DELETABLE,
                        'callback'            => array( $this, 'delete_item' ),
                        'permission_callback' => array( $this, 'permissions_check' ),
                    ),
                )
            );
        }

        /**
         * Check permission if manipulation is allowed for this user. Currently we allow all, not sure yet if we need restrictions.
         *
         * @since 1.19
         * @access public
         *
         * @param WP_REST_Request $request Full details about the request.
         *
         * @return bool
         */
        public function permissions_check( $request ) {

            return true; // modify later on if we want to do some restrictions.
        }

        /**
         * Get all items from the collection.
         *
         * @param WP_REST_Request $request Full details about the request.
         *
         * @since 1.19
         * @access public
         * @return WP_REST_Request
         */
        public function get_items( $request ) {

            $wholesale_roles = apply_filters( 'wwpp_api_fetch_wholesale_role_filter', $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->getAllRegisteredWholesaleRoles(), $request );

            return new WP_REST_Response( $wholesale_roles, 200 );
        }

        /**
         * Get one item from the collection.
         *
         * @param WP_REST_Request $request Full details about the request.
         *
         * @since 1.19
         * @access public
         * @return WP_Error|WP_REST_Request
         */
        public function get_item( $request ) {

            $wholesale_roles = $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $role_key        = isset( $request['role_key'] ) ? $request['role_key'] : '';

            if ( ! empty( $role_key ) && isset( $wholesale_roles[ $role_key ] ) ) {

                $wholesale_role = apply_filters( 'wwpp_api_fetch_wholesale_role_filter', $wholesale_roles[ $role_key ], $request );

                return new WP_REST_Response( $wholesale_role, 200 );

            }

            return new WP_Error( 'cant-fetch', __( 'Item not found.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
        }

        /**
         * Create new wholesale role.
         *
         * @param WP_REST_Request $request Full details about the request.
         *
         * @since 1.19
         * @since 1.23.9 Removed shippingClassName and shippingClassTermId when creating a role via api.
         * @access public
         * @return WP_Error|WP_REST_Request
         */
        public function create_item( $request ) {

            $wholesale_roles = $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $role_key        = isset( $request['role_key'] ) ? $request['role_key'] : '';
            $role_key        = preg_replace( '/[^a-zA-Z0-9_]/', '', $role_key );

            if ( ! empty( $role_key ) && ! isset( $wholesale_roles[ $role_key ] ) && isset( $request['role_name'] ) ) {

                $wholesale_roles[ $role_key ]['roleName'] = $request['role_name'];
                $wholesale_roles[ $role_key ]['desc']     = '';

                if ( isset( $request['description'] ) ) {
                    $wholesale_roles[ $role_key ]['desc'] = $request['description'];
                }

                if ( isset( $request['only_allow_wholesale_purchases'] ) && in_array( $request['only_allow_wholesale_purchases'], array( 'yes', 'no' ), true ) ) {
                    $wholesale_roles[ $role_key ]['onlyAllowWholesalePurchases'] = $request['only_allow_wholesale_purchases'];
                } else {
                    $wholesale_roles[ $role_key ]['onlyAllowWholesalePurchases'] = 'no';
                }

                $updated_wholesale_roles = apply_filters( 'wwpp_api_update_wholesale_roles_filter', $wholesale_roles, $request );

                update_option( WWP_OPTIONS_REGISTERED_CUSTOM_ROLES, serialize( $updated_wholesale_roles ), 'no' ); // phpcs:ignore.

                $result = array(
                    'message' => 'Successfully created role "' . $role_key . '"',
                    'data'    => array( $role_key => $wholesale_roles[ $role_key ] ),
                );

                // Register Custom Role.
                $this->register_custom_role(
                    array(
                        'roleKey'                     => $role_key,
                        'roleName'                    => $request['role_name'],
                        'roleDesc'                    => isset( $request['description'] ) ? $request['description'] : '',
                        'onlyAllowWholesalePurchases' => $wholesale_roles[ $role_key ]['onlyAllowWholesalePurchases'],
                    )
                );

                return new WP_REST_Response( $result, 200 );

            }

            return new WP_Error( 'cant-create', __( 'Can\'t create item. Please make sure "role_key" is unique. Both "role_key" and "role_name" property are required.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
        }

        /**
         * Update one item from the collection.
         *
         * @param WP_REST_Request $request Full details about the request.
         *
         * @since 1.19
         * @since 1.23.9 Removed shippingClassName and shippingClassTermId when updating a role via api.
         * @access public
         * @return WP_Error|WP_REST_Request
         */
        public function update_item( $request ) {

            $wholesale_roles = $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $role_key        = isset( $request['role_key'] ) ? $request['role_key'] : '';

            if ( ! empty( $role_key ) && isset( $wholesale_roles[ $role_key ] ) ) {

                if ( isset( $request['role_name'] ) ) {
                    $wholesale_roles[ $role_key ]['roleName'] = $request['role_name'];
                }

                if ( isset( $request['description'] ) ) {
                    $wholesale_roles[ $role_key ]['desc'] = $request['description'];
                }

                if ( isset( $request['only_allow_wholesale_purchases'] ) && in_array( $request['only_allow_wholesale_purchases'], array( 'yes', 'no' ), true ) ) {
                    $wholesale_roles[ $role_key ]['onlyAllowWholesalePurchases'] = $request['only_allow_wholesale_purchases'];
                }

                $wholesale_roles = apply_filters( 'wwpp_api_update_wholesale_roles_filter', $wholesale_roles, $request );

                update_option( WWP_OPTIONS_REGISTERED_CUSTOM_ROLES, serialize( $wholesale_roles ), 'no' ); // phpcs:ignore.

                $result = array(
                    'message' => 'Wholesale Role "' . $role_key . '" has been updated.',
                    'data'    => array( $role_key => $wholesale_roles[ $role_key ] ),
                );

                return new WP_REST_Response( $result, 200 );

            }

            return new WP_Error( 'cant-update', __( 'Item not found.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
        }

        /**
         * Delete one item from the collection.
         *
         * @param WP_REST_Request $request Full details about the request.
         *
         * @since 1.19
         * @access public
         * @return WP_Error|WP_REST_Request
         */
        public function delete_item( $request ) {

            $wholesale_roles = $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();
            $role_key        = isset( $request['role_key'] ) ? $request['role_key'] : '';

            if ( ! empty( $role_key ) && isset( $wholesale_roles[ $role_key ] ) ) {

                $data = $wholesale_roles[ $role_key ];

                unset( $wholesale_roles[ $role_key ] );

                $wholesale_roles = apply_filters( 'wwpp_api_delete_wholesale_roles_filter', $wholesale_roles, $request );

                update_option( WWP_OPTIONS_REGISTERED_CUSTOM_ROLES, serialize( $wholesale_roles ), 'no' ); // phpcs:ignore.

                $result = array(
                    'message' => 'Wholesale Role "' . $role_key . '" has been deleted.',
                    'data'    => array( $role_key => $data ),

                );

                // Unregister custom role from wp.
                $this->remove_custom_role( $role_key );

                return new WP_REST_Response( $result, 200 );

            }

            return new WP_Error( 'cant-delete', __( 'Item not found.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
        }

        /**
         * Register Custom Role
         *
         * @param array $new_role New role data.
         *
         * @since 1.24.4
         * @access public
         */
        public function register_custom_role( $new_role ) {

            // Add plugin custom roles and capabilities.
            $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->addCustomRole( $new_role['roleKey'], $new_role['roleName'] );
            $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->registerCustomRole(
                $new_role['roleKey'],
                $new_role['roleName'],
                array(
                    'desc'                        => $new_role['roleDesc'],
                    'onlyAllowWholesalePurchases' => $new_role['onlyAllowWholesalePurchases'],
                )
            );
            $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->addCustomCapability( $new_role['roleKey'], 'have_wholesale_price' );
        }

        /**
         * Unregister custom role from wp
         *
         * @param string $role_key New role data.
         *
         * @since 1.24.4
         * @access public
         */
        public function remove_custom_role( $role_key ) {

            // Remove plugin custom roles and capabilities.
            $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->removeCustomCapability( $role_key, 'have_wholesale_price' );
            $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->removeCustomRole( $role_key );
            $this->wc_wholesale_prices_premium->wwpp_wholesale_roles->unregisterCustomRole( $role_key );
        }
    }

}

return new WWPP_API_Wholesale_Roles_Controller();
