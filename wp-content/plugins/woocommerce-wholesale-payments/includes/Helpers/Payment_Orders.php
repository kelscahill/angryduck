<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Helpers
 */

namespace RymeraWebCo\WPay\Helpers;

use RymeraWebCo\WPay\Factories\Payment_Order;
use RymeraWebCo\WPay\Helpers\WPAY_Invoices;
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

        // Get filterable args.
        $filterable_args = array(
            'dates'     => $query_args['dates'],
            'status'    => $query_args['status'],
            'from_date' => $query_args['from_date'],
            'to_date'   => $query_args['to_date'],
            'search'    => $query_args['search'],
            'customer'  => $query_args['customer'],
        );

        // Remove filterable args from query args.
        $query_args = array_filter(
            $query_args,
            function ( $value, $key ) use ( $filterable_args ) {
                return ! isset( $filterable_args[ $key ] );
            },
            ARRAY_FILTER_USE_BOTH
        );

        $defaults = array(
            'status'   => array( 'wc-completed', 'wc-pending', 'wc-on-hold', 'wc-processing', 'wc-cancelled', 'wc-refunded' ),
            'orderby'  => 'date',
            'order'    => 'DESC',
            'return'   => 'objects',
            'paginate' => true,
        );

        $args = apply_filters( 'wpay_get_payment_orders', wp_parse_args( $query_args, $defaults ) );

        $meta_query = array(
            'relation' => 'AND',
            array(
                'key'     => '_wpay_stripe_invoice',
                'compare' => 'EXISTS',
            ),
        );

        // Filter by status.
        if ( ! empty( $filterable_args['status'] ) && 'all' !== $filterable_args['status'] ) {
            $meta_query[] = array(
                'key'     => '_wpay_stripe_invoice_status',
                'value'   => $filterable_args['status'],
                'compare' => '=',
            );
        }

        // Filter by order date.
        if ( ! empty( $filterable_args['from_date'] ) && ! empty( $filterable_args['to_date'] ) ) {
            $start_date = strtotime( $filterable_args['from_date'] );
            $end_date   = strtotime( $filterable_args['to_date'] );
            if ( 'today' === $filterable_args['dates'] ) {
                $start_date = strtotime( gmdate( 'Y-m-d 00:00:00', strtotime( 'today' ) ) );
                $end_date   = strtotime( gmdate( 'Y-m-d 23:59:59', strtotime( 'today' ) ) );
            }

            $args['date_created'] = $start_date . '...' . $end_date;
        }

        // Filter by search.
        if ( ! empty( $filterable_args['search'] ) ) {
            $meta_query[] = array(
                'key'     => '_wpay_stripe_invoice',
                'value'   => $filterable_args['search'],
                'compare' => 'LIKE',
            );
        }

        $args['meta_query'] = $meta_query;

        // Filter by customer.
        if ( ! empty( $filterable_args['customer'] ) ) {
            $args['customer_id'] = $filterable_args['customer'];
        }

        $orders = wc_get_orders( $args );

        $all_orders = array();
        if ( ! empty( $orders->orders ) && ( empty( $args['fields'] ) || 'ids' !== $args['fields'] ) ) {
            $invoices   = WPAY_Invoices::get_invoices();
            $all_orders = array_map(
                function ( $order ) use ( $invoices ) {
                    $order_invoices = array_filter(
                        $invoices,
                        function ( $invoice ) use ( $order ) {
                            $order_id = (int) $invoice->order_id;
                            return $order_id === $order->get_id();
                        }
                    );
                    return Payment_Order::get_instance( $order, $order_invoices );
                },
                $orders->orders
            );
        }

        return array(
            'total'      => $orders->total,
            'totalPages' => $orders->max_num_pages,
            'data'       => $all_orders,
        );
    }

    /**
     * Get order statuses.
     *
     * @since 1.0.2
     * @return array
     */
    public static function get_statuses() {
        return array(
            'all'     => self::count_orders_by_status( 'all' ),
            'overdue' => self::count_orders_by_status( 'overdue' ),
            'paid'    => self::count_orders_by_status( 'paid' ),
            'pending' => self::count_orders_by_status( 'pending' ),
            'void'    => self::count_orders_by_status( 'void' ),
            'open'    => self::count_orders_by_status( 'open' ),
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

        if ( 'all' === $status ) {
            unset( $args['meta_query'] );
        }

        $orders = wc_get_orders( $args );

        return ! empty( $orders ) ? count( $orders ) : 0;
    }

    /**
     * Get all customers.
     *
     * @param array $filterable_args Filterable args.
     *
     * @since 1.0.2
     * @return array
     */
    public static function get_customers( $filterable_args ) {
        $args = array(
            'status' => array( 'wc-completed', 'wc-pending', 'wc-on-hold', 'wc-processing', 'wc-cancelled', 'wc-refunded' ),
            'limit'  => -1,
        );

        $meta_query = array(
            'relation' => 'AND',
            array(
                'key'     => '_wpay_stripe_invoice',
                'compare' => 'EXISTS',
            ),
        );

        // Filter by status.
        if ( ! empty( $filterable_args['status'] ) && 'all' !== $filterable_args['status'] ) {
            $meta_query[] = array(
                'key'     => '_wpay_stripe_invoice_status',
                'value'   => $filterable_args['status'],
                'compare' => '=',
            );
        }

        $args['meta_query'] = $meta_query;

        // Filter by order date.
        if ( ! empty( $filterable_args['from_date'] ) && ! empty( $filterable_args['to_date'] ) ) {
            $start_date = strtotime( $filterable_args['from_date'] );
            $end_date   = strtotime( $filterable_args['to_date'] );
            if ( 'today' === $filterable_args['dates'] ) {
                $start_date = strtotime( gmdate( 'Y-m-d 00:00:00', strtotime( 'today' ) ) );
                $end_date   = strtotime( gmdate( 'Y-m-d 23:59:59', strtotime( 'today' ) ) );
            }

            $args['date_created'] = $start_date . '...' . $end_date;
        }

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
