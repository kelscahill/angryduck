<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Helpers
 */

namespace RymeraWebCo\WWOF\Helpers;

/**
 * WWLC class.
 *
 * @since 3.0
 */
class WWLC {

    /**
     * WWP plugin file basename.
     */
    const WWLC_FILE_BASENAME = 'woocommerce-wholesale-lead-capture/woocommerce-wholesale-lead-capture.bootstrap.php';

    /**
     * Path to WWP main plugin file.
     */
    const WWLC_FILE = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . self::WWLC_FILE_BASENAME;

    /**
     * Checks if WooCommerce Wholesale Prices Premium plugin is active.
     *
     * @since 3.0
     * @return bool
     */
    public static function is_active() {

        return is_plugin_active( self::WWLC_FILE_BASENAME );
    }
}
