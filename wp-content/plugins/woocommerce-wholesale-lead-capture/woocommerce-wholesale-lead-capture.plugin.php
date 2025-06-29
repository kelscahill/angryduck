<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WooCommerce_Wholesale_Lead_Capture' ) ) {

    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-wws-license-manager.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-wws-update-manager.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-bootstrap.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-scripts.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-ajax.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-forms.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-user-account.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-user-custom-fields.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-emails.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-registration-form-custom-fields.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-shortcode.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-cron.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-dashboard-widget.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-upgrade-account.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-invisible-recaptcha.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-admin-settings.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-leads-app.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-leads-admin-page.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'api/class-wwlc-rest-api.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-roles-migrator.php';
    require_once WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-roles-migrator-cli.php';

    /**
     * This is the main plugin class. It's purpose generally is for "ALL PLUGIN RELATED STUFF ONLY".
     * This file or class may also serve as a controller to some degree but most if not all business logic is distributed
     * across include files.
     *
     * Class WooCommerce_Wholesale_Lead_Capture
     */
    class WooCommerce_Wholesale_Lead_Capture {

        /**
         * Class Members
         */

        // phpcs:disable
        private static $_instance;

        public $_wwlc_license_manager;
        public $_wwlc_update_manager;
        public $_wwlc_bootstrap;
        public $_wwlc_scripts;
        public $_wwlc_forms;
        public $_wwlc_user_account;
        public $_wwlc_user_custom_fields;
        public $_wwlc_emails;
        public $_wwlc_wws_license_setting;
        public $_wwlc_registration_form_custom_fields;
        public $_wwlc_shortcode;
        public $_wwlc_ajax;
        public $_wwlc_cron;
        public $_wwlc_dashboard_widget;
        public $_wwlc_upgrade_account;
        public $_wwlc_admin_settings;
        public $_wwlc_leads_admin_page;

        public $_wwlc_rest_api;
        public $_wwlc_roles_migrator;

        // Plugin Integrations.
        public $_wwlc_invisible_recaptcha;
        // phpcs:enable

        const VERSION = '2.0.1';

        /*
        |--------------------------------------------------------------------------------------------------------------
        | Mesc Functions
        |--------------------------------------------------------------------------------------------------------------
         */

        /**
         * Class constructor.
         *
         * @since 1.0.0
         */
        public function __construct() {
            $this->_wwlc_license_manager = WWLC_WWS_License_Manager::instance(
                array(
					'WWLC_Version' => self::VERSION,
                )
            );
            $this->_wwlc_update_manager  = WWLC_WWS_Update_Manager::instance();

            $this->_wwlc_forms = WWLC_Forms::instance();

            $this->_wwlc_scripts = WWLC_Scripts::instance(
                array(
					'WWLC_Forms'   => $this->_wwlc_forms,
					'WWLC_Version' => self::VERSION,
                )
            );

            $this->_wwlc_user_account = WWLC_User_Account::instance();

            $this->_wwlc_emails = WWLC_Emails::instance(
                array(
					'WWLC_User_Account' => $this->_wwlc_user_account,
                )
            );

            $this->_wwlc_registration_form_custom_fields = WWLC_Registration_Form_Custom_Fields::instance();

            $this->_wwlc_bootstrap = WWLC_Bootstrap::instance(
                array(
					'WWLC_Forms'               => $this->_wwlc_forms,
                    'WWLC_WWS_License_Manager' => $this->_wwlc_license_manager,
					'WWLC_CURRENT_VERSION'     => self::VERSION,
                )
            );

            $this->_wwlc_user_custom_fields = WWLC_User_Custom_Fields::instance(
                array(
					'WWLC_User_Account' => $this->_wwlc_user_account,
					'WWLC_Emails'       => $this->_wwlc_emails,
                )
            );

            $this->_wwlc_shortcode = WWLC_Shortcode::instance(
                array(
					'WWLC_Forms' => $this->_wwlc_forms,
                )
            );

            $this->_wwlc_ajax = WWLC_AJAX::instance(
                array(
					'WWLC_Bootstrap'                       => $this->_wwlc_bootstrap,
					'WWLC_User_Account'                    => $this->_wwlc_user_account,
					'WWLC_Emails'                          => $this->_wwlc_emails,
					'WWLC_Forms'                           => $this->_wwlc_forms,
					'WWLC_Registration_Form_Custom_Fields' => $this->_wwlc_registration_form_custom_fields,
                )
            );

            $this->_wwlc_cron             = WWLC_Cron::instance();
            $this->_wwlc_dashboard_widget = WWLC_Dashboard_Widget::instance();

            $this->_wwlc_upgrade_account = WWLC_Upgrade_Account::instance(
                array(
					'WWLC_User_Account' => $this->_wwlc_user_account,
                )
            );

            $this->_wwlc_invisible_recaptcha = WWLC_Invisible_Recaptcha::instance();

            $this->_wwlc_admin_settings = WWLC_Admin_Settings::instance();

            $this->_wwlc_leads_admin_page = WWLC_Leads_Admin_Page::instance(
                array(
                    'WWLC_User_Account'       => $this->_wwlc_user_account,
                    'WWLC_Version'            => self::VERSION,
                    'WWLC_User_Custom_Fields' => $this->_wwlc_user_custom_fields,
                )
            );

            $this->_wwlc_rest_api = WWLC_REST_API::instance(
                array(
					'WWLC_User_Account'       => $this->_wwlc_user_account,
                    'WWLC_Emails'             => $this->_wwlc_emails,
                    'WWLC_User_Custom_Fields' => $this->_wwlc_user_custom_fields,
                )
            );

            $this->_wwlc_roles_migrator = WWLC_Roles_Migrator::instance(
                array(
					'WWLC_Version' => self::VERSION,
                )
            );
        }

        /**
         * Singleton Pattern.
         *
         * @return WooCommerce_Wholesale_Lead_Capture
         * @since 1.0.0
         */
        public static function instance() {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        /**
         * Settings
         */

        /**
         * Initialize plugin settings.
         *
         * @since 1.0.0
         * @param array $settings Array of settings.
         */
        public function initialize_plugin_settings( $settings ) {

            $settings[] = include WWLC_INCLUDES_ROOT_DIR . 'class-wwlc-settings.php';

            return $settings;
        }

        /**
         * Declare compatibility with WooCommerce HPOS.
         *
         * @since 1.17.6
         */
        public function declare_hpos_compatibility() {
            if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'woocommerce-wholesale-lead-capture/woocommerce-wholesale-lead-capture.bootstrap.php', true );
            }
        }

        /**
         * Admin old settings page redirection.
         *
         * @since  2.0
         * @access public
         */
        public function admin_old_wholesale_lc_settings_redirect() {
            // For wholesale prices tabs.
            if ( strpos( $_SERVER['REQUEST_URI'], 'page=wc-settings&tab=wwlc_settings' ) !== false ) {
                wp_safe_redirect( admin_url( 'admin.php?page=wholesale-settings&tab=wholesale_lead' ) );
                exit;
            }
        }

        /*
        |-------------------------------------------------------------------------------------------------------------------
        | Execution WWLC
        |
        | This will be the new way of executing the plugin.
        |-------------------------------------------------------------------------------------------------------------------
         */

        /**
         * Execute WWLC. Triggers the execution codes of the plugin models.
         *
         * @since 1.6.3
         * @access public
         */
        public function run() {

            // Redirect old wholesale settings.
            add_action( 'admin_init', array( $this, 'admin_old_wholesale_lc_settings_redirect' ) );

            // HPOS compatibility.
            add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

            $this->_wwlc_license_manager->run();
            $this->_wwlc_update_manager->run();
            $this->_wwlc_bootstrap->run();
            $this->_wwlc_scripts->run();
            $this->_wwlc_user_account->run();
            $this->_wwlc_ajax->run();
            $this->_wwlc_shortcode->run();
            $this->_wwlc_user_custom_fields->run();
            $this->_wwlc_emails->run();
            $this->_wwlc_cron->run();
            $this->_wwlc_forms->run();
            $this->_wwlc_dashboard_widget->run();
            $this->_wwlc_upgrade_account->run();
            $this->_wwlc_admin_settings->run();
            $this->_wwlc_leads_admin_page->run();
            $this->_wwlc_rest_api->init_hooks();
            $this->_wwlc_roles_migrator->run();

            // Plugin Integrations.
            $this->_wwlc_invisible_recaptcha->run();
        }
    }
}
