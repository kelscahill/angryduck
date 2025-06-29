<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_Wholesale_Login_Logout' ) ) {

    /**
     * Model that houses the codebase that is executed on wholesale users logging in and logging out.
     *
     * @since 1.12.8
     */
    class WWPP_Wholesale_Login_Logout {

        /**
         * Property that holds the single main instance of WWPP_Wholesale_Login_Logout.
         *
         * @since 1.12.8
         * @access private
         * @var WWPP_Wholesale_Login_Logout
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
         * WWPP_Wholesale_Login_Logout constructor.
         *
         * @since 1.12.8
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Login_Logout model.
         */
        public function __construct( $dependencies ) {

            $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
        }

        /**
         * Ensure that only one instance of WWPP_Wholesale_Login_Logout is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.12.8
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Login_Logout model.
         * @return WWPP_Wholesale_Login_Logout
         */
        public static function instance( $dependencies ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Initialize the session and the cart when a wholesale user logs in.
         * We are setting flags on options coz cart is not yet available at this moment. It will only be available on wp_loaded.
         *
         * @param string  $user_login Username.
         * @param WP_User $user       WP_User object.
         *
         * @since 1.12.2
         * @since 1.12.5 Refactor codebase.
         * @since 1.12.8 Refactor codebase. Moved to separate model from plugin.php.
         */
        public function initialize_session_and_cart_on_login( $user_login, $user ) {

            $all_wholesale_role_keys = array();
            foreach ( $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles() as $wholesale_role_key => $wholesale_role ) {
                $all_wholesale_role_keys[] = $wholesale_role_key;
            }

            $user_wholesale_role = array_intersect( $user->roles, $all_wholesale_role_keys );

            if ( ! empty( $user_wholesale_role ) ) {

                if ( get_option( 'wwpp_settings_clear_cart_on_login' ) === 'yes' ) {
                    update_option( 'wwpp_clear_cart_on_login', 'yes', 'no' );
                }

                update_option( 'wwpp_refresh_cart_session', 'yes', 'no' );

            }
        }

        /**
         * Clear wholesale user cart session. To reset also any mapped tax exemptions back to default.
         * This is past wp_loaded, so cart is available here.
         *
         * @since 1.12.2
         * @since 1.12.5 Refactor codebase.
         * @since 1.12.8 Refactor codebase. Moved to separate model from plugin.php.
         */
        public function clear_session_on_logout() {

            $user_wholesale_role = $this->_wwpp_wholesale_roles->getUserWholesaleRole();
            if ( ! empty( $user_wholesale_role ) && WC()->session ) {
                WC()->session->destroy_session();
            }
        }

        /**
         * Refresh cart session properly on the right time. On the right time part is important. You have to trigger this on wp_loaded function.
         *
         * @since 1.12.5
         * @since 1.12.8 Refactor codebase. Moved to separate model from plugin.php.
         * @access public
         */
        public function initialize_session_and_cart() {

            if ( get_option( 'wwpp_clear_cart_on_login', false ) === 'yes' ) {

                if ( WC()->cart ) {
                    WC()->cart->empty_cart();
                }

                if ( WC()->session ) {
                    WC()->session->destroy_session();
                }

                delete_option( 'wwpp_clear_cart_on_login' );

            }

            if ( get_option( 'wwpp_refresh_cart_session', false ) === 'yes' ) {

                // Explicitly trigger to refresh cart totals.

                if ( WC()->session ) {
                    WC()->session->set( 'refresh_totals', true );
                }

                if ( WC()->cart ) {
                    WC()->cart->calculate_totals();
                }

                // Clear site caches.
                wp_cache_flush();

                delete_option( 'wwpp_refresh_cart_session' );

            }
        }

        /**
         * Execute model.
         *
         * @since 1.12.8
         */
        public function run() {

            add_action( 'wp_login', array( $this, 'initialize_session_and_cart_on_login' ), 10, 2 );
            add_action( 'wp_logout', array( $this, 'clear_session_on_logout' ), 10 );
            add_action( 'wp_loaded', array( $this, 'initialize_session_and_cart' ), 10 );
        }
    }

}
