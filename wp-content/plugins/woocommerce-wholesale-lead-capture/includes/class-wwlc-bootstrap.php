<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWLC_Bootstrap' ) ) {

    /**
     * Model that houses the logic of bootstrapping the plugin.
     *
     * @since 1.0.0
     */
    class WWLC_Bootstrap {

        /**
         * Class Properties
         */

        /**
         * Property that holds the single main instance of WWLC_Bootstrap.
         *
         * @since 1.6.3
         * @access private
         * @var WWLC_Bootstrap
         */
        private static $_instance;

        /**
         * Model that houses the logic of retrieving information relating to Forms.
         *
         * @since 1.6.3
         * @access private
         * @var WWLC_Forms
         */
        private $_wwlc_forms;

        /**
         * Model that houses the logic of retrieving information of the license.
         *
         * @since 1.7.7
         * @access private
         * @var WWLC_WWS_License_Manager
         */
        private $_wwlc_license_manager;

        /**
         * Current WWLC version.
         *
         * @since 1.6.3
         * @access private
         * @var int
         */
        private $_wwlc_current_version;

        /**
         * Class Methods
         */

        /**
         * WWLC_Bootstrap constructor.
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Bootstrap model.
         *
         * @access public
         * @since 1.6.3
         */
        public function __construct( $dependencies ) {
            $this->_wwlc_forms           = $dependencies['WWLC_Forms'];
            $this->_wwlc_license_manager = $dependencies['WWLC_WWS_License_Manager'];
            $this->_wwlc_current_version = $dependencies['WWLC_CURRENT_VERSION'];
        }

        /**
         * Ensure that only one instance of WWLC_Bootstrap is loaded or can be loaded (Singleton Pattern).
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWLC_Bootstrap model.
         *
         * @return WWLC_Bootstrap
         * @since 1.6.3
         */
        public static function instance( $dependencies = null ) {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Load plugin text domain.
         *
         * @since 1.3.1
         */
        public function wwlc_load_plugin_text_domain() {
            load_plugin_textdomain( 'woocommerce-wholesale-lead-capture', false, WWLC_PLUGIN_BASE_PATH . 'languages/' );
        }

        /**
         * Plugin initialization.
         *
         * @since 1.0.0
         * @since 1.6.3 Multisite compatibility. Run the initialization of plugin data only once.
         */
        public function wwlc_initialize() {
            $activation_flag   = get_option( WWLC_ACTIVATION_CODE_TRIGGERED, false );
            $installed_version = is_multisite() ? get_site_option( WWLC_OPTION_INSTALLED_VERSION, false ) : get_option( WWLC_OPTION_INSTALLED_VERSION, false );

            if ( version_compare( $installed_version, $this->_wwlc_current_version, '!=' ) || 'yes' !== $activation_flag ) {

                if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
                    require_once ABSPATH . '/wp-admin/includes/plugin.php';
                }

                $network_wide = is_plugin_active_for_network( 'woocommerce-wholesale-lead-capture/woocommerce-wholesale-lead-capture.bootstrap.php' );

                $this->wwlc_activate( $network_wide );

            }
        }

        /**
         * Plugin activation hook callback.
         *
         * @param bool $network_wide The network wide.
         *
         * @since 1.0.0
         * @since 1.6.3 Multisite Compatibility
         */
        public function wwlc_activate( $network_wide ) {
            global $wpdb;

            if ( is_multisite() ) {

                if ( $network_wide ) {

                    // get ids of all sites.
                    $blogIDs = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

                    foreach ( $blogIDs as $blogID ) {

                        switch_to_blog( $blogID );
                        $this->wwlc_activate_action( $blogID );

                    }

                    restore_current_blog();

                } else {

                    // activated on a single site, in a multi-site.
                    $this->wwlc_activate_action( $wpdb->blogid );

                }
            } else {

                // activated on a single site.
                $this->wwlc_activate_action( $wpdb->blogid );

            }
        }

        /**
         * Perform actions on plugin activation.
         *
         * @since 1.6.3
         * @since 1.8.0 wwlc_general_login_page, wwlc_general_registration_page and wwlc_general_registration_thankyou options now stores the page ID.
         * @since 1.11 Refactor support for multisite setup.
         */
        private function wwlc_activate_action() {
            /**
             * Previously multisite installs site store license options using normal get/add/update_option functions.
             * These stores the option on a per sub-site basis. We need move these options network wide in multisite setup
             * via get/add/update_site_option functions.
             */
            if ( is_multisite() ) {

                $license_email     = get_option( WWLC_OPTION_LICENSE_EMAIL );
                $license_key       = get_option( WWLC_OPTION_LICENSE_KEY );
                $installed_version = get_option( WWLC_OPTION_INSTALLED_VERSION );

                if ( $license_email ) {
                    update_site_option( WWLC_OPTION_LICENSE_EMAIL, $license_email );
                    delete_option( WWLC_OPTION_LICENSE_EMAIL );
                }

                if ( $license_key ) {
                    update_site_option( WWLC_OPTION_LICENSE_KEY, $license_key );
                    delete_option( WWLC_OPTION_LICENSE_KEY );
                }

                if ( $installed_version ) {
                    update_site_option( WWLC_OPTION_INSTALLED_VERSION, $installed_version );
                    delete_option( WWLC_OPTION_INSTALLED_VERSION );
                }
            }

            // Add inactive user role.
            add_role( WWLC_UNAPPROVED_ROLE, 'Unapproved', array() );
            add_role( WWLC_REJECTED_ROLE, 'Rejected', array() );

            // On activation, create registration, thank you and login page
            // Then save these pages on the general settings of this plugin
            // relating to log in and registration page options.
            // But only do this if, the user has not yet set a login, thank you and registration page ( Don't overwrite the users settings ).

            if ( ! get_option( 'wwlc_general_login_page' ) && ! get_option( 'wwlc_general_registration_page' ) && ! get_option( 'wwlc_general_registration_thankyou' ) ) {

                if ( $this->_wwlc_forms->wwlc_create_lead_pages( null, false ) ) {

                    $login_page_id        = defined( 'WWLC_OPTIONS_LOGIN_PAGE_ID' ) && WWLC_OPTIONS_LOGIN_PAGE_ID ? get_option( WWLC_OPTIONS_LOGIN_PAGE_ID ) : '';
                    $registration_page_id = defined( 'WWLC_OPTIONS_REGISTRATION_PAGE_ID' ) && WWLC_OPTIONS_REGISTRATION_PAGE_ID ? get_option( WWLC_OPTIONS_REGISTRATION_PAGE_ID ) : '';
                    $thank_you_page_id    = defined( 'WWLC_OPTIONS_THANK_YOU_PAGE_ID' ) && WWLC_OPTIONS_THANK_YOU_PAGE_ID ? get_option( WWLC_OPTIONS_THANK_YOU_PAGE_ID ) : '';

                    update_option( 'wwlc_general_login_page', $login_page_id );
                    update_option( 'wwlc_general_registration_page', $registration_page_id );
                    update_option( 'wwlc_general_registration_thankyou', $thank_you_page_id );

                }
            }

            // On activation, assign New Lead Role to Wholesale Customer role, if not present default to Customer
            // Get all user roles.
            global $wp_roles;

            if ( ! isset( $wp_roles ) ) {
                $wp_roles = new WP_Roles(); // phpcs:ignore
            }

            $all_user_roles = $wp_roles->get_names();

            // If 'wholesale_customer' exist in wp roles and 'wwlc_general_new_lead_role' is not yet set then we assign "New Lead Role" option value to default 'wholesale_customer' else we set 'customer'.
            if ( array_key_exists( 'wholesale_customer', $all_user_roles ) && get_option( 'wwlc_general_new_lead_role' ) === false ) {
                update_option( 'wwlc_general_new_lead_role', 'wholesale_customer' );
            } elseif ( get_option( 'wwlc_general_new_lead_role' ) === false ) {
                update_option( 'wwlc_general_new_lead_role', 'customer' );
            }

            // on activation, add event in cron to delete all uploaded temporary files that haven't been assigned to a user.
            if ( ! wp_next_scheduled( 'wwlc_delete_temp_files_daily' ) ) {
                wp_schedule_event( time(), 'daily', 'wwlc_delete_temp_files_daily' );
            }

            // Schedule the migrate roles action if the WWLC plugin is overwritten.
            $wwlc_roles_migrator = WWLC_Roles_Migrator::instance();
            $wwlc_roles_migrator->maybe_schedule();

            // Address Placeholder Default.
            if ( get_option( 'wwlc_fields_address_placeholder', '' ) === '' ) {
                update_option( 'wwlc_fields_address_placeholder', __( 'Street address', 'woocommerce-wholesale-lead-capture' ) );
            }

            if ( get_option( 'wwlc_fields_address2_placeholder', '' ) === '' ) {
                update_option( 'wwlc_fields_address2_placeholder', __( 'Apartment, suite, unit etc. (optional)', 'woocommerce-wholesale-lead-capture' ) );
            }

            if ( get_option( 'wwlc_fields_city_placeholder', '' ) === '' ) {
                update_option( 'wwlc_fields_city_placeholder', __( 'Town / City', 'woocommerce-wholesale-lead-capture' ) );
            }

            if ( get_option( 'wwlc_fields_state_placeholder', '' ) === '' ) {
                update_option( 'wwlc_fields_state_placeholder', __( 'State / County', 'woocommerce-wholesale-lead-capture' ) );
            }

            if ( get_option( 'wwlc_fields_postcode_placeholder', '' ) === '' ) {
                update_option( 'wwlc_fields_postcode_placeholder', __( 'Postcode / Zip', 'woocommerce-wholesale-lead-capture' ) );
            }

            // Auto Approve New Leads.
            if ( get_option( 'wwlc_general_auto_approve_new_leads' ) === false ) {
                update_option( 'wwlc_general_auto_approve_new_leads', 'no' );
            }

            // Allow managing of users via email.
            if ( get_option( 'wwlc_email_allow_managing_of_users' ) === false ) {
                update_option( 'wwlc_email_allow_managing_of_users', 'yes' );
            }

            // Getting Started Notice.
            if ( ! get_option( 'wwlc_admin_notice_getting_started_show', false ) ) {
                update_option( 'wwlc_admin_notice_getting_started_show', 'yes' );
            }

            // Getting Started Notice.
            if ( ! get_option( 'wwlc_show_account_upgrade', false ) ) {
                update_option( 'wwlc_show_account_upgrade', 'yes' );
            }

            // WWLC-206: Delete the unused metas when updating from version 1.7.0 or lower.
            $this->wwlc_cleanup_unused_user_meta();

            // Since 1.17.4 WWLC are now using WC_Email object for handling the emails.
            $this->_migrate_wwlc_emails();

            flush_rewrite_rules();

            update_option( WWLC_ACTIVATION_CODE_TRIGGERED, 'yes' );

            // Register action scheduler to handle license check every 24 hours,
            // Check if license action scheduler is already registered.
            if ( ! as_next_scheduled_action( 'wws_license_check' ) ) {
                // Enqueue license check action.
                as_enqueue_async_action( 'wws_license_check', array(), 'wws_license_check', true );

                // Schedule license check, randomize the time in a day to avoid multiple sites checking at the same time.
                as_schedule_recurring_action( time() + wp_rand( 0, 86400 ), 86400, 'wws_license_check', array(), 'wws_license_check', true );
            }

            // Record the installed version & date in the DB.
            if ( is_multisite() ) {
                update_site_option( 'wwlc_option_installed_version', $this->_wwlc_current_version );

                // Set the last license check.
                if ( ! get_site_option( WWLC_LAST_LICENSE_CHECK, false ) ) {
                    update_site_option( WWLC_LAST_LICENSE_CHECK, time() );
                }
            } else {
                update_option( 'wwlc_option_installed_version', $this->_wwlc_current_version );

                // Set the last license check.
                if ( ! get_option( WWLC_LAST_LICENSE_CHECK, false ) ) {
                    update_option( WWLC_LAST_LICENSE_CHECK, time() );
                }
            }
        }

        /**
         * WWLC-206: Delete the unused metas when updating from version 1.7.0 or lower.
         *
         * @since 1.7.1
         * @access private
         */
        private function wwlc_cleanup_unused_user_meta() {
            global $wpdb;

            $installed_version = is_multisite() ? get_site_option( WWLC_OPTION_INSTALLED_VERSION, false ) : get_option( WWLC_OPTION_INSTALLED_VERSION, false );
            if ( null !== $installed_version && version_compare( $installed_version, '1.7.0', '>' ) ) {
                return;
            }

            $unused_metas = $wpdb->get_col( "SELECT umeta_id FROM $wpdb->usermeta WHERE meta_key LIKE '%wwlc_password%'" );

            if ( is_array( $unused_metas ) && ! empty( $unused_metas ) ) {
                $unused_metas_string = implode( ',', $unused_metas );
                $wpdb->query( $wpdb->prepare( "DELETE from $wpdb->usermeta WHERE umeta_id IN ( %s )", $unused_metas_string ) );
            }
        }

        /**
         * Plugin deactivation hook callback.
         *
         * @param bool $network_wide The network wide.
         *
         * @since 1.0.0
         */
        public function wwlc_deactivate( $network_wide ) {
            global $wpdb;

            // check if it is a multisite network.
            if ( is_multisite() ) {

                // check if the plugin has been deactivated on the network or on a single site.
                if ( $network_wide ) {

                    // get ids of all sites.
                    $blogIDs = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

                    foreach ( $blogIDs as $blogID ) {

                        switch_to_blog( $blogID );
                        $this->wwlc_deactivate_action();

                    }

                    restore_current_blog();

                } else {

                    // deactivated on a single site, in a multi-site.
                    $this->wwlc_deactivate_action();

                }
            } else {

                // deactivated on a single site.
                $this->wwlc_deactivate_action();

            }
        }

        /**
         * Perform actions on plugin deactivation.
         *
         * @since 1.6.3
         */
        private function wwlc_deactivate_action() {
            // Remove inactive user role.
            remove_role( WWLC_REJECTED_ROLE );
            remove_role( WWLC_UNAPPROVED_ROLE );

            // clear scheduled cron event.
            wp_clear_scheduled_hook( 'wwlc_delete_temp_files_daily' );
            $wwlc_roles_migrator = WWLC_Roles_Migrator::instance();
            $wwlc_roles_migrator->clear_schedule();

            flush_rewrite_rules();
        }

        /**
         * Plugin deactivation perform actions.
         *
         * @since 1.6.3
         * @access private
         *
         * @param int    $blog_id Site ID.
         * @param int    $user_id User ID.
         * @param string $domain  Site domain.
         * @param string $path    Site path.
         * @param int    $site_id Network ID. Only relevant on multi-network installations.
         * @param array  $meta    Meta data. Used to set initial site options.
         */
        private function wwlc_multisite_init( $blog_id, $user_id, $domain, $path, $site_id, $meta ) { // phpcs:ignore.
            if ( is_plugin_active_for_network( 'woocommerce-wholesale-lead-capture/woocommerce-wholesale-lead-capture.bootstrap.php' ) ) {

                switch_to_blog( $blog_id );
                $this->wwlc_activate( $blog_id );
                restore_current_blog();

            }
        }

        /**
         * Set temporary upload directory to for wp_handle_upload
         *
         * @since 1.6.0
         * @access public
         *
         * @param array $upload_dir_params Array of directory params.
         *
         * @return array
         */
        public function wwlc_set_temp_directory( $upload_dir_params ) {
            $temp_upload = get_option( 'wwlc_temp_upload_directory' );

            if ( empty( $temp_upload ) ) {

                $dir_name           = uniqid( 'wwlc-temp-' );
                $temp_upload['dir'] = $upload_dir_params['basedir'] . '/' . $dir_name;
                $temp_upload['url'] = $upload_dir_params['baseurl'] . '/' . $dir_name;

                update_option( 'wwlc_temp_upload_directory', $temp_upload );
            }

            // In case the temp upload directory doesn't exist, create it.
            if ( ! file_exists( $temp_upload['dir'] ) ) {
                wp_mkdir_p( $temp_upload['dir'] );
            }

            // Setup the params and pass back.
            $upload_dir_params['path'] = $temp_upload['dir'];
            $upload_dir_params['url']  = $temp_upload['url'];

            return $upload_dir_params;
        }

        /**
         * When WWLC page is trashed or deleted, delete also the values in option.
         *
         * @param int $post_id Post ID.
         *
         * @since 1.8.0
         */
        public function wwlc_trash_delete_page( $post_id ) { // phpcs:ignore.
            $login_redirect = get_option( 'wwlc_general_login_redirect_page' );
            if ( is_int( (int) $login_redirect ) && get_post_status( $login_redirect ) === 'trash' ) {
                update_option( 'wwlc_general_login_redirect_page', '' );
            }

            $logout_redirect = get_option( 'wwlc_general_logout_redirect_page' );
            if ( is_int( (int) $logout_redirect ) && get_post_status( $logout_redirect ) === 'trash' ) {
                update_option( 'wwlc_general_logout_redirect_page', '' );
            }

            $login_page = get_option( 'wwlc_general_login_page' );
            if ( is_int( (int) $login_page ) && get_post_status( $login_page ) === 'trash' ) {
                update_option( 'wwlc_general_login_page', '' );
            }

            $registration_page = get_option( 'wwlc_general_registration_page' );
            if ( is_int( (int) $registration_page ) && get_post_status( $registration_page ) === 'trash' ) {
                update_option( 'wwlc_general_registration_page', '' );
            }

            $registration_thankyou_page = get_option( 'wwlc_general_registration_thankyou' );
            if ( is_int( (int) $registration_thankyou_page ) && get_post_status( $registration_thankyou_page ) === 'trash' ) {
                update_option( 'wwlc_general_registration_thankyou', '' );
            }

            $terms_condition_page = get_option( 'wwlc_general_terms_and_condition_page_url' );
            if ( is_int( (int) $terms_condition_page ) && get_post_status( $terms_condition_page ) === 'trash' ) {
                update_option( 'wwlc_general_terms_and_condition_page_url', '' );
            }
        }

        /**
         * Reformat custom fields from base64 to serialize.
         * Fixes the issue on WWLC-204 where the custom fields are not translatable because it is in base64 format.
         *
         * @since 1.12
         */
        public function wwlc_reformat_custom_fields_data() {
            $installed_version = is_multisite() ? get_site_option( WWLC_OPTION_INSTALLED_VERSION ) : get_option( WWLC_OPTION_INSTALLED_VERSION );

            if ( version_compare( $installed_version, $this->_wwlc_current_version, '!=' ) && version_compare( $installed_version, '1.12', '<' ) ) {

                $custom_fields = get_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS );

                if ( ! is_array( $custom_fields ) && ! empty( $custom_fields ) ) {
                    update_option( WWLC_OPTION_REGISTRATION_FORM_CUSTOM_FIELDS, unserialize( base64_decode( $custom_fields ) ) ); //phpcs:ignore
                }

                if ( is_multisite() ) {
                    update_site_option( WWLC_OPTION_INSTALLED_VERSION, $this->_wwlc_current_version );
                } else {
                    update_option( WWLC_OPTION_INSTALLED_VERSION, $this->_wwlc_current_version );
                }
            }
        }

        /**
         * Add plugin listing custom action link ( settings ).
         *
         * @since 1.0.2
         * @since 1.14.3 Trasfer code to its proper model
         *
         * @param string[] $links An array of plugin action links. By default this can include
	     *                        'activate', 'deactivate', and 'delete'.
         * @param string   $file  Path to the plugin file relative to the plugins directory.
         *
         * @return mixed
         */
        public function add_plugin_listing_custom_action_links( $links, $file ) {
            // Do not show this for multi site installs.
            if ( plugin_basename( WWLC_PLUGIN_DIR . 'woocommerce-wholesale-lead-capture.bootstrap.php' ) === $file ) {

                if ( ! is_multisite() ) {
                    if ( $this->_wwlc_license_manager->is_license_status( 'expired' ) ) {
                        $license_data      = $this->_wwlc_license_manager->get_wws_license_data( 'WWLC' );
                        $renew_license_url = add_query_arg(
                            array(
                                'utm_source'   => 'wwlc',
                                'utm_medium'   => 'drm',
                                'utm_campaign' => 'wwlcdrmpluginlinkrenew',
                            ),
                            $license_data['management_url']
                        );

                        $renew_license_link = '<a href="' . esc_url( $renew_license_url ) . '">' . __( 'Renew License', 'woocommerce-wholesale-lead-capture' ) . '</a>';
                        array_unshift( $links, $renew_license_link );
                    }

                    $license_link = '<a href="options-general.php?page=wws-license-settings&tab=wwlc">' . __( 'License', 'woocommerce-wholesale-lead-capture' ) . '</a>';

                    if ( WWLC_Helper_Functions::is_wwp_v2() ) {
                        $license_link = '<a href="admin.php?page=wws-license-settings&tab=wwlc">' . __( 'License', 'woocommerce-wholesale-lead-capture' ) . '</a>';
                    }

                    array_unshift( $links, $license_link );

                }

                $settings_link = '<a href="admin.php?page=wc-settings&tab=wwlc_settings">' . __( 'Settings', 'woocommerce-wholesale-lead-capture' ) . '</a>';
                array_unshift( $links, $settings_link );

                $getting_started          = '<a href="https://wholesalesuiteplugin.com/kb/woocommerce-wholesale-lead-capture-getting-started-guide/?utm_source=wwlc&utm_medium=kb&utm_campaign=WWLCGettingStartedGuide" target="_blank">' . __( 'Getting Started', 'woocommerce-wholesale-lead-capture' ) . '</a>';
                $links['getting_started'] = $getting_started;

            }

            return $links;
        }

        /**
         * Remove WWP, WWPP and WWOF Getting Started notice.
         * We will compile them into 1 in WWLC.
         *
         * @since 1.14.3
         * @access public
         */
        public function remove_getting_started_notice() {
            $wwlc_notice = get_option( 'wwlc_admin_notice_getting_started_show' );

            if ( 'yes' === $wwlc_notice || false === $wwlc_notice ) {

                global $wc_wholesale_prices, $wc_wholesale_prices_premium, $wc_wholesale_order_form;

                if ( ! function_exists( 'is_plugin_active' ) ) {
                    include_once ABSPATH . 'wp-admin/includes/plugin.php';
                }

                if ( $wc_wholesale_prices ) {
                    remove_action( 'admin_notices', array( $wc_wholesale_prices->wwp_bootstrap, 'getting_started_notice' ), 10 );
                }

                if ( $wc_wholesale_prices && $wc_wholesale_prices_premium ) {
                    remove_action( 'admin_notices', array( $wc_wholesale_prices_premium->wwpp_bootstrap, 'wwpp_getting_started_notice' ), 10 );
                }

                if ( $wc_wholesale_order_form ) {
                    remove_action( 'admin_notices', array( $wc_wholesale_order_form->_wwof_bootstrap, 'wwof_getting_started_notice' ), 10 );
                }
            }
        }

        /**
         * Getting Started notice on plugin activation.
         *
         * @since 1.14.3
         * @access public
         */
        public function wwlc_getting_started_notice() {
            require_once WWLC_VIEWS_ROOT_DIR . 'wwlc-notice/view-wwlc-notices.php';
        }

        /**
         * Hide WWLC getting started notice on close.
         *
         * @since 1.14.3
         * @access public
         */
        public function wwlc_getting_started_notice_hide() {
            if ( ! function_exists( 'is_plugin_active' ) ) {
                include_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $wwp_active  = is_plugin_active( 'woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php' );
            $wwpp_active = is_plugin_active( 'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php' );
            $wwof_active = is_plugin_active( 'woocommerce-wholesale-order-form/woocommerce-wholesale-order-form.bootstrap.php' );

            if ( $wwp_active ) {
                update_option( 'wwp_admin_notice_getting_started_show', 'no' );
            }

            if ( $wwpp_active ) {
                update_option( 'wwpp_admin_notice_getting_started_show', 'no' );
            }

            if ( $wwof_active ) {
                update_option( 'wwof_admin_notice_getting_started_show', 'no' );
            }

            // Hide WWLC notice.
            update_option( 'wwlc_admin_notice_getting_started_show', 'no' );

            wp_send_json( array( 'status' => 'success' ) );
        }

        /**
         * Run migration from old wwlc email processor to new wwlc email processor with WC_Email
         *
         * @since 1.17.4
         * @access private
         */
        private function _migrate_wwlc_emails() {

            global $wpdb;

            if ( 'no' === get_option( 'wwlc_migrate_emails_database', 'no' ) ) {

                // New User Admin Notification Email Template.
                $wwlc_emails_new_user_admin_notification_subject             = get_option( 'wwlc_emails_new_user_admin_notification_subject' );
                $wwlc_emails_new_user_admin_notification_template            = get_option( 'wwlc_emails_new_user_admin_notification_template' );
                $wwlc_emails_new_user_admin_notification_default_message_old = '&lt;p&gt;' . __( 'New User Registration,', 'woocommerce-wholesale-lead-capture' ) . '&lt;/p&gt; &lt;p&gt;' .
                    /* Translators: %1$s: Full Name */
                    sprintf( __( 'Full Name : %1$s', 'woocommerce-wholesale-lead-capture' ), '{full_name}' ) . '&lt;br /&gt; ' .
                    /* Translators: %1$s: Email */
                    sprintf( __( 'Email : %1$s', 'woocommerce-wholesale-lead-capture' ), '{email}' ) . '&lt;/p&gt; &lt;p&gt;' .
                    '{user_management_url}&lt;/p&gt;';

                if (
                    ( $wwlc_emails_new_user_admin_notification_subject && $wwlc_emails_new_user_admin_notification_template ) &&
                    (
                        __( 'New User Registration', 'woocommerce-wholesale-lead-capture' ) !== $wwlc_emails_new_user_admin_notification_subject ||
                        $wwlc_emails_new_user_admin_notification_template !== $wwlc_emails_new_user_admin_notification_default_message_old
                    )
                ) {

                    $wwlc_emails_new_user_admin_notification_default_message_new = '<p>' . __( 'A new wholesale lead just registered for an account,', 'woocommerce-wholesale-lead-capture' ) . '</p><p>' .
                        /* Translators: %1$s: Full Name */
                        sprintf( __( 'Full Name : %1$s', 'woocommerce-wholesale-lead-capture' ), '{full_name}' ) . '<br/>' .
                        /* Translators: %1$s: Email */
                        sprintf( __( 'Email : %1$s', 'woocommerce-wholesale-lead-capture' ), '{email}' ) . '</p>' .
                        '<p><a href="{user_management_url}">{user_management_url}</a></p>';

                    $wwlc_email_new_wholesale_lead_value = array(
                        'enabled'    => 'yes',
                        'subject'    => __( 'New User Registration', 'woocommerce-wholesale-lead-capture' ) !== $wwlc_emails_new_user_admin_notification_subject ? $wwlc_emails_new_user_admin_notification_subject : __( 'New Wholesale Lead', 'woocommerce-wholesale-lead-capture' ),
                        'heading'    => __( 'New User Registration', 'woocommerce-wholesale-lead-capture' ) !== $wwlc_emails_new_user_admin_notification_subject ? $wwlc_emails_new_user_admin_notification_subject : __( 'New Wholesale Lead', 'woocommerce-wholesale-lead-capture' ),
                        'message'    => $wwlc_emails_new_user_admin_notification_template !== $wwlc_emails_new_user_admin_notification_default_message_old ? $wwlc_emails_new_user_admin_notification_template : $wwlc_emails_new_user_admin_notification_default_message_new,
                        'email_type' => 'html',
                    );

                    update_option( 'woocommerce_wwlc_email_new_wholesale_lead_settings', $wwlc_email_new_wholesale_lead_value );
                    delete_option( 'wwlc_emails_new_user_admin_notification_subject' );
                    delete_option( 'wwlc_emails_new_user_admin_notification_template' );

                }

                // New User Admin Notification Email Template ( Auto Approved ).
                $wwlc_emails_new_user_admin_notification_auto_approved_subject             = get_option( 'wwlc_emails_new_user_admin_notification_auto_approved_subject' );
                $wwlc_emails_new_user_admin_notification_auto_approved_template            = get_option( 'wwlc_emails_new_user_admin_notification_auto_approved_template' );
                $wwlc_emails_new_user_admin_notification_auto_approved_default_message_old = '&lt;p&gt;' . __( 'New User Registered and Approved,', 'woocommerce-wholesale-lead-capture' ) . '&lt;/p&gt; &lt;p&gt;' .
                    /* Translators: %1$s: Full Name */
                    sprintf( __( 'Full Name : %1$s', 'woocommerce-wholesale-lead-capture' ), '{full_name}' ) . '&lt;br /&gt; ' .
                    /* Translators: %1$s: Email */
                    sprintf( __( 'Email : %1$s', 'woocommerce-wholesale-lead-capture' ), '{email}' ) . '&lt;/p&gt; &lt;p&gt;' .
                    __( 'User is Auto Approved', 'woocommerce-wholesale-lead-capture' ) . '&lt;/p&gt;';

                if (
                    ( $wwlc_emails_new_user_admin_notification_auto_approved_subject && $wwlc_emails_new_user_admin_notification_auto_approved_template ) &&
                    (
                        __( 'New User Registered And Approved', 'woocommerce-wholesale-lead-capture' ) !== $wwlc_emails_new_user_admin_notification_auto_approved_subject ||
                        $wwlc_emails_new_user_admin_notification_auto_approved_template !== $wwlc_emails_new_user_admin_notification_auto_approved_default_message_old
                    )
                ) {

                    $wwlc_emails_new_user_admin_notification_auto_approved_default_message_new = '<p>' . __( 'A new wholesale lead just registered for an account and was auto approved,', 'woocommerce-wholesale-lead-capture' ) . '</p><p>' .
                        /* Translators: %1$s: Full Name */
                        sprintf( __( 'Full Name: %1$s', 'woocommerce-wholesale-lead-capture' ), '{full_name}' ) . '<br/>' .
                        /* Translators: %1$s: Email */
                        sprintf( __( 'Email: %1$s', 'woocommerce-wholesale-lead-capture' ), '{email}' ) . '</p><p>' .
                        __( 'View user : ', 'woocommerce-wholesale-lead-capture' ) .
                        '<a href="{user_edit_profile_url}">{user_edit_profile_url}</a></p>';

                    $wwlc_emails_new_user_admin_notification_auto_approved_value = array(
                        'enabled'    => 'yes',
                        'subject'    => __( 'New User Registered And Approved', 'woocommerce-wholesale-lead-capture' ) !== $wwlc_emails_new_user_admin_notification_auto_approved_subject ? $wwlc_emails_new_user_admin_notification_auto_approved_subject : __( 'New Wholesale Lead Approved', 'woocommerce-wholesale-lead-capture' ),
                        'heading'    => __( 'New User Registered And Approved', 'woocommerce-wholesale-lead-capture' ) !== $wwlc_emails_new_user_admin_notification_auto_approved_subject ? $wwlc_emails_new_user_admin_notification_auto_approved_subject : __( 'New Wholesale Lead Approved', 'woocommerce-wholesale-lead-capture' ),
                        'message'    => $wwlc_emails_new_user_admin_notification_auto_approved_template !== $wwlc_emails_new_user_admin_notification_auto_approved_default_message_old ? $wwlc_emails_new_user_admin_notification_auto_approved_template : $wwlc_emails_new_user_admin_notification_auto_approved_default_message_new,
                        'email_type' => 'html',
                    );

                    update_option( 'woocommerce_wwlc_email_new_wholesale_lead_auto_approved_settings', $wwlc_emails_new_user_admin_notification_auto_approved_value );
                    delete_option( 'wwlc_emails_new_user_admin_notification_auto_approved_subject' );
                    delete_option( 'wwlc_emails_new_user_admin_notification_auto_approved_template' );

                }

                // New User Email Template.
                $wwlc_emails_new_user_subject             = get_option( 'wwlc_emails_new_user_subject' );
                $wwlc_emails_new_user_template            = get_option( 'wwlc_emails_new_user_template' );
                $wwlc_emails_new_user_default_message_old = '&lt;p&gt;' .
                    /* Translators: %1$s: Full name */
                    sprintf( __( 'Hi %1$s,', 'woocommerce-wholesale-lead-capture' ), '{full_name}' ) . ',&lt;/p&gt; &lt;p&gt;' .
                    __( 'You have successfully registered as a Wholesale customer', 'woocommerce-wholesale-lead-capture' ) . '&lt;/p&gt; &lt;p&gt;' .
                    __( 'Please save your credentials below:', 'woocommerce-wholesale-lead-capture' ) . '&lt;br /&gt; ' .
                    /* Translators: %1$s: Username */
                    sprintf( __( 'username: %1$s', 'woocommerce-wholesale-lead-capture' ), '{username}' ) . '&lt;br /&gt; ' .
                    /* Translators: %1$s: Password */
                    sprintf( __( 'password: %1$s', 'woocommerce-wholesale-lead-capture' ), '{password}' ) . '&lt;/p&gt; &lt;p&gt;' .
                    __( "We'll send you an email once your application has been approved", 'woocommerce-wholesale-lead-capture' ) . '&lt;/p&gt; &lt;p&gt;' .
                    __( 'Kind Regards', 'woocommerce-wholesale-lead-capture' ) . '&lt;br /&gt; ' .
                        '{site_name}&lt;/p&gt;';

                if (
                    ( $wwlc_emails_new_user_subject && $wwlc_emails_new_user_template ) &&
                    (
                        __( 'Registration Successful', 'woocommerce-wholesale-lead-capture' ) !== $wwlc_emails_new_user_subject ||
                        $wwlc_emails_new_user_template !== $wwlc_emails_new_user_default_message_old
                    )
                ) {

                    $wwlc_emails_new_user_default_message_new = '<p>' .
                        /* Translators: %1$s: Full name */
                        sprintf( __( 'Hi %1$s,', 'woocommerce-wholesale-lead-capture' ), '{full_name}' ) . '</p><p>' .
                        __( 'Thank you for your interest in becoming a wholesale customer. We\'ll send you an email once your application has been approved.', 'woocommerce-wholesale-lead-capture' ) . '</p><p>' .
                        __( 'Please save your credentials below:', 'woocommerce-wholesale-lead-capture' ) . '<br>' .
                        /* Translators: %1$s: Username */
                        sprintf( __( 'Username : %1$s', 'woocommerce-wholesale-lead-capture' ), '{username}' ) . '<br>' .
                        /* Translators: %1$s: Password */
                        sprintf( __( 'Password : %1$s', 'woocommerce-wholesale-lead-capture' ), '{password}' ) . '<br></p>' .
                        __( 'Kind Regards,', 'woocommerce-wholesale-lead-capture' ) . '<br>{site_title}';

                    $wwlc_emails_new_user_default_message_value = array(
                        'enabled'    => 'yes',
                        'subject'    => __( 'Registration Successful', 'woocommerce-wholesale-lead-capture' ) !== $wwlc_emails_new_user_subject ? $wwlc_emails_new_user_subject : __( 'Wholesale Application Received', 'woocommerce-wholesale-lead-capture' ),
                        'heading'    => __( 'Registration Successful', 'woocommerce-wholesale-lead-capture' ) !== $wwlc_emails_new_user_subject ? $wwlc_emails_new_user_subject : __( 'Wholesale Application Received', 'woocommerce-wholesale-lead-capture' ),
                        'message'    => $wwlc_emails_new_user_template !== $wwlc_emails_new_user_default_message_old ? $wwlc_emails_new_user_template : $wwlc_emails_new_user_default_message_new,
                        'email_type' => 'html',
                    );

                    update_option( 'woocommerce_wwlc_email_wholesale_application_received_settings', $wwlc_emails_new_user_default_message_value );
                    delete_option( 'wwlc_emails_new_user_subject' );
                    delete_option( 'wwlc_emails_new_user_template' );

                }

                // Approval Email Template.
                $wwlc_emails_approval_email_subject             = get_option( 'wwlc_emails_approval_email_subject' );
                $wwlc_emails_approval_email_template            = get_option( 'wwlc_emails_approval_email_template' );
                $wwlc_emails_approval_email_default_message_old = '&lt;p&gt;' .
                    /* Translators: %1$s: Full name */
                    sprintf( __( 'Hi %1$s,', 'woocommerce-wholesale-lead-capture' ), '{full_name}' ) . '&lt;/p&gt; &lt;p&gt;' .
                    __( 'Congratulations, you have been approved as a wholesale customer.', 'woocommerce-wholesale-lead-capture' ) . '&lt;/p&gt; &lt;p&gt;' .
                    __( 'Login using your new account to start shopping as a wholesale user.', 'woocommerce-wholesale-lead-capture' ) . '&lt;/p&gt; &lt;p&gt;' .
                    __( 'Please save your credentials below:', 'woocommerce-wholesale-lead-capture' ) . '&lt;br /&gt; ' .
                    /* Translators: %1$s: Username */
                    sprintf( __( 'username: %1$s', 'woocommerce-wholesale-lead-capture' ), '{username}' ) . '&lt;br /&gt; ' .
                    /* Translators: %1$s: Password */
                    sprintf( __( 'password: %1$s', 'woocommerce-wholesale-lead-capture' ), '{password}' ) . '&lt;/p&gt; &lt;p&gt;' .
                    __( 'login link below:', 'woocommerce-wholesale-lead-capture' ) . '&lt;br /&gt; ' .
                    '{wholesale_login_url}&lt;/p&gt; &lt;p&gt;' .
                    __( 'Kind Regards,', 'woocommerce-wholesale-lead-capture' ) . '&lt;br /&gt; {site_name}&lt;/p&gt;';

                if (
                    ( $wwlc_emails_approval_email_subject && $wwlc_emails_approval_email_template ) &&
                    (
                        __( 'Registration Approved', 'woocommerce-wholesale-lead-capture' ) !== $wwlc_emails_approval_email_subject ||
                        $wwlc_emails_approval_email_template !== $wwlc_emails_approval_email_default_message_old
                    )
                ) {

                    $wwlc_emails_approval_email_default_message_new = '<p>' .
                        /* Translators: %1$s: Full name */
                        sprintf( __( 'Hi %1$s,', 'woocommerce-wholesale-lead-capture' ), '{full_name}' ) . '</p><p>' .
                        __( 'Congratulations, your wholesale account has been approved.', 'woocommerce-wholesale-lead-capture' ) . '</p><p>' .
                        __( 'Login using your new account to start shopping as a wholesale customer.', 'woocommerce-wholesale-lead-capture' ) . '</p><p>' .
                        __( 'Please save your credentials below:', 'woocommerce-wholesale-lead-capture' ) . '<br/>' .
                        /* Translators: %1$s: Username */
                        sprintf( __( 'username : %1$s', 'woocommerce-wholesale-lead-capture' ), '{username}' ) . '<br/>' .
                        /* Translators: %1$s: Password */
                        sprintf( __( 'password : %1$s', 'woocommerce-wholesale-lead-capture' ), '{password}' ) . '<br/></p><p>' .
                        __( 'You can login here:', 'woocommerce-wholesale-lead-capture' ) . '<br/>' .
                        '<a href="{wholesale_login_url}">{wholesale_login_url}</a></p><p>' .
                        __( 'Kind Regards,', 'woocommerce-wholesale-lead-capture' ) . '<br/>' .
                        sprintf( '%1$s', '{site_title}' ) . '<br/></p>';

                    $wwlc_emails_new_user_default_message_value = array(
                        'enabled'    => 'yes',
                        'subject'    => __( 'Registration Approved', 'woocommerce-wholesale-lead-capture' ) !== $wwlc_emails_approval_email_subject ? $wwlc_emails_approval_email_subject : __( 'Wholesale Account Approved', 'woocommerce-wholesale-lead-capture' ),
                        'heading'    => __( 'Registration Approved', 'woocommerce-wholesale-lead-capture' ) !== $wwlc_emails_approval_email_subject ? $wwlc_emails_approval_email_subject : __( 'Wholesale Account Approved', 'woocommerce-wholesale-lead-capture' ),
                        'message'    => $wwlc_emails_approval_email_template !== $wwlc_emails_approval_email_default_message_old ? $wwlc_emails_approval_email_template : $wwlc_emails_approval_email_default_message_new,
                        'email_type' => 'html',
                    );

                    update_option( 'woocommerce_wwlc_email_wholesale_account_approved_settings', $wwlc_emails_new_user_default_message_value );
                    delete_option( 'wwlc_emails_approval_email_subject' );
                    delete_option( 'wwlc_emails_approval_email_template' );

                }

                // Rejected Email Template.
                $wwlc_emails_rejected_email_subject             = get_option( 'wwlc_emails_rejected_email_subject' );
                $wwlc_emails_rejected_email_template            = get_option( 'wwlc_emails_rejected_email_template' );
                $wwlc_emails_rejected_email_default_message_old = '&lt;p&gt;' .
                    /* Translators: %1$s: Full name */
                    sprintf( __( 'Hi %1$s,', 'woocommerce-wholesale-lead-capture' ), '{full_name}' ) . '&lt;/p&gt; &lt;p&gt;' .
                    __( 'Unfortunately you have not been approved as a Wholesale customer at this time.', 'woocommerce-wholesale-lead-capture' ) . '&lt;/p&gt; &lt;p&gt;' .
                    __( 'If you feel this decision has been made in error please get in touch.', 'woocommerce-wholesale-lead-capture' ) . '&lt;/p&gt; &lt;p&gt;' .
                    __( 'Kind Regards,', 'woocommerce-wholesale-lead-capture' ) . '&lt;br /&gt; ' .
                        '{site_name}&lt;/p&gt;';

                if (
                    ( $wwlc_emails_rejected_email_subject && $wwlc_emails_rejected_email_template ) &&
                    (
                        __( 'Registration Rejected', 'woocommerce-wholesale-lead-capture' ) !== $wwlc_emails_rejected_email_subject ||
                        $wwlc_emails_rejected_email_template !== $wwlc_emails_rejected_email_default_message_old
                    )
                ) {

                    $wwlc_emails_rejected_email_default_message_new = '<p>' .
                        /* Translators: %1$s: Full name */
                        sprintf( __( 'Hi %1$s,', 'woocommerce-wholesale-lead-capture' ), '{full_name}' ) . '</p><p>' .
                        __( 'Unfortunately your application for a wholesale account has not been approved at this time.', 'woocommerce-wholesale-lead-capture' ) . '</p><p>' .
                        __( 'If you feel this decision has been made in error please get in touch.', 'woocommerce-wholesale-lead-capture' ) . '</p><p>' .
                        __( 'Kind Regards,', 'woocommerce-wholesale-lead-capture' ) . '<br/>' .
                        sprintf( '%1$s', '{site_title}' ) . '<br/></p>';

                    $wwlc_emails_new_user_default_message_value = array(
                        'enabled'    => 'yes',
                        'subject'    => __( 'Registration Rejected', 'woocommerce-wholesale-lead-capture' ) !== $wwlc_emails_rejected_email_subject ? $wwlc_emails_rejected_email_subject : __( 'Wholesale Account Rejected', 'woocommerce-wholesale-lead-capture' ),
                        'heading'    => __( 'Registration Rejected', 'woocommerce-wholesale-lead-capture' ) !== $wwlc_emails_rejected_email_subject ? $wwlc_emails_rejected_email_subject : __( 'Wholesale Account Rejected', 'woocommerce-wholesale-lead-capture' ),
                        'message'    => $wwlc_emails_rejected_email_template !== $wwlc_emails_rejected_email_default_message_old ? $wwlc_emails_rejected_email_template : $wwlc_emails_rejected_email_default_message_new,
                        'email_type' => 'html',
                    );

                    update_option( 'woocommerce_wwlc_email_wholesale_account_rejected_settings', $wwlc_emails_new_user_default_message_value );
                    delete_option( 'wwlc_emails_rejected_email_subject' );
                    delete_option( 'wwlc_emails_rejected_email_template' );

                }

                update_option( 'wwlc_migrate_emails_database', WooCommerce_Wholesale_Lead_Capture::VERSION );
            }
        }

        /**
         * Execute model.
         *
         * @since 1.6.3
         * @access public
         */
        public function run() {
            // Load Plugin Text Domain
            // WWLC-113: Changed the action from 'plugins_loaded' to 'after_setup_theme', because translation file is not working when the file location is set to "languages/loco/plugins/".
            add_action( 'init', array( $this, 'wwlc_load_plugin_text_domain' ), 20 );

            // Add Custom Plugin Listing Action Links.
            add_filter( 'plugin_action_links', array( $this, 'add_plugin_listing_custom_action_links' ), 10, 2 );

            // Register Activation Hook.
            register_activation_hook( WWLC_PLUGIN_DIR . 'woocommerce-wholesale-lead-capture.bootstrap.php', array( $this, 'wwlc_activate' ) );

            // Register Deactivation Hook.
            register_deactivation_hook( WWLC_PLUGIN_DIR . 'woocommerce-wholesale-lead-capture.bootstrap.php', array( $this, 'wwlc_deactivate' ) );

            // Plugin Initialization.
            add_action( 'init', array( $this, 'wwlc_initialize' ) );

            // Execute plugin initialization ( plugin activation ) on every newly created site in a multi site set up.
            add_action( 'wpmu_new_blog', array( $this, 'wwlc_multisite_init' ), 10, 6 );

            // When the WWLC pages are trashed or deleted, delete also the values in option.
            add_action( 'deleted_post', array( $this, 'wwlc_trash_delete_page' ), 10, 1 );
            add_action( 'publish_to_trash', array( $this, 'wwlc_trash_delete_page' ), 10, 1 );

            // Reformat custom fields from base64 to serialize.
            add_action( 'plugins_loaded', array( $this, 'wwlc_reformat_custom_fields_data' ) );

            // Getting Started notice.
            add_action( 'init', array( $this, 'remove_getting_started_notice' ) );
            add_action( 'admin_notices', array( $this, 'wwlc_getting_started_notice' ), 10 );
            add_action( 'wp_ajax_wwlc_getting_started_notice_hide', array( $this, 'wwlc_getting_started_notice_hide' ) );
        }
    }

}
