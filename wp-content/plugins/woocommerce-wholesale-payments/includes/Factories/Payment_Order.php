<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WPay\Factories
 */

namespace RymeraWebCo\WPay\Factories;

use RymeraWebCo\WPay\Helpers\WPay;
use RymeraWebCo\WPay\Traits\Magic_Get_Trait;
use WC_Order;
use WP_Post;

/**
 * Payment_Order class.
 *
 * @since 1.0.2
 */
class Payment_Order {

    use Magic_Get_Trait;

    /**
     * Holds the Order Object.
     *
     * @since 1.0.2
     * @var WC_Order Order Object.
     */
    public $order;

    /**
     * Holds the Order ID.
     *
     * @since 1.0.2
     * @var string Order ID.
     */
    public $order_id;

    /**
     * Holds the Invoice ID.
     *
     * @since 1.0.2
     * @var string Invoice ID.
     */
    public $invoice_id;

    /**
     * Holds the Order date.
     *
     * @since 1.0.2
     * @var string Order date.
     */
    public $order_date;

    /**
     * Holds the Invoice Data.
     *
     * @since 1.0.2
     * @var array Invoice Data.
     */
    public $invoice_data;

    /**
     * Holds the Invoice Plan.
     *
     * @since 1.0.2
     * @var array Invoice Plan.
     */
    public $invoice_plan;

    /**
     * Holds the Order Customer.
     *
     * @since 1.0.2
     * @var array Customer Data.
     */
    public $customer;

    /**
     * Holds the Order edit link.
     *
     * @since 1.0.2
     * @var array Order edit link.
     */
    public $order_link;

    /**
     * Holds the Payment status.
     *
     * @since 1.0.2
     * @var string Payment status.
     */
    public $status;

    /**
     * Holds the Order status.
     *
     * @since 1.0.2
     * @var string Order status.
     */
    public $order_status;

    /**
     * Holds the Order currency.
     *
     * @since 1.0.2
     * @var string Order currency.
     */
    public $order_currency;

    /**
     * Holds the Order line items.
     *
     * @since 1.0.2
     * @var array Order line items.
     */
    public $order_line_items;

    /**
     * Holds the Order amount due.
     *
     * @since 1.0.2
     * @var array Order amount due.
     */
    public $order_amount_due;

    /**
     * Holds the Order invoices.
     *
     * @since 1.0.5
     * @var array Order invoices.
     */
    public $order_invoices;

    /**
     * Holds the auto charge.
     *
     * @since 1.0.5
     * @var string Auto charge.
     */
    public $auto_charge;

    /**
     * Payment_Order constructor.
     *
     * @param array|WC_Order $order Order Object.
     * @param array          $invoices Invoices.
     *
     * @since 1.0.2
     * @since 1.0.2 Changes: Added 'active_orders'.
     */
    public function __construct( &$order, &$invoices = array() ) {

        $this->order = &$order;

        $this->order_id       = $this->order->get_id();
        $this->order_date     = $this->order->get_date_created()->date( 'Y-m-d H:i:s' );
        $this->invoice_id     = $this->order->get_meta( '_wpay_stripe_invoice_id' );
        $this->invoice_data   = (array) $this->order->get_meta( '_wpay_stripe_invoice' );
        $this->invoice_plan   = (array) json_decode( $this->order->get_meta( '_wpay_plan' ), true );
        $this->order_status   = $this->order->get_status();
        $this->order_link     = get_edit_post_link( $this->order->get_id() );
        $this->order_currency = array(
            'code'   => $this->order->get_currency(),
            'symbol' => get_woocommerce_currency_symbol( $this->order->get_currency() ),
        );
        $this->set_customer();
        $this->set_line_items();
        $this->set_amount_due();
        $this->set_order_invoices( $invoices );
        $this->set_payment_status();
    }

    /**
     * Get an instance of a Payment Plan.
     *
     * @param array $order Order Object.
     * @param array $invoices Invoices.
     *
     * @return Payment_Order|bool
     */
    public static function get_instance( $order, $invoices = array() ) {

        if ( ! is_a( $order, 'WC_Order' ) ) {
            return false;
        }

        return new Payment_Order( $order, $invoices );
    }

    /**
     * Get the payment status.
     *
     * @since 1.0.5
     * @return void
     */
    public function set_payment_status() {
        // Get the last row from order_invoices array.
        $status = $this->order->get_meta( '_wpay_stripe_invoice_status' );

        if ( 'yes' === $this->auto_charge && is_array( $status ) ) {
            $last_invoice = ! empty( $this->order_invoices ) ? end( $this->order_invoices ) : array();
            $this->status = ! empty( $last_invoice['stripe_invoice_status'] ) ? $last_invoice['stripe_invoice_status'] : 'pending';
        } else {
            $this->status = $status;
        }
    }

    /**
     * Set the customer data.
     *
     * @since 1.0.2
     * @return void
     */
    private function set_customer() {
        $this->customer = array(
            'id'    => $this->order->get_customer_id(),
            'email' => $this->order->get_billing_email(),
            'name'  => $this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name(),
        );
    }

    /**
     * Set the order line items.
     *
     * @since 1.0.2
     * @return void
     */
    private function set_line_items() {
        $stripe_line_items = $this->invoice_data;

        $this->order_line_items = array(
            'subtotal'         => wc_price( $this->order->get_subtotal() ),
            'total'            => wc_price( $this->order->get_total() ),
            'excluding_tax'    => wc_price( $this->order->get_total() - $this->order->get_total_tax() ),
            'tax'              => wc_price( $this->order->get_total_tax() ),
            'amount_paid'      => ! empty( $stripe_line_items['amount_paid'] ) ? wc_price( $stripe_line_items['amount_paid'] / 100 ) : wc_price( 0 ),
            'amount_remaining' => ! empty( $stripe_line_items['amount_remaining'] ) ? wc_price( $stripe_line_items['amount_remaining'] / 100 ) : wc_price( 0 ),
        );
    }

    /**
     * Set the order amount due.
     *
     * @since 1.0.2
     * @return void
     */
    private function set_amount_due() {
        $stripe_line_items = $this->invoice_data;

        if ( empty( $stripe_line_items['amounts_due'] ) ) {
            $stripe_line_items['amounts_due'] = array(
                array(
                    'amount'      => 0,
                    'amount_paid' => 0,
                    'due_date'    => '',
                    'paid_at'     => '',
                    'status'      => '',
                    'description' => '',
                ),
            );
        }

        $this->order_amount_due = array_map( array( $this, 'map_amount_due' ), $stripe_line_items['amounts_due'] );
    }

    /**
     * Map amount due.
     *
     * @param array $order_amount_due Order amount due.
     *
     * @since 1.0.2
     * @return array
     */
    public function map_amount_due( $order_amount_due ) {
        return array(
            'order_id'    => $this->order_id,
            'name'        => $order_amount_due['description'],
            'amount'      => wc_price( $order_amount_due['amount'] / 100 ),
            'amount_paid' => wc_price( $order_amount_due['amount_paid'] / 100 ),
            'currency'    => $this->order->get_currency(),
            'due_date'    => $order_amount_due['due_date'],
            'paid_at'     => $order_amount_due['paid_at'],
            'status'      => $order_amount_due['status'],
            'date'        => ! empty( $order_amount_due['paid_at'] ) ? $order_amount_due['paid_at'] : strtotime( $this->order_date ),
            'order_link'  => get_edit_post_link( $this->order->get_id() ),
        );
    }

    /**
     * Set the order invoices.
     *
     * @param array $invoices Invoices.
     *
     * @since 1.0.2
     * @return void
     */
    private function set_order_invoices( $invoices ) {
        $wpay_auto_charge     = $this->order->get_meta( '_wpay_auto_charge' );
        $this->auto_charge    = ! empty( $wpay_auto_charge ) && 'yes' === $wpay_auto_charge ? 'yes' : 'no';
        $this->order_invoices = 'yes' === $this->auto_charge ? $this->map_order_invoices( $invoices ) : array();
    }

    /**
     * Map order invoices.
     *
     * @param array $invoices Invoices.
     *
     * @since 1.0.2
     * @return array
     */
    public function map_order_invoices( $invoices ) {
        $order_invoices = array();

        if ( ! empty( $invoices ) ) {
            foreach ( $invoices as $invoice ) {
                $breakdown_due_date = (int) $invoice->breakdown_due_date;
                $created_date       = strtotime( $invoice->created_at );
                $updated_date       = strtotime( $invoice->updated_at );
                $created_at         = gmdate( 'Y-m-d H:i:s', $created_date );
                $due_date           = strtotime( $created_at . ' + ' . $breakdown_due_date . ' days' );

                $order_invoices[] = array(
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
            }
        }

        return $order_invoices;
    }

    /**
     * Convert payment plan to JSON.
     *
     * @since 1.0.2
     *
     * @return false|string
     */
    public function to_json() {

        return wp_json_encode(
            array(
                'id' => $this->order->get_id(),
            )
        );
    }
}
