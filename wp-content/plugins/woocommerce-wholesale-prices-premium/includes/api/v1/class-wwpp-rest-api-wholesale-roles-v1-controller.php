<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_REST_Wholesale_Roles_V1_Controller' ) ) {

    /**
     * Model that houses the logic of WWPP integration with WC API WPP Wholesale Products.
     *
     * @since 1.19
     */
    class WWPP_REST_Wholesale_Roles_V1_Controller extends WWP_REST_Wholesale_Roles_V1_Controller {


        /**
         * WWPP object.
         *
         * @var object
         */
        protected $wc_wholesale_prices_premium;

        /**
         * WWPP_REST_Wholesale_Roles_V1_Controller constructor.
         *
         * @since 1.19
         * @access public
         */
        public function __construct() {
            global $wc_wholesale_prices_premium;

            $this->wc_wholesale_prices_premium = $wc_wholesale_prices_premium;

            // Fires when preparing to serve an API request.
            add_action( 'rest_api_init', array( $this, 'register_routes' ) );

            // Update item.
            add_filter( 'wwp_api_update_wholesale_roles_filter', array( $this, 'update_role' ), 10, 2 );
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
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => array( $this, 'create_wholesale_role' ),
                        'permission_callback' => array( $this, 'permissions_check' ),
                    ),
                )
            );

            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/(?P<role_key>[a-z0-9_]*)',
                array(
                    array(
                        'methods'             => WP_REST_Server::DELETABLE,
                        'callback'            => array( $this, 'delete_wholesale_role' ),
                        'permission_callback' => array( $this, 'permissions_check' ),
                    ),
                )
            );
        }

        /**
         * Create new wholesale role.
         *
         * @param WP_REST_Request $request REST request object.
         *
         * @since 1.19
         * @since 1.23.9 Removed shippingClassName and shippingClassTermId when creating a role via api.
         * @access public
         * @return WP_Error|WP_REST_Request
         */
        public function create_wholesale_role( $request ) {
            $wholesale_roles = $this->getAllRegisteredWholesaleRoles();
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

                update_option( WWP_OPTIONS_REGISTERED_CUSTOM_ROLES, serialize( $updated_wholesale_roles ), 'no' ); //phpcs:ignore

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

            return new WP_Error( 'wholesale_rest_role_cannot_create', __( 'Can\'t create item. Please make sure "role_key" is unique. Both "role_key" and "role_name" property are required.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
        }

        /**
         * Update wholesale role data from the collection.
         *
         * @param array           $wholesale_roles Array of wholesale roles.
         * @param WP_REST_Request $request         REST request object.
         *
         * @since 1.19
         * @since 1.23.9 Removed shippingClassName and shippingClassTermId when updating a role via api.
         * @since 1.26.1 Utilize the function to update the wholesale role in class WWPP_Wholesale_Roles_Admin_Page function edit_wholesale_role()
         *
         * @access public
         * @return WP_Error|WP_REST_Request
         */
        public function update_role( $wholesale_roles, $request ) {
            global $wc_wholesale_prices_premium;

            $wholesale_roles = apply_filters( 'wwpp_api_update_wholesale_roles_filter', $wholesale_roles, $request );
            $role_key        = isset( $request['role_key'] ) ? sanitize_text_field( $request['role_key'] ) : '';

            if ( '' !== $role_key && isset( $wholesale_roles[ $role_key ] ) ) {
                if ( isset( $request['only_allow_wholesale_purchases'] ) && in_array( $request['only_allow_wholesale_purchases'], array( 'yes', 'no' ), true ) ) {
                    $wholesale_roles[ $role_key ]['onlyAllowWholesalePurchases'] = $request['only_allow_wholesale_purchases'];
                }

                // Set args needed to update the wholesale role.
                $wholesale_role_data            = $wholesale_roles[ $role_key ];
                $wholesale_role_data['roleKey'] = $role_key;

                if ( isset( $wholesale_role_data['desc'] ) ) {
                    $wholesale_role_data['roleDesc'] = $wholesale_role_data['desc'];
                    unset( $wholesale_role_data['desc'] );
                }

                // Update Role using the function from WWPP edit wholesale role.
                $wc_wholesale_prices_premium->wwpp_wholesale_roles_admin_page->edit_wholesale_role( $wholesale_role_data );

            }

            return $wholesale_roles;
        }

        /**
         * Delete one item from the collection.
         *
         * @param WP_REST_Request $request REST request object.
         *
         * @since 1.19
         * @access public
         * @return WP_Error|WP_REST_Request
         */
        public function delete_wholesale_role( $request ) {
            $wholesale_roles = $this->getAllRegisteredWholesaleRoles();
            $role_key        = isset( $request['role_key'] ) ? $request['role_key'] : '';

            if ( ! empty( $role_key ) && isset( $wholesale_roles[ $role_key ] ) ) {

                if ( 'wholesale_customer' === $role_key ) {
                    return new WP_Error( 'wholesale_rest_role_cannot_delete', __( 'You can\'t delete this role.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
                }

                $data = $wholesale_roles[ $role_key ];

                unset( $wholesale_roles[ $role_key ] );

                $wholesale_roles = apply_filters( 'wwpp_api_delete_wholesale_roles_filter', $wholesale_roles, $request );

                update_option( WWP_OPTIONS_REGISTERED_CUSTOM_ROLES, serialize( $wholesale_roles ), 'no' ); //phpcs:ignore

                $result = array(
                    'message' => 'Wholesale Role "' . $role_key . '" has been deleted.',
                    'data'    => array( $role_key => $data ),

                );

                // Unregister custom role from wp.
                $this->remove_custom_role( $role_key );

                return new WP_REST_Response( $result, 200 );

            }

            return new WP_Error( 'wholesale_rest_role_cannot_delete', __( 'Wholesale role not found.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => rest_authorization_required_code() ) );
        }

        /**
         * Register Custom Role
         *
         * @param array $new_role Role data.
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
         * @param string $role_key Role key.
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
