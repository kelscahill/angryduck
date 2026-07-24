<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that houses the logic relating wholesale roles.
 *
 * @since 1.14.0
 */
class WWPP_Wholesale_Roles extends WWP_Wholesale_Roles {

    /**
     * Class Properties
     */

    /**
     * Property that holds the single main instance of WWPP_Wholesale_Role.
     *
     * @since 1.14.0
     * @access private
     * @var WWPP_Wholesale_Roles
     */
    private static $_instance;

    /**
     * Class Methods
     */

    /**
     * WWPP_Wholesale_Role constructor.
     *
     * @since 1.14.0
     * @access public
     */
    public function __construct() {}

    /**
     * Ensure that only one instance of WWPP_Wholesale_Role is loaded or can be loaded (Singleton Pattern).
     *
     * @since 1.14.0
     * @access public
     *
     * @return WWPP_Wholesale_Roles
     */
    public static function instance() {
        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Get all registered wholesale roles.
     *
     * @since 1.0.0
     * @since 1.14.0 Refactor codebase and move it to its proper model.
     * @since 2.1.1 Require the manage_woocommerce capability before exposing the role configuration over
     *              AJAX so low-privilege authenticated users cannot enumerate the wholesale-role setup.
     * @access public
     *
     * @return array|void Array of registered wholesale roles.
     */
    public function get_all_registered_wholesale_roles() {
        $all_registered_wholesale_roles = $this->getAllRegisteredWholesaleRoles();

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

            // This AJAX endpoint is only consumed by store-management screens; do not disclose the
            // wholesale-role configuration to users who cannot manage the store.
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error(
                    array( 'message' => __( 'You are not allowed to perform this action.', 'woocommerce-wholesale-prices-premium' ) ),
                    403
                );
            }

            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode( $all_registered_wholesale_roles );
            wp_die();
        } else {
            return $all_registered_wholesale_roles;
        }
    }

    /**
     * Register model ajax handlers.
     *
     * @since 1.14.0
     * @access public
     */
    public function register_ajax_handler() {
        add_action( 'wp_ajax_wwppGetAllRegisteredWholesaleRoles', array( $this, 'get_all_registered_wholesale_roles' ) );
    }

    /**
     * Execute model.
     *
     * @since 1.14.0
     * @access public
     */
    public function run() {
        add_action( 'init', array( $this, 'register_ajax_handler' ) );
    }
}
