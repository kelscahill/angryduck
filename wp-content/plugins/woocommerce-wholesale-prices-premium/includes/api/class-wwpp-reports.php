<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWPP_REPORTS_REST_API' ) ) {

    /**
     * Model that houses the logic of WWPP Reports API.
     *
     * @since 1.24.4
     */
    class WWPP_REPORTS_REST_API {
        /*
        |--------------------------------------------------------------------------
        | Class Properties
        |--------------------------------------------------------------------------
         */

        /**
         * Property that holds the single main instance of WWPP_REPORTS_REST_API.
         *
         * @var WWPP_REPORTS_REST_API
         */
        private static $_instance;

        /**
         * Property that holds WWPP API Controllers.
         *
         * @since 1.25
         * @var object
         */
        public $wwpp_reports_sales;

        /**
         * Property that holds WWPP Unique Wholesale Customer Orders API.
         *
         * @var object
         */
        public $wwpp_reports_customers;


        /**
         * Property that holds WWPP Category Performance API.
         *
         * @var object
         */
        public $wwpp_reports_category_performance;


        /**
         * Property that holds WWPP top products wholesale volume API.
         *
         * @var object
         */
        public $wwpp_top_products_wholesale_volume;

        /**
         * Property that holds WWPP Roles API.
         *
         * @since 2.2.0
         * @var object
         */
        public $wwpp_reports_roles;


        /**
         * WWPP_REPORTS_REST_API constructor.
         *
         * @since 1.24.4
         * @access public
         */
        public function __construct() {

            $this->require_reports_api();

            add_action( 'rest_api_init', array( $this, 'register_reports_api' ), 6 );
        }

        /**
         * Ensure that only one instance of WWPP_REPORTS_REST_API is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.24.4
         * @access public
         *
         * @return WWPP_REPORTS_REST_API
         */
        public static function instance() {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        /**
         * Include the files containing api responses.
         *
         * @return void
         */
        public function require_reports_api() {
            require_once WWPP_INCLUDES_PATH . 'analytics/analytics-traits/trait-wwpp-analytics-query.php';
            require_once WWPP_INCLUDES_PATH . 'analytics/analytics-traits/trait-wwpp-analytics-date-time.php';
            require_once WWPP_INCLUDES_PATH . 'analytics/analytics-traits/trait-wwpp-top-products-price-format.php';
            require_once WWPP_INCLUDES_PATH . 'analytics/class-wwpp-sales-controller.php';
            require_once WWPP_INCLUDES_PATH . 'analytics/class-wwpp-unique-wholesale-customer-orders-controller.php';
            require_once WWPP_INCLUDES_PATH . 'analytics/class-wwpp-category-performance-controller.php';
            require_once WWPP_INCLUDES_PATH . 'analytics/class-wwpp-products-controller.php';
            require_once WWPP_INCLUDES_PATH . 'analytics/class-wwpp-roles-controller.php';
        }

        /**
         * Load the api responses.
         *
         * @return void
         */
        public function register_reports_api() {
            $this->wwpp_reports_sales = new WWPP_Sales_Controller();
            $this->wwpp_reports_sales->run();

            $this->wwpp_reports_customers = new WWPP_Unique_Wholesale_Customer_Orders_Controller();
            $this->wwpp_reports_customers->run();

            $this->wwpp_reports_category_performance = new WWPP_Category_Performance_Api_Controller();
            $this->wwpp_reports_category_performance->run();

            $this->wwpp_top_products_wholesale_volume = new WWPP_Products_Controller();
            $this->wwpp_top_products_wholesale_volume->run();

            $this->wwpp_reports_roles = new WWPP_Roles_Controller();
            $this->wwpp_reports_roles->run();
        }
    }
}
