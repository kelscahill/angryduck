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
 * @since 1.0.5
 */
class WPAY_Invoices {

    /**
     * Table name.
     *
     * @since 1.0.5
     * @var string
     */
    private static $table_name = 'wpay_invoices';

    /**
     * Create invoice table.
     *
     * @since 1.0.5
     * @return void
     */
    public static function create_invoice_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::$table_name;

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (    
            id INT NOT NULL AUTO_INCREMENT,
            order_id INT NOT NULL,
            customer_id VARCHAR(255) DEFAULT '',
            invoice_id VARCHAR(255) DEFAULT '',
            invoice_item_id VARCHAR(255) DEFAULT '',
            breakdown_index INT DEFAULT 0,
            breakdown_last_index INT DEFAULT 0,
            breakdown_amount INT DEFAULT 0,
            breakdown_description TEXT DEFAULT '',
            breakdown_due_date VARCHAR(255) DEFAULT '',
            schedule VARCHAR(255) DEFAULT '',
            status VARCHAR(255) DEFAULT 'pending',
            stripe_invoice TEXT DEFAULT '',
            stripe_invoice_status VARCHAR(255) DEFAULT '',
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
     * @since 1.0.5
     * @return void
     */
    public static function update_invoice_status( $order_id, $invoice_id, $status ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::$table_name;

        $wpdb->update(
            $table_name,
            array( 'stripe_invoice_status' => $status ),
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
     * @since 1.0.5
     * @return void
     */
    public static function update_invoice_data( $field, $value, $invoice_data ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::$table_name;

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
     * @since 1.0.5
     * @return int
     */
    public static function add_invoice_breakdown( $customer_id, $breakdown_index, $order_id, $breakdown ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::$table_name;

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
                'stripe_invoice_status' => $status,
            ),
            array(
                '%s',
                '%d',
                '%d',
                '%s',
                '%s',
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
     * @since 1.0.5
     * @return bool
     */
    public static function check_auto_charge( $payment_plan ) {
        $stripe_auto_charge = get_option( 'wpay_stripe_auto_charge_invoices', 'no' );
        $auto_charge        = ! empty( $stripe_auto_charge ) && 'yes' === $stripe_auto_charge ? true : false;

        if ( ! empty( $payment_plan->apply_auto_charge ) && 'yes' === $payment_plan->apply_auto_charge ) {
            $auto_charge = true;
        }

        // Filter to set auto-charge.
        $auto_charge = apply_filters( 'wpay_auto_charge_invoices', $auto_charge );

        return $auto_charge;
    }

    /**
     * Get invoices due.
     *
     * @since 1.0.5
     * @return array
     */
    public static function get_invoices_due() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::$table_name;

        // phpcs:disable
        $invoices = $wpdb->get_results( "SELECT *, (SELECT SUM(breakdown_amount) FROM $table_name WHERE order_id = invoices.order_id) as total_amount FROM $table_name as invoices WHERE status = 'pending' AND created_at < NOW() - INTERVAL breakdown_due_date DAY" );
        // phpcs:enable

        return $invoices;
    }

    /**
     * Get invoices.
     *
     * @param int $order_id Order ID.
     *
     * @since 1.0.5
     * @return array
     */
    public static function get_invoices( $order_id = null ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::$table_name;

        // phpcs:disable
        $query = "SELECT *, (SELECT SUM(breakdown_amount) FROM $table_name WHERE order_id = invoices.order_id) as total_amount FROM $table_name as invoices";
        if ( ! empty( $order_id ) ) {
            $query .= " WHERE order_id = $order_id";
        }

        $invoices = $wpdb->get_results( $query );
        // phpcs:enable

        return $invoices;
    }

    /**
     * Get invoice status.
     *
     * @param int $order_id Order ID.
     *
     * @since 1.0.5
     * @return array
     */
    public static function get_invoice_status( $order_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::$table_name;

        // phpcs:disable
        // Get the last invoice order_id.
        $last_invoice = $wpdb->get_results( "SELECT * FROM $table_name WHERE order_id = $order_id ORDER BY id DESC LIMIT 1" );
        // phpcs:enable

        if ( empty( $last_invoice ) ) {
            return array(
                'status' => 'pending',
                'ts'     => null,
            );
        }

        $breakdown_due_date = (int) $last_invoice[0]->breakdown_due_date;
        $created_at         = gmdate( 'Y-m-d H:i:s', strtotime( $last_invoice[0]->created_at ) );
        $due_date           = strtotime( $created_at . ' + ' . $breakdown_due_date . ' days' );

        if ( 'paid' === $last_invoice[0]->status ) {
            $status = 'paid';
        } elseif ( 'overdue' === $last_invoice[0]->status ) {
            $status = 'overdue';
        } elseif ( $due_date < time() ) {
            $status = 'overdue';
        } else {
            $status = 'pending';
        }

        return array(
            'status' => $status,
            'ts'     => $due_date,
        );
    }

    /**
     * Map invoices.
     *
     * @param object $invoice Invoice object.
     *
     * @since 1.0.5
     * @return array
     */
    public static function map_invoice( $invoice ) {
        $breakdown_due_date = (int) $invoice->breakdown_due_date;
        $created_date       = strtotime( $invoice->created_at );
        $updated_date       = strtotime( $invoice->updated_at );
        $created_at         = gmdate( 'Y-m-d H:i:s', $created_date );
        $due_date           = strtotime( $created_at . ' + ' . $breakdown_due_date . ' days' );

        $order_invoice = array(
            'invoice_id'            => $invoice->invoice_id,
            'invoice_item_id'       => $invoice->invoice_item_id,
            'breakdown_amount'      => $invoice->breakdown_amount,
            'breakdown_description' => $invoice->breakdown_description,
            'breakdown_due_days'    => $breakdown_due_date,
            'breakdown_due_date'    => $due_date,
            'stripe_invoice'        => ! empty( $invoice->stripe_invoice ) ? json_decode( $invoice->stripe_invoice, true ) : '',
            'stripe_invoice_status' => $invoice->stripe_invoice_status,
            'createdDate'           => $created_date,
            'updatedDate'           => $updated_date > $created_date ? $updated_date : null,
        );

        return $order_invoice;
    }
}
