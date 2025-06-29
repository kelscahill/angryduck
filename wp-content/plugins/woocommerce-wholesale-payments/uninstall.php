<?php
/**
 * Uninstall script for WooCommerce Wholesale Payments.
 *
 * @package RymeraWebCo\WPay
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( is_multisite() ) {
    delete_site_option( 'wpay_update_data' );
    delete_site_option( 'wpay_retrieving_update_data' );
    delete_site_option( 'wpay_installed_version' );
    delete_site_option( 'wpay_getting_started_notice_dismiss' );
    delete_site_option( 'wpay_license_email' );
    delete_site_option( 'wpay_license_key' );
    delete_site_option( 'wpay_license_activated' );
    delete_site_option( 'wpay_license_notice_dismissed' );
    delete_site_option( 'wpay_license_last_check' );
    delete_site_option( 'wws_license_data' );
    delete_site_option( 'wpay_api_mode' );
    delete_site_option( 'wpay_account_number' );
    delete_site_option( 'wpay_test_access_token' );
    delete_site_option( 'wpay_test_token_type' );
    delete_site_option( 'wpay_test_publishable_key' );
    delete_site_option( 'wpay_test_scope' );
    delete_site_option( 'wpay_test_webhook_secret' );
    delete_site_option( 'wpay_live_access_token' );
    delete_site_option( 'wpay_live_token_type' );
    delete_site_option( 'wpay_live_publishable_key' );
    delete_site_option( 'wpay_live_scope' );
    delete_site_option( 'wpay_live_webhook_secret' );
    delete_site_option( 'wpay_display_name' );
    delete_site_option( 'wpay_payment_method_name' );
    delete_site_option( 'wpay_refresh_token' );
} else {
    delete_option( 'wpay_update_data' );
    delete_option( 'wpay_retrieving_update_data' );
    delete_option( 'wpay_installed_version' );
    delete_option( 'wpay_getting_started_notice_dismiss' );
    delete_option( 'wpay_license_email' );
    delete_option( 'wpay_license_key' );
    delete_option( 'wpay_license_activated' );
    delete_option( 'wpay_license_notice_dismissed' );
    delete_option( 'wpay_license_last_check' );
    delete_option( 'wws_license_data' );
    delete_option( 'wpay_api_mode' );
    delete_option( 'wpay_account_number' );
    delete_option( 'wpay_test_access_token' );
    delete_option( 'wpay_test_token_type' );
    delete_option( 'wpay_test_publishable_key' );
    delete_option( 'wpay_test_scope' );
    delete_option( 'wpay_test_webhook_secret' );
    delete_option( 'wpay_live_access_token' );
    delete_option( 'wpay_live_token_type' );
    delete_option( 'wpay_live_publishable_key' );
    delete_option( 'wpay_live_scope' );
    delete_option( 'wpay_live_webhook_secret' );
    delete_option( 'wpay_display_name' );
    delete_option( 'wpay_payment_method_name' );
    delete_option( 'wpay_refresh_token' );
}
