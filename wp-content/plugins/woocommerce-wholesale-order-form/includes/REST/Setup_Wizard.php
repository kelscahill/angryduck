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
 * Setup_Wizard class.
 *
 * @since 3.0
 */
class Setup_Wizard extends Abstract_REST {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @var Setup_Wizard $instance object
     * @since 3.0.6
     */
    protected static $instance;

    /**
     * Cron hook for displaying the setup wizard notice.
     *
     * @since 3.0
     */
    const CRON_HOOK = 'wwof_cron_display_wizard_notice';

    /**
     * Set `rest_base` to 'setup-wizard'.
     *
     * @since 3.0
     * @return void
     */
    public function register_plugin_routes() {

        $this->rest_base = 'setup-wizard';
        parent::register_plugin_routes();
    }

    /**
     * Registers the routes for the objects of the controller.
     *
     * @see   register_rest_route()
     * @since 3.0
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            "/$this->rest_base/notice",
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'handle_notice' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args'                => array(
                    'display' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );
    }

    /**
     * Handle setup wizard notice related requests.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 3.0
     * @return WP_REST_Response|WP_Error Custom response or WP_Error object.
     */
    public function handle_notice( $request ) {

        $display = $request->get_param( 'display' );

        $message = '';
        if ( 'dismiss' === $display ) {
            if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
                wp_schedule_single_event( strtotime( '+7 days' ), self::CRON_HOOK );
            }

            update_option( WWOF_DISPLAY_WIZARD_NOTICE, 'no' );
            $message = __( 'The setup wizard has been dismissed.', 'woocommerce-wholesale-order-form' );
        }

        return $this->rest_response( compact( 'message' ), $request );
    }
}
