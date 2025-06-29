<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\REST
 */

namespace RymeraWebCo\WWOF\REST;

use RymeraWebCo\WWOF\Abstracts\Abstract_REST;
use RymeraWebCo\WWOF\Traits\Singleton_Trait;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * License_Manager class.
 *
 * @since 3.0
 */
class License_Manager extends Abstract_REST {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @var License_Manager $instance object
     * @since 3.0.6
     */
    protected static $instance;

    /**
     * Set `rest_base` to 'license-manager'.
     *
     * @since 3.0
     */
    public function register_plugin_routes() {

        $this->rest_base = 'license-manager';
        parent::register_plugin_routes();
    }

    /**
     * Register custom routes for this class.
     *
     * @since 3.0
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            "/$this->rest_base/activate",
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
            "/$this->rest_base/dismiss-notice",
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'dismiss_activate_notice' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
            )
        );
    }

    /**
     * Dismiss license activation notice handler.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 3.0
     * @return WP_REST_Response
     */
    public function dismiss_activate_notice( $request ) {

        if ( is_multisite() ) {
            update_site_option( WWOF_ACTIVATE_LICENSE_NOTICE, 'yes' );
        } else {
            update_option( WWOF_ACTIVATE_LICENSE_NOTICE, 'yes' );
        }

        return $this->rest_response(
            array(
                'message' => __( 'License activation notice dismissed!', 'woocommerce-wholesale-order-form' ),
                'status'  => 'success',
            ),
            $request
        );
    }

    /**
     * Activate license handler.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 3.0
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function activate_license( $request ) {

        $activation_email = $request->get_param( 'license_email' );
        $license_key      = $request->get_param( 'license_key' );

        $activation_url = esc_url_raw(
            add_query_arg(
                array(
                    'activation_email' => rawurlencode( $activation_email ),
                    'license_key'      => $license_key,
                    'site_url'         => home_url(),
                    'software_key'     => 'WWOF',
                    'multisite'        => is_multisite() ? 1 : 0,
                ),
                /**
                 * Filter the license activation URL.
                 *
                 * @param string $url The license activation URL.
                 *
                 * @since 3.0
                 */
                apply_filters( 'wwof_license_activation_url', WWOF_LICENSE_ACTIVATION_URL )
            )
        );

        if ( is_multisite() ) {
            update_site_option( WWOF_OPTION_LICENSE_EMAIL, $activation_email );
            update_site_option( WWOF_OPTION_LICENSE_KEY, $license_key );
        } else {
            update_option( WWOF_OPTION_LICENSE_EMAIL, $activation_email );
            update_option( WWOF_OPTION_LICENSE_KEY, $license_key );
        }

        /**
         * Filter the license activation options.
         *
         * @param array $option The license activation options.
         *
         * @since 3.0
         */
        $option = apply_filters(
            'wwof_license_activation_options',
            array(
                'timeout' => 10,
                'headers' => array( 'Accept' => 'application/json' ),
            )
        );

        $license_server_response = wp_remote_get( $activation_url, $option );
        if ( is_wp_error( $license_server_response ) ) {
            return $license_server_response;
        }

        $result = wp_remote_retrieve_body( $license_server_response );

        if ( empty( $result ) ) {
            if ( is_multisite() ) {
                delete_site_option( WWOF_LICENSE_EXPIRED );
            } else {
                delete_option( WWOF_LICENSE_EXPIRED );
            }

            return new WP_Error(
                'wwof_license_activation_error',
                __(
                    'Failed to activate license. Failed to connect to activation access point. Please contact plugin support.',
                    'woocommerce-wholesale-order-form'
                ),
                array( 'status' => 500 )
            );
        }

        $result = json_decode( $result );
        if ( empty( $result ) || ! property_exists( $result, 'status' ) ) {
            if ( is_multisite() ) {
                delete_site_option( WWOF_LICENSE_EXPIRED );
            } else {
                delete_option( WWOF_LICENSE_EXPIRED );
            }

            return new WP_Error(
                'wwof_license_activation_error',
                __(
                    'Failed to activate license. Activation access point return invalid response. Please contact plugin support.',
                    'woocommerce-wholesale-order-form'
                ),
                array( 'status' => 500 )
            );
        }

        if ( 'success' === $result->status ) {
            if ( is_multisite() ) {
                delete_site_option( WWOF_LICENSE_EXPIRED );
                update_site_option( WWOF_LICENSE_ACTIVATED, 'yes' );
            } else {
                delete_option( WWOF_LICENSE_EXPIRED );
                update_option( WWOF_LICENSE_ACTIVATED, 'yes' );
            }

            if ( isset( $result->error_msg ) ) {
                // Error message from license server with a success result means already activated.
                $response = array( 'message' => $result->error_msg );
            } else {
                // Report activation successful.
                $response = array( 'message' => $result->success_msg );
            }
        } else {
            $response = array( 'message' => $result->error_msg );

            if ( is_multisite() ) {
                update_site_option( WWOF_LICENSE_ACTIVATED, 'no' );
            } else {
                update_option( WWOF_LICENSE_ACTIVATED, 'no' );
            }

            $wp_site_transient = get_site_transient( 'update_plugins' );
            if ( $wp_site_transient ) {
                $wwof_plugin_basename = plugin_basename( WWOF_PLUGIN_FILE );

                if ( isset( $wp_site_transient->checked ) &&
                    is_array( $wp_site_transient->checked ) &&
                    array_key_exists( $wwof_plugin_basename, $wp_site_transient->checked ) ) {
                    unset( $wp_site_transient->checked[ $wwof_plugin_basename ] );
                }

                if ( isset( $wp_site_transient->response ) &&
                    is_array( $wp_site_transient->response ) &&
                    array_key_exists( $wwof_plugin_basename, $wp_site_transient->response ) ) {
                    unset( $wp_site_transient->response[ $wwof_plugin_basename ] );
                }

                set_site_transient( 'update_plugins', $wp_site_transient );

                wp_update_plugins();
            }

            if ( property_exists( $result, 'expiration_timestamp' ) ) {

                $response['expired_date'] = gmdate( 'Y-m-d', $result->expiration_timestamp );

                if ( is_multisite() ) {
                    update_site_option( WWOF_LICENSE_EXPIRED, $result->expiration_timestamp );
                } else {
                    update_option( WWOF_LICENSE_EXPIRED, $result->expiration_timestamp );
                }
            } elseif ( is_multisite() ) {
                delete_site_option( WWOF_LICENSE_EXPIRED );
            } else {
                delete_option( WWOF_LICENSE_EXPIRED );
            }
        }

        // Clear license cache after data refreshed to accurately show Dashboard data (but only for non-multisite).
        if ( ! is_multisite() ) {
            delete_transient( WWOF_PLUGIN_LICENSE_STATUSES_CACHE );
        }

        /**
         * Deprecated action hook for license activation REST response.
         *
         * @param array  $response         The license activation response.
         * @param string $activation_email The activation email.
         * @param string $license_key      The license key.
         *
         * @deprecated 3.0
         */
        do_action_deprecated( 'wwof_license_activation_rest_response', compact( 'response', 'activation_email', 'license_key' ), '3.0' );

        if ( 'success' !== $result->status ) {
            $data = array( 'status' => 400 );
            if ( ! empty( $response['expired_date'] ) ) {
                $data['expired_date'] = $response['expired_date'];
            }

            return new WP_Error(
                'wwof_license_activation_error',
                ! empty( $response['message'] ) ? $response['message'] : __( 'Something went wrong activating the license. Please contact plugin support.', 'woocommerce-wholesale-order-form' ),
                $data
            );
        }

        return $this->rest_response( $response, $request );
    }
}
