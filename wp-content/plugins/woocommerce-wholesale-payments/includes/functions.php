<?php
/**
 * Wholesale Payments functions.
 *
 * @package RymeraWebCo\WPay\Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'wpay_trigger_payment_nearly_due_email' ) ) {
    /**
     * Trigger the payment nearly due email.
     *
     * @since 1.0.4
     * @param int   $order_id The order ID.
     * @param array $payment_data The payment data.
     * @return void
     */
    function wpay_trigger_payment_nearly_due_email( $order_id, $payment_data = array() ) {
        do_action( 'wpay_payment_nearly_due_notification', $order_id, $payment_data );
    }
}

if ( ! function_exists( 'wpay_trigger_payment_due_today_email' ) ) {
    /**
     * Trigger the payment due today email.
     *
     * @since 1.0.4
     * @param int   $order_id The order ID.
     * @param array $payment_data The payment data.
     * @return void
     */
    function wpay_trigger_payment_due_today_email( $order_id, $payment_data = array() ) {
        do_action( 'wpay_payment_due_today_notification', $order_id, $payment_data );
    }
}


if ( ! function_exists( 'wpay_trigger_payment_overdue_email' ) ) {
    /**
     * Trigger the payment overdue email.
     *
     * @since 1.0.4
     * @param int   $order_id The order ID.
     * @param array $payment_data The payment data.
     * @return void
     */
    function wpay_trigger_payment_overdue_email( $order_id, $payment_data = array() ) {
        do_action( 'wpay_payment_overdue_notification', $order_id, $payment_data );
    }
}

// Temporary disable auto-charge invoices.
add_filter( 'wpay_auto_charge_invoices', '__return_false' );
