<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


if ( ! class_exists( 'WWP_WWS_License_Manager' ) ) {

    /**
     * Model that houses the logic of Wholesale Suite license manager.
     *
     * @since 2.1.3
     */
    class WWP_WWS_License_Manager {

        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWP_WWS_License_Manager.
         *
         * @since 2.1.3
         * @access private
         * @var WWP_WWS_License_Manager
         */
        private static $_instance;

        /**
         * Property that holds the default plugin to be displayed in the license settings page.
         *
         * @since 2.1.11
         */
        const DEFAULT_PLUGIN = 'wwpp';

        /**
         * Class Methods
         */

        /**
         * WWP_WWS_License_Manager constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_WWS_License_Manager model.
         *
         * @access public
         * @since 2.1.3
         */
        public function __construct( $dependencies ) {}

        /**
         * Ensure that only one instance of WWP_WWS_License_Manager is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_WWS_License_Manager model.
         *
         * @return WWP_WWS_License_Manager
         * @since 2.1.3
         */
        public static function instance( $dependencies = null ) {

            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /*
        |---------------------------------------------------------------------------------------------------------------
        | WooCommerce WholeSale Suite License Settings
        |---------------------------------------------------------------------------------------------------------------
         */

        /**
         * Register general wws license settings page in a multi-site environment.
         *
         * @since 2.1.3
         * @access public
         */
        public function register_ms_wws_licenses_settings_menu() {
            add_menu_page(
                __( 'WWS License', 'woocommerce-wholesale-prices' ),
                __( 'WWS License', 'woocommerce-wholesale-prices' ),
                'manage_sites',
                'wws-ms-license-settings',
                array( self::instance(), 'generate_wws_licenses_settings_page' )
            );

            /**
             * Compatibility to WWPP 1.30.4, WWLC 1.17.7.1 & WWOF 3.0.4 and below.
             * This is to ensure that the default plugin is set to WWPP.
             * TODO: Remove this in future versions after the constant usage is removed in other plugins.
             */
            if ( ! defined( 'WWS_LICENSE_SETTINGS_DEFAULT_PLUGIN' ) ) {
                define( 'WWS_LICENSE_SETTINGS_DEFAULT_PLUGIN', self::DEFAULT_PLUGIN );
            }
        }

        /**
         * Register general wws license settings page.
         *
         * @since 2.1.3
         */
        public function register_wws_license_settings_menu() {
            // Register WWS Settings Menu.
            add_submenu_page(
                'wholesale-suite', // Settings.
                __( 'License', 'woocommerce-wholesale-prices' ),
                __( 'License', 'woocommerce-wholesale-prices' ),
                'manage_woocommerce',
                'wws-license-settings',
                array( self::instance(), 'generate_wws_licenses_settings_page' ),
                7
            );

            /**
             * Compatibility to WWPP 1.30.4, WWLC 1.17.7.1 & WWOF 3.0.4 and below.
             * This is to ensure that the default plugin is set to WWPP.
             * TODO: Remove this in future versions after the constant usage is removed in other plugins.
             */
            if ( ! defined( 'WWS_LICENSE_SETTINGS_DEFAULT_PLUGIN' ) ) {
                define( 'WWS_LICENSE_SETTINGS_DEFAULT_PLUGIN', self::DEFAULT_PLUGIN );
            }
        }

        /**
         * Add general WWS license markup.
         *
         * @since 2.1.3
         * @access public
         */
        public function generate_wws_licenses_settings_page() {
            require_once WWP_PLUGIN_PATH . 'views/wws-license-settings/view-wws-license-settings-page.php';
        }

        /**
         * Add WWP license header markup.
         *
         * @since 2.1.3
         * @access public
         */
        public function wwpp_license_tab() {
            ob_start();

            $key = 'wwpp';

            // phpcs:disable WordPress.Security.NonceVerification.Recommended
            if ( isset( $_GET['tab'] ) ) {
                $tab = $_GET['tab'];
            } elseif ( ! isset( $_GET['tab'] ) && self::DEFAULT_PLUGIN === $key ) {
                $tab = self::DEFAULT_PLUGIN;
            } else {
                $tab = '';
            }
            // phpcs:enable WordPress.Security.NonceVerification.Recommended

            if ( is_multisite() ) {
                $wwp_license_settings_url = get_site_url() . '/wp-admin/network/admin.php?page=wws-ms-license-settings&tab=wwpp';
            } else {
                $wwp_license_settings_url = get_site_url() . '/wp-admin/admin.php?page=wws-license-settings&tab=wwpp';
            }
            ?>

			<a href="<?php echo wp_kses_post( $wwp_license_settings_url ); ?>" class="nav-tab <?php echo ( 'wwpp' === $tab ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Wholesale Prices', 'woocommerce-wholesale-prices' ); ?></a>

			<?php
            echo wp_kses_post( ob_get_clean() );
        }

        /**
         * Add WWOF license header markup.
         *
         * @since 2.1.3
         * @access public
         */
        public function wwof_license_tab() {
            ob_start();

            $key = 'wwof';

            // phpcs:disable WordPress.Security.NonceVerification.Recommended
            if ( isset( $_GET['tab'] ) ) {
                $tab = $_GET['tab'];
            } elseif ( ! isset( $_GET['tab'] ) && self::DEFAULT_PLUGIN === $key ) {
                $tab = self::DEFAULT_PLUGIN;
            } else {
                $tab = '';
            }
            // phpcs:enable WordPress.Security.NonceVerification.Recommended

            if ( is_multisite() ) {
                $wwof_license_settings_url = get_site_url() . '/wp-admin/network/admin.php?page=wws-ms-license-settings&tab=wwof';
            } else {
                $wwof_license_settings_url = get_site_url() . '/wp-admin/admin.php?page=wws-license-settings&tab=wwof';
            }
            ?>

            <a href="<?php echo wp_kses_post( $wwof_license_settings_url ); ?>" class="nav-tab <?php echo ( 'wwof' === $tab ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Wholesale Ordering', 'woocommerce-wholesale-prices' ); ?></a>

			<?php
            echo wp_kses_post( ob_get_clean() );
        }

        /**
         * Add WWLC license header markup.
         *
         * @since 2.1.3
         * @access public
         */
        public function wwlc_license_tab() {
            ob_start();

            $key = 'wwlc';

            // phpcs:disable WordPress.Security.NonceVerification.Recommended
            if ( isset( $_GET['tab'] ) ) {
                $tab = $_GET['tab'];
            } elseif ( ! isset( $_GET['tab'] ) && self::DEFAULT_PLUGIN === $key ) {
                $tab = self::DEFAULT_PLUGIN;
            } else {
                $tab = '';
            }
            // phpcs:enable WordPress.Security.NonceVerification.Recommended

            if ( is_multisite() ) {
                $wwlc_license_settings_url = get_site_url() . '/wp-admin/network/admin.php?page=wws-ms-license-settings&tab=wwlc';
            } else {
                $wwlc_license_settings_url = get_site_url() . '/wp-admin/admin.php?page=wws-license-settings&tab=wwlc';
            }
            ?>

            <a href="<?php echo wp_kses_post( $wwlc_license_settings_url ); ?>" class="nav-tab <?php echo ( 'wwlc' === $tab ) ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Wholesale Lead', 'woocommerce-wholesale-lead-capture' ); ?></a>

			<?php
            echo wp_kses_post( ob_get_clean() );
        }

        /**
         * Add WWPP license upsell content markup.
         *
         * @since 2.1.3
         * @access public
         */
        public function wwpp_license_content() {
            ob_start();

            require_once WWP_PLUGIN_PATH . 'views/wws-license-settings/view-wwpp-license-upsell-content.php';

            echo wp_kses_post( ob_get_clean() );
        }

        /**
         * Add WWLC license upsell content markup.
         *
         * @since 2.1.3
         * @access public
         */
        public function wwlc_license_content() {
            ob_start();

            require_once WWP_PLUGIN_PATH . 'views/wws-license-settings/view-wwlc-license-upsell-content.php';

            echo wp_kses_post( ob_get_clean() );
        }

        /**
         * Add WWOF license upsell content markup.
         *
         * @since 2.1.3
         * @access public
         */
        public function wwof_license_content() {
            ob_start();

            require_once WWP_PLUGIN_PATH . 'views/wws-license-settings/view-wwof-license-upsell-content.php';

            echo wp_kses_post( ob_get_clean() );
        }

        /**
         * Inserts License and Tab Contents if Premium Plugins are not active
         *
         * @since 2.1.3
         * @since 2.1.4 Bug fix #229
         *
         * @access public
         */
        public function license_tab_and_contents() {
            // WWPP ---------------------------------------------------------------------------------------------------.

            if ( ! WWP_Helper_Functions::is_wwpp_active() ) {

                add_action( 'wws_action_license_settings_tab', array( $this, 'wwpp_license_tab' ) );
                add_action( 'wws_action_license_settings_wwpp', array( $this, 'wwpp_license_content' ) );

            } else {

                /**
                 * ! Important:
                 *
                 * We need to register license menu and content even if WWPP is active but if it is on version 1.27.11.
                 * This version no longer registers license menu and content so its solely the responsibility of WWP to register such.
                 */
                $wwpp_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php', false, false );

                if ( version_compare( $wwpp_plugin_data['Version'], '1.27.11', '>=' ) ) {

                    add_action( 'wws_action_license_settings_tab', array( $this, 'wwpp_license_tab' ) );
                    add_action( 'wws_action_license_settings_wwpp', array( $this, 'wwpp_license_content' ) );

                }
            }

            // WWOF ---------------------------------------------------------------------------------------------------.

            if ( ! WWP_Helper_Functions::is_wwof_active() ) {

                add_action( 'wws_action_license_settings_tab', array( $this, 'wwof_license_tab' ) );
                add_action( 'wws_action_license_settings_wwof', array( $this, 'wwof_license_content' ) );

            } else {

                /**
                 * ! Important:
                 *
                 * We need to register license menu and content even if WWOF is active but if it is on version 2.0.3.
                 * This version no longer registers license menu and content so its solely the responsibility of WWP to register such.
                 */
                $wwof_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/woocommerce-wholesale-order-form/woocommerce-wholesale-order-form.bootstrap.php', false, false );

                if ( version_compare( $wwof_plugin_data['Version'], '2.0.3', '>=' ) ) {

                    add_action( 'wws_action_license_settings_tab', array( $this, 'wwof_license_tab' ) );
                    add_action( 'wws_action_license_settings_wwof', array( $this, 'wwof_license_content' ) );

                }
            }

            // WWLC ---------------------------------------------------------------------------------------------------.

            if ( ! WWP_Helper_Functions::is_wwlc_active() ) {

                add_action( 'wws_action_license_settings_tab', array( $this, 'wwlc_license_tab' ) );
                add_action( 'wws_action_license_settings_wwlc', array( $this, 'wwlc_license_content' ) );

            } else {

                /**
                 * ! Important:
                 *
                 * We need to register license menu and content even if WWLC is active but if it is on version 1.17.2.
                 * This version no longer registers license menu and content so its solely the responsibility of WWP to register such.
                 */
                $wwlc_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/woocommerce-wholesale-lead-capture/woocommerce-wholesale-lead-capture.bootstrap.php', false, false );

                if ( version_compare( $wwlc_plugin_data['Version'], '1.17.2', '>=' ) ) {

                    add_action( 'wws_action_license_settings_tab', array( $this, 'wwlc_license_tab' ) );
                    add_action( 'wws_action_license_settings_wwlc', array( $this, 'wwlc_license_content' ) );

                }
            }

            do_action( 'wwp_license_tab_and_contents', $this );
        }

        /**
         * Execute model.
         *
         * @since 1.11
         * @access public
         */
        public function run() {
            if ( is_multisite() && get_current_blog_id() === 1 ) {

                // Add WooCommerce Wholesale Suite License Settings In Multi-Site Environment.
                add_action( 'network_admin_menu', array( $this, 'register_ms_wws_licenses_settings_menu' ) );

                // Add License Tab and Contents.
                add_action( 'init', array( $this, 'license_tab_and_contents' ) );

            } else {

                // Add WooCommerce Wholesale Suite License Menu.
                add_action( 'admin_menu', array( $this, 'register_wws_license_settings_menu' ), 99 );

                // Add License Tab and Contents.
                add_action( 'init', array( $this, 'license_tab_and_contents' ) );

            }
        }
    }
}
