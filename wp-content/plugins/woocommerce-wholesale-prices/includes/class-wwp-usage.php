<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Wholesale Suite usage tracking functions for reporting usage to the Rymera Web Co servers for users who have opted in
 *
 * @since 1.14
 */
class WWP_Usage {

    /**
     * Class Properties.
     */

    /**
     * Property that holds the single main instance of WWP_Usage.
     *
     * @since  1.14
     * @access private
     * @var WWP_Usage
     */
    private static $_instance;

    /*
    |--------------------------------------------------------------------------
    | Class Methods
    |--------------------------------------------------------------------------
    */

    /**
     * WWP_Usage constructor.
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWP_Usage model.
     *
     * @since  1.14
     * @access public
     */
    public function __construct( $dependencies = array() ) {
        // Nothing to see here yet.
    }

    /**
     * Ensure that only one instance of WWP_Usage is loaded or can be loaded (Singleton Pattern).
     *
     * @param array $dependencies Array of instance objects of all dependencies of WWP_Usage model.
     *
     * @since  1.14
     * @access public
     *
     * @return WWP_Usage
     */
    public static function instance( $dependencies = array() ) {

        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( $dependencies );
        }

        return self::$_instance;
    }

    /**
     * Gather the tracking data together
     *
     * @since  1.14
     * @access public
     */
    private function get_data() {

        $data = array();

        // Merge plugin specific data.
        $data = array_merge( $data, $this->_fetch_plugin_version_data() );

        // Merge license data.
        $data = array_merge( $data, $this->_fetch_license_data() );

        // Wholesale Roles.
        $wwp_wholesale_roles     = WWP_Wholesale_Roles::getInstance();
        $data['wholesale_roles'] = $wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

        // Settings.
        $data['settings'] = $this->_fetch_all_wws_settings();

        // Merge environment settings data.
        $data = array_merge( $data, $this->_fetch_environment_settings_data() );

        // Retrieve current plugin information.
        $data['active_plugins'] = $this->_fetch_plugin_data();

        // Effectiveness data.
        $allow = true;

        // Don't track effectiveness data on local sites.
        if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === WP_ENVIRONMENT_TYPE ) {
            $allow = false;
        } else {
            $parsed_home = wp_parse_url( get_option( 'home' ) );
            $host        = $parsed_home['host'];

            $disallowed_tlds = array(
                '.test',
                '.loc',
                '.local',
            );
            foreach ( $disallowed_tlds as $tld ) {
                if ( mb_substr( $host, -strlen( $tld ) ) === $tld ) {
                    $allow = false;
                    break;
                }
            }

            if ( $allow ) {
                $disallowed_hosts = array(
					'localhost',
					'127.0.0.1',
				);
                if ( in_array( $host, $disallowed_hosts, true ) ) {
                    $allow = false;
                }
            }
        }

        if ( $allow ) {
            $data['effectiveness'] = $this->_fetch_effectiveness_data();
        } else {
            $data['effectiveness'] = array();
        }

        return $data;
    }

    /**
     * Fetch plugin specific data and compile them together into an array
     *
     * @since  2.1.7
     * @access private
     * @return array All plugin specific data.
     */
    private function _fetch_plugin_version_data() {

        $data = array();

        $data['wwp_version']  = WWP_Helper_Functions::get_wwp_version();
        $data['wwpp_version'] = WWP_Helper_Functions::get_wwpp_version();
        $data['wwof_version'] = WWP_Helper_Functions::get_wwof_version();
        $data['wwlc_version'] = WWP_Helper_Functions::get_wwlc_version();
        $data['wpay_version'] = WWP_Helper_Functions::get_wwlc_version();
        $data['wwpp']         = (int) WWP_Helper_Functions::is_wwpp_active();
        $data['wwof']         = (int) WWP_Helper_Functions::is_wwof_active();
        $data['wwlc']         = (int) WWP_Helper_Functions::is_wwlc_active();
        $data['wpay']         = (int) WWP_Helper_Functions::is_wpay_active();

        return $data;
    }

    /**
     * Fetch license data and compile them together into an array
     *
     * @since  2.1.7
     * @since  2.2.0 Added WPAY license data.
     * @access private
     * @return array All license data.
     */
    private function _fetch_license_data() {

        $data     = array();
        $licenses = WWP_Helper_Functions::get_license_data();

        $data['wwpp_license_email'] = isset( $licenses ) && isset( $licenses['wwpp_license_email'] ) ? $licenses['wwpp_license_email'] : '';
        $data['wwpp_license_key']   = isset( $licenses ) && isset( $licenses['wwpp_license_key'] ) ? $licenses['wwpp_license_key'] : '';
        $data['wwof_license_email'] = isset( $licenses ) && isset( $licenses['wwof_license_email'] ) ? $licenses['wwof_license_email'] : '';
        $data['wwof_license_key']   = isset( $licenses ) && isset( $licenses['wwof_license_key'] ) ? $licenses['wwof_license_key'] : '';
        $data['wwlc_license_email'] = isset( $licenses ) && isset( $licenses['wwlc_license_email'] ) ? $licenses['wwlc_license_email'] : '';
        $data['wpay_license_key']   = isset( $licenses ) && isset( $licenses['wpay_license_key'] ) ? $licenses['wpay_license_key'] : '';
        $data['wpay_license_email'] = isset( $licenses ) && isset( $licenses['wpay_license_email'] ) ? $licenses['wpay_license_email'] : '';

        return $data;
    }

    /**
     * Fetch all WWS settings (across Premium plugins too if installed) and compile them together into an array
     *
     * @since  1.14
     * @since  2.1.7 Make this function private.
     * @since  2.2.0 Added WPAY settings.
     * @access private
     * @return array All settings from WWP, WWPP, WWOF, WWLC, WPAY plugins
     */
    private function _fetch_all_wws_settings() {

        global $wpdb;
        $settings = array();

        // WWP.
        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value
                FROM {$wpdb->prefix}options
                WHERE option_name like %s
                    OR option_name like %s
                    OR option_name like %s
                    OR option_name like %s
                    OR option_name like %s",
                'wwp_%',
                'wwpp_%',
                'wwof_%',
                'wwlc_%',
                'wpay_%'
            ),
            ARRAY_A
        );

        // WPAY.
        $wpay_settings_whitelist = array(
            'wpay_api_mode',
            'wpay_installed_version',
            'wpay_license_activated',
            'wpay_license_notice_dismissed',
            'wpay_payment_method_name',
        );
        foreach ( $result as $option ) {
            if ( isset( $option ) && isset( $option['option_name'] ) && isset( $option['option_value'] ) ) {
                // if option_name starts with wpay and not included in whitelist, continue.
                if ( strpos( $option['option_name'], 'wpay_' ) === 0 && ! in_array( $option['option_name'], $wpay_settings_whitelist, true ) ) {
                    continue;
                }

                $settings[ $option['option_name'] ] = $option['option_value'];
            }
        }

        // Unset unwanted settings.
        unset( $settings['wwp_settings_hash'] );
        unset( $settings['wwpp_settings_hash'] );
        unset( $settings['wwp_product_hash'] );
        unset( $settings['wwpp_product_hash'] );
        unset( $settings['wwp_product_cat_hash'] );
        unset( $settings['wwpp_product_cat_hash'] );
        unset( $settings['wwpp_update_data'] );
        unset( $settings['wwof_update_data'] );
        unset( $settings['wwlc_update_data'] );
        unset( $settings['wwof_order_form_v2_consumer_key'] );
        unset( $settings['wwof_order_form_v2_consumer_secret'] );
        unset( $settings['wwlc_security_recaptcha_secret_key'] );
        unset( $settings['wwlc_security_recaptcha_site_key'] );

        if ( WWP_Helper_Functions::is_wpay_active() ) {
            $plans_query = \RymeraWebCo\WPay\Helpers\Payment_Plans::get_payment_plans(
                array(
                    'posts_per_page' => -1,
                )
            );

            $percentage_count            = 0;
            $fixed_count                 = 0;
            $mixed_count                 = 0;
            $enabled_plan_count          = 0;
            $disabled_plan_count         = 0;
            $enabled_restrictions_count  = 0;
            $disabled_restrictions_count = 0;
            $restricted_to_roles_count   = 0;
            $restricted_to_users_count   = 0;
            foreach ( $plans_query->posts as $plan ) {

                /***************************************************************************
                 * Check for plan types
                 ***************************************************************************
                 *
                 * We gather data for plans that have mixed 'fixed' + 'percentage' types.
                 * We also count the number of plans that have only 'fixed' and 'percentage'
                 * types.
                 */
                $plan_types = array_column( array_column( $plan->breakdown, 'due' ), 'type' );
                $plan_types = array_flip( $plan_types );
                if ( count( $plan_types ) > 1 ) {
                    ++$mixed_count;
                } elseif ( isset( $plan_types['percentage'] ) ) {
                    ++$percentage_count;
                } elseif ( isset( $plan_types['fixed'] ) ) {
                    ++$fixed_count;
                }

                /***************************************************************************
                 * Check for enabled/disabled plans
                 ***************************************************************************
                 *
                 * We count the number of enabled and disabled plans.
                 */
                if ( 'yes' === $plan->enabled ) {
                    ++$enabled_plan_count;
                } else {
                    ++$disabled_plan_count;
                }

                if ( 'yes' === $plan->apply_restrictions ) {
                    ++$enabled_restrictions_count;
                    if ( ! empty( $plan->wholesale_roles ) ) {
                        ++$restricted_to_roles_count;
                    }
                    if ( ! empty( $plan->allowed_users ) ) {
                        ++$restricted_to_users_count;
                    }
                } else {
                    ++$disabled_restrictions_count;
                }
            }

            $settings['wpay_plan_percentage_count']       = $percentage_count;
            $settings['wpay_plan_fixed_count']            = $fixed_count;
            $settings['wpay_plan_mixed_count']            = $mixed_count;
            $settings['wpay_enabled_plan_count']          = $enabled_plan_count;
            $settings['wpay_disabled_plan_count']         = $disabled_plan_count;
            $settings['wpay_enabled_restrictions_count']  = $enabled_restrictions_count;
            $settings['wpay_disabled_restrictions_count'] = $disabled_restrictions_count;
            $settings['wpay_restricted_to_roles_count']   = $restricted_to_roles_count;
            $settings['wpay_restricted_to_users_count']   = $restricted_to_users_count;
        }

        // Return the settings as an array.
        return $settings;
    }

    /**
     * Fetch environment settings data and compile them together into an array
     *
     * @since  2.1.7
     * @access private
     * @return array All environment settings data.
     */
    private function _fetch_environment_settings_data() {

        $data = array();

        // Get current theme info.
        $theme_data = wp_get_theme();

        // Get multisite data.
        $count_blogs = 1;
        if ( is_multisite() ) {
            if ( function_exists( 'get_blog_count' ) ) {
                $count_blogs = get_blog_count();
            } else {
                $count_blogs = 'Not Set';
            }
        }

        $data['url']                            = home_url();
        $data['php_version']                    = phpversion();
        $data['wp_version']                     = get_bloginfo( 'version' );
        $data['wc_version']                     = WWP_Helper_Functions::get_current_woocommerce_version();
        $data['server']                         = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : '';
        $data['multisite']                      = is_multisite();
        $data['sites']                          = $count_blogs;
        $data['usercount']                      = function_exists( 'count_users' ) ? count_users() : 'Not Set';
        $data['themename']                      = $theme_data->Name;
        $data['themeversion']                   = $theme_data->Version;
        $data['admin_email']                    = get_bloginfo( 'admin_email' );
        $data['usagetracking']                  = get_option( 'wwp_usage_tracking_config', false );
        $data['timezoneoffset']                 = wp_timezone_string();
        $data['locale']                         = get_locale();
        $data['is_hpos_enabled']                = 'yes' === get_option( 'woocommerce_feature_custom_order_tables_enabled' );
        $data['is_custom_orders_table_enabled'] = OrderUtil::custom_orders_table_usage_is_enabled();
        $data['is_cart_block']                  = has_block( 'woocommerce/cart', wc_get_page_id( 'cart' ) );
        $data['is_checkout_block']              = has_block( 'woocommerce/checkout', wc_get_page_id( 'checkout' ) );

        return $data;
    }

    /**
     * Fetch all plugin data and compile them together into an array
     *
     * @since  2.1.7
     * @access private
     * @return array All plugin data.
     */
    private function _fetch_plugin_data() {

        // This site's active plugins list.
        $active_plugins = get_option( 'active_plugins', array() );

        // Multi-site network activated plugins (if we're on multi-site).
        $network_active_plugins = array_keys( get_site_option( 'active_sitewide_plugins', array() ) );

        // Merge to get the final active plugins list.
        $all_active_plugins = array_unique( array_merge( $active_plugins, $network_active_plugins ) );

        return $all_active_plugins;
    }

    /**
     * Fetch effectiveness data and compile them together into an array
     *
     * @since  2.1.7
     * @since  2.2.2 Change date from Sunday last week to Saturday last week.
     * @access private
     * @return array All effectiveness data.
     */
    private function _fetch_effectiveness_data() {

        $data = array();

        // Set the start date Sunday last week.
        $start_date = strtotime( 'monday last week' ) - DAY_IN_SECONDS;

        // Set the end date Saturday last week.
        $end_date = strtotime( 'saturday last week' );

        $order_args = array(
            'limit'        => -1,
            'status'       => array( 'wc-completed', 'wc-processing' ),
            'date_created' => gmdate( 'Y-m-d', $start_date ) . '...' . gmdate( 'Y-m-d', $end_date ),
            'return'       => 'objects',
        );

        if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
            $order_args['meta_query'] = array(
                array(
                    'key'     => '_wwpp_order_type',
                    'value'   => 'wholesale',
                    'compare' => '=',
                ),
            );
        } else {
            $order_args['wholesale_order'] = true;
        }

        // Fetch the orders with wc_get_orders.
        $orders = wc_get_orders( $order_args );

        // Get the total number of orders.
        $total_orders = count( $orders );

        // Get the total number of orders using WPAY payment method with generated Stripe invoice.
        $wpay_order_count = 0;

        // Get the revenue total that used WPAY payment method.
        $revenue_total_wpay = 0;

        // Get the revenue total.
        $revenue_total = 0;
        foreach ( $orders as $order ) {
            $order_total = $order->get_total();

            $revenue_total += $order_total;

            // Get WPAY additional data.
            if ( $order->get_payment_method() === 'wc_wholesale_payments' && $order->get_meta( '_wpay_stripe_invoice_id' ) ) {
                ++$wpay_order_count;
                $revenue_total_wpay += $order_total;
            }
        }

        // Get the total number of user registrations for wholesale.

        // Get all registered wholesale roles.
        $wwp_wholesale_roles        = WWP_Wholesale_Roles::getInstance();
        $registered_wholesale_roles = $wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

        // Get all users registered after the $start_date but before $end_date with a wholesale user role.
        $args = array(
            'role__in'    => array_keys( $registered_wholesale_roles ),
            'date_query'  => array(
                array(
                    'after'     => gmdate( 'Y-m-d H:i:s', $start_date ),
                    'before'    => gmdate( 'Y-m-d H:i:s', $end_date ),
                    'inclusive' => true,
                ),
            ),
            'count_total' => true,
            'fields'      => 'ID',
        );

        $total_registrations = count( get_users( $args ) );

        // Set the data and pass back.

        $data['currency']                = get_option( 'woocommerce_currency' );
        $data['date']                    = gmdate( 'Y-m-d H:i:s', $end_date );
        $data['wholesale_order_count']   = $total_orders;
        $data['wholesale_order_revenue'] = $revenue_total;
        $data['wholesale_new_leads']     = $total_registrations;

        // WPAY additional data.
        $data['wpay_order_count']   = $wpay_order_count;
        $data['wpay_order_revenue'] = $revenue_total_wpay;

        return $data;
    }

    /**
     * Handle a custom 'wholesale_order' query var to get orders with the 'wwp_wholesale_role' meta.
     *
     * @param array $query      - Args for WP_Query.
     * @param array $query_vars - Query vars from WC_Order_Query.
     *
     * @return array modified $query
     */
    public function handle_custom_order_query_var( $query, $query_vars ) {

        if ( ! empty( $query_vars['wholesale_order'] ) && true === $query_vars['wholesale_order'] ) {
            // Adjust meta query to get orders where 'wwp_wholesale_role' exists (indicating its a wholesale order).
            $query['meta_query'][] = array(
                'key'     => 'wwp_wholesale_role',
                'compare' => 'EXISTS',
            );
        }

        return $query;
    }

    /**
     * Send the checkin
     *
     * @param BOOL $override            Flag to override if tracking is allowed or not.
     * @param BOOL $ignore_last_checkin Flag to ignore that last checkin time check.
     *
     * @since  1.14
     * @access public
     *
     * @return BOOL  Whether the checkin was sent successfully
     */
    public function send_checkin( $override = false, $ignore_last_checkin = false ) {

        // Don't track anything from our domains.
        $home_url = trailingslashit( home_url() );
        if ( strpos( $home_url, 'wholesalesuiteplugin.com' ) !== false ) {
            return false;
        }

        // Check if tracking is allowed on this site.
        if ( ! $this->tracking_allowed() && ! $override ) {
            return false;
        }

        // Send a maximum of once per week.
        $last_send = get_option( 'wwp_usage_tracking_last_checkin' );
        if ( is_numeric( $last_send ) && $last_send > strtotime( '-1 week' ) && ! ( $ignore_last_checkin || defined( 'WWS_TESTING_SITE' ) ) ) {
            return false;
        }

        $checkin_url = 'https://usg.rymeraplugins.com/v1/wwp-checkin/';
        if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === WP_ENVIRONMENT_TYPE &&
            defined( 'RYMERA_LOCAL_USAGE_TRACKING_URL' ) &&
            wc_is_valid_url( RYMERA_LOCAL_USAGE_TRACKING_URL ) ) {
            $checkin_url = RYMERA_LOCAL_USAGE_TRACKING_URL;
        }

        wp_remote_post(
            $checkin_url,
            array(
                'method'      => 'POST',
                'timeout'     => 5,
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking'    => false,
                'body'        => $this->get_data(),
                'user-agent'  => 'WWP/' . WWP_Helper_Functions::get_wwp_version() . '; ' . get_bloginfo( 'url' ),
            )
        );

        // If we have completed successfully, recheck in 1 week.
        update_option( 'wwp_usage_tracking_last_checkin', time(), 'no' );

        return true;
    }

    /**
     * Check if tracking is allowed on this site
     *
     * @since  1.14
     * @access public
     * @return BOOL whether this site can be tracked or not
     */
    private function tracking_allowed() {

        $allow_usage = get_option( 'wwp_anonymous_data', false );

        return ( false !== $allow_usage && 'no' !== $allow_usage ) || WWP_Helper_Functions::has_paid_plugin_active();
    }

    /**
     * Schedule when we should send tracking data
     *
     * @since  1.14
     * @access public
     */
    public function schedule_send() {

        if ( ! wp_next_scheduled( 'wwp_usage_tracking_cron' ) ) {
            $tracking             = array();
            $tracking['day']      = wp_rand( 0, 6 );
            $tracking['hour']     = wp_rand( 0, 23 );
            $tracking['minute']   = wp_rand( 0, 59 );
            $tracking['second']   = wp_rand( 0, 59 );
            $tracking['offset']   = ( $tracking['day'] * DAY_IN_SECONDS ) +
                ( $tracking['hour'] * HOUR_IN_SECONDS ) +
                ( $tracking['minute'] * MINUTE_IN_SECONDS ) +
                $tracking['second'];
            $tracking['initsend'] = strtotime( 'next sunday' ) + $tracking['offset'];

            wp_schedule_event( $tracking['initsend'], 'weekly', 'wwp_usage_tracking_cron' );
            update_option( 'wwp_usage_tracking_config', $tracking, 'no' );
        }
    }

    /**
     * Check if the admin notice was opted in to tracking and if so, send the data and schedule the cron for future
     * sends
     *
     * @since  1.14
     * @access public
     */
    public function check_for_optin() {

        if ( ! ( ! empty( $_REQUEST['wwp_action'] ) && 'opt_into_tracking' === $_REQUEST['wwp_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        if ( get_option( 'wwp_anonymous_data' ) === 'yes' ) {
            return;
        }

        if ( WWP_Helper_Functions::has_paid_plugin_active() ) {
            update_option( 'wwp_anonymous_data', 'yes', 'no' );

            return;
        }

        update_option( 'wwp_anonymous_data', 'yes', 'no' );
        $this->send_checkin( true, true );
        update_option( 'wwp_tracking_notice', 1, 'no' );
    }

    /**
     * Check for optout via the admin notice and handle appropriately
     *
     * @since  1.14
     * @access public
     */
    public function check_for_optout() {

        if ( ! ( ! empty( $_REQUEST['wwp_action'] ) && 'opt_out_of_tracking' === $_REQUEST['wwp_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        if ( get_option( 'wwp_anonymous_data' ) === 'yes' ) {
            return;
        }

        if ( WWP_Helper_Functions::has_paid_plugin_active() ) {
            return;
        }

        update_option( 'wwp_anonymous_data', 'no', 'no' );
        update_option( 'wwp_tracking_notice', 1, 'no' );
    }

    /**
     * Add the cron schedule
     *
     * @param array $schedules The schedules array from the filter.
     *
     * @since  1.14
     * @access public
     */
    public function add_schedules( $schedules = array() ) {

        // Adds once weekly to the existing schedules.
        $schedules['weekly'] = array(
            'interval' => 604800,
            'display'  => __( 'Once Weekly', 'woocommerce-wholesale-prices' ),
        );

        return $schedules;
    }

    /**
     * Set up the usage tracking notice for admin users. Only shows once so as not to annoy them.
     *
     * @since  1.14
     * @access public
     */
    public function wwp_admin_setup_usage_tracking_notice() {

        if ( current_user_can( 'administrator' ) ) { // phpcs:ignore
            if ( ! is_network_admin() ) {

                if ( ! get_option( 'wwp_tracking_notice' ) || defined( 'WWS_TESTING_SITE' ) ) {

                    if ( ! get_option( 'wwp_anonymous_data', false ) ) {

                        if ( ! WWP_Helper_Functions::is_dev_url( network_site_url( '/' ) ) || defined( 'WWS_TESTING_SITE' ) ) {

                            if ( WWP_Helper_Functions::has_paid_plugin_active() ) {
                                update_option( 'wwp_anonymous_data', 1, 'no' );

                                return;
                            }

                            $optin_url  = add_query_arg( 'wwp_action', 'opt_into_tracking' );
                            $optout_url = add_query_arg( 'wwp_action', 'opt_out_of_tracking' );

                            $output = '<div class="updated">';

                            $output .= '<p style="font-weight:700;">' . __( 'Wholesale Suite Usage Tracking Permission', 'woocommerce-wholesale-prices' ) . '</p>';
                            $output .= '<p>';
                            $output .= __( 'Allow Wholesale Suite to track plugin usage? Opt-in to let us track usage data so we know with which WordPress configurations, themes and plugins we should test with.', 'woocommerce-wholesale-prices' );
                            $output .= '</p>';
                            $output .= '<a href="' . esc_url( $optin_url ) . '" class="button-primary">' . __( 'Allow', 'woocommerce-wholesale-prices' ) . '</a>';
                            $output .= '&nbsp;<a href="' . esc_url( $optout_url ) . '" class="button-secondary">' . __( 'Do not allow', 'woocommerce-wholesale-prices' ) . '</a>';
                            $output .= '</p></div>';

                            echo wp_kses_post( $output );
                        } else {
                            // is testing site.
                            update_option( 'wwp_tracking_notice', '1', 'no' );
                        }
                    }
                }
            }
        }
    }

    /**
     * Execute model.
     *
     * @since  1.14
     * @access public
     */
    public function run() {

        // Schedule sending.
        add_action( 'init', array( $this, 'schedule_send' ) );
        add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );
        add_action( 'wwp_usage_tracking_cron', array( $this, 'send_checkin' ) );

        // Handle admin notice for optin.
        add_action( 'admin_notices', array( $this, 'wwp_admin_setup_usage_tracking_notice' ) );
        add_action( 'network_admin_notices', array( $this, 'wwp_admin_setup_usage_tracking_notice' ) );
        add_action( 'admin_head', array( $this, 'check_for_optin' ) );
        add_action( 'admin_head', array( $this, 'check_for_optout' ) );

        // Custom query filter for orders.
        add_filter(
            'woocommerce_order_data_store_cpt_get_orders_query',
            array(
                $this,
                'handle_custom_order_query_var',
            ),
            10,
            2
        );
    }
}
