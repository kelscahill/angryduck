<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\REST
 */

namespace RymeraWebCo\WWOF\REST;

use RymeraWebCo\WWOF\Abstracts\Abstract_REST;
use RymeraWebCo\WWOF\Helpers\WWOF;
use RymeraWebCo\WWOF\Traits\Singleton_Trait;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Admin_Notices class.
 *
 * @since 3.0
 */
class Admin_Notices extends Abstract_REST {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @var Admin_Notices $instance object
     * @since 3.0.6
     */
    protected static $instance;

    /**
     * Holds the list of whitelisted admin notice keys.
     *
     * @since 3.0
     * @var array
     */
    protected $notice_keys_whitelist;

    /**
     * Set `rest_base` to `admin-notices`.
     *
     * @since 3.0
     */
    public function register_plugin_routes() {

        $this->rest_base = 'admin-notices';

        $this->notice_keys_whitelist = array(
            'wwof_admin_notice_getting_started_show',
        );

        parent::register_plugin_routes();
    }

    /**
     * Register admin-notices routes.
     *
     * @since 3.0
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            "/$this->rest_base/dismiss/(?P<notice_key>[\w-]+)",
            array(
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'dismiss_admin_notice' ),
                    'permission_callback' => 'is_user_logged_in',
                ),
            )
        );
    }

    /**
     * Dismiss admin notice handler.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @since 3.0
     * @return WP_REST_Response|WP_Error
     */
    public function dismiss_admin_notice( $request ) {

        $notice_key = $request->get_param( 'notice_key' );

        if ( ! in_array( $notice_key, $this->notice_keys_whitelist, true ) ) {
            return new WP_Error(
                'wwof_rest_invalid_notice_key',
                __( 'Invalid notice key.', 'woocommerce-wholesale-order-form' ),
                array( 'status' => 400 )
            );
        }

        WWOF::update_getting_started_notice( 'no' );

        return $this->rest_response(
            array(
                'success' => true,
            ),
            $request
        );
    }
}
