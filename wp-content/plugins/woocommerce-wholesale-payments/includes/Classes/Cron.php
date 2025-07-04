<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Classes
 */

namespace RymeraWebCo\WPay\Classes;

use RymeraWebCo\WPay\Abstracts\Abstract_Class;
use RymeraWebCo\WPay\Traits\Singleton_Trait;
use RymeraWebCo\WPay\Helpers\WPAY_Invoices;
use RymeraWebCo\WPay\Helpers\Stripe;
use Automattic\WooCommerce\Enums\OrderStatus;

/**
 * Cron class.
 *
 * @since 1.0.5
 */
class Cron extends Abstract_Class {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @since 1.0.5
     * @var Cron $instance object
     */
    protected static $instance;

    /**
     * Schedule the cron job.
     *
     * @since 1.0.5
     * @return void
     */
    public static function schedule_cron_job() {
        // Get the cron schedule in seconds.
        $cron_schedule_seconds = self::convert_cron_schedule_to_seconds();

        if ( ! as_next_scheduled_action( 'wpay_cron_event_invoices' ) ) {
            as_schedule_recurring_action(
                time() + wp_rand( 0, $cron_schedule_seconds ),
                $cron_schedule_seconds,
                'wpay_cron_event_invoices',
                array(),
                'wpay_cron_event_invoices'
            );
        }
    }

    /**
     * Convert cron schedule to seconds.
     *
     * @since 1.0.5
     * @return int The cron schedule in seconds.
     */
    public static function convert_cron_schedule_to_seconds() {
        $cron_schedule = get_option( 'wpay_cron_schedule', 'daily' );
        $cron_schedule = apply_filters( 'wpay_filter_cron_schedule', $cron_schedule );

        switch ( $cron_schedule ) {
            case 'daily':
                return DAY_IN_SECONDS;
            case 'hourly':
                return HOUR_IN_SECONDS;
            case '15min':
                return 15 * MINUTE_IN_SECONDS;
            default:
                return DAY_IN_SECONDS;
        }
    }

    /**
     * Process cron invoices.
     *
     * @since 1.0.5
     */
    public function wpay_cron_invoices() {
        $logger = wc_get_logger();

        // Get all invoices that are due.
        $invoices = WPAY_Invoices::get_invoices_due();

        if ( ! empty( $invoices ) ) {
            foreach ( $invoices as $invoice ) {
                $order_id    = $invoice->order_id;
                $customer_id = $invoice->customer_id;
                $order       = new \WC_Order( $order_id );

                $total_amount = $invoice->total_amount;
                $amount       = $invoice->breakdown_amount;
                $description  = $invoice->breakdown_description;

                // Set the total amount in array.
                $amounts_due = array(
                    array(
                        'amount' => $total_amount,
                    ),
                );

                /***************************************************************************
                 * Create Stripe invoice
                 ***************************************************************************
                 *
                 * We create the Stripe invoice with the payload for this order.
                 */
                $invoice_response = Stripe::instance()->create_invoice( $customer_id, $amounts_due, true );
                if ( is_wp_error( $invoice_response ) ) {
                    $logger->info( $invoice_response->get_error_message(), array( 'source' => 'wpay_cron_invoices' ) );
                } else {

                    $invoice_id = $invoice_response['id'];

                    $invoice_id_meta_key = '_wpay_stripe_invoice_id';
                    $invoice_ids         = $order->get_meta( $invoice_id_meta_key );
                    if ( ! empty( $invoice_ids ) && is_array( $invoice_ids ) ) {
                        $invoice_ids[] = $invoice_id;
                    } else {
                        $invoice_ids = array( $invoice_id );
                    }
                    $order->update_meta_data( $invoice_id_meta_key, $invoice_ids );

                    /***************************************************************************
                     * Add Stripe invoice item
                     ***************************************************************************
                     *
                     * We add the Stripe invoice item for this order.
                     */
                    $invoice_item = Stripe::instance()->add_invoice_item(
                        $customer_id,
                        $invoice_id,
                        $amount,
                        $description
                    );
                    if ( is_wp_error( $invoice_item ) ) {
                        $logger->info( $invoice_item->get_error_message(), array( 'source' => 'wpay_cron_invoices' ) );
                    }

                    $invoice_item_id = $invoice_item['id'];

                    $invoice_item_id_meta_key = '_wpay_stripe_invoice_item_id';
                    $invoice_item_ids         = $order->get_meta( $invoice_item_id_meta_key );
                    if ( ! empty( $invoice_item_ids ) && is_array( $invoice_item_ids ) ) {
                        $invoice_item_ids[] = $invoice_item_id;
                    } else {
                        $invoice_item_ids = array( $invoice_item_id );
                    }
                    $order->update_meta_data( $invoice_item_id_meta_key, $invoice_item_ids );

                    /***************************************************************************
                     * Finalize invoice
                     ***************************************************************************
                     *
                     * We finalize the invoice to send it to the customer.
                     */
                    $finalized = Stripe::instance()->finalize_invoice( $invoice_id );
                    if ( is_wp_error( $finalized ) ) {
                        $logger->info( $finalized->get_error_message(), array( 'source' => 'wpay_cron_invoices' ) );

                        if ( str_contains( $finalized->get_error_message(), 'already finalized' ) ) {
                            $order->set_status( OrderStatus::PROCESSING, __( 'Invoice already finalized.', 'woocommerce-wholesale-payments' ) );
                            $order->save();
                        }
                    }

                    /***************************************************************************
                     * Record finalized invoice to order meta
                     ***************************************************************************
                     *
                     * We record the finalized invoice to the order meta.
                     */
                    $status                     = ! empty( $finalized['status'] ) ? $finalized['status'] : '';
                    $wpay_stripe_invoice        = '_wpay_stripe_invoice';
                    $wpay_stripe_invoice_status = '_wpay_stripe_invoice_status';
                    $finalized_items            = $order->get_meta( $wpay_stripe_invoice );

                    $invoice_data = array(
                        'invoice_id'            => $invoice_id,
                        'invoice_item_id'       => $invoice_item_id,
                        'stripe_invoice'        => wp_json_encode( $finalized ),
                        'stripe_invoice_status' => $status,
                        'status'                => 'created',
                    );
                    WPAY_Invoices::update_invoice_data( 'id', $invoice->id, $invoice_data );

                    if ( ! empty( $finalized_items ) ) {
                        $finalized_items[] = $finalized;
                    } else {
                        $finalized_items = array( $finalized );
                    }

                    $stripe_invoices = WPAY_Invoices::get_invoices( $order_id );
                    $statusses       = array();
                    if ( ! empty( $stripe_invoices ) ) {
                        foreach ( $stripe_invoices as $stripe_invoice ) {
                            $statusses[] = ! empty( $stripe_invoice->stripe_invoice_status ) ? $stripe_invoice->stripe_invoice_status : 'pending';
                        }
                    }

                    $status = ! empty( $statusses ) && is_array( $statusses ) ? end( $statusses ) : $status;

                    $order->update_meta_data( $wpay_stripe_invoice, $finalized_items );
                    $order->update_meta_data( $wpay_stripe_invoice_status, $status );

                    $order->save_meta_data();

                    // Add order note with invoice number.
                    $invoice_number = $finalized['number'];
                    $order_link     = $finalized['hosted_invoice_url'];
                    $order_note     = sprintf(
                    /* translators: %1$s = invoice number; %2$s = order link */
                        __( 'Invoice #%1$s has been created. <a href="%2$s" target="_blank">View Invoice</a>.', 'woocommerce-wholesale-payments' ),
                        $invoice_number,
                        $order_link
                    );
                    $order->add_order_note( $order_note );

                    $order->update_status( 'processing' );
                }
            }
        }
    }

    /**
     * Run the cron job.
     *
     * @since 1.0.5
     */
    public function run() {
        add_action( 'wpay_cron_event_invoices', array( $this, 'wpay_cron_invoices' ) );
    }
}
