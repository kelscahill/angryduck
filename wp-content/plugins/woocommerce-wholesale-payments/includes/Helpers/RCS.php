<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Helpers
 */

namespace RymeraWebCo\WPay\Helpers;

use RymeraWebCo\WPay\REST\Rymera_Stripe;
use RymeraWebCo\WPay\Traits\Singleton_Trait;
use WP_Error;

/**
 * RCS class.
 *
 * @since 1.0.0
 */
class RCS {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @since 3.0.6
     * @var RCS $instance object
     */
    protected static $instance;

    /**
     * Environment variables.
     *
     * @var array
     */
    private $env;

    /**
     * RCS constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {

        $this->env = Helper::parse_env();
    }

    /**
     * Get RCS base API URl.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_rcs_base_api_url() {

        if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === WP_ENVIRONMENT_TYPE ) {
            return trailingslashit( $this->env['WPAY_RCS_API_URL'] );
        }

        return 'https://connect.rymeraplugins.com/api/';
    }

    /**
     * Send a POST request to RCS.
     *
     * @see   wp_remote_post()
     *
     * @param array  $payload  The payload to send to RCS.
     * @param array  $options  wp_remote_post() options.
     *
     * @param string $endpoint The RCS endpoint to send the request to.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function send_post_request( $endpoint, $payload, $options = array() ) {

        $args     = wp_parse_args(
            $options,
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode( $payload ),
            )
        );
        $response = wp_remote_post( $this->get_rcs_base_api_url() . $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $json = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $response['response']['code'] ) && $response['response']['code'] >= 400 ) {
            if ( isset( $json['message'] ) ) {
                return new WP_Error( $response['response']['code'], $json['message'] );
            }

            return new WP_Error( $response['response']['code'], $response['response']['message'] );
        }

        return $json;
    }

    /**
     * Send a PUT request to RCS.
     *
     * @param string $endpoint The RCS endpoint to send the request to.
     * @param array  $payload  The payload to send to RCS.
     * @param array  $options  wp_remote_post() options.
     *
     * @return array|mixed|WP_Error
     */
    public function send_put_request( $endpoint, $payload, $options = array() ) {

        $options = wp_parse_args(
            $options,
            array(
                'method' => 'PUT',
            )
        );

        return $this->send_post_request( $endpoint, $payload, $options );
    }

    /**
     * Send a GET request to RCS.
     *
     * @see   wp_remote_get()
     *
     * @param mixed  $payload  Query string parameters.
     * @param array  $options  wp_remote_get() options.
     *
     * @param string $endpoint RCS endpoint.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function send_get_request( $endpoint, $payload, $options = array() ) {

        $args = wp_parse_args(
            $options,
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
            )
        );

        $url      = add_query_arg( $payload, $this->get_rcs_base_api_url() . $endpoint );
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $json = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $response['response']['code'] ) && $response['response']['code'] >= 400 ) {
            if ( isset( $json['message'] ) ) {
                return new WP_Error( $response['response']['code'], $json['message'] );
            }

            return new WP_Error( $response['response']['code'], $response['response']['message'] );
        }

        return $json;
    }

    /**
     * Send a `connect` request to RCS.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function connect() {

        $payload = array(
            'site_url'         => Helper::get_site_url(),
            'return_url'       => WPay::get_return_url( array( 'action' => 'success' ) ),
            'error_url'        => WPay::get_return_url( array( 'action' => 'error' ) ),
            'software_key'     => 'WPAY',
            'software_version' => Helper::get_plugin_data( 'Version' ),
            'webhook_url'      => rest_url( Rymera_Stripe::instance()->namespace . '/' . Rymera_Stripe::instance()->rest_base . '/webhook' ),
            'connect_nonce'    => wp_create_nonce( 'rcs-connect' ),
        );

        return $this->send_post_request( 'connect', $payload );
    }

    /**
     * Get Stripe account.
     *
     * @param string|null $account_number Stripe account number.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function get_stripe_account( $account_number = null ) {

        if ( empty( $account_number ) ) {
            $account_number = WPay::get_account_number();
        }

        return $this->send_get_request( 'retrieve-credentials', array( 'account_number' => $account_number ) );
    }

    /**
     * Checkin with RCS.
     *
     * @param array $payload Payload to send to RCS. Expects `site_url`, `software_key`, and `account_number`.
     *
     * @since 1.0.0
     * @return array|mixed|WP_Error
     */
    public function checkin( $payload ) {

        return $this->send_post_request( 'checkin', $payload );
    }
}
