<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Helpers
 */

namespace RymeraWebCo\WPay\Helpers;

use RymeraWebCo\WPay\Classes\Settings_Page;
use RymeraWebCo\WPay\Factories\Payment_Plan;
use WC_Order;

/**
 * Stripe class.
 *
 * @since 1.0.0
 */
class WPay {

    /**
     * Get Stripe account number.
     *
     * @since 1.0.0
     * @return null|string
     */
    public static function get_account_number() {

        return get_option( 'wpay_account_number', null );
    }

    /**
     * Saves Stripe account number.
     *
     * @param string $account_number Stripe account number.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function save_account_number( $account_number ) {

        update_option( 'wpay_account_number', $account_number );

        return true;
    }

    /**
     * Get Stripe account key or token.
     *
     * @param string    $which     Which token to get. Possible values: <code>access_token</code>,
     *                             <code>token_type, publishable_key, scope</code>.
     * @param bool|null $test_mode Whether to get test mode token.
     *
     * @since 1.0.0
     * @return string|null
     */
    public static function get_token( $which = 'access_token', $test_mode = null ) {

        if ( null === $test_mode && self::get_api_mode() === 'test' ) {
            $test_mode = true;
        }

        $meta_key = 'wpay_';

        if ( 'refresh' === $which ) {
            $meta_key .= 'refresh_token';
        } else {
            $meta_key .= $test_mode ? 'test_' : 'live_';
        }

        $keys = array_flip(
            array(
                'access_token',
                'token_type',
                'publishable_key',
                'scope',
            )
        );

        if ( ! isset( $keys[ $which ] ) ) {
            return null;
        }

        $meta_key .= $which;

        return get_option( $meta_key, null );
    }

    /**
     * Get masked Stripe account key or token.
     *
     * @param string    $which     Which token to get. Possible values: <code>access_token</code>,
     *                             <code>token_type, publishable_key, scope</code>.
     * @param bool|null $test_mode Whether to get test mode token.
     *
     * @since 1.0.0
     * @return string|null
     */
    public static function get_masked_token( $which = 'access_token', $test_mode = null ) {

        $token  = self::get_token( $which, $test_mode );
        $masked = array_flip(
            array(
                'access_token',
                'refresh_token',
                'publishable_key',
            )
        );
        if ( isset( $masked[ $which ] ) ) {
            if ( $token ) {
                $token = substr( $token, 0, 7 ) . ' **** ' . substr( $token, -4 );
            } elseif ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === WP_ENVIRONMENT_TYPE ) {
                $token = str_repeat( '*', 18 );
            }
        }

        return $token;
    }

    /**
     * Save Stripe credentials.
     *
     * @param array $credentials Stripe credentials.
     *
     * @since 1.0.0
     * @return true
     */
    public static function save_credentials( $credentials ) {

        $defaults = array(
            'account_number'       => '',
            'display_name'         => '',
            'refresh_token'        => '',
            'live_access_token'    => '',
            'live_token_type'      => '',
            'live_publishable_key' => '',
            'live_scope'           => '',
            'test_access_token'    => '',
            'test_token_type'      => '',
            'test_publishable_key' => '',
            'test_scope'           => '',
        );

        $args = wp_parse_args( array_intersect_key( $credentials, $defaults ), $defaults );

        foreach ( $args as $meta_key => $meta_value ) {
            update_option( 'wpay_' . $meta_key, $meta_value );
        }

        return true;
    }

    /**
     * Save Stripe webhook secret.
     *
     * @param string    $secret    Webhook secret.
     * @param null|true $test_mode Whether to save test mode secret.
     *
     * @since 1.0.0
     * @return true
     */
    public static function save_webhook_secret( $secret, $test_mode = null ) {

        if ( null === $test_mode && self::get_api_mode() === 'test' ) {
            $test_mode = true;
        }

        $key = $test_mode ? 'wpay_test_webhook_secret' : 'wpay_live_webhook_secret';

        update_option( $key, $secret );

        return true;
    }

    /**
     * Get Stripe webhook secret.
     *
     * @param null|true $test_mode Whether to get test mode secret.
     *
     * @since 1.0.0
     * @return mixed
     */
    public static function get_webhook_secret( $test_mode = null ) {

        /***************************************************************************
         * Override webhook secret
         ***************************************************************************
         *
         * For local testing of Stripe webhook, we're using Stripe CLI to listen to
         * webhook events and the command for this shows the webhook signing secret
         * in the CLI output which is what needs to be used.
         */
        if ( defined( 'WPAY_WEBHOOK_SIGNING_SECRET' ) && WPAY_WEBHOOK_SIGNING_SECRET ) {
            return WPAY_WEBHOOK_SIGNING_SECRET;
        }

        if ( null === $test_mode && self::get_api_mode() === 'test' ) {
            $test_mode = true;
        }

        $key = $test_mode ? 'wpay_test_webhook_secret' : 'wpay_live_webhook_secret';

        return get_option( $key, false );
    }

    /**
     * Get plugin admin page URL.
     *
     * @param array|null $raw_args URL Query args.
     *
     * @since 1.0.0
     * @return string
     */
    public static function get_plugin_admin_url( $raw_args = array() ) {

        $default_query_args = array(
            'subpage' => 'stripe',
        );

        $query_args = wp_parse_args( $raw_args, $default_query_args );

        $url = admin_url(
            sprintf(
                'admin.php?page=%1$s',
                Settings_Page::MENU_SLUG
            )
        );
        if ( is_null( $raw_args ) ) {
            return $url;
        }

        return esc_url_raw( add_query_arg( $query_args, $url ) );
    }

    /**
     * Get Stripe return URL.
     *
     * @param array|null $query_args URL query args.
     *
     * @since 1.0.0
     * @return string
     */
    public static function get_return_url( $query_args ) {

        return wp_nonce_url(
            self::get_plugin_admin_url( $query_args ),
            'rcs-connect',
            'connect_nonce'
        );
    }

    /**
     * Parse day value.
     *
     * @param int|string $day     Day value.
     * @param string     $context The context. Either 'view' or 'raw'.
     *
     * @since 1.0.0
     * @return mixed|null
     */
    public static function parse_day( $day, $context = 'raw' ) {

        $filter_key = str_replace( '-', '_', sanitize_key( $day ) );

        return apply_filters( "wpay_parse_$filter_key", null, $context );
    }

    /**
     * Get order plan.
     *
     * @param int|WC_Order $order   Order ID or instance.
     * @param string       $context The context.
     *
     * @since 1.0.0
     * @return object|null
     */
    public static function get_order_plan( $order, $context = 'view' ) {

        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order );
        }

        $plan = $order->get_meta( '_wpay_plan', true, $context );
        if ( $plan ) {
            $plan = json_decode( $plan );
        }

        return $plan;
    }

    /**
     * Get the invoice payment progress status.
     *
     * @param array  $invoice Invoice data.
     * @param string $fields      Return type. Possible values: <code>'all' (default)</code>, <code>'status'</code>, or
     *                            <code>'ts'</code>.
     *
     * @since 1.0.0
     * @since 1.0.3 Added void status and change the parameter to invoice.
     * @return array{status: string, ts: int|null}
     */
    public static function get_invoice_payment_progress_status( $invoice, $fields = 'all' ) {
        $amounts_due = $invoice['amounts_due'];

        $payment_status = array(
            'status' => 'draft',
            'ts'     => null,
        );

        // check if invoice is voided.
        if ( 'void' === $invoice['status'] ) {
            $payment_status['status'] = 'void';
            return $payment_status;
        }

        $amounts_due_count = count( $amounts_due );
        $paid_count        = 0;
        foreach ( $amounts_due as $amount_due ) {
            [
                'due_date' => $due_date,
                'status'   => $status,
            ] = $amount_due;
            if ( 'open' === $status ) {
                $payment_status['ts'] = $due_date;
                if ( $due_date < time() ) {
                    $payment_status['status'] = 'overdue';
                } else {
                    $payment_status['status'] = 'pending';
                }
                break;
            } elseif ( 'past_due' === $status ) {
                $payment_status = array(
                    'status' => 'overdue',
                    'ts'     => $due_date,
                );
                break;
            } elseif ( 'paid' === $status ) {
                ++$paid_count;
                if ( $paid_count && ( 1 === $amounts_due_count || $amounts_due_count === $paid_count ) ) {
                    $payment_status['status'] = 'paid';
                    $payment_status['ts']     = null;
                }
            }
        }

        if ( 'status' === $fields ) {
            return $payment_status['status'];
        } elseif ( 'ts' === $fields ) {
            return $payment_status['ts'];
        }

        return $payment_status;
    }

    /**
     * Get the invoice payment progress status.
     *
     * @param string $status Invoice status from Stripe.
     *
     * @since 1.0.0
     * @return string
     */
    public static function get_human_readable_invoice_status( $status ) {

        $statuses = array(
            'past_due' => __( 'Overdue', 'woocommerce-wholesale-payments' ),
            'open'     => __( 'Pending Payment', 'woocommerce-wholesale-payments' ),
            'paid'     => __( 'Paid', 'woocommerce-wholesale-payments' ),
        );

        return $statuses[ $status ] ?? $status;
    }

    /**
     * Returns the webhook events.
     *
     * @since 1.0.0
     * @return string[]
     */
    public static function get_webhook_events() {

        return apply_filters(
            'wpay_webhook_events',
            array(
                'invoice.updated',
            )
        );
    }

    /**
     * Get the payment method name.
     *
     * @param string $field Either <code>value</code> or <code>key</code>.
     *
     * @since 1.0.0
     * @return false|mixed|null
     */
    public static function get_payment_method_name( $field = 'value' ) {

        $key = 'wpay_payment_method_name';
        if ( 'key' === $field ) {
            return $key;
        }

        return get_option( 'wpay_payment_method_name', __( 'Wholesale Payments', 'woocommerce-wholesale-payments' ) );
    }

    /**
     * Get the API mode.
     *
     * @since 1.0.0
     * @return false|mixed|null|string Either <code>live</code> or <code>test</code>.
     */
    public static function get_api_mode() {

        if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === WP_ENVIRONMENT_TYPE ) {
            return 'test';
        }

        return is_multisite() ? get_site_option( 'wpay_api_mode', 'live' ) : get_option( 'wpay_api_mode', 'live' );
    }

    /**
     * Update the API mode.
     *
     * @param string $mode Either <code>live</code> or <code>test</code>.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function update_api_mode( $mode ) {

        if ( null === $mode ) {
            return is_multisite() ? delete_site_option( 'wpay_api_mode' ) : delete_option( 'wpay_api_mode' );
        }

        return is_multisite() ? update_site_option( 'wpay_api_mode', $mode ) : update_option( 'wpay_api_mode', $mode );
    }

    /**
     * Enable advanced payment plan due days.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function maybe_enable_advanced_payment_plan_due_days() {

        return apply_filters( 'wpay_plan_advanced_payment_due_days', false );
    }
}
