<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WWLC REST API
 *
 * Clas that load the files the register the API endopoints
 */
class WWLC_REST_API {
    /**
     * Property that holds the single main instance of WWPP_Dashboard.
     *
     * @since  2.0.0
     * @access private
     * @var WWLC_REST_API
     */
	private static $_instance;

    /**
     * The API namespace
     *
     * @var string
     * @version 2.0.0
     * @since   2.0.0
     */
    public $namespace = 'wwlc/v1';

    /**
     * The REST base
     *
     * @var string
     * @version 2.0.0
     * @since   2.0.0
     */
    public $rest_base = '';

    /**
     * Holds instance of WWLC_Leads_Api
     *
     * @var WWLC_Leads_Api
     */
    public $leads_api;

    /**
     * List of routes to register.
     *
     * @var array
     */
    public $routes = array();

    /**
	 * Ensure that only one instance ofthis class is created/loaded
	 *
	 * @since  2.0.0
	 * @access public
     *
     * @param array $dependencies The dependencies of the class.
	 *
	 * @return WWLC_REST_API
	 */
	public static function instance( $dependencies ) {

		if ( ! self::$_instance instanceof self ) {
			self::$_instance = new self( $dependencies );
		}

		return self::$_instance;
	}

    /**
     * Create a new instance of the class.
     *
     * @param array $dependencies The dependencies of the class.
     *
     * @access public
     * @return void
     */
    public function __construct( $dependencies ) {

        $this->includes();

        $this->leads_api = WWLC_Leads_Api::instance( $dependencies );

        $this->routes = $this->get_routes();
    }

    /**
     * Initialize the hooks.
     *
     * @return void
     * @version 2.0.0
     * @since   2.0.0
     */
    public function init_hooks() {
        $this->leads_api->init_hooks();

        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Include files that define the API's logic.
     *
     * @access public
     * @return void
     */
    public function includes() {
        include_once 'abstract/class-abstract-wwlc-rest-api.php';
        include_once 'class-wwlc-leads-api.php';
    }

    /**
     * Register rest routes
     *
     * @return void
     * @version 2.0.0
     * @since   2.0.0
     */
    public function register_routes() {
        foreach ( $this->get_routes() as $route => $args ) {
            register_rest_route(
                $this->namespace,
                "/$this->rest_base/$route",
                $args
            );
        }
    }

    /**
     * Get the registered routes.
     *
     * @return array
     */
    public function get_routes() {
        return apply_filters(
            'wwlc_rest_api_routes',
            $this->routes
        );
    }
}
