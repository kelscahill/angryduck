<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Integrations
 */

namespace RymeraWebCo\WPay\Integrations;

use RymeraWebCo\WPay\Abstracts\Abstract_Class;
use RymeraWebCo\WPay\Helpers\Helper;
use RymeraWebCo\WPay\Helpers\RCS;
use RymeraWebCo\WPay\Helpers\WPay;
use WP_REST_Request;

/**
 * WP class.
 *
 * @since 1.0.0
 */
class WP extends Abstract_Class {

    /**
     * Get user display name.
     *
     * @param array           $user       User data.
     * @param string          $field_name Field name.
     * @param WP_REST_Request $request    Request object.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_user_display_name( $user, $field_name, $request ) {

        return get_user_by( 'id', $user['id'] )->display_name;
    }

    /**
     * Expose REST fields.
     *
     * @since 1.0.0
     * @return void
     */
    public function expose_fields() {

        register_rest_field(
            'user',
            'display_name',
            array(
                'get_callback'    => array( $this, 'get_user_display_name' ),
                'update_callback' => null,
                'schema'          => null,
            )
        );
    }

    /**
     * Handle RCS check-in.
     *
     * @since 1.0.0
     * @since 1.0.1 Added software version to the payload.
     * @return void
     */
    public function rcs_check_in() {

        $payload = array(
            'site_url'         => site_url(),
            'software_key'     => 'WPAY',
            'account_number'   => WPay::get_account_number(),
            'software_version' => Helper::get_plugin_data( 'Version' ),
        );

        $payload_count = count( $payload );

        $payload = array_filter( $payload );
        if ( count( $payload ) < $payload_count ) {
            wc_get_logger()->warning( __( 'Wholesale Payments check-in is missing required data.', 'woocommerce-wholesale-payments' ) );

            return;
        }

        $response = RCS::instance()->checkin( $payload );
        if ( is_wp_error( $response ) ) {
            wc_get_logger()->error(
            /* translators: %s = error message text */
                sprintf( __( 'Wholesale Payments check-in failed: %s', 'woocommerce-wholesale-payments' ), $response->get_error_message() )
            );

            return;
        }

        wc_get_logger()->info( $response['message'] );
    }

    /**
     * Run the integration.
     */
    public function run() {

        add_action( 'rest_api_init', array( $this, 'expose_fields' ) );
        add_action( 'wpay_check_in', array( $this, 'rcs_check_in' ) );
    }
}
