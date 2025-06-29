<?php
/**
 * Payment Due Today Email.
 *
 * @package RymeraWebCo\WPay\Emails
 */

namespace RymeraWebCo\WPay\Emails;

use WC_Email;
use RymeraWebCo\WPay\Integrations\WPay_Emails;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Define the WPay_Payment_Due_Today class.
 *
 * @since 1.0.4
 */
class WPay_Payment_Due_Today extends WC_Email {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id             = 'wpay_payment_due_today';
        $this->customer_email = true;
        $this->title          = __( 'Wholesale Payment Due Today', 'woocommerce-wholesale-payments' );
        $this->description    = __( 'Email sent to customers when their payment is due today', 'woocommerce-wholesale-payments' );

        $this->template_html  = apply_filters( 'wpay_payment_due_today_email_template_html', 'payment-due-today.php' );
        $this->template_plain = apply_filters( 'wpay_payment_due_today_email_template_plain', 'payment-due-today.php' );
        $this->template_base  = trailingslashit( plugin_dir_path( WPAY_PLUGIN_FILE ) ) . 'templates/emails/';

        $this->placeholders = array(
            '{site_title}'     => $this->get_blogname(),
            '{payment_amount}' => '',
        );

        // Call parent constructor.
        parent::__construct();

        // Set default subject and heading.
        $this->subject = __( 'Your payment is due today on {site_title}', 'woocommerce-wholesale-payments' );
        $this->heading = __( 'Your payment is due today', 'woocommerce-wholesale-payments' );

        // Hook for the notification trigger.
        add_action( 'wpay_payment_due_today_notification', array( $this, 'trigger' ), 10, 2 );

        // Instead of hooking to woocommerce_email_footer_text, use a more specific approach.
        add_action( 'woocommerce_email_footer_' . $this->id, array( $this, 'override_email_footer_text' ), 9 );
    }

    /**
     * Trigger the email.
     *
     * @since 1.0.4
     * @param int   $order_id The order ID.
     * @param array $payment_data The payment data.
     */
    public function trigger( $order_id, $payment_data = array() ) {

        if ( ! WPay_Emails::is_email_reminders_enabled() || ! WPay_Emails::is_payment_due_today_reminder_enabled() ) {
            return;
        }

        if ( ! $order_id ) {
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
        if ( ! empty( $payment_data['amount'] ) ) {
            $this->placeholders['{payment_amount}'] = wc_price( $payment_data['amount'], array( 'currency' => $order->get_currency() ) );
        }

        // Get invoice URL from order meta.
        $stripe_invoice = $order->get_meta( '_wpay_stripe_invoice' );
        $invoice_url    = '';
        if ( ! empty( $stripe_invoice['hosted_invoice_url'] ) ) {
            $invoice_url = $stripe_invoice['hosted_invoice_url'];
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
            $this->template_html,
            array(
                'order'              => $this->object,
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => false,
                'email'              => $this,
                'payment_amount'     => $this->placeholders['{payment_amount}'],
                'site_title'         => $this->placeholders['{site_title}'] ?? $this->get_blogname(),
                'invoice_url'        => $invoice_url,
            ),
            $this->template_base . 'payment-due-today.php',
            $this->template_base
        );
    }

    /**
     * Get the plain text content of the email.
     *
     * @since 1.0.4
     * @return string
     */
    public function get_content_plain() {
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
                'payment_amount'     => $this->placeholders['{payment_amount}'],
                'invoice_url'        => $invoice_url,
            ),
            $this->template_base . 'plain/payment-due-today.php',
            $this->template_base
        );
    }

    /**
     * Get the default heading.
     *
     * @since 1.0.4
     * @return string
     */
    public function get_default_heading() {
        return __( 'Your payment is due today', 'woocommerce-wholesale-payments' );
    }

    /**
     * Get the default subject.
     *
     * @since 1.0.4
     * @return string
     */
    public function get_default_subject() {
        return __( 'Your payment is due today on {site_title}', 'woocommerce-wholesale-payments' );
    }

    /**
     * Override the email footer text.
     *
     * @param string $footer_text The email footer text.
     *
     * @since 1.0.4
     * @return string
     */
    public function override_email_footer_text( $footer_text ) {

        if ( ! in_array( $this->id, array( 'wpay_payment_due_today', 'wpay_payment_overdue', 'wpay_payment_nearly_due' ), true ) ) {
            return $footer_text;
        }

        ob_start();

        ?>
        <div class="wpay-powered-by">
            <div class="wpay-powered-by-content">
                <span class="wpay-powered-by-text"><?php esc_html_e( 'Powered by', 'woocommerce-wholesale-payments' ); ?></span>
                <a href="<?php echo esc_url( WPay_Emails::get_utm_url( 'payment-due-today-template' ) ); ?>">
                    <img
                        src="<?php echo esc_url( plugin_dir_url( WPAY_PLUGIN_FILE ) . 'static/images/logo.svg' ); ?>"
                        alt="Wholesale Suite Logo"
                        class="wpay-powered-by-logo"
                    />
                </a>
            </div>
        </div>
        <?php

        $footer_text = ob_get_clean();

        /**
         * Filter the email footer text.
         *
         * @param string $footer_text The email footer text.
         * @return string
         */
        return apply_filters( 'wpay_email_footer_text', $footer_text );
    }
}
