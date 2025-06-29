<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Helpers
 */

namespace RymeraWebCo\WPay\Helpers;

use RymeraWebCo\WPay\Traits\Singleton_Trait;
use WP_Error;

/**
 * Stripe class.
 *
 * @since 1.0.0
 */
class Stripe {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @since 3.0.6
     * @var Stripe $instance object
     */
    protected static $instance;

    /**
     * Stripe API version.
     *
     * @since 1.0.0 Stripe Invoice Payment Plans is in beta.
     */
    private const STRIPE_API_VERSION = '2023-10-16;invoice_payment_plans_beta=v1';

    /**
     * Stripe API base URL.
     *
     * @since 1.0.0
     * @var string
     */
    private $base_url;

    /**
     * Stripe API headers.
     *
     * @since 1.0.0
     * @var array
     */
    private $headers;

    /**
     * Stripe constructor.
     */
    public function __construct() {

        $this->base_url = 'https://api.stripe.com/v1';
        $this->headers  = array(
            //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
            'Authorization'  => 'Basic ' . base64_encode( WPay::get_token() . ':' ),
            'Content-Type'   => 'application/json',
            'Stripe-Version' => self::STRIPE_API_VERSION,
        );
    }

    /**
     * Send a GET request to Stripe API.
     *
     * @see   wp_remote_get()
     *
     * @param string $endpoint     Stripe API endpoint.
     * @param mixed  $query_string Query string parameters.
     * @param array  $options      wp_remote_get() options.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function send_get_request( $endpoint, $query_string = array(), $options = array() ) {

        $args = wp_parse_args(
            $options,
            array(
                'headers' => $this->headers,
            )
        );

        $url = "$this->base_url/$endpoint";
        if ( ! empty( $query_string ) ) {
            $url = esc_url_raw( add_query_arg( wp_parse_args( $query_string ), $url ) );
        }
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $json = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $json['error']['code'] ) && isset( $json['error']['message'] ) ) {
            return new WP_Error( $json['error']['code'], $json['error']['message'] );
        } elseif ( isset( $response['response']['code'] ) && $response['response']['code'] >= 400 ) {
            if ( isset( $json['error']['type'] ) && isset( $json['error']['message'] ) ) {
                return new WP_Error( $json['error']['type'], $json['error']['message'] );
            } elseif ( isset( $json['message'] ) ) {
                return new WP_Error( $response['response']['code'], $json['message'] );
            }

            return new WP_Error( $response['response']['code'], $response['response']['message'] );
        } elseif ( empty( $json ) ) {
            return new WP_Error( 'wpay_stripe_invalid_response', __( 'Invalid response from Stripe.', 'woocommerce-wholesale-payments' ) );
        }

        return $json;
    }

    /**
     * Send a POST request to Stripe.
     *
     * @see   wp_remote_post()
     *
     * @param string $endpoint The Stripe endpoint to send the request to.
     * @param array  $payload  The payload to send to Stripe.
     * @param array  $options  wp_remote_post() options.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function send_post_request( $endpoint, $payload = array(), $options = array() ) {

        $args     = wp_parse_args(
            $options,
            array(
                'headers' => wp_parse_args( array( 'Content-Type' => 'application/x-www-form-urlencoded' ), $this->headers ),
                'body'    => $payload,
            )
        );
        $response = wp_remote_post( "$this->base_url/$endpoint", $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $json = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $response['response']['code'] ) && $response['response']['code'] >= 400 ) {
            if ( isset( $json['message'] ) ) {
                return new WP_Error( $response['response']['code'], $json['message'] );
            } elseif ( isset( $json['error']['type'] ) && isset( $json['error']['message'] ) ) {
                return new WP_Error( $json['error']['type'], $json['error']['message'] );
            }

            return new WP_Error( $response['response']['code'], $response['response']['message'] );
        }

        return $json;
    }

    /**
     * Get Stripe account details.
     *
     * @param string|null $account_id Stripe account ID.
     *
     * @since 1.0.0
     * @return array|WP_Error
     */
    public function get_account( $account_id = null ) {

        if ( null === $account_id ) {
            $account_id = WPay::get_account_number();
        }

        if ( empty( $account_id ) ) {
            return new WP_Error( 'wpay_stripe_account_id_not_found', __( 'Stripe account ID not found.', 'woocommerce-wholesale-payments' ) );
        }

        return $this->send_get_request( "accounts/$account_id" );
    }

    /**
     * Search for a customer.
     *
     * @see   https://stripe.com/docs/search#query-fields-for-customers.
     *
     * @param array $query Stripe customers query fields.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function search_customer( $query ) {

        $query_array = array();
        $operators   = array(
            ':',
            '~',
            '<',
            '>',
            '=',
        );
        foreach ( $query as $key => $value ) {
            if ( ! in_array( substr( $key, -1 ), $operators, true ) ) {
                $key .= ':';
            }
            $query_array[] = "$key" . ( is_numeric( $value ) ? $value : "\"$value\"" );
        }

        return $this->send_get_request(
            'customers/search',
            array(),
            array(
                'body' => array(
                    'query' => implode( ' AND ', $query_array ),
                ),
            )
        );
    }

    /**
     * Create Stripe customer.
     *
     * @param string $name  Name of the customer.
     * @param string $email Email of the customer.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function create_customer( $name, $email ) {

        return $this->send_post_request(
            'customers',
            array(
                'name'  => $name,
                'email' => $email,
            )
        );
    }

    /**
     * Create Stripe Invoice Payment plan.
     *
     * @param string $customer_id Stripe customer ID.
     * @param array  $amounts_due Amounts due (in cents) for the invoice.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function create_invoice( $customer_id, $amounts_due ) {

        $payload = array(
            'customer'          => $customer_id,
            'collection_method' => 'send_invoice',
            'auto_advance'      => 'true',
            'amounts_due'       => $amounts_due,
            'currency'          => get_woocommerce_currency(),
        );

        $license_status = License::get_license_status_type();
        if ( ( in_array( $license_status['type'], array( 'no_license', 'expired', 'disabled-cancelled' ), true ) &&
                'gte_14' === $license_status['interval'] ) ||
            ( in_array( $license_status['type'], array( 'disabled-active', 'disabled-pending-cancel' ), true ) &&
                in_array( $license_status['interval'], array( 'lt_14', 'gte_14' ), true ) ) ) {
            $total_amount = array_sum( wp_list_pluck( $amounts_due, 'amount' ) );

            $payload['application_fee_amount'] = $total_amount * 0.03;
        }

        return $this->send_post_request(
            'invoices',
            $payload
        );
    }

    /**
     * Add an item to a Stripe Invoice Payment plan.
     *
     * @param string $customer_id Stripe customer ID.
     * @param string $invoice_id  Stripe invoice ID.
     * @param int    $amount      Total amount (in cents) to be added to the invoice.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function add_invoice_item( $customer_id, $invoice_id, $amount ) {

        $payload = array(
            'customer' => $customer_id,
            'invoice'  => $invoice_id,
            'amount'   => $amount,
            'currency' => get_woocommerce_currency(),
        );

        return $this->send_post_request(
            'invoiceitems',
            $payload
        );
    }

    /**
     * Finalize Stripe Invoice Payment plan.
     *
     * @param string $invoice_id Stripe invoice ID.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function finalize_invoice( $invoice_id ) {

        return $this->send_post_request( "invoices/$invoice_id/finalize" );
    }

    /**
     * Send Stripe Invoice Payment plan.
     *
     * @param array $query Stripe invoice ID.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function search_invoice( $query ) {

        $query_array = array();
        $operators   = array(
            ':',
            '~',
            '<',
            '>',
            '=',
        );
        foreach ( $query as $key => $value ) {
            if ( ! in_array( substr( $key, -1 ), $operators, true ) ) {
                $key .= ':';
            }
            $query_array[] = "$key" . ( is_numeric( $value ) ? $value : "\"$value\"" );
        }

        return $this->send_get_request(
            'invoices/search',
            array(),
            array(
                'body' => array(
                    'query' => implode( ' AND ', $query_array ),
                ),
            )
        );
    }

    /**
     * Get Stripe invoice.
     *
     * @param string $invoice_id Stripe invoice ID.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function get_invoice( $invoice_id ) {

        return $this->send_get_request(
            "invoices/$invoice_id"
        );
    }

    /**
     * Get Stripe registered webhooks.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function get_webhooks() {

        return $this->send_get_request(
            'webhook_endpoints'
        );
    }

    /**
     * Create a Stripe webhook.
     *
     * @param string   $url    Site URL to receive the webhook.
     * @param string[] $events Stripe webhook events.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function create_webhook( $url, $events ) {

        $payload = array(
            'url'            => $url,
            'enabled_events' => $events,
            'api_version'    => self::STRIPE_API_VERSION,
        );

        return $this->send_post_request(
            'webhook_endpoints',
            $payload
        );
    }

    /**
     * Verify Stripe signature.
     *
     * @param string $signature Stripe signature.
     * @param string $payload   Raw body of the request from Stripe.
     *
     * @since 1.0.0
     * @return bool
     */
    public function verify_signature( $signature, $payload ) {

        $elements = explode( ',', $signature );
        if ( is_array( $elements ) ) {
            $timestamp  = null;
            $signatures = array();
            foreach ( $elements as $element ) {
                $parts = explode( '=', $element );
                if ( 't' === $parts[0] ) {
                    $timestamp = $parts[1];
                }
                if ( 'v1' === $parts[0] ) {
                    $signatures[] = $parts[1];
                }
            }

            if ( empty( $timestamp ) || empty( $signatures ) ) {
                return false;
            }

            $signed_payload     = "$timestamp.$payload";
            $expected_signature = hash_hmac( 'sha256', $signed_payload, WPay::get_webhook_secret() );
            foreach ( $signatures as $signature ) {
                if ( hash_equals( $expected_signature, $signature ) ) {
                    return true;
                }
            }
        }

        return false;
    }
}
