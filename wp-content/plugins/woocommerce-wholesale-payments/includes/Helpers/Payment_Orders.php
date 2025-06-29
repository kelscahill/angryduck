<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Helpers
 */

namespace RymeraWebCo\WPay\Helpers;

use RymeraWebCo\WPay\Factories\Payment_Order;
use WP_Post;
use WP_Query;

/**
 * Payment_Orders class.
 *
 * @since 1.0.2
 */
class Payment_Orders {

    /**
     * Get payment orders.
     *
     * @param array $query_args Query args.
     *
     * @return Payment_Order[]
     */
    public static function get_orders( $query_args = array() ) {

        $defaults = array(
            'status'  => array( 'wc-completed', 'wc-pending', 'wc-on-hold', 'wc-processing', 'wc-cancelled', 'wc-refunded' ),
            'orderby' => 'date',
            'order'   => 'DESC',
        );

        $args = apply_filters( 'wpay_get_payment_orders', wp_parse_args( $query_args, $defaults ) );

        $args['meta_query'] = array(
            array(
                'key'     => '_wpay_stripe_invoice',
                'compare' => 'EXISTS',
            ),
        );

        $orders = wc_get_orders( $args );

        if ( ! empty( $orders ) && ( empty( $args['fields'] ) || 'ids' !== $args['fields'] ) ) {
            $orders = array_map( array( Payment_Order::class, 'get_instance' ), $orders );
        }

        return $orders;
    }

    /**
     * Get order statuses.
     *
     * @since 1.0.2
     * @return array
     */
    public static function get_statuses() {
        return array(
            'overdue' => self::count_orders_by_status( 'overdue' ),
            'paid'    => self::count_orders_by_status( 'paid' ),
            'pending' => self::count_orders_by_status( 'pending' ),
            'void'    => self::count_orders_by_status( 'void' ),
        );
    }

    /**
     * Get the number of status orders.
     *
     * @param string $status Order status.
     *
     * @since 1.0.2
     * @return int
     */
    private static function count_orders_by_status( $status ) {
        $args = array(
            'status'     => array( 'wc-completed', 'wc-pending', 'wc-on-hold', 'wc-processing', 'wc-cancelled', 'wc-refunded' ),
            'return'     => 'ids',
            'limit'      => -1,
            'meta_query' => array(
                array(
                    'key'     => '_wpay_stripe_invoice_status',
                    'value'   => $status,
                    'compare' => '=',
                ),
            ),
        );

        $orders = wc_get_orders( $args );

        return ! empty( $orders ) ? count( $orders ) : 0;
    }

    /**
     * Get all customers.
     *
     * @since 1.0.2
     * @return array
     */
    public static function get_customers() {
        $args = array(
            'status'     => array( 'wc-completed', 'wc-pending', 'wc-on-hold', 'wc-processing', 'wc-cancelled', 'wc-refunded' ),
            'limit'      => -1,
            'meta_query' => array(
                array(
                    'key'     => '_wpay_stripe_invoice',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        $orders = wc_get_orders( $args );

        $customers = array();

        foreach ( $orders as $order ) {

            if ( ! is_a( $order, 'WC_Order' ) ) {
                continue;
            }

            $ids = array_column( $customers, 'id' );

            if ( ! in_array( $order->get_customer_id(), $ids, true ) ) {
                $customers[] = array(
                    'id'    => $order->get_customer_id(),
                    'name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'email' => $order->get_billing_email(),
                );
            }
        }

        return $customers;
    }
}
