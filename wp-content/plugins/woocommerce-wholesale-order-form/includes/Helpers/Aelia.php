<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Helpers
 */

namespace RymeraWebCo\WWOF\Helpers;

/**
 * Aelia class.
 *
 * @since 3.0
 */
class Aelia {

    /**
     * Checks if the Aelia Currency Switcher plugin is active.
     *
     * @since 3.0
     * @return bool
     */
    public static function is_active() {

        return class_exists( '\Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher' ) ||
            class_exists( 'WC_Aelia_CurrencySwitcher' );
    }
}
