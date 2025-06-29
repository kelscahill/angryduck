<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\REST
 */

namespace RymeraWebCo\WPay\REST;

use RymeraWebCo\WPay\Abstracts\Abstract_REST;
use RymeraWebCo\WPay\Helpers\License;
use RymeraWebCo\WPay\Traits\Singleton_Trait;
use RymeraWebCo\WPay\Classes\License_Manager as License_Manager_Class;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * License_Manager class.
 *
 * @since 1.0.0
 */
class License_Manager extends Abstract_REST {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @since 3.0.6
     * @var License_Manager $instance object
     */
    protected static $instance;

    /**
     * Register plugin routes
     *
     * @return void
     */
    public function register_plugin_routes() {

        $this->rest_base = 'license-manager';
        parent::register_plugin_routes();
    }

    /**
     * Register REST routes.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            "/$this->rest_base/license/activate",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'activate_license' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args'                => array(
                    'license_key'   => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'license_email' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            "/$this->rest_base/license/notice/dismiss",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'dismiss_notice' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            "/$this->rest_base/license/refresh",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'license_refresh' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
            )
        );
    }

    /**
     * Activate license handler.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 1.0.0
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function activate_license( $request ) {

        $activation_email = $request->get_param( 'license_email' );
        $license_key      = $request->get_param( 'license_key' );

        if ( License::is_license_key_masked( $license_key ) ) {
            return new WP_Error(
                'wpay_license_activation_error',
                __(
                    'It seems license key is masked. Unable to activate masked license key.',
                    'woocommerce-wholesale-payments'
                ),
                array( 'status' => 400 )
            );
        }

        $activation_url = esc_url_raw(
            add_query_arg(
                array(
                    'activation_email' => rawurlencode( $activation_email ),
                    'license_key'      => $license_key,
                    'site_url'         => home_url(),
                    'software_key'     => 'WPAY',
                    'multisite'        => is_multisite() ? 1 : 0,
                ),
                apply_filters( 'wpay_license_activation_url', WWS_SLMW_SERVER_URL . '/wp-json/slmw/v1/license/activate' )
            )
        );

        License::update_plugin_license_email( $activation_email );
        License::update_plugin_license_key( $license_key );

        if ( empty( $activation_email ) ) {
            License::update_license_notice_dismissed( 'no' );

            return new WP_Error(
                'wpay_license_activation_error',
                __(
                    'Please enter a valid license email.',
                    'woocommerce-wholesale-payments'
                ),
                array( 'status' => 400 )
            );
        }

        if ( empty( $license_key ) ) {
            License::update_license_notice_dismissed( 'no' );

            return new WP_Error(
                'wpay_license_activation_error',
                __(
                    'Please enter a valid license key.',
                    'woocommerce-wholesale-payments'
                ),
                array( 'status' => 400 )
            );
        }

        $option = apply_filters(
            'wpay_license_activation_options',
            array(
                'timeout' => 10,
                'headers' => array( 'Accept' => 'application/json' ),
            )
        );

        $license_server_response = wp_remote_get( $activation_url, $option );
        if ( is_wp_error( $license_server_response ) ) {
            return $license_server_response;
        }

        $response = wp_remote_retrieve_body( $license_server_response );
        $response = json_decode( $response );
        /**
         * At this point, $response should be an object.
         *
         * @var object $response
         */
        $response = License_Manager_Class::instance()->update_license_data( $response, 'activation' );
        if ( 'fail' === $response->status ) {
            return new WP_Error(
                'wpay_license_activation_error',
                $response->error_msg,
                array( 'status' => 400 )
            );
        }

        if ( isset( $response->success_msg ) ) {
            $response->message = $response->success_msg;
        }

        if ( 'success' === $response->status && property_exists( $response, 'license_status' ) ) {
            License::update_last_license_check();
        }

        return $this->rest_response( $response, $request );
    }

    /**
     * Dismiss license notice handler.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 1.0.0
     * @return WP_REST_Response
     */
    public function dismiss_notice( $request ) {

        $response = array(
            'message' => __( 'License notice dismissed.', 'woocommerce-wholesale-payments' ),
        );

        License::update_license_notice_dismissed( 'yes' );

        return $this->rest_response( $response, $request );
    }

    /**
     * Refresh license handler.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 1.0.0
     * @return WP_REST_Response
     */
    public function license_refresh( $request ) {

        [
            'WPAY' => $license_data,
        ] = License_Manager_Class::instance()->license_check();

        return $this->rest_response( $license_data, $request );
    }
}
