<?php
/**
 * Payment Nearly Due Email.
 *
 * @package RymeraWebCo\WPay\Emails
 */

namespace RymeraWebCo\WPay\Emails;

use WC_Email;
use Exception;
use RymeraWebCo\WPay\Integrations\WPay_Emails;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Define the WPay_Payment_Nearly_Due class.
 *
 * @since 1.0.4
 */
class WPay_Payment_Nearly_Due extends WC_Email {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id             = 'wpay_payment_nearly_due';
        $this->customer_email = true;
        $this->title          = __( 'Wholesale Payment Nearly Due', 'woocommerce-wholesale-payments' );
        $this->description    = __( 'Email sent to customers when their payment is nearly due', 'woocommerce-wholesale-payments' );

        $this->template_html  = apply_filters( 'wpay_payment_nearly_due_email_template_html', 'emails/payment-nearly-due.php' );
        $this->template_plain = apply_filters( 'wpay_payment_nearly_due_email_template_plain', 'emails/plain/payment-nearly-due.php' );

        $this->template_base = trailingslashit( plugin_dir_path( WPAY_PLUGIN_FILE ) . 'templates/' );

        $this->placeholders = array(
            '{site_title}'     => $this->get_blogname(),
            '{payment_date}'   => '',
            '{payment_amount}' => '',
        );

        // Call parent constructor.
        parent::__construct();

        // Set default subject and heading.
        $this->subject = __( 'Your payment is nearly due on {site_title}', 'woocommerce-wholesale-payments' );
        $this->heading = __( 'Your payment is nearly due', 'woocommerce-wholesale-payments' );

        // Hook for the notification trigger.
        add_action( 'wpay_payment_nearly_due_notification', array( $this, 'trigger' ), 10, 2 );
    }

    /**
     * Trigger the email.
     *
     * @since 1.0.4
     * @param int   $order_id The order ID.
     * @param array $payment_data The payment data.
     */
    public function trigger( $order_id, $payment_data = array() ) {
        if ( ! WPay_Emails::is_email_reminders_enabled() || ! $order_id ) {
            return;
        }

        $this->setup_locale();

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        $this->object    = $order;
        $this->recipient = $order->get_billing_email();

        // Replace placeholders with actual data.
        if ( ! empty( $payment_data['date'] ) ) {
            $this->placeholders['{payment_date}'] = $payment_data['date'];
        }

        if ( ! empty( $payment_data['amount'] ) ) {
            $this->placeholders['{payment_amount}'] = wc_price( $payment_data['amount'], array( 'currency' => $order->get_currency() ) );
        }

        if ( $this->is_enabled() && $this->get_recipient() ) {
            $this->send(
                $this->get_recipient(),
                $this->get_subject(),
                $this->get_content(),
                $this->get_headers(),
                $this->get_attachments()
            );
        }

        $this->restore_locale();
    }

    /**
     * Get the HTML content of the email.
     *
     * @since 1.0.4
     * @return string
     */
    public function get_content_html() {
        try {
            // Get invoice URL from order meta.
            $stripe_invoice = $this->object->get_meta( '_wpay_stripe_invoice' );
            $invoice_url    = '';
            if ( ! empty( $stripe_invoice['hosted_invoice_url'] ) ) {
                $invoice_url = $stripe_invoice['hosted_invoice_url'];
            } else {
                // Fallback to specific order view page.
                $invoice_url = $this->object->get_view_order_url();
            }

            // Add full data array with all possible variables your template might need.
            return wc_get_template_html(
                $this->template_html,
                array(
                    'order'              => $this->object,
                    'email_heading'      => $this->get_heading(),
                    'additional_content' => $this->get_additional_content(),
                    'sent_to_admin'      => false,
                    'plain_text'         => false,
                    'email'              => $this,
                    'site_title'         => $this->placeholders['{site_title}'] ?? $this->get_blogname(),
                    'payment_date'       => $this->placeholders['{payment_date}'] ?? '',
                    'payment_amount'     => $this->placeholders['{payment_amount}'] ?? '',
                    'customer_name'      => $this->placeholders['{customer_name}'] ?? '',
                    'invoice_url'        => $invoice_url,
                ),
                '', // Empty string as a fallback to default to plugin directory.
                $this->template_base
            );
        } catch ( Exception $e ) {
            return sprintf(
                '<p>%1$s %2$s</p>',
                esc_html__( 'Error rendering email template:', 'woocommerce-wholesale-payments' ),
                esc_html( $e->getMessage() )
            );
        }
    }

    /**
     * Get the plain text content of the email.
     *
     * @since 1.0.4
     * @return string
     */
    public function get_content_plain() {
        try {
            // Get invoice URL from order meta.
            $stripe_invoice = $this->object->get_meta( '_wpay_stripe_invoice' );
            $invoice_url    = '';
            if ( ! empty( $stripe_invoice['hosted_invoice_url'] ) ) {
                $invoice_url = $stripe_invoice['hosted_invoice_url'];
            } else {
                // Fallback to specific order view page.
                $invoice_url = $this->object->get_view_order_url();
            }

            return wc_get_template_html(
                $this->template_plain,
                array(
                    'order'              => $this->object,
                    'email_heading'      => $this->get_heading(),
                    'additional_content' => $this->get_additional_content(),
                    'sent_to_admin'      => false,
                    'plain_text'         => true,
                    'email'              => $this,
                    'payment_date'       => $this->placeholders['{payment_date}'] ?? '',
                    'payment_amount'     => $this->placeholders['{payment_amount}'] ?? '',
                    'customer_name'      => $this->placeholders['{customer_name}'] ?? '',
                    'invoice_url'        => $invoice_url,
                ),
                '',
                $this->template_base
            );
        } catch ( Exception $e ) {
            return sprintf(
                '<p>%1$s %2$s</p>',
                esc_html__( 'Error rendering plain email template:', 'woocommerce-wholesale-payments' ),
                esc_html( $e->getMessage() )
            );
        }
    }

    /**
     * Get the default heading.
     *
     * @since 1.0.4
     * @return string
     */
    public function get_default_heading() {
        return __( 'Your payment is nearly due', 'woocommerce-wholesale-payments' );
    }

    /**
     * Get the default subject.
     *
     * @since 1.0.4
     * @return string
     */
    public function get_default_subject() {
        return __( 'Your payment is nearly due on {site_title}', 'woocommerce-wholesale-payments' );
    }
}
