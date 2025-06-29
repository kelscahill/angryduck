<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Actions
 */

namespace RymeraWebCo\WWOF\Actions;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;

/**
 * Class Deactivation
 *
 * @since 3.0
 */
class Deactivation extends Abstract_Class {

    /**
     * Holds boolean value whether the plugin is being activated network wide.
     *
     * @since 3.0
     * @var bool
     */
    protected $network_wide;

    /**
     * Constructor.
     *
     * @param bool $network_wide Whether the plugin is being activated network wide.
     *
     * @since 3.0
     */
    public function __construct( $network_wide ) {

        $this->network_wide = $network_wide;
    }

    /**
     * Run plugin deactivation actions.
     *
     * @since 3.0
     */
    public function run() {

        global $wpdb;

        /***************************************************************************
         * Check multisite setup
         ***************************************************************************
         *
         * Check if it is a multisite network
         */
        if ( is_multisite() ) {
            /***************************************************************************
             * Single or Network wide deactivation
             ***************************************************************************
             *
             * Check if the plugin has been deactivated on the network or on a single site
             */
            if ( $this->network_wide ) {
                /***************************************************************************
                 * Get all blog ids
                 ***************************************************************************
                 *
                 * Get ids of all sites in the network and deactivate the plugin on each one.
                 */
                $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

                foreach ( $blog_ids as $blog_id ) {
                    switch_to_blog( $blog_id );
                    delete_option( 'wwof_activation_code_triggered' );
                    delete_site_option( 'wwof_option_installed_version' );
                    delete_site_option( 'wwof_update_data' );
                    delete_site_option( 'wwof_license_expired' );
                    restore_current_blog();
                }
            } else {
                /***************************************************************************
                 * Single site deactivation from network
                 ***************************************************************************
                 *
                 * Deactivated on a single site, in a multi-site install.
                 */
                delete_option( 'wwof_activation_code_triggered' );
                delete_site_option( 'wwof_option_installed_version' );
                delete_site_option( 'wwof_update_data' );
                delete_site_option( 'wwof_license_expired' );
            }
        } else {
            /***************************************************************************
             * Single site deactivation
             ***************************************************************************
             *
             * Deactivated on a single site
             */
            delete_option( 'wwof_activation_code_triggered' );
            delete_option( 'wwof_option_installed_version' );
            delete_option( 'wwof_update_data' );
            delete_option( 'wwof_license_expired' );

            // Clear license cache after data refreshed to accurately show Dashboard data (but only for non-multisite).
            delete_transient( WWOF_PLUGIN_LICENSE_STATUSES_CACHE );

        }
    }
}
