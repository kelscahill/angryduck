<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Classes
 */

namespace RymeraWebCo\WWOF\Post_Types;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Factories\Order_Form;
use RymeraWebCo\WWOF\Helpers\WWP;

/**
 * For `order_form` custom post type.
 *
 * @since 3.0
 */
class Order_Form_Post_Type extends Abstract_Class {

    /**
     * Integration of WC Navigation Bar.
     *
     * @since  1.15
     * @access public
     */
    public function wc_navigation_bar() {

        if ( function_exists( 'wc_admin_connect_page' ) ) {
            wc_admin_connect_page(
                array(
                    'id'        => 'woocommerce-wholesale-order-form',
                    'screen_id' => WWP::is_v2() ? 'wholesale_page_order-forms' : 'woocommerce_page_order-forms',
                    'title'     => __( 'Order Forms', 'woocommerce-wholesale-order-form' ),
                )
            );
        }
    }

    /**
     * Register Order Form CPT.
     *
     * @since  1.15
     * @access public
     */
    public function register_post_type() {

        $labels = array(
            'name'               => __( 'Order Forms', 'woocommerce-wholesale-order-form' ),
            'singular_name'      => __( 'Order Form', 'woocommerce-wholesale-order-form' ),
            'menu_name'          => __( 'Order Forms', 'woocommerce-wholesale-order-form' ),
            'parent_item_colon'  => __( 'Parent Order Form', 'woocommerce-wholesale-order-form' ),
            'all_items'          => __( 'Order Forms', 'woocommerce-wholesale-order-form' ),
            'view_item'          => __( 'View Order Form', 'woocommerce-wholesale-order-form' ),
            'add_new_item'       => __( 'Add Order Form', 'woocommerce-wholesale-order-form' ),
            'add_new'            => __( 'New Order Form', 'woocommerce-wholesale-order-form' ),
            'edit_item'          => __( 'Edit Order Form', 'woocommerce-wholesale-order-form' ),
            'update_item'        => __( 'Update Order Form', 'woocommerce-wholesale-order-form' ),
            'search_items'       => __( 'Search Order Forms', 'woocommerce-wholesale-order-form' ),
            'not_found'          => __( 'No Order Form found', 'woocommerce-wholesale-order-form' ),
            'not_found_in_trash' => __( 'No Order Forms found in Trash', 'woocommerce-wholesale-order-form' ),
        );

        $args = array(
            'label'               => __( 'Order Forms', 'woocommerce-wholesale-order-form' ),
            'description'         => __( 'Order Forms CPT', 'woocommerce-wholesale-order-form' ),
            'labels'              => $labels,
            'query_var'           => true,
            'rewrite'             => array(
                'slug'       => Order_Form::POST_TYPE,
                'with_front' => false,
                'pages'      => false,
            ),
            'can_export'          => true,
            'exclude_from_search' => true,
            'publicly_queryable'  => true,
            'capability_type'     => 'post',

            /*
            |--------------------------------------------------------------------------
            | Disable default REST for Order Form Custom Post Type
            |--------------------------------------------------------------------------
            |
            |We set to false so, it is not included on wp/v2 REST API namespace.
            |
            */
            'show_in_rest'        => false,
        );

        register_post_type(
            Order_Form::POST_TYPE,
            /**
             * Filters the arguments for registering the `order_form` custom post type.
             *
             * @param array $args   Array of arguments for registering a post type.
             * @param array $labels Array of labels for the post type.
             *
             * @since 3.0
             */
            apply_filters( 'order_forms_cpt_args', $args, $labels )
        );

        /**
         * Action hook to run after registering the `order_form` custom post type.
         *
         * @param string $post_type The post type name.
         *
         * @since 3.0
         */
        do_action( 'wwof_after_register_order_form_post_type', Order_Form::POST_TYPE );
    }

    /**
     * Registers the `order_form` custom post type.
     *
     * @since 3.0
     */
    public function run() {

        // WooCommerce Navigation Bar.
        add_action( 'init', array( $this, 'wc_navigation_bar' ) );

        // Register Order Form CPT.
        add_action( 'init', array( $this, 'register_post_type' ) );
    }
}
