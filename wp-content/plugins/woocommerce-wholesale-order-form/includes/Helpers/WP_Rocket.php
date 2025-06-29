<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Helpers
 */

namespace RymeraWebCo\WWOF\Helpers;

/**
 * WP_Rocket class.
 *
 * @since 1.0.0
 */
class WP_Rocket {

    /**
     * Check if WP Rocket plugin is active.
     *
     * @since 3.0.1
     * @return bool
     */
    public static function is_active() {

        return defined( 'WP_ROCKET_FILE' ) || class_exists( 'WP_Rocket' );
    }
}
