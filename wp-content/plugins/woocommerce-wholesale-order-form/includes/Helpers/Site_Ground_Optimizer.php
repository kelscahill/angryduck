<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Helpers
 */

namespace RymeraWebCo\WWOF\Helpers;

/**
 * Site_Ground_Optimizer class.
 *
 * @since 3.0
 */
class Site_Ground_Optimizer {

    /**
     * Check if SiteGround Optimizer is active.
     *
     * @since 3.0
     * @return bool
     */
    public static function is_active() {

        return is_plugin_active( 'sg-cachepress/sg-cachepress.php' );
    }
}
