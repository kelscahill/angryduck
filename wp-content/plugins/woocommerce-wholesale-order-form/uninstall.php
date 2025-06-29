<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Classes
 * @since   1.8.8
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( is_multisite() ) {

    delete_site_option( 'wwof_option_license_email' );
    delete_site_option( 'wwof_option_license_key' );
    delete_site_option( 'wwof_license_activated' );
    delete_site_option( 'wwof_update_data' );
    delete_site_option( 'wwof_retrieving_update_data' );
    delete_site_option( 'wwof_option_installed_version' );
    delete_site_option( 'wwof_activate_license_notice' );
    delete_site_option( 'wwof_license_expired' );

} else {

    delete_option( 'wwof_option_license_email' );
    delete_option( 'wwof_option_license_key' );
    delete_option( 'wwof_license_activated' );
    delete_option( 'wwof_update_data' );
    delete_option( 'wwof_retrieving_update_data' );
    delete_option( 'wwof_option_installed_version' );
    delete_option( 'wwof_activate_license_notice' );
    delete_option( 'wwof_license_expired' );

}

if ( get_option( 'wwof_settings_help_clean_plugin_options_on_uninstall' ) === 'yes' ) {

    global $wpdb;

    $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'wwof_%'" );
}
