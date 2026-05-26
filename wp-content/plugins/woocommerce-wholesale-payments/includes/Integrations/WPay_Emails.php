<?php
/**
 * WooCommerce Emails Integration.
 *
 * @package RymeraWebCo\WPay\Integrations
 */

namespace RymeraWebCo\WPay\Integrations;

use RymeraWebCo\WPay\Abstracts\Abstract_Class;
use RymeraWebCo\WPay\Emails\WPay_Payment_Nearly_Due;
use RymeraWebCo\WPay\Emails\WPay_Payment_Due_Today;
use RymeraWebCo\WPay\Emails\WPay_Payment_Overdue;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WPay_Emails class.
 *
 * @since 1.0.0
 */
class WPay_Emails extends Abstract_Class {
    /**
     * Option name for the email reminders enabled setting.
     *
     * @since 1.0.4
     * @var string
     */

    const EMAIL_REMINDERS_ENABLED_OPTION = 'wpay_enable_email_reminders';

    /**
     * Option name for the invoice reminders enabled setting.
     *
     * @since 1.0.4
     * @var string
     */
    const INVOICE_REMINDERS_ENABLED_OPTION = 'wpay_enable_stripe_invoice_reminders';

    /**
     * Option name for the days before payment due setting.
     *
     * @since 1.0.4
     * @var string
     */
    const DAYS_BEFORE_PAYMENT_DUE_OPTION = 'wpay_days_before_payment_due';

    /**
     * Option name for the days after payment due setting.
     *
     * @since 1.0.4
     * @var string
     */
    const DAYS_AFTER_PAYMENT_DUE_OPTION = 'wpay_days_after_payment_due';

    /**
     * Option name for the payment due today reminder enabled setting.
     *
     * @since 1.0.4
     * @var string
     */
    const DUE_TODAY_REMINDER_ENABLED_OPTION = 'wpay_enable_payment_due_today_reminder';

    /**
     * Template base.
     *
     * @since 1.0.4
     * @var string
     */
    private $template_base;

    /**
     * Current preview email.
     *
     * @since 1.0.4
     * @var WC_Email
     */
    private $current_preview_email;

    /**
     * Run the integration.
     *
     * @since 1.0.4
     * @return void
     */
    public function run() {
        $this->template_base = trailingslashit( plugin_dir_path( WPAY_PLUGIN_FILE ) ) . 'templates/emails/';

        add_filter( 'woocommerce_email_classes', array( $this, 'register_emails' ) );
        add_filter( 'woocommerce_email_actions', array( $this, 'register_email_actions' ) );

        // Add support for email preview.
        add_filter( 'woocommerce_prepare_email_for_preview', array( $this, 'prepare_email_for_preview' ) );

        // Hook into order events to manage scheduled reminders.
        add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_change' ), 10, 3 );
        add_action( 'woocommerce_update_order', array( $this, 'handle_order_update' ) );
    }

    /**
     * Get the email styles.
     *
     * @since 1.0.4
     * @param bool $is_preview Whether this is an email preview.
     * @return bool|string
     */
    public static function get_email_styles( $is_preview = false ) {
        $template_base = trailingslashit( plugin_dir_path( WPAY_PLUGIN_FILE ) ) . 'templates/emails/';

        $settings = array(
            'is_email_preview' => $is_preview,
        );

        // Pass the settings to the template.
        ob_start();
        wc_get_template(
            'wpay-email-styles.php',
            array( 'settings' => $settings ),
            $template_base,
            $template_base
        );
        return ob_get_clean();
    }

    /**
     * Check if email reminders are enabled.
     *
     * @since 1.0.4
     * @return bool
     */
    public static function is_email_reminders_enabled() {
        return wc_string_to_bool( get_option( self::EMAIL_REMINDERS_ENABLED_OPTION, 'yes' ) );
    }

    /**
     * Check if Stripe invoice reminders are enabled.
     *
     * @since 1.0.4
     * @return bool
     */
    public static function is_stripe_invoice_reminders_enabled() {
        return wc_string_to_bool( get_option( self::INVOICE_REMINDERS_ENABLED_OPTION, 'no' ) );
    }

    /**
     * Check if payment due today reminder is enabled.
     *
     * @since 1.0.4
     * @return bool
     */
    public static function is_payment_due_today_reminder_enabled() {
        return wc_string_to_bool( get_option( self::DUE_TODAY_REMINDER_ENABLED_OPTION, 'yes' ) );
    }

    /**
     * Register the emails.
     *
     * @since 1.0.4
     * @param array $email_classes The email classes.
     * @return array
     */
    public function register_emails( $email_classes ) {
        $email_classes['WPay_Payment_Nearly_Due'] = new WPay_Payment_Nearly_Due();
        $email_classes['WPay_Payment_Due_Today']  = new WPay_Payment_Due_Today();
        $email_classes['WPay_Payment_Overdue']    = new WPay_Payment_Overdue();

        return $email_classes;
    }

    /**
     * Register the email actions.
     *
     * @since 1.0.4
     * @param array $actions The actions.
     * @return array
     */
    public function register_email_actions( $actions ) {
        $actions[] = 'wpay_payment_nearly_due_notification';
        $actions[] = 'wpay_payment_due_today_notification';
        $actions[] = 'wpay_payment_overdue_notification';

        return $actions;
    }

    /**
     * Prepare WPay email for preview.
     *
     * @since 1.0.4
     * @param WC_Email $email The email object.
     * @return WC_Email
     */
    public function prepare_email_for_preview( $email ) {
        // Check if this is one of our WPay emails.
        if ( is_a( $email, 'RymeraWebCo\WPay\Emails\WPay_Payment_Nearly_Due' ) ||
            is_a( $email, 'RymeraWebCo\WPay\Emails\WPay_Payment_Due_Today' ) ||
            is_a( $email, 'RymeraWebCo\WPay\Emails\WPay_Payment_Overdue' ) ) {

            // Get a sample order or create a dummy order to get customer details for preview.
            if ( ! is_a( $email->object, 'WC_Order' ) ) {
                $orders = wc_get_orders( array( 'limit' => 1 ) );

                if ( ! empty( $orders ) ) {
                    $email->object = reset( $orders );
                } else {
                    $email->object = new \WC_Order();
                    $email->object->set_billing_email( 'customer@example.com' );
                    $email->object->set_billing_first_name( 'John' );
                    $email->object->set_billing_last_name( 'Doe' );
                    $email->object->set_currency( 'USD' );
                }
            }

            // Calculate the days overdue.
            $due_date_timestamp = strtotime( '-3 days' );
            $due_date_obj       = new \DateTime( gmdate( 'Y-m-d', $due_date_timestamp ) );
            $today_obj          = new \DateTime( gmdate( 'Y-m-d', strtotime( 'today' ) ) );
            $days_overdue       = date_diff( $today_obj, $due_date_obj )->days;

            // Set up sample payment data for preview.
            $payment_data = array(
                'date'         => date_i18n( wc_date_format(), $due_date_timestamp ),
                'amount'       => 100,
                'days_overdue' => $days_overdue,
            );

            // Update placeholders for preview.
            if ( isset( $email->placeholders['{payment_date}'] ) ) {
                $email->placeholders['{payment_date}'] = $payment_data['date'];
            }

            if ( isset( $email->placeholders['{payment_amount}'] ) ) {
                $email->placeholders['{payment_amount}'] = wc_price(
                    $payment_data['amount'],
                    array( 'currency' => $email->object->get_currency() )
                );
            }

            // Add any additional placeholders your emails might use.
            if ( isset( $email->placeholders['{customer_name}'] ) ) {
                $email->placeholders['{customer_name}'] = $email->object->get_billing_first_name();
            }

            if ( isset( $email->placeholders['{order_number}'] ) ) {
                $email->placeholders['{order_number}'] = $email->object->get_order_number();
            }

            if ( isset( $email->placeholders['{days_overdue}'] ) ) {
                $email->placeholders['{days_overdue}'] = $days_overdue;
            }

            // Instead of trying to override the method directly, use a filter.
            add_filter( 'woocommerce_mail_content', array( $this, 'override_email_content' ), 10 );

            // Store the current email in a property so our filter can access it.
            $this->current_preview_email = $email;
        }

        return $email;
    }

    /**
     * Override the email content for preview.
     *
     * This is a workaround to allow us to preview the WPAY emails in the admin area.
     *
     * @param string $content The email content.
     * @return string
     */
    public function override_email_content( $content ) {
        // Only apply to our preview emails.
        if ( ! isset( $this->current_preview_email ) ) {
            return $content;
        }

        $email = $this->current_preview_email;

        ob_start();
        $order              = $email->object;
        $email_heading      = $email->get_heading();
        $additional_content = method_exists( $email, 'get_additional_content' ) ? $email->get_additional_content() : '';
        $sent_to_admin      = false;
        $plain_text         = false;
        $email_obj          = $email;
        $payment_date       = $email->placeholders['{payment_date}'] ?? '';
        $payment_amount     = $email->placeholders['{payment_amount}'] ?? '';
        $site_title         = $email->placeholders['{site_title}'] ?? $email->get_blogname();
        $days_overdue       = $email->placeholders['{days_overdue}'] ?? 2;
        $is_email_preview   = true; // Set preview flag for templates.

        // Include the email template directly.
        include $email->template_base . $email->template_html;

        // Clean up.
        remove_filter( 'woocommerce_mail_content', array( $this, 'override_email_content' ), 10 );
        unset( $this->current_preview_email );

        $content = ob_get_clean();

        return $email->style_inline( $content );
    }

    /**
     * Get the UTM URL for the powered by link.
     *
     * @since 1.0.4
     * @param string $utm_content The UTM content.
     * @param string $utm_source The UTM source.
     * @param string $utm_medium The UTM medium.
     * @param string $utm_campaign The UTM campaign.
     * @return string
     */
    public static function get_utm_url( $utm_content, $utm_source = 'wpay', $utm_medium = 'email_reminders', $utm_campaign = 'powered-by' ) {
        $base_url = 'https://wholesalesuiteplugin.com/powered-by/';
        return add_query_arg(
            array(
                'utm_content'  => $utm_content,
                'utm_source'   => $utm_source,
                'utm_medium'   => $utm_medium,
                'utm_campaign' => $utm_campaign,
            ),
            $base_url
        );
    }

    /**
     * Get the email styles settings.
     *
     * @since 1.0.4
     * @return array
     */
    public static function email_styles_settings() {
        $email_style_setting_ids = array(
            'background_color'      => get_option( 'woocommerce_email_background_color' ),
            'base_color'            => get_option( 'woocommerce_email_base_color' ),
            'body_background_color' => get_option( 'woocommerce_email_body_background_color' ),
            'font_family'           => get_option( 'woocommerce_email_font_family' ),
            'footer_text'           => get_option( 'woocommerce_email_footer_text' ),
            'footer_text_color'     => get_option( 'woocommerce_email_footer_text_color' ),
            'header_alignment'      => get_option( 'woocommerce_email_header_alignment' ),
            'header_image'          => get_option( 'woocommerce_email_header_image' ),
            'header_image_width'    => get_option( 'woocommerce_email_header_image_width' ),
            'text_color'            => get_option( 'woocommerce_email_text_color' ),
        );

        return $email_style_setting_ids;
    }

    /**
     * Handle order status changes to manage scheduled reminders.
     *
     * @since 1.0.4
     * @param int    $order_id The order ID.
     * @param string $old_status The old status.
     * @param string $new_status The new status.
     * @return void
     */
    public function handle_order_status_change( $order_id, $old_status, $new_status ) {
        $order = wc_get_order( $order_id );

        if ( ! $order || $order->get_payment_method() !== \RymeraWebCo\WPay\Payment_Gateway\WC_Wholesale_Payments::instance()->id ) {
            return;
        }

        $is_deferred = 'yes' === $order->get_meta( '_wpay_schedule_deferred' );

        // Activate deferred invoices on completion.
        if ( 'completed' === $new_status && $is_deferred ) {
            $auto_charge = 'yes' === $order->get_meta( '_wpay_auto_charge' );

            if ( $auto_charge ) {
                $result = \RymeraWebCo\WPay\Helpers\WPAY_Invoices::create_deferred_invoices( $order_id );
            } else {
                $result = $this->finalize_deferred_invoice( $order_id );
            }

            // Schedule email reminders from completion time.
            if ( ! is_wp_error( $result ) ) {
                $plan_json = $order->get_meta( '_wpay_plan' );
                $plan_data = json_decode( $plan_json, true );

                if ( ! empty( $plan_data ) ) {
                    $payment_plan = new \RymeraWebCo\WPay\Factories\Payment_Plan( $plan_data );
                    \RymeraWebCo\WPay\Schedulers\WPay_Email_Scheduler::schedule_order_reminders_on_creation( $order_id, $payment_plan );
                }

                $order->add_order_note(
                    __( 'Payment plan schedule activated — invoices created from order completion date.', 'woocommerce-wholesale-payments' )
                );
            }

            // Return early — don't clear the reminders we just scheduled.
            return;
        }

        // Clean up deferred orders on cancellation/refund.
        if ( in_array( $new_status, array( 'cancelled', 'refunded' ), true ) && $is_deferred ) {
            $auto_charge = 'yes' === $order->get_meta( '_wpay_auto_charge' );

            if ( $auto_charge ) {
                // Auto-charge: no DB rows exist, just clean up meta.
                $order->delete_meta_data( '_wpay_schedule_deferred' );
                $order->delete_meta_data( '_wpay_deferred_breakdown' );
                $order->save_meta_data();
            } else {
                // Non-auto-charge: delete the draft Stripe invoice.
                $invoice_id = $order->get_meta( '_wpay_stripe_invoice_id' );
                if ( ! empty( $invoice_id ) ) {
                    \RymeraWebCo\WPay\Helpers\Stripe::instance()->delete_invoice( $invoice_id );
                }
                $order->delete_meta_data( '_wpay_schedule_deferred' );
                $order->save_meta_data();
            }

            $order->add_order_note(
                __( 'Deferred payment plan schedule cancelled.', 'woocommerce-wholesale-payments' )
            );
        }

        // Clear reminders if order is completed, cancelled, or refunded.
        if ( in_array( $new_status, array( 'completed', 'cancelled', 'refunded' ), true ) ) {
            \RymeraWebCo\WPay\Schedulers\WPay_Email_Scheduler::clear_order_reminders( $order_id );
        }

        // Schedule reminders for processing or on-hold orders.
        if ( in_array( $new_status, array( 'processing', 'on-hold' ), true ) ) {
            \RymeraWebCo\WPay\Schedulers\WPay_Email_Scheduler::reschedule_order_reminders( $order_id );
        }
    }

    /**
     * Finalize a deferred non-auto-charge Stripe invoice on order completion.
     *
     * When a non-auto-charge order with deferred scheduling is marked as completed,
     * this method finalizes the draft Stripe invoice. Stripe interprets days_until_due
     * relative to finalization time, so due dates automatically anchor to completion.
     *
     * @since 1.0.6
     *
     * @param int $order_id The WooCommerce order ID.
     * @return true|\WP_Error True on success, WP_Error on failure.
     */
    private function finalize_deferred_invoice( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new \WP_Error( 'invalid_order', __( 'Order not found.', 'woocommerce-wholesale-payments' ) );
        }

        $invoice_id = $order->get_meta( '_wpay_stripe_invoice_id' );

        if ( empty( $invoice_id ) ) {
            $error = new \WP_Error( 'no_invoice', __( 'Deferred invoice finalization failed: no invoice ID found.', 'woocommerce-wholesale-payments' ) );
            $order->add_order_note( $error->get_error_message() );
            return $error;
        }

        $finalize_response = \RymeraWebCo\WPay\Helpers\Stripe::instance()->finalize_invoice( $invoice_id );

        if ( is_wp_error( $finalize_response ) ) {
            // If finalization fails, the invoice may already be finalized (e.g., by Stripe auto_advance).
            // Fetch the current invoice state from Stripe and proceed if it's no longer a draft.
            $current_invoice = \RymeraWebCo\WPay\Helpers\Stripe::instance()->get_invoice( $invoice_id );
            if ( ! is_wp_error( $current_invoice ) && isset( $current_invoice['status'] ) && 'draft' !== $current_invoice['status'] ) {
                $finalize_response = $current_invoice;
            } else {
                $order->add_order_note(
                    sprintf(
                        /* translators: %s = error message */
                        __( 'Failed to finalize deferred invoice: %s', 'woocommerce-wholesale-payments' ),
                        $finalize_response->get_error_message()
                    )
                );
                return $finalize_response;
            }
        }

        // Update order meta with finalized invoice data.
        $order->update_meta_data( '_wpay_stripe_invoice', $finalize_response );
        $order->update_meta_data(
            '_wpay_stripe_invoice_status',
            \RymeraWebCo\WPay\Helpers\WPay::get_invoice_payment_progress_status( $finalize_response, 'status' )
        );
        $order->delete_meta_data( '_wpay_schedule_deferred' );
        $order->save_meta_data();

        return true;
    }

    /**
     * Handle order updates to reschedule reminders if payment details changed.
     *
     * @since 1.0.4
     * @param int $order_id The order ID.
     * @return void
     */
    public function handle_order_update( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order || $order->get_payment_method() !== \RymeraWebCo\WPay\Payment_Gateway\WC_Wholesale_Payments::instance()->id ) {
            return;
        }

        // Check if payment due date or amount changed.
        $due_date = $order->get_meta( '_wpay_payment_due_date' );
        $amount   = $order->get_meta( '_wpay_payment_amount' );

        if ( $due_date && $amount ) {
            \RymeraWebCo\WPay\Schedulers\WPay_Email_Scheduler::reschedule_order_reminders( $order_id );
        }
    }
}
