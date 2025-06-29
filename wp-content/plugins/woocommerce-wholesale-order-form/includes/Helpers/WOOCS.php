<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Helpers
 */

namespace RymeraWebCo\WWOF\Helpers;

/**
 * WOOCS class.
 *
 * @since 3.0
 */
class WOOCS {

    /**
     * Checks if WOOCS/FoxCS is active.
     *
     * @since 3.0
     * @return bool
     */
    public static function is_active() {

        return defined( 'WOOCS_PLUGIN_NAME' ) || class_exists( '\WOOCS_STARTER' );
    }
}
