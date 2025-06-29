<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

// SLMW.
if ( is_multisite() ) {

    delete_site_option( 'wwlc_option_license_email' );
    delete_site_option( 'wwlc_option_license_key' );
    delete_site_option( 'wwlc_license_activated' );
    delete_site_option( 'wwlc_update_data' );
    delete_site_option( 'wwlc_retrieving_update_data' );
    delete_site_option( 'wwlc_option_installed_version' );
    delete_site_option( 'wwlc_activate_license_notice' );
    delete_site_option( 'wwlc_license_expired' );


} else {

    delete_option( 'wwlc_option_license_email' );
    delete_option( 'wwlc_option_license_key' );
    delete_option( 'wwlc_license_activated' );
    delete_option( 'wwlc_update_data' );
    delete_option( 'wwlc_retrieving_update_data' );
    delete_option( 'wwlc_option_installed_version' );
    delete_option( 'wwlc_activate_license_notice' );
    delete_option( 'wwlc_license_expired' );

}

if ( get_option( 'wwlc_settings_help_clean_plugin_options_on_uninstall' ) === 'yes' ) {

    global $wpdb;

    // DELETES WWLC SETTINGS.
    $wpdb->query(
        "DELETE FROM $wpdb->options
     WHERE option_name LIKE 'wwlc_%'
    "
    );

}

flush_rewrite_rules();
