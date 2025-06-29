<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Updates
 */

namespace RymeraWebCo\WPay\Updates;

use RymeraWebCo\WPay\Abstracts\Abstract_Update;
use RymeraWebCo\WPay\Factories\Payment_Plan;
use RymeraWebCo\WPay\Helpers\Payment_Plans;
use WC_Order;
use WC_Order_Query;
use WP_Query;

/**
 * Version_1_0_0_Beta_2 class.
 *
 * @since 1.0.0
 */
class Version_1_0_0_Beta_2 extends Abstract_Update {

    /**
     * Update existing invoices status to new status.
     *
     * @since 1.0.0
     * @return void
     */
    private function update_existing_invoices_status() {

        $orders_query = new WC_Order_Query(
            array(

                'meta_query' => array(
                    array(
                        'key'     => '_wpay_stripe_invoice_status',
                        'compare' => 'EXISTS',
                    ),
                ),
            )
        );

        try {
            /**
             * Update pre v1.0.0-beta.2 invoice statuses.
             *
             * @var WC_Order[] $orders_with_wpay
             */
            $orders_with_wpay = $orders_query->get_orders();
            foreach ( $orders_with_wpay as $order ) {
                $status = $order->get_meta( '_wpay_stripe_invoice_status' );
                $update = false;
                if ( 'completed' === $status ) {
                    /***************************************************************************
                     * Update 'completed' to 'paid'
                     ***************************************************************************
                     *
                     * We are updating the invoice status from 'completed' to 'paid'.
                     */
                    $order->update_meta_data( '_wpay_stripe_invoice_status', 'paid' );
                    $update = true;
                } elseif ( 'upcoming' === $status ) {
                    /***************************************************************************
                     * Update 'upcoming' to 'pending'
                     ***************************************************************************
                     *
                     * We are updating the invoice status from 'upcoming' to 'pending'.
                     */
                    $order->update_meta_data( '_wpay_stripe_invoice_status', 'pending' );
                    $update = true;
                }

                if ( $update ) {
                    $order->save_meta_data();
                }
            }
        } catch ( \Exception $e ) {
            wc_get_logger()->error( $e->getMessage(), array( 'source' => 'woocommerce-wholesale-payments' ) );
        }
    }

    /**
     * Update apply plan restrictions switch for those plans that have wholesale roles restrictions.
     *
     * @since 1.0.0
     * @return void
     */
    private function update_apply_plan_restrictions_switch() {

        $args        = array(
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'paged'                  => 1,
            'post_status'            => 'publish',
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'post_type'              => Payment_Plan::POST_TYPE,
            'update_post_term_cache' => false,
            'no_found_rows'          => true,
            'meta_query'             => array(
                'relation' => 'AND',
                array(
                    'key'     => 'apply_restrictions',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => 'wholesale_roles',
                    'compare' => 'EXISTS',
                ),
            ),
        );
        $plans_query = new WP_Query( $args );

        if ( $plans_query->have_posts() ) {
            while ( ! empty( $plans_query->posts ) ) {
                foreach ( $plans_query->posts as $post_id ) {
                    update_post_meta( $post_id, 'apply_restrictions', 'yes' );
                }
                $plans_query->query( $args );
            }
        }
    }

    /**
     * Run 1.0.0-beta.2 update actions.
     */
    public function actions() {

        $this->update_existing_invoices_status();
        $this->update_apply_plan_restrictions_switch();
    }
}
