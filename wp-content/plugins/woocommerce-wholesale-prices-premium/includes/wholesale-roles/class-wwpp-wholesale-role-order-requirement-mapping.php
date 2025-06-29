<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that houses logic of wholesale role order requirement mapping.
 *
 * @since 1.14.0
 */
class WWPP_Wholesale_Role_Order_Requirement_Mapping {

    /**
     * Class Properties
     */

    /**
     * Property that holds the single main instance of WWPP_Wholesale_Role_Order_Requirement_Mapping.
     *
     * @since 1.14.0
     * @access private
     * @var WWPP_Wholesale_Role_Order_Requirement_Mapping
     */
    private static $_instance;

    /**
     * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
     *
     * @since 1.14.0
     * @access private
     * @var WWPP_Wholesale_Roles
     */
    private $_wwpp_wholesale_roles;

    /**
     * Class Methods
     */

    /**
     * WWPP_Wholesale_Role_Order_Requirement_Mapping constructor.
     *
     * @since 1.14.0
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Role_Order_Requirement_Mapping model.
     */
    public function __construct( $dependencies ) {
        $this->_wwpp_wholesale_roles = $dependencies['WWPP_Wholesale_Roles'];
    }

    /**
     * Ensure that only one instance of WWPP_Wholesale_Role_Order_Requirement_Mapping is loaded or can be loaded (Singleton Pattern).
     *
     * @since 1.14.0
     * @access public
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWPP_Wholesale_Role_Order_Requirement_Mapping model.
     * @return WWPP_Wholesale_Role_Order_Requirement_Mapping
     */
    public static function instance( $dependencies ) {
        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( $dependencies );
        }

        return self::$_instance;
    }

    /**
     * Add an entry to wholesale role / order requirement mapping.
     * Design based on trust that the caller will supply an array with the following elements below.
     * wholesale_role
     * minimum_order_quantity
     * minimum_order_subtotal
     * minimum_order_logic
     *
     * @since 1.5.0
     * @since 1.14.0 Refactor codebase and move to its proper model.
     * @access public
     *
     * @param null|array $mapping Entry of order requirement for wholesale role.
     */
    public function add_wholesale_role_order_requirement( $mapping = null ) {
        // Security checks.
        if ( ! check_ajax_referer( 'wwpp_add_wholesale_role_order_requirement', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        $mapping = array_map( 'sanitize_text_field', $_POST['mapping'] );

        $order_requirement_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_ORDER_REQUIREMENT_MAPPING, array() );
        if ( ! is_array( $order_requirement_mapping ) ) {
            $order_requirement_mapping = array();
        }

        if ( array_key_exists( $mapping['wholesale_role'], $order_requirement_mapping ) ) {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Duplicate Wholesale Role Order Requirement Entry, Already Exist', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            $wholesale_role = $mapping['wholesale_role'];
            unset( $mapping['wholesale_role'] );
            $order_requirement_mapping[ $wholesale_role ] = $mapping;
            update_option( WWPP_OPTION_WHOLESALE_ROLE_ORDER_REQUIREMENT_MAPPING, $order_requirement_mapping, 'no' );
            $response = array( 'status' => 'success' );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Edit an entry of wholesale role / order requirement mapping.
     *
     * Design based on trust that the caller will supply an array with the following elements below.
     *
     * wholesale_role
     * minimum_order_quantity
     * minimum_order_subtotal
     * minimum_order_logic
     *
     * @since 1.5.0
     * @since 1.14.0 Refactor codebase and move to its proper model.
     * @access public
     *
     * @param null|array $mapping Entry of order requirement for wholesale role.
     */
    public function edit_wholesale_role_order_requirement( $mapping = null ) {
        // Security checks.
        if ( ! check_ajax_referer( 'wwpp_edit_wholesale_role_order_requirement', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        $mapping = array_map( 'sanitize_text_field', $_POST['mapping'] );

        $order_requirement_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_ORDER_REQUIREMENT_MAPPING, array() );
        if ( ! is_array( $order_requirement_mapping ) ) {
            $order_requirement_mapping = array();
        }

        if ( ! array_key_exists( $mapping['wholesale_role'], $order_requirement_mapping ) ) {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Wholesale Role Order Requirement Entry You Wish To Edit Does Not Exist', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            $wholesale_role = $mapping['wholesale_role'];
            unset( $mapping['wholesale_role'] );
            $order_requirement_mapping[ $wholesale_role ] = $mapping;
            update_option( WWPP_OPTION_WHOLESALE_ROLE_ORDER_REQUIREMENT_MAPPING, $order_requirement_mapping, 'no' );
            $response = array( 'status' => 'success' );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Delete an entry of wholesale role / order requirement mapping.
     *
     * @since 1.5.0
     * @since 1.14.0 Refactor codebase and move to its proper model.
     * @access public
     *
     * @param null|string $wholesale_role Wholesale role key.
     */
    public function delete_wholesale_role_order_requirement( $wholesale_role = null ) {
        // Security checks.
        if ( ! check_ajax_referer( 'wwpp_delete_wholesale_role_order_requirement', 'nonce', false ) ) {
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
            echo wp_json_encode(
                array(
                    'status'    => 'fail',
                    'error_msg' => __(
                        'Security check failed',
                        'woocommerce-wholesale-prices-premium'
                    ),
                )
            );
            wp_die();
        }

        $wholesale_role = sanitize_text_field( $_POST['wholesale_role'] );

        $order_requirement_mapping = get_option( WWPP_OPTION_WHOLESALE_ROLE_ORDER_REQUIREMENT_MAPPING, array() );
        if ( ! is_array( $order_requirement_mapping ) ) {
            $order_requirement_mapping = array();
        }

        if ( ! array_key_exists( $wholesale_role, $order_requirement_mapping ) ) {
            $response = array(
                'status'        => 'fail',
                'error_message' => __( 'Wholesale Role Order Requirement Entry You Wish To Delete Does Not Exist', 'woocommerce-wholesale-prices-premium' ),
            );
        } else {
            unset( $order_requirement_mapping[ $wholesale_role ] );
            update_option( WWPP_OPTION_WHOLESALE_ROLE_ORDER_REQUIREMENT_MAPPING, $order_requirement_mapping, 'no' );
            $response = array( 'status' => 'success' );
        }

        @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) ); // phpcs:ignore
        echo wp_json_encode( $response );
        wp_die();
    }

    /**
     * Register model ajax handlers.
     *
     * @since 1.14.0
     * @access public
     */
    public function register_ajax_handler() {
        add_action( 'wp_ajax_wwpp_add_wholesale_role_order_requirement', array( $this, 'add_wholesale_role_order_requirement' ) );
        add_action( 'wp_ajax_wwpp_edit_wholesale_role_order_requirement', array( $this, 'edit_wholesale_role_order_requirement' ) );
        add_action( 'wp_ajax_wwpp_delete_wholesale_role_order_requirement', array( $this, 'delete_wholesale_role_order_requirement' ) );
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
