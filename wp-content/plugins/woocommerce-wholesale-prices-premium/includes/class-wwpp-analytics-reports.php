<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RymeraWebCo\WWPP\Vite_App;

if ( ! class_exists( 'WWPP_Reports' ) ) {

    /**
     * Woocommerce Wholesale Prices Premium Reports.
     */
    class WWPP_Reports {

        /**
         * Contains the instance of the reports.
         *
         * @var object
         */
        private static $instance = null;

        /**
         * Contains the singleton instance of the 'Reports'
         *
         * @param array $dependencies The dependencies related to this class.
         * @return object the current instance of the class.
         */
        public static function instance( $dependencies ) {
            if ( null === self::$instance ) {
                self::$instance = new self( $dependencies );
            }

            return self::$instance;
        }

        /**
         * Main view for the reports page
         *
         * @return void
         */
        public function view_wwpp_reports_page() {
            include WWPP_VIEWS_PATH . '/analytics/wwpp-reports-page.php';
        }

        /**
         * Register the new 'Reports' page.
         *
         * @return void
         */
        public function register_wwpp_reports_page() {
            remove_submenu_page( 'wholesale-suite', 'wholesale-reports' ); // remove the existing version menu first.

            add_submenu_page(
                'wholesale-suite',
                __( 'Reports', 'woocommerce-wholesale-prices-premium' ),
                __( 'Reports', 'woocommerce-wholesale-prices-premium' ),
                'manage_woocommerce',
                'wholesale-analytics',
                array( $this, 'view_wwpp_reports_page' ),
                10
            );
        }

        /**
         * Load the 'WWPP Reports' scripts and styles.
         *
         * @param string $hook The current admin page hook suffix.
         * @return void
         */
        public function register_wwpp_reports_scripts( $hook ) {
            if ( 'wholesale_page_wholesale-analytics' !== $hook ) {
                return;
            }

            $app = new Vite_App(
                'wwpp-reports-app-scripts',
                'src/apps/wwpp-reports/index.ts',
                array(
                    'wp-i18n',
                    'wp-url',
                    'wp-hooks',
                    'wp-html-entities',
                    'lodash',
                    'jquery',
                    // `wc-currency` exposes `window.wc.currency` (the
                    // @woocommerce/currency package) which the
                    // useCurrencyFormatter composable consumes to render
                    // prices in the store's configured currency / locale.
                    'wc-currency',
                ),
                array(
                    'wpNonce' => wp_create_nonce( 'wp_rest' ),
                    'wcNonce' => wp_create_nonce( 'wc_store_api' ),
                    'restUrl' => rest_url(),
                ),
                'wwppReports'
            );

            $app->enqueue();
        }

        /**
         * The main initiator of this class
         *
         * @return void
         */
        public function run() {
            add_action( 'admin_menu', array( $this, 'register_wwpp_reports_page' ), 99 );
            add_action( 'admin_enqueue_scripts', array( $this, 'register_wwpp_reports_scripts' ), 10, 1 );
        }
    }

}
