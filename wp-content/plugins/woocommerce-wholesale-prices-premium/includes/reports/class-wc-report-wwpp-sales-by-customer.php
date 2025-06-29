<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WC_Report_WWPP_Sales_By_Customer' ) ) {

    include_once WP_PLUGIN_DIR . '/woocommerce/includes/admin/reports/class-wc-report-customer-list.php';

    /**
     * Model that handles the logic of wholesale sales by wholesale customer.
     *
     * We name the class with prepend of 'WC_Report', this is intentional.
     * Purpose is so we hook smoothly on this filter 'wc_admin_reports_path'.
     *
     * @since 1.13.0
     */
    class WC_Report_WWPP_Sales_By_Customer extends WC_Report_Customer_List {

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

        /**
         * WC_Report_WWPP_Sales_By_Customer constructor.
         *
         * @since 1.13.0
         * @access public
         */
        public function __construct() {

            parent::__construct();

            $this->_wwpp_wholesale_roles       = WWP_Wholesale_Roles::getInstance();
            $this->_registered_wholesale_roles = $this->_wwpp_wholesale_roles->getAllRegisteredWholesaleRoles();

        }

        /**
         * 99.99% similar codebase with WC_Report_Customer_List prepare_items with the exemption of only getting wholesale customers data.
         *
         * @since 1.13.0
         * @access public
         */
        public function prepare_items() {

            global $wpdb;

            $current_page = absint( $this->get_pagenum() );
            $per_page     = 20;

            // Init column headers.
            $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

            add_action( 'pre_user_query', array( $this, 'order_by_last_name' ) );

            // Get wholesale users ids.
            $wholesale_users_query = $wpdb->prepare(
                "SELECT 
                    $wpdb->users.ID 
                 FROM 
                    $wpdb->users 
                    INNER JOIN $wpdb->usermeta ON $wpdb->users.ID = $wpdb->usermeta.user_id 
                 WHERE 
                    $wpdb->usermeta.meta_key = %s AND (",
                $wpdb->prefix . 'capabilities'
            );

            $counter = 1;
            foreach ( $this->_registered_wholesale_roles as $role_key => $role_data ) {

                $wholesale_users_query .= $wpdb->prepare( " $wpdb->usermeta.meta_value LIKE %s ", '%' . $role_key . '%' );

                if ( $counter < count( $this->_registered_wholesale_roles ) ) {
                    $wholesale_users_query .= ' OR ';
                }

                $counter++;

            }
            $wholesale_users_query .= ')';

            $query_result = $wpdb->get_results( $wholesale_users_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

            $wholesale_user_ids = array();

            foreach ( $query_result as $result ) {
                $wholesale_user_ids[] = $result['ID'];
            }

            // We need this for pagination.
            $query = new WP_User_Query(
                array(
					'include' => $wholesale_user_ids,
					'number'  => $per_page,
					'offset'  => ( $current_page - 1 ) * $per_page,
                )
            );

            $this->items = $query->get_results();

            remove_action( 'pre_user_query', array( $this, 'order_by_last_name' ) );

            // Pagination.
            $this->set_pagination_args(
                array(
					'total_items' => $query->total_users,
					'per_page'    => $per_page,
					'total_pages' => ceil( $query->total_users / $per_page ),
                )
            );

        }

    }

}
