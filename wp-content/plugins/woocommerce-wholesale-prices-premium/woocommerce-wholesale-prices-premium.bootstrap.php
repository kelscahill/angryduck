<?php
/*
Plugin Name:          WooCommerce Wholesale Prices Premium
Requires Plugins:     woocommerce, woocommerce-wholesale-prices
Plugin URI:           https://wholesalesuiteplugin.com/
Update URI:           wholesalesuiteplugin.com
Description:          WooCommerce Premium Extension for the Woocommerce Wholesale Prices Plugin
Author:               Rymera Web Co
Version:              2.0.4
Author URI:           http://rymera.com.au/
Text Domain:          woocommerce-wholesale-prices-premium
Requires at least:    5.2
Tested up to:         6.7
WC requires at least: 4.0
WC tested up to:      9.5
*/

// This file is the main plugin boot loader..

/**
 * Ref to main plugin file.
 *
 * @since 2.0.0
 */
define( 'WWPP_PLUGIN_FILE', __FILE__ );

/**
 * Ref to plugin directory.
 *
 * @since 2.0.0
 */
define( 'WWPP_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Register Global Deactivation Hook.
 * Codebase that must be run on plugin deactivation whether or not dependencies are present.
 * Necessary to prevent activation code from being executed more than once.
 *
 * @since 1.12.5
 * @since 1.13.0 Add multisite support.
 *
 * @param boolean $network_wide Flag that determines if plugin is deactivated on network wide or not.
 */
function wwpp_global_plugin_deactivate( $network_wide ) {

    global $wpdb;

    // check if it is a multisite network.
    if ( is_multisite() ) {

        // check if the plugin has been activated on the network or on a single site.
        if ( $network_wide ) {

            // get ids of all sites.
            $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

            foreach ( $blog_ids as $blog_id ) {

                switch_to_blog( $blog_id );
                delete_option( 'wwpp_option_activation_code_triggered' );
                delete_site_option( 'wwpp_option_installed_version' );
                delete_site_option( 'wwpp_update_data' );
                delete_site_option( 'wwpp_license_expired' );
            }

            restore_current_blog();
        } else {

            // activated on a single site, in a multi-site.
            delete_option( 'wwpp_option_activation_code_triggered' );
            delete_site_option( 'wwpp_option_installed_version' );
            delete_site_option( 'wwpp_update_data' );
            delete_site_option( 'wwpp_license_expired' );
        }
    } else {

        // activated on a single site.
        delete_option( 'wwpp_option_activation_code_triggered' );
        delete_option( 'wwpp_option_installed_version' );
        delete_option( 'wwpp_update_data' );
        delete_option( 'wwpp_license_expired' );
    }
}

register_deactivation_hook( __FILE__, 'wwpp_global_plugin_deactivate' );

// Makes sure the plugin is defined before trying to use it.
if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'get_plugin_data' ) ) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

/**
 * Check if Woocommerce Wholesale Prices is installed and active
 */
if ( is_plugin_active( 'woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php' ) ) {

    // Moved the options here coz license manager also need this once wc is deactivated.
    require_once 'woocommerce-wholesale-prices-premium.options.php';

    $wwp_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php', false, false );

    // Check WWP version.
    // WWPP ( 1.6.0 and up ) we need WWP 1.1.7.
    // WWPP ( 1.7.0 and up ) we need WWP 1.2.0.
    // WWPP ( 1.7.4 and up ) we need WWP 1.2.2.
    // WWPP ( 1.8.0 and up ) we need WWP 1.2.3.
    // WWPP ( 1.13.0 and up ) we need WWP 1.3.0.
    // WWPP ( 1.13.4 and up ) we need WWP 1.3.1.
    // WWPP ( 1.14.1 and up ) we need WWP 1.4.1.
    // WWPP ( 1.14.4 and up ) we need WWP 1.4.4.
    // WWPP ( 1.15.0 and up ) we need WWP 1.5.0.
    // WWPP ( 1.16.0 and up ) we need WWP 1.6.0.
    // WWPP ( 1.16.4 and up ) we need WWP 1.6.4.
    // WWPP ( 1.21 and up ) we need WWP 1.8.
    // WWPP ( 1.23 and up ) we need WWP 1.10.
    // WWPP ( 1.24 and up ) we need WWP 1.11.
    // WWPP ( 1.25 and up ) we need WWP 1.12.
    // WWPP ( 1.25.2 and up ) we need WWP 1.13.3.
    // WWPP ( 1.26 and up ) we need WWP 1.14.
    // WWPP ( 1.27 and up ) we need WWP 1.16.
    // WWPP ( 1.27.2 and up ) we need WWP 1.16.1.
    // WWPP ( 1.27.3 and up ) we need WWP 2.0.
    // WWPP ( 1.27.4 and up ) we need WWP 2.0.1.
    // WWPP ( 1.27.8 and up ) we need WWP 2.0.2.
    // WWPP ( 1.27.9 and up ) we need WWP 2.1.3.
    if ( version_compare( $wwp_plugin_data['Version'], '2.1.3', '<' ) ) {

        // Required minimum version of wwp is not met.

        /**
         * Provide admin notice when WWP version does not meet the required version for this plugin.
         *
         * @since 1.14.1
         * @since 1.16.5 Renamed function name. WWPP-576.
         * @since 1.21   Removed condition if( ! get_user_meta( $user_id , 'wwpp_ignore_incompatible_free_version_notice' ) ). WWPP-725
         */
        function wwpp_admin_notice_incompatible_wwp() {

            global $current_user;

            $user_id      = $current_user->ID;
            $wwp_basename = plugin_basename( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'woocommerce-wholesale-prices' . DIRECTORY_SEPARATOR . 'woocommerce-wholesale-prices.bootstrap.php' ); ?>

            <div class="error">
                <p>
                    <?php
                        echo wp_kses_post(
                            sprintf(
                                // translators: %1$s link to the WWP plugin page.
                                __( '<b>WooCommerce Wholesale Prices Premium</b><br/> Please ensure you have the latest version of <a href="%1$s" target="_blank">WooCommerce Wholesale Prices</a> plugin installed and activated along with the Premium extension.', 'woocommerce-wholesale-prices-premium' ),
                                'http://wordpress.org/plugins/woocommerce-wholesale-prices/'
                            )
                        );
                    ?>
                </p>
                <p>
                    <?php
                        echo wp_kses_post(
                            sprintf(
                                // translators: %1$s link to the WWP plugin page.
                                __( '<a href="%1$s">Click here to update WooCommerce Wholesale Prices Plugin &rarr;</a>', 'woocommerce-wholesale-prices-premium' ),
                                wp_nonce_url( 'update.php?action=upgrade-plugin&plugin=' . $wwp_basename, 'upgrade-plugin_' . $wwp_basename )
                            )
                        );
                    ?>
                </p>
            </div>
            <?php
        }

        add_action( 'admin_notices', 'wwpp_admin_notice_incompatible_wwp' );
    } elseif ( get_option( 'wwp_running' ) !== 'no' ) {
        // so if value is 'yes' or blank ( for older wwp version which wwp_running option is not yet introduced ).

        // Only run wwpp if indeed wwp is running.

        // Include Necessary Files.
        require_once 'includes/autoload.php';
        require_once 'woocommerce-wholesale-prices-premium.plugin.php';

        // Get Instance of Main Plugin Class.
        $wc_wholesale_prices_premium            = WooCommerceWholeSalePricesPremium::instance();
        $GLOBALS['wc_wholesale_prices_premium'] = $wc_wholesale_prices_premium;

        // Execute WWPP.
        $wc_wholesale_prices_premium->run();
    }
} else {

    // WooCommerce Wholesale Prices plugin not installed or inactive.

    /**
     * Provide admin admin notice when premium plugin is active but the WWP is either not installed or inactive.
     *
     * @since 1.0.0
     * @since 1.16.5 Renamed function name. WWPP-576.
     * @since 1.21   Removed condition if( ! get_user_meta( $user_id , 'wwpp_ignore_inactive_free_version_notice' ) ). WWPP-725
     */
    function wwpp_admin_notice_wwp_not_active() {

        global $current_user;

        $user_id     = $current_user->ID;
        $plugin_file = 'woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php';
        $wwp_file    = trailingslashit( WP_PLUGIN_DIR ) . plugin_basename( $plugin_file );

        $wwp_install_text = '<a href="' . wp_nonce_url( 'update.php?action=install-plugin&plugin=woocommerce-wholesale-prices', 'install-plugin_woocommerce-wholesale-prices' ) . '">' . __( 'Click here to install from WordPress.org repo &rarr;', 'woocommerce-wholesale-prices-premium' ) . '</a>';
        if ( file_exists( $wwp_file ) ) {
            $wwp_install_text = '<a href="' . wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin_file . '&amp;plugin_status=all&amp;s', 'activate-plugin_' . $plugin_file ) . '" title="' . __( 'Activate this plugin', 'woocommerce-wholesale-prices-premium' ) . '" class="edit">' . __( 'Click here to activate &rarr;', 'woocommerce-wholesale-prices-premium' ) . '</a>';
        }
        ?>

        <div class="error">
            <p>
                <?php
                    echo wp_kses_post(
                        sprintf(
                            // translators: %1$s link to the WWP plugin page.
                            __( 'Please ensure you have the <a href="%1$s" target="_blank">WooCommerce Wholesale Prices</a> plugin installed and activated along with the Premium extension.', 'woocommerce-wholesale-prices-premium' ),
                            'http://wordpress.org/plugins/woocommerce-wholesale-prices/'
                        )
                    ) . '<br />' . wp_kses_post( $wwp_install_text );
                ?>
            </p>
        </div>
        <?php
    }

    add_action( 'admin_notices', 'wwpp_admin_notice_wwp_not_active' );
}
