<?php
/**
 * Author: Rymera Web Co.
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
use WP_REST_Server;

/**
 * Favourites class.
 *
 * @since 3.0.5
 */
class Favourites extends Abstract_REST {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @var Favourites $instance object
     * @since 3.0.6
     */
    protected static $instance;

    /**
     * Initialize REST routes.
     *
     * @return void
     */
    public function register_plugin_routes() {

        $this->namespace = 'wwof/v3';
        $this->rest_base = 'favourites';
        parent::register_plugin_routes();
    }

    /**
     * Checks if a given request has access to update a specific item.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 3.0
     * @return bool True if the request has access to update the item, false otherwise.
     */
    public function update_item_permissions_check( $request ) {

        return is_user_logged_in();
    }

    /**
     * Checks if a given request has access to delete a specific item.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 3.0
     * @return bool True if the request has access to delete the item, false otherwise.
     */
    public function delete_item_permissions_check( $request ) {

        return is_user_logged_in();
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            "/$this->rest_base/save/(?P<id>[\d]+)",
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'save' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args'                => array(
                    'id' => array(
                        'description' => __( 'Product ID.', 'woocommerce-wholesale-order-form' ),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            "/$this->rest_base/remove/(?P<id>[\d]+)",
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'remove' ),
                'permission_callback' => array( $this, 'delete_item_permissions_check' ),
                'args'                => array(
                    'id' => array(
                        'description' => __( 'Product ID.', 'woocommerce-wholesale-order-form' ),
                        'type'        => 'integer',
                        'required'    => true,
                    ),
                ),
            )
        );
    }

    /**
     * Save a favourite.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @since 3.0.5
     * @return WP_REST_Response
     */
    public function save( $request ) {

        $product_id = $request->get_param( 'id' );

        if ( WWOF::save_favourite_product( $product_id ) ) {
            $message = __( 'Product saved to favourites.', 'woocommerce-wholesale-order-form' );
        } else {
            $message = __( 'Product already in favourites.', 'woocommerce-wholesale-order-form' );
        }

        return $this->rest_response( compact( 'message' ), $request );
    }

    /**
     * Remove a product from favourites.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @since 3.0.5
     * @return WP_REST_Response
     */
    public function remove( $request ) {

        $product_id = $request->get_param( 'id' );

        if ( WWOF::remove_favourite_product( $product_id ) ) {
            $message = __( 'Product removed from favourites.', 'woocommerce-wholesale-order-form' );
        } else {
            $message = __( 'Product not found in favourites.', 'woocommerce-wholesale-order-form' );
        }

        return $this->rest_response( compact( 'message' ), $request );
    }
}
