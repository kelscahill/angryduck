<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WPay\Actions
 */

namespace RymeraWebCo\WPay\Actions;

use RymeraWebCo\WPay\Abstracts\Abstract_Class;
use RymeraWebCo\WPay\Helpers\License;
use RymeraWebCo\WPay\Post_Types\Payment_Plan_Post_Type;
use RymeraWebCo\WPay\Updates\Version_1_0_0_Beta_2;

/**
 * Activation class.
 *
 * @since 1.0.0
 */
class Activation extends Abstract_Class {

    /**
     * Holds boolean value whether the plugin is being activated network wide.
     *
     * @var bool
     */
    protected $network_wide;

    /**
     * Constructor.
     *
     * @param bool $network_wide Whether the plugin is being activated network wide.
     */
    public function __construct( $network_wide ) {

        $this->network_wide = $network_wide;
    }

    /**
     * Run plugin activation actions.
     *
     * @since 1.0.0
     */
    public function run() {

        if ( ! as_next_scheduled_action( 'wws_license_check', array(), 'wws_license_check' ) ) {
            as_enqueue_async_action( 'wws_license_check', array(), 'wws_license_check' );

            as_schedule_recurring_action( time() + wp_rand( 0, DAY_IN_SECONDS ), DAY_IN_SECONDS, 'wws_license_check', array(), 'wws_license_check' );
        }

        if ( ! as_next_scheduled_action( 'wpay_check_in', array(), 'wpay_check_in' ) ) {
            as_schedule_recurring_action( time() + WEEK_IN_SECONDS + wp_rand( 0, DAY_IN_SECONDS ), WEEK_IN_SECONDS, 'wpay_check_in', array(), 'wpay_check_in' );
        }

        if ( ! License::get_last_license_check() ) {
            License::update_last_license_check();
        }

        /***************************************************************************
         * Register Payment Plan Custom Post Type
         ***************************************************************************
         *
         * Execute custom post type registration on plugin activation.
         */
        ( new Payment_Plan_Post_Type() )->register_post_type();

        /***************************************************************************
         * Run 1.0.0-beta.2 update actions
         ***************************************************************************
         *
         * Execute 1.0.0-beta.2 update actions on plugin activation.
         */
        ( new Version_1_0_0_Beta_2() )->run();
    }
}
