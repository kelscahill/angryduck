<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Actions
 */

namespace RymeraWebCo\WWOF\Actions;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\Post_Types\Order_Form_Post_Type;
use RymeraWebCo\WWOF\Updates\Version_1_21_Update;
use RymeraWebCo\WWOF\Updates\Version_1_7_Update;
use RymeraWebCo\WWOF\Updates\Version_2_0_Update;
use RymeraWebCo\WWOF\Updates\Version_3_0_1_Update;
use RymeraWebCo\WWOF\Updates\Version_3_0_Update;

/**
 * Class Activation
 *
 * @since 3.0
 */
class Activation extends Abstract_Class {

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
     * Run plugin activation actions.
     *
     * @since 3.0
     */
    public function run() {

        /***************************************************************************
         * Register post type
         ***************************************************************************
         *
         * We register post type here so its rewrite rules are available when
         * it's flushed.
         */
        ( new Order_Form_Post_Type() )->register_post_type();

        /**
         * Previously multisite installs site store license options using normal get/add/update_option functions.
         * These stores the option on a per sub-site basis. We need move these options network wide in multisite setup
         * via get/add/update_site_option functions.
         */
        if ( is_multisite() ) {
            $license_email = get_option( WWOF_OPTION_LICENSE_EMAIL );
            if ( $license_email ) {

                update_site_option( WWOF_OPTION_LICENSE_EMAIL, $license_email );

                delete_option( WWOF_OPTION_LICENSE_EMAIL );
            }

            $license_key = get_option( WWOF_OPTION_LICENSE_KEY );
            if ( $license_key ) {

                update_site_option( WWOF_OPTION_LICENSE_KEY, $license_key );

                delete_option( WWOF_OPTION_LICENSE_KEY );
            }

            $installed_version = get_option( WWOF_OPTION_INSTALLED_VERSION );
            if ( $installed_version ) {

                update_site_option( WWOF_OPTION_INSTALLED_VERSION, $installed_version );

                delete_option( WWOF_OPTION_INSTALLED_VERSION );
            }
        } else {
            // Clear license cache after data refreshed to accurately show Dashboard data (but only for non-multisite).
            delete_transient( WWOF_PLUGIN_LICENSE_STATUSES_CACHE );
        }

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
            // Set the last license check.
            if ( ! get_site_option( WWOF_LAST_LICENSE_CHECK, false ) ) {
                update_site_option( WWOF_LAST_LICENSE_CHECK, time() );
            }
        } else { // phpcs:ignore Universal.ControlStructures.DisallowLonelyIf.Found
            // Set the last license check.
            if ( ! get_option( WWOF_LAST_LICENSE_CHECK, false ) ) {
                update_option( WWOF_LAST_LICENSE_CHECK, time() );
            }
        }

        /***************************************************************************
         * Getting Started Notice
         ***************************************************************************
         *
         * Set getting started notice.
         */
        if ( ! get_option( 'wwof_admin_notice_getting_started_show', false ) ) {
            update_option( 'wwof_admin_notice_getting_started_show', 'yes' );
        }

        /***************************************************************************
         * V1.17 Updates
         ***************************************************************************
         *
         * Run updates for v1.17
         */
        ( new Version_1_7_Update() )->run();

        /***************************************************************************
         * V1.21 Updates
         ***************************************************************************
         *
         * Run updates for v1.21
         */
        ( new Version_1_21_Update() )->run();

        /***************************************************************************
         * V2.0 Updates
         ***************************************************************************
         *
         * Run updates for v2.0
         */
        ( new Version_2_0_Update() )->run();

        /***************************************************************************
         * Run v3.0 updates
         ***************************************************************************
         *
         * We run the v3.0 updates.
         */
        ( new Version_3_0_Update() )->run();

        update_option( WWOF_ACTIVATION_CODE_TRIGGERED, 'yes' );

        /***************************************************************************
         * Run 3.0.1 updates
         ***************************************************************************
         *
         * We run the v3.0.1 updates.
         */
        ( new Version_3_0_1_Update() )->run();
    }
}
