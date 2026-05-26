<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Roles_Controller' ) ) {
    /**
     * Analytics API controller for retrieving all registered wholesale roles.
     *
     * @since 2.2.0
     */
    class WWPP_Roles_Controller {

        /**
         * Namespace of the API.
         *
         * @var string
         */
        private $namespace = 'wholesale/v1/analytics';

        /**
         * API base name.
         *
         * @var string
         */
        private $rest_base = 'roles';

        /**
         * Register the REST route for the roles endpoint.
         *
         * @since 2.2.0
         * @return void
         */
        public function run() {
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base,
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_roles' ),
                    'permission_callback' => array( $this, 'get_roles_permission_callback' ),
                )
            );
        }

        /**
         * Return all registered wholesale roles as a flat list of name/value entries.
         *
         * Each entry includes:
         *   - role_name  (string) Human-readable label.
         *   - role_value (string) Role key used internally.
         *
         * @since 2.2.0
         * @return \WP_REST_Response|\WP_Error
         */
        public function get_roles() {
            try {
                $roles           = array();
                $wholesale_roles = maybe_unserialize( get_option( WWP_OPTIONS_REGISTERED_CUSTOM_ROLES, array() ) );

                if ( ! is_array( $wholesale_roles ) ) {
                    return rest_ensure_response( $roles );
                }

                foreach ( $wholesale_roles as $role_key => $role_data ) {
                    $roles[] = array(
                        'role_name'  => isset( $role_data['roleName'] ) ? sanitize_text_field( $role_data['roleName'] ) : $role_key,
                        'role_value' => sanitize_key( $role_key ),
                    );
                }

                return rest_ensure_response( $roles );
            } catch ( \Exception $e ) {
                wc_get_logger()->error( $e->getMessage(), array( 'source' => 'wwpp-analytics' ) );
                return new \WP_Error( 'wwpp_api_error', __( 'An internal server error occurred.', 'woocommerce-wholesale-prices-premium' ), array( 'status' => 500 ) );
            }
        }

        /**
         * Permission callback — requires WooCommerce management capability.
         *
         * @since 2.2.0
         * @return bool
         */
        public function get_roles_permission_callback() {
            return current_user_can( 'manage_woocommerce' );
        }
    }
}
