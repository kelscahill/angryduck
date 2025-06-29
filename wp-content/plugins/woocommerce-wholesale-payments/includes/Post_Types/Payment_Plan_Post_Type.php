<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WPay\Post_Types
 */

namespace RymeraWebCo\WPay\Post_Types;

use RymeraWebCo\WPay\Abstracts\Abstract_Class;
use RymeraWebCo\WPay\Classes\Settings_Page;
use RymeraWebCo\WPay\Factories\Payment_Plan;
use RymeraWebCo\WPay\Helpers\Helper;

/**
 * Payment_Plan_Post_type class.
 *
 * @since 1.0.0
 */
class Payment_Plan_Post_Type extends Abstract_Class {

    /**
     * Register payment plan post type.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_post_type() {

        $labels = array(
            'name'               => __( 'Payment Plans', 'woocommerce-wholesale-payments' ),
            'singular_name'      => __( 'Payment Plan', 'woocommerce-wholesale-payments' ),
            'menu_name'          => __( 'Payment Plans', 'woocommerce-wholesale-payments' ),
            'parent_item_colon'  => __( 'Parent Payment Plan', 'woocommerce-wholesale-payments' ),
            'all_items'          => __( 'Payment Plans', 'woocommerce-wholesale-payments' ),
            'view_item'          => __( 'View Payment Plan', 'woocommerce-wholesale-payments' ),
            'add_new_item'       => __( 'Add Payment Plan', 'woocommerce-wholesale-payments' ),
            'add_new'            => __( 'New Payment Plan', 'woocommerce-wholesale-payments' ),
            'edit_item'          => __( 'Edit Payment Plan', 'woocommerce-wholesale-payments' ),
            'update_item'        => __( 'Update Payment Plan', 'woocommerce-wholesale-payments' ),
            'search_items'       => __( 'Search Payment Plans', 'woocommerce-wholesale-payments' ),
            'not_found'          => __( 'No Payment Plan found', 'woocommerce-wholesale-payments' ),
            'not_found_in_trash' => __( 'No Payment Plans found in Trash', 'woocommerce-wholesale-payments' ),
        );

        $args = array(
            'label'               => __( 'Payment Plans', 'woocommerce-wholesale-payments' ),
            'description'         => __( 'Payment Plans CPT', 'woocommerce-wholesale-payments' ),
            'labels'              => $labels,
            'query_var'           => true,
            'rewrite'             => array(
                'slug'       => Payment_Plan::POST_TYPE,
                'with_front' => false,
                'pages'      => false,
            ),
            'can_export'          => true,
            'exclude_from_search' => true,
            'publicly_queryable'  => true,
            'capability_type'     => 'post',

            /***************************************************************************
             * Disable default REST for Payment Plan Custom Post Type
             ***************************************************************************
             *
             * We set to false so, it is not included on wp/v2 REST API namespace.
             */
            'show_in_rest'        => false,
        );

        register_post_type( Payment_Plan::POST_TYPE, $args );

        do_action( 'wpay_after_register_' . Payment_Plan::POST_TYPE . '_post_type' );
    }

    /**
     * Redirect payment plan post type to admin page.
     *
     * @return void
     */
    public function redirect_post_type_to_admin_page() {

        if ( Helper::is_ajax_or_cli() ) {
            return;
        }

        if ( filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) === Payment_Plan::POST_TYPE ) {
            wp_safe_redirect( admin_url( 'admin.php?page=' . Settings_Page::MENU_SLUG . '&subpage=payment_plans' ) );
            exit;
        }
    }

    /**
     * Run post type actions.
     */
    public function run() {

        add_action( 'init', array( $this, 'register_post_type' ) );

        if ( is_admin() ) {
            add_action( 'admin_init', array( $this, 'redirect_post_type_to_admin_page' ) );
        }
    }
}
