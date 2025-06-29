<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Define API to handle connect to RCS API.
 *
 * @version 2.0.0
 * @since   2.0.0
 */
abstract class WWLC_Abstract_REST_Controller extends WP_REST_Controller {


	/**
     * List of routes to register
     *
     * @var array
     */
    public $routes = array();

    /**
     * The base path for the route.
     *
     * @var string
     */
    public $collection = 'leads';

    /**
     * The constructor method
     *
     * Instantiates all of the routes for this api
     *
     * @since 2.0.0
     */
    public function __construct() {}

	/**
     * Initialize the hooks to register the routes
     *
     * @since 2.0.0
     * @return void
     */
    public function init_hooks() {
        add_filter( 'wwlc_rest_api_routes', array( $this, 'api_routes' ) );
    }

    /**
     * Filter the list of API routes.
     *
     * @param mixed $routes The list of api routes.
     * @return array
     */
    public function api_routes( $routes ) {
        foreach ( $this->routes as $route => $args ) {
            $routes[ "{$this->collection}/{$route}" ] = $args;
        }
        return $routes;
    }

    /**
     * Check if the user exists in the database or not
     *
     * @param   string          $user - The user ID, username, or email.
     * @param   WP_REST_Request $request - The server request object.
     * @param   string          $param   - The parameter name.
     * @return  boolean|WP_Error
     * @since   2.0.0
     * @version 2.0.0
     */
    public function validate_user( $user, $request, $param ) {
        $wp_user = null;
        if ( is_numeric( $user ) ) {
            $wp_user = get_user_by( 'id', $user );
        } elseif ( is_email( $user ) ) {
            $wp_user = get_user_by( 'email', $user );
        } else {
            $wp_user = get_user_by( 'login', $user );
        }

        if ( is_a( $wp_user, 'WP_User' ) ) {
            return true;
        }

        return new WP_Error(
            'invalid_user',
            __( 'The user does not exist in the database', 'woocommerce-wholesale-lead-capture' )
        );
    }

    /**
     * Get the query params for collections
     *
     * @return array
     */
    public function get_collection_params() {
        return array(
            'page'     => array(
                'description'       => 'Current page of the collection.',
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'description'       => 'Maximum number of items to be returned in result set.',
                'type'              => 'integer',
                'default'           => 10,
                'sanitize_callback' => 'absint',
            ),
            'search'   => array(
                'description'       => 'Limit results to those matching a string.',
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    /**
     * Check if is a valid array.
     *
     * @param   array           $data The parameter to be validated as array.
     * @param   WP_REST_Request $request The WordPress REST Request object.
     * @param   string          $param The name of the parameter.
     * @return  boolean         $is_array Whether the given value is array or not.
     * @version 2.0.0
     * @since   2.0.0
     */
    public function validate_array( $data, $request, $param ) {
        return is_array( $data );
    }

    /**
     * Check if value is numeric.
     *
     * @param   array           $number The number to validate.
     * @param   WP_REST_Request $request The WordPress REST Request object.
     * @param   string          $param The name of the parameter.
     * @return  boolean         $is_numeric Whether the given value is array or not.
     * @version 2.0.0
     * @since   2.0.0
     */
    public function is_numeric( $number, $request, $param ) {
        return is_numeric( $number );
    }

    /**
     * REST response.
     *
     * @param mixed|WP_Error  $data    Response data.
     * @param WP_REST_Request $request Request object.
     * @param int             $status  Response Status code.
     * @param array           $headers Additional headers.
     *
     * @since 2.0.0
     * @return WP_REST_Response REST response header.
     */
    public function rest_response( $data, $request, $status = 200, $headers = array() ) {
        return new WP_REST_Response(
            $data,
            $status,
            $headers
        );
    }

    /**
     * Checks if a given request has access to get items.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 2.0.0
     * @return WP_Error|bool True if the request has read access, WP_Error object otherwise.
     */
    public function get_items_permissions_check( $request ) {
        return current_user_can( 'list_users' );
    }

    /**
     * Checks if a given request has access to get a specific item.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 2.0.0
     * @return WP_Error|bool True if the request has read access for the item, WP_Error object otherwise.
     */
    public function get_item_permissions_check( $request ) {
        return current_user_can( 'list_users' );
    }

    /**
     * Checks if a given request has access to create items.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 2.0.0
     * @return WP_Error|bool True if the request has access to create items, WP_Error object otherwise.
     */
    public function create_item_permissions_check( $request ) {
        return current_user_can( 'list_users' );
    }

    /**
     * Checks if a given request has access to update a specific item.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 2.0.0
     * @return WP_Error|bool True if the request has access to update the item, WP_Error object otherwise.
     */
    public function update_item_permissions_check( $request ) {
        return current_user_can( 'list_users' );
    }

    /**
     * Checks if a given request has access to delete a specific item.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @since 2.0.0
     * @return WP_Error|bool True if the request has access to delete the item, WP_Error object otherwise.
     */
    public function delete_item_permissions_check( $request ) {
        return current_user_can( 'list_users' );
    }

	/**
     * Return prepared item.
     *
     * @param mixed           $item    Item to be sent as response.
     * @param WP_REST_Request $request Request object.
     *
     * @return mixed|WP_REST_Response
     */
    public function prepare_item_for_response( $item, $request ) {
        return array(
            'object'  => 'lead',
            'message' => __( 'Item retrieved successfully.', 'woocommerce-wholesale-lead-capture' ),
            'data'    => $item,
        );
    }

	/**
     * Prepare a collection for a response.
     *
     * @param mixed  $data The data to be sent as the collection.
     * @param string $collection_data The key of the data to be sent as the collection.
     * @return array
     */
    public function prepare_collection_for_response( $data, $collection_data = 'data' ) {
        $message = 0 === count( $data )
            ? sprintf(
				// translators: %s is the name of the collection.
                __( 'No %s found in database.', 'woocommerce-wholesale-lead-capture' ),
                $this->collection
            )
            : sprintf(
				// translators: %1$s the number of items in the collection, %2$s is the name of the collection.
                __( 'Retrieved %1$d %2$s from the database.', 'woocommerce-wholesale-lead-capture' ),
                count( $data ),
                $this->collection
            );

        return array(
            'error'      => false,
            'collection' => $this->collection,
            'count'      => count( $data[ $collection_data ] ),
            'message'    => $message,
            'data'       => $data,
        );
    }

	/**
     * This is the index of the endpoints. It should endpoint details.
     *
     * @since 2.0.0
     * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
     */
    public function index( $request ) {
        return $this->rest_response(
            array(),
            $request
        );
    }

    /**
     * This is the route to list resource in the endpoint.
     *
     * @since 2.0.0
     * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
     */
    public function get_items( $request ) {
        return $this->rest_response(
            array(),
            $request
        );
    }

    /**
     * Endpoint to get a single item
     *
     * @since 2.0.0
     * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
     */
    public function get_item( $request ) {
        return $this->rest_response(
            array(),
            $request
        );
    }

    /**
     * Endpoint to create a new item
     *
     * @since 2.0.0
     * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
     */
    public function create_item( $request ) {
        return $this->rest_response(
            array(),
            $request
        );
    }

    /**
     * Endpoint to update an existing item
     *
     * @since 2.0.0
     * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
     */
    public function update_item( $request ) {
        return $this->rest_response(
            array(),
            $request
        );
    }

    /**
     * Endpoint to delete an item
     *
     * @since 2.0.0
     * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
     */
    public function delete_item( $request ) {
        return $this->rest_response(
            array(),
            $request
        );
    }
}
