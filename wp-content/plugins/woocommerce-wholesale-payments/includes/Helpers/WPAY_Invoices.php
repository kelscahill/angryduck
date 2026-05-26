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
     * Create invoices for a deferred auto-charge order upon completion.
     *
     * Inserts invoice breakdown rows into the wpay_invoices table and creates
     * a Stripe draft invoice. Called when an order with deferred scheduling
     * transitions to "completed" status.
     *
     * @since 1.0.6
     *
     * @param int $order_id The WooCommerce order ID.
     * @return bool|\WP_Error True on success, WP_Error on failure.
     */
    public static function create_deferred_invoices( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new \WP_Error( 'invalid_order', __( 'Order not found.', 'woocommerce-wholesale-payments' ) );
        }

        $amounts_due = $order->get_meta( '_wpay_deferred_breakdown' );
        $plan_json   = $order->get_meta( '_wpay_plan' );
        $customer_id = $order->get_meta( '_wpay_stripe_customer_id' );

        if ( empty( $amounts_due ) || empty( $customer_id ) || empty( $plan_json ) ) {
            return new \WP_Error( 'missing_data', __( 'Missing deferred breakdown data or customer ID.', 'woocommerce-wholesale-payments' ) );
        }

        $plan_data = json_decode( $plan_json, true );
        $breakdown = isset( $plan_data['breakdown'] ) ? $plan_data['breakdown'] : array();

        // Recalculate custom day formats relative to completion time (now).
        foreach ( $amounts_due as $i => &$item ) {
            if ( isset( $breakdown[ $i ] ) ) {
                $breakdown_item = $breakdown[ $i ];
                $day_format     = isset( $breakdown_item['day_format'] ) ? $breakdown_item['day_format'] : '';

                if ( in_array( $day_format, array( 'custom', 'timestamp' ), true ) ) {
                    $days = WPay::parse_day( $breakdown_item['day'] );

                    if ( 'timestamp' === $day_format ) {
                        $item['due_date'] = $days;
                        unset( $item['days_until_due'] );
                    } else {
                        $item['days_until_due'] = $days;
                        unset( $item['due_date'] );
                    }
                }
            }
        }
        unset( $item );

        // Insert breakdown rows — created_at = CURRENT_TIMESTAMP = completion time.
        $breakdown_ids = array();
        foreach ( $amounts_due as $i => $amount_args ) {
            $breakdown_ids[] = self::add_invoice_breakdown(
                $customer_id,
                $i,
                $order_id,
                $amount_args
            );
        }

        // Create Stripe invoice (auto-charge draft — will be finalized by cron when due).
        $invoice_response = Stripe::instance()->create_invoice(
            $customer_id,
            $amounts_due,
            true
        );

        if ( is_wp_error( $invoice_response ) ) {
            // Clean up orphaned breakdown rows that were just inserted.
            global $wpdb;
            $table_name = $wpdb->prefix . self::$table_name;
            foreach ( $breakdown_ids as $row_id ) {
                $wpdb->delete( $table_name, array( 'id' => $row_id ), array( '%d' ) );
            }

            $order->add_order_note(
                sprintf(
                    /* translators: %s = error message */
                    __( 'Failed to create deferred invoices: %s', 'woocommerce-wholesale-payments' ),
                    $invoice_response->get_error_message()
                )
            );
            return $invoice_response;
        }

        $invoice_id = $invoice_response['id'];

        // Update order meta with invoice data.
        $order->update_meta_data( '_wpay_stripe_invoice_id', array( $invoice_id ) );
        $order->update_meta_data( '_wpay_stripe_invoice', array( $invoice_response ) );
        $order->update_meta_data( '_wpay_stripe_invoice_status', ! empty( $invoice_response['status'] ) ? $invoice_response['status'] : '' );
        $order->update_meta_data( '_wpay_invoice_breakdown', $breakdown_ids );

        // Update DB rows with Stripe data.
        if ( ! empty( $breakdown_ids ) && is_array( $breakdown_ids ) ) {
            $invoice_data = array(
                'invoice_id'            => $invoice_id,
                'stripe_invoice'        => wp_json_encode( $invoice_response ),
                'stripe_invoice_status' => ! empty( $invoice_response['status'] ) ? $invoice_response['status'] : '',
                'status'                => 'created',
            );

            self::update_invoice_data( 'id', $breakdown_ids[0], $invoice_data );
            self::update_invoice_data( 'id', end( $breakdown_ids ), array( 'breakdown_last_index' => 1 ) );
        }

        // Clear deferral flags.
        $order->delete_meta_data( '_wpay_schedule_deferred' );
        $order->delete_meta_data( '_wpay_deferred_breakdown' );
        $order->save_meta_data();

        return true;
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
     * Check if a customer has any overdue invoices.
     *
     * Checks both auto-charge invoices (wpay_invoices table) and
     * non-auto-charge invoices (_wpay_stripe_invoice_status order meta).
     *
     * @param int $user_id        WordPress user ID.
     * @param int $days_threshold  Only count invoices overdue by more than this many days. Default 0.
     *
     * @since 1.0.6
     * @return bool True if customer has overdue invoices exceeding the threshold.
     */
    public static function customer_has_overdue_invoices( $user_id, $days_threshold = 0 ) {
        if ( empty( $user_id ) ) {
            return false;
        }

        $days_threshold = absint( $days_threshold );

        // Use request-level cache to avoid repeated expensive queries (called from
        // is_available(), payment_fields(), and process_payment() in the same request).
        $cache_key = "wpay_overdue_user_{$user_id}_{$days_threshold}";
        $cached    = wp_cache_get( $cache_key, 'wpay' );
        if ( false !== $cached ) {
            return (bool) $cached;
        }

        $result = self::check_overdue_invoices( $user_id, $days_threshold );

        wp_cache_set( $cache_key, $result ? 1 : 0, 'wpay' );

        return $result;
    }

    /**
     * Internal method that performs the actual overdue invoice check.
     *
     * Checks two paths:
     * - Path A: Non-auto-charge orders where `_wpay_stripe_invoice_status` order meta is 'overdue'.
     *   For these, the individual `amounts_due` entries are checked for unpaid items ('past_due' or 'open')
     *   with a due_date (Unix timestamp) in the past, since the order-level meta may be stale.
     * - Path B: Auto-charge invoices in the `wpay_invoices` table where `breakdown_due_date` stores
     *   an integer number of days from `created_at` (not a date/timestamp).
     *   The due date is computed as: created_at + breakdown_due_date days.
     *
     * @param int $user_id        WordPress user ID.
     * @param int $days_threshold  Days past due threshold.
     *
     * @since 1.0.6
     * @return bool
     */
    private static function check_overdue_invoices( $user_id, $days_threshold ) {
        $now = time();

        // Fetch all active customer orders once — used by both Path A and Path B.
        $customer_order_ids = wc_get_orders(
            array(
                'customer_id' => $user_id,
                'status'      => array( 'wc-processing', 'wc-on-hold', 'wc-pending' ),
                'return'      => 'ids',
                'limit'       => -1,
            )
        );

        if ( empty( $customer_order_ids ) ) {
            return false;
        }

        // Path A: Non-auto-charge orders — filter for '_wpay_stripe_invoice_status' = 'overdue' in PHP
        // instead of issuing a separate meta_query, since we already have the order IDs.
        // The order-level meta is a coarse pre-filter; the amounts_due check below is the precise validation.
        foreach ( $customer_order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                continue;
            }

            $invoice_status = $order->get_meta( '_wpay_stripe_invoice_status' );
            if ( 'overdue' !== $invoice_status ) {
                continue;
            }

            $invoice = $order->get_meta( '_wpay_stripe_invoice' );
            if ( empty( $invoice ) ) {
                // No invoice data but status is overdue — treat as overdue with no threshold check.
                if ( 0 === $days_threshold ) {
                    return true;
                }
                continue;
            }

            // Invoice meta may be stored as a sequential array; unwrap first element if so.
            $invoice_data = is_array( $invoice ) && isset( $invoice[0] ) ? $invoice[0] : $invoice;
            $amounts_due  = isset( $invoice_data['amounts_due'] ) ? $invoice_data['amounts_due'] : array();

            foreach ( $amounts_due as $amount ) {
                $status   = isset( $amount['status'] ) ? $amount['status'] : '';
                $due_date = isset( $amount['due_date'] ) ? (int) $amount['due_date'] : 0;

                // Check unpaid amounts ('past_due' from Stripe, 'open' for pending payment)
                // whose due_date (Unix timestamp) has passed.
                if ( ( 'past_due' === $status || 'open' === $status ) && $due_date > 0 && $due_date < $now ) {
                    $days_overdue = (int) floor( ( $now - $due_date ) / DAY_IN_SECONDS );
                    if ( $days_overdue > $days_threshold ) {
                        return true;
                    }
                }
            }
        }

        // Path B: Auto-charge invoices — `breakdown_due_date` is an integer (days offset from created_at).
        // Due date = created_at + breakdown_due_date days. An invoice is overdue when:
        // created_at + breakdown_due_date + days_threshold < NOW()
        // Which is equivalent to: created_at < NOW() - INTERVAL (breakdown_due_date + days_threshold) DAY.
        global $wpdb;

        $table_name = $wpdb->prefix . self::$table_name;
        $order_ids  = implode( ',', array_map( 'absint', $customer_order_ids ) );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $overdue_invoices = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name
                WHERE order_id IN ($order_ids)
                AND status IN ('pending', 'created')
                AND created_at < NOW() - INTERVAL (breakdown_due_date + %d) DAY",
                $days_threshold
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ( $overdue_invoices > 0 ) {
            return true;
        }

        return false;
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

    /**
     * Get the total outstanding balance for a customer.
     *
     * Calculates the sum of all unpaid invoice amounts across all orders
     * for the given customer. Supports both auto-charge and non-auto-charge orders.
     *
     * @param int $user_id WordPress user ID.
     *
     * @since 1.0.6
     * @return float Total outstanding balance in store currency units.
     */
    public static function get_customer_outstanding_balance( $user_id ) {
        $cached = wp_cache_get( "wpay_outstanding_{$user_id}", 'wpay' );
        if ( false !== $cached ) {
            return (float) $cached;
        }

        $balance = 0.0;

        // Auto-charge path: query wpay_invoices table directly.
        $auto_charge_orders = wc_get_orders(
            array(
                'customer_id' => $user_id,
                'status'      => array( 'wc-processing', 'wc-on-hold', 'wc-pending' ),
                'meta_key'    => '_wpay_auto_charge',
                'meta_value'  => 'yes',
                'limit'       => -1,
                'return'      => 'ids',
            )
        );

        if ( ! empty( $auto_charge_orders ) ) {
            global $wpdb;
            $table_name   = $wpdb->prefix . self::$table_name;
            $placeholders = implode( ',', array_fill( 0, count( $auto_charge_orders ), '%d' ) );
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $auto_balance = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(breakdown_amount) FROM {$table_name} WHERE order_id IN ({$placeholders}) AND status != 'paid'",
                    $auto_charge_orders
                )
            );
            // phpcs:enable
            if ( $auto_balance ) {
                $balance += (float) $auto_balance / 100;
            }
        }

        // Non-auto-charge path: read amounts_due from order meta.
        $non_auto_orders = wc_get_orders(
            array(
                'customer_id' => $user_id,
                'status'      => array( 'wc-processing', 'wc-on-hold', 'wc-pending' ),
                'meta_key'    => '_wpay_stripe_invoice',
                'limit'       => -1,
                'return'      => 'ids',
            )
        );

        $auto_charge_set = array_flip( $auto_charge_orders );

        foreach ( $non_auto_orders as $order_id ) {
            // Skip auto-charge orders already counted above.
            if ( isset( $auto_charge_set[ $order_id ] ) ) {
                continue;
            }

            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                continue;
            }

            $invoice = (array) $order->get_meta( '_wpay_stripe_invoice' );
            if ( empty( $invoice['amounts_due'] ) ) {
                continue;
            }

            foreach ( $invoice['amounts_due'] as $entry ) {
                if ( isset( $entry['status'] ) && 'paid' === $entry['status'] ) {
                    continue;
                }
                $amount      = isset( $entry['amount'] ) ? (float) $entry['amount'] : 0;
                $amount_paid = isset( $entry['amount_paid'] ) ? (float) $entry['amount_paid'] : 0;
                $balance    += ( $amount - $amount_paid ) / 100;
            }
        }

        wp_cache_set( "wpay_outstanding_{$user_id}", $balance, 'wpay' );

        return $balance;
    }

    /**
     * Get the effective credit limit for a customer.
     *
     * Resolves the credit limit using priority: per-user > per-role > global.
     * Returns null if no credit limit is configured or the feature is disabled.
     *
     * @param int $user_id WordPress user ID.
     *
     * @since 1.0.6
     * @return float|null Credit limit in store currency units, or null if none.
     */
    public static function get_effective_credit_limit( $user_id ) {
        if ( 'yes' !== get_option( 'wpay_credit_limit_enabled', 'no' ) ) {
            return null;
        }

        $cache_key = "wpay_credit_limit_{$user_id}";
        $cached    = wp_cache_get( $cache_key, 'wpay' );
        if ( false !== $cached ) {
            return 'null' === $cached ? null : (float) $cached;
        }

        // Per-user override (highest priority).
        $user_limit = get_user_meta( $user_id, '_wpay_credit_limit', true );
        if ( '' !== $user_limit && false !== $user_limit ) {
            $limit = (float) $user_limit;
            wp_cache_set( $cache_key, $limit, 'wpay' );
            return $limit;
        }

        // Per-role limit.
        global $wc_wholesale_prices;
        if ( isset( $wc_wholesale_prices->wwp_wholesale_roles ) ) {
            $user           = get_userdata( $user_id );
            $wholesale_role = $user ? $wc_wholesale_prices->wwp_wholesale_roles->getUserWholesaleRole( $user ) : array();
            if ( ! empty( $wholesale_role[0] ) ) {
                $role_limit = get_option( "wpay_credit_limit_role_{$wholesale_role[0]}", '' );
                if ( '' !== $role_limit ) {
                    $limit = (float) $role_limit;
                    wp_cache_set( $cache_key, $limit, 'wpay' );
                    return $limit;
                }
            }
        }

        // Global limit (lowest priority).
        $global_limit = get_option( 'wpay_credit_limit_global_amount', '' );
        if ( '' !== $global_limit ) {
            $limit = (float) $global_limit;
            wp_cache_set( $cache_key, $limit, 'wpay' );
            return $limit;
        }

        wp_cache_set( $cache_key, 'null', 'wpay' );

        return null;
    }

    /**
     * Check if a customer exceeds their credit limit.
     *
     * @param int $user_id WordPress user ID.
     *
     * @since 1.0.6
     * @return bool True if outstanding balance meets or exceeds the credit limit.
     */
    public static function customer_exceeds_credit_limit( $user_id ) {
        $limit = self::get_effective_credit_limit( $user_id );
        if ( null === $limit ) {
            return false;
        }

        $balance = self::get_customer_outstanding_balance( $user_id );

        return $balance >= $limit;
    }

    /**
     * Get the credit limit notice message.
     *
     * @since 1.0.6
     * @return string Translatable notice message.
     */
    public static function get_credit_limit_notice_message() {
        return __( 'Your outstanding balance has reached the credit limit. Your order will be placed on hold for review.', 'woocommerce-wholesale-payments' );
    }
}
