<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// class-wc-admin-reports.php

if ( !class_exists( 'WWPP_Report' ) ) {

    /**
     * Model that houses the logic of wholesale various wholesale reports.
     *
     * @since 1.13.0
     */
    class WWPP_Report {

        /*
        |--------------------------------------------------------------------------
        | Class Properties
        |--------------------------------------------------------------------------
        */

        /**
         * Property that holds the single main instance of WWPP_Report.
         *
         * @since 1.13.0
         * @access private
         * @var WWPP_Report
         */
        private static $_instance;
        
        /**
         * Model that houses the logic of retrieving information relating to wholesale role/s of a user.
         *
         * @since 1.13.0
         * @access private
         * @var WWPP_Wholesale_Roles
         */
        private $_wwpp_wholesale_roles;

        /**
         * Array of registered wholesale roles.
         *
         * @since 1.13.0
         * @access private
         * @var array
         */
        private $_registered_wholesale_roles;



        
        /*
        |--------------------------------------------------------------------------
        | Class Methods
        |--------------------------------------------------------------------------
        */

        /**
         * WWPP_Report constructor.
         *
         * @since 1.13.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Report model.
         */
        public function __construct( $dependencies ) {

            $this->_wwpp_wholesale_roles = $dependencies[ 'WWPP_Wholesale_Roles' ];

            $this->_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

        }

        /**
         * Ensure that only one instance of WWPP_Report is loaded or can be loaded (Singleton Pattern).
         *
         * @since 1.13.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWPP_Report model.
         * @return WWPP_Report
         */
        public static function instance( $dependencies ) {

            if ( !self::$_instance instanceof self )
                self::$_instance = new self( $dependencies );

            return self::$_instance;

        }

        /**
         * Integrate WWPP reports to WooCommerce reports.
         *
         * @since 1.13.0
         * @access public
         *
         * @param array $reports Array of reports data and sections.
         * @return array Filtered array of reports data and sections.
         */
        public function wwpp_reports( $reports ) {

            $report_sections = array(
                                    'wwpp_sales_by_date' => array(
                                        'title'       => __( 'Sales by date' , 'woocommerce-wholesale-prices-premium' ),
                                        'description' => '',
                                        'hide_title'  => true,
                                        'callback'    => array( 'WC_Admin_Reports' , 'get_report' )
                                    ),
                                    'wwpp_sales_by_customer' => array(
                                        'title'       => __( 'Sales by current wholesale customer' , 'woocommerce-wholesale-prices-premium' ),
                                        'description' => '',
                                        'hide_title'  => true,
                                        'callback'    => array( 'WC_Admin_Reports' , 'get_report' )
                                    ),
                                    'wwpp_sales_by_product' => array(
                                        'title'       => __( 'Sales by product' , 'woocommerce-wholesale-prices-premium' ),
                                        'description' => '',
                                        'hide_title'  => true,
                                        'callback'    => array( 'WC_Admin_Reports' , 'get_report' )
                                    )
                                );

            $report_sections = apply_filters( 'wwpp_report_sections' , $report_sections );

            $reports[ 'wwpp_reports' ] = array(
                                                'title'   => __( 'Wholesale' , 'woocommerce-wholesale-prices-premium' ),
                                                'reports' => $report_sections
                                            );

            return $reports;

        }

        /**
         * Load in WWPP report files.
         *
         * @since 1.13.0
         * @access public
         *
         * @param string $file_path Report file path.
         * @param string $name      Report name.
         * @param string $class     Report class name.
         * @return string Filtered report file path.
         */
        public function wwpp_reports_files( $file_path , $name , $class ) {

            switch ( $name ) {

                case 'wwpp-sales-by-date':
                    return WP_PLUGIN_DIR . '/woocommerce-wholesale-prices-premium/includes/reports/class-wc-report-wwpp-sales-by-date.php';
                
                case 'wwpp-sales-by-customer':
                    return WP_PLUGIN_DIR . '/woocommerce-wholesale-prices-premium/includes/reports/class-wc-report-wwpp-sales-by-customer.php';
                
                case 'wwpp-sales-by-product':
                    return WP_PLUGIN_DIR . '/woocommerce-wholesale-prices-premium/includes/reports/class-wc-report-wwpp-sales-by-product.php';
                
            }

            return $file_path;

        }
        

        /**
         * Execute model.
         *
         * @since 1.13.0
         * @access public
         */
        public function run() {

            add_filter( 'woocommerce_admin_reports' , array( $this , 'wwpp_reports' )       , 100  , 1 );
            add_filter( 'wc_admin_reports_path'     , array( $this , 'wwpp_reports_files' ) , 100 , 3 );

        }

    }

}
