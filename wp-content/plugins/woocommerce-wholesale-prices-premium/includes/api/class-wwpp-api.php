<?php if (!defined('ABSPATH')) {
    exit;
}
// Exit if accessed directly

if (!class_exists('WWPP_REST_API')) {

    /**
     * Model that houses the logic of WWPP API.
     *
     * @since 1.24.4
     */
    class WWPP_REST_API
    {

        /*
        |--------------------------------------------------------------------------
        | Class Properties
        |--------------------------------------------------------------------------
         */

        /**
         * Property that holds the single main instance of WWPP_REST_API.
         *
         * @var WWPP_REST_API
         */
        private static $_instance;

        /**
         * Property that holds WWPP API Controllers.
         *
         * @since 1.25
         */
        public $wwpp_rest_api_wholesale_products_controller;
        public $wwpp_rest_api_wholesale_variations_controller;
        public $wwpp_rest_api_wholesale_roles_controller;
        public $wwpp_rest_api_wholesale_general_discount_controller;
        public $wwpp_rest_api_wholesale_category_discount_controller;

        /**
         * WWP API Minimum Version
         */
        const WWP_MIN_VERSION = "1.16";

        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
         */

        /**
         * WWPP_REST_API constructor.
         *
         * @since 1.24.4
         * @access public
         */
        public function __construct()
        {
            add_action('woocommerce_loaded', array($this, 'load_wwpp_api'), 10);
        }

        /**
         * Ensure that only one instance of WWPP_REST_API is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.24.4
         * @access public
         *
         * @return WWPP_REST_API
         */
        public static function instance()
        {

            if (!self::$_instance instanceof self) {
                self::$_instance = new self();
            }

            return self::$_instance;

        }

        /**
         * Load WWPP API.
         *
         * @since 1.27.0
         * @access public
         */
        public function load_wwpp_api()
        {

            // API legacy Controllers using wc/v3 namespace
            add_action('rest_api_init', array($this, 'load_api_legacy_controllers'), 6);

            // API controllers using wholesale/v1 namespace
            add_action('rest_api_init', array($this, 'load_api_wwpp_controllers'), 6);

            // Authenticate users if api keys are provided
            add_action('woocommerce_rest_is_request_to_rest_api', array($this, 'authenticate_user'));

        }

        /**
         * WWPP Legacy API controllers under wc/v3 namespace.
         *
         * @since 1.24.4
         * @access public
         */
        public function load_api_legacy_controllers()
        {

            require_once WWPP_INCLUDES_PATH . 'api/legacy/class-wc-api-wholesale-products-controller.php';
            require_once WWPP_INCLUDES_PATH . 'api/legacy/class-wc-api-wholesale-products-variations-controller.php';
            require_once WWPP_INCLUDES_PATH . 'api/legacy/class-wc-api-wholesale-roles-controller.php';

        }

        /**
         * WWPP API controllers under wholesale/v1 namespace.
         *
         * @since 1.24.4
         * @access public
         */
        public function load_api_wwpp_controllers()
        {

            if (self::wwp_api_min_version()) {

                // WWPP API Helpers
                require_once WWPP_INCLUDES_PATH . 'api/helpers/class-wwpp-api-helpers.php';

                // WWPP API Controllers
                require_once WWPP_INCLUDES_PATH . 'api/v1/class-wwpp-rest-api-wholesale-products-v1-controller.php';
                require_once WWPP_INCLUDES_PATH . 'api/v1/class-wwpp-rest-api-wholesale-products-variations-v1-controller.php';
                require_once WWPP_INCLUDES_PATH . 'api/v1/class-wwpp-rest-api-wholesale-roles-v1-controller.php';
                require_once WWPP_INCLUDES_PATH . 'api/v1/class-wwpp-rest-api-wholesale-general-discount-v1-controller.php';
                require_once WWPP_INCLUDES_PATH . 'api/v1/class-wwpp-rest-api-wholesale-category-discount-v1-controller.php';

                $this->wwpp_rest_api_wholesale_products_controller = new WWPP_REST_Wholesale_Products_V1_Controller;
                $this->wwpp_rest_api_wholesale_variations_controller = new WWPP_REST_Wholesale_Product_Variations_V1_Controller;
                $this->wwpp_rest_api_wholesale_roles_controller = new WWPP_REST_Wholesale_Roles_V1_Controller;
                $this->wwpp_rest_api_wholesale_general_discount_controller = new WWPP_REST_Wholesale_General_Discount_V1_Controller;
                $this->wwpp_rest_api_wholesale_category_discount_controller = new WWPP_REST_Wholesale_Category_Discount_V1_Controller;

            }

        }

        /**
         * Authenticate if user if using WWPP rest base if api keys are provided.
         *
         * @since 1.24.4
         * @access public
         *
         * @param bool $rest_request
         * @return boolean
         */
        public function authenticate_user($rest_request)
        {

            $rest_prefix = trailingslashit(rest_get_url_prefix());
            $request_uri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']));

            // Authenticate if the request is using the wholesale/v1 namespace.
            if ((false !== strpos($request_uri, $rest_prefix . 'wholesale/'))) {
                return true;
            }

            return $rest_request;

        }

        /**
         * Authenticate if user if using WWPP rest base if api keys are provided.
         *
         * @since 1.24.4
         * @access public
         *
         * @param bool $rest_request
         * @return boolean
         */
        public static function wwp_api_min_version()
        {

            $wwp_plugin_data = WWP_Helper_Functions::get_plugin_data('woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php');

            return (version_compare($wwp_plugin_data['Version'], self::WWP_MIN_VERSION, '>=')) ? true : false;

        }

    }

}
