<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( 'WC_Report_WWPP_Sales_By_Product' ) ) {

    include_once ( WP_PLUGIN_DIR . '/woocommerce/includes/admin/reports/class-wc-report-sales-by-product.php' );

    /**
     * Model that handles the logic of wholesale sales by product.
     * 
     * We name the class with prepend of 'WC_Report', this is intentional.
     * Purpose is so we hook smoothly on this filter 'wc_admin_reports_path'.
     *
     * @since 1.13.0
     */
    class WC_Report_WWPP_Sales_By_Product extends WC_Report_Sales_By_Product {

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

        public function __construct() {

            // Parent class has a constructor
            parent::__construct();

            $this->_wwpp_wholesale_roles       = WWP_Wholesale_Roles::getInstance();
            $this->_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

        }
        
        /**
         * Filter the report query to only retrieve wholesale orders.
         * As of v1.13.0 wholesale orders means orders made by wholesale customers.
         * It does not take in to account if the items in the order is indeed wholesale priced.
         * As long as the customer making the order is have a wholesale role, then the order is considered as wholesale order.
         *
         * @since 1.13.0
         * @since 1.16.8 
         * Wholesale orders are orders with order meta of 'wwp_wholesale_role'. We do not need to check the value it self 
         * (which is the wholesale role key of the customer during the order). The mere presence of the meta alone is enough
         * to signify that this is a wholesale order. This is intentional for the purpose of, what if they removed the
         * specific wholesale role later? Then if we rely on the value, then that order would not be included on the report.
         * @access public
         * 
         * @param array $query Array of sql query data.
         * @return array Filtered array of sql query data.
         */
        public function filter_report_query( $query ) {

            global $wpdb;
            
            // ! Very Important ! this must be posts.ID not $wpdb->posts.ID or else it will error out
            $wwpp_where_query = " AND posts.ID IN (
                SELECT $wpdb->postmeta.post_id FROM $wpdb->postmeta
                WHERE $wpdb->postmeta.meta_key = 'wwp_wholesale_role'
            )";

            $query[ 'where' ] .= $wwpp_where_query;

            return $query;

        }

        /**
         * Output the report.
         * Override the parent's output report function.
         * We add hooks before and after the report is outputed to alter the sql query.
         *
         * @since 1.13.0
         * @access public
         */
        public function output_report() {

            add_filter( 'woocommerce_reports_get_order_report_query' , array( $this , 'filter_report_query' ) , 10 , 1 );

            parent::output_report();

            remove_filter( 'woocommerce_reports_get_order_report_query' , array( $this , 'filter_report_query' ) , 10 );

        }

    }

}
