<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Helpers
 */

namespace RymeraWebCo\WPay\Helpers;

use RymeraWebCo\WPay\Helpers\Stripe;
use RymeraWebCo\WPay\Helpers\WPay;
use Automattic\WooCommerce\Enums\OrderStatus;
use WC_Order;

/**
 * WPAY_Invoices class.
 *
 * @since 1.0.4
 */
class WPAY_Invoices {

    /**
     * Create invoice table.
     *
     * @since 1.0.4
     * @return void
     */
    public static function create_invoice_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wpay_invoices';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (    
            id INT NOT NULL AUTO_INCREMENT,
            order_id INT NOT NULL,
            customer_id INT NOT NULL,
            invoice_id VARCHAR(255) DEFAULT '',
            invoice_item_id VARCHAR(255) DEFAULT '',
            breakdown_index INT DEFAULT 0,
            breakdown_amount INT DEFAULT 0,
            breakdown_description TEXT DEFAULT '',
            breakdown_due_date VARCHAR(255) DEFAULT '',
            schedule VARCHAR(255) DEFAULT '',
            status VARCHAR(255) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,   
            PRIMARY KEY (id)
        )";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Update invoice status.
     *
     * @param int    $order_id Order ID.
     * @param string $invoice_id Invoice ID.
     * @param string $status Status to update.
     *
     * @since 1.0.4
     * @return void
     */
    public static function update_invoice_status( $order_id, $invoice_id, $status ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wpay_invoices';

        $wpdb->update(
            $table_name,
            array( 'status' => $status ),
            array(
                'order_id'   => $order_id,
                'invoice_id' => $invoice_id,
            ),
            array( '%s' ),
            array( '%d', '%s' )
        );
    }

    /**
     * Update invoice data.
     *
     * @param string $field Field to update.
     * @param string $value Value to update.
     * @param array  $invoice_data Invoice data.
     *
     * @since 1.0.4
     * @return void
     */
    public static function update_invoice_data( $field, $value, $invoice_data ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wpay_invoices';

        $wpdb->update(
            $table_name,
            $invoice_data,
            array( $field => $value ),
            array( '%s' ),
            array( '%d' )
        );
    }

    /**
     * Add invoice breakdown.
     *
     * @param int   $customer_id Customer ID.
     * @param int   $breakdown_index Breakdown index.
     * @param int   $order_id Order ID.
     * @param array $breakdown Breakdown.
     *
     * @since 1.0.4
     * @return int
     */
    public static function add_invoice_breakdown( $customer_id, $breakdown_index, $order_id, $breakdown ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wpay_invoices';

        $breakdown_index = absint( $breakdown_index );
        $order_id        = absint( $order_id );
        $status          = 'pending';

        $wpdb->insert(
            $table_name,
            array(
                'customer_id'           => $customer_id,
                'order_id'              => $order_id,
                'breakdown_index'       => $breakdown_index,
                'breakdown_amount'      => $breakdown['amount'],
                'breakdown_description' => $breakdown['description'],
                'breakdown_due_date'    => $breakdown['days_until_due'],
                'status'                => $status,
            ),
            array(
                '%d',
                '%d',
                '%s',
                '%s',
            )
        );

        return $wpdb->insert_id;
    }

    /**
     * Check if auto-charge is enabled.
     *
     * @param object $payment_plan Payment plan object.
     *
     * @since 1.0.4
     * @return bool
     */
    public static function check_auto_charge( $payment_plan ) {
        $stripe_auto_charge = get_option( 'wpay_stripe_auto_charge_invoices', 'no' );
        $auto_charge        = ! empty( $stripe_auto_charge ) && 'yes' === $stripe_auto_charge ? true : false;

        if ( ! empty( $payment_plan->auto_charge ) ) {
            $auto_charge = true;
        }

        // Filter to set auto-charge.
        $auto_charge = apply_filters( 'wpay_auto_charge_invoices', $auto_charge );

        return $auto_charge;
    }

    /**
     * Run the invoices.
     *
     * @since 1.0.4
     * @return void
     */
    public static function run() {
    }
}
