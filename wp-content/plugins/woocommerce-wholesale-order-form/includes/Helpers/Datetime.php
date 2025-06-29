<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Helpers
 */

namespace RymeraWebCo\WWOF\Helpers;

/**
 * Datetime class.
 *
 * @since 3.0.4
 */
class Datetime {

    /**
     * Get default datetime format for display.
     *
     * @since 3.0.4
     * @access public
     *
     * @return string Datetime format.
     */
    public static function get_default_datetime_format() {
        return sprintf( '%s %s', get_option( 'date_format', 'F j, Y' ), get_option( 'time_format', 'g:i a' ) );
    }

    /**
     * Get datetime with site timezone.
     *
     * @since 3.0.4
     * @access public
     *
     * @param string $datetime Datetime string.
     * @param string $interval Datetime interval.
     *
     * @return \WC_DateTime Datetime object.
     */
    public static function get_datetime_with_site_timezone( $datetime, $interval = '' ) {
        $datetime = new \WC_DateTime( $datetime, new \DateTimeZone( 'UTC' ) );
        $datetime->setTimezone( new \DateTimeZone( wc_timezone_string() ) );
        if ( ! empty( $interval ) ) {
            $interval = \DateInterval::createFromDateString( $interval );
            $datetime->add( $interval ); // Add interval if needed.
        }

        return $datetime;
    }

    /**
     * Convert datetime to site standard format.
     * 1. Datetime must use site timezone.
     * 2. Datetime must use site default datetime format.
     * 3. Datetime must be localize using i18n.
     *
     * @since 3.0.4
     *
     * @param string $datetime Datetime string.
     * @param string $format   Datetime format.
     * @param string $interval Datetime interval.
     *
     * @return string
     */
    public static function convert_datetime_to_site_standard_format( $datetime, $format = '', $interval = '' ) {
        $standard = self::get_datetime_with_site_timezone( $datetime, $interval ); // Convert to site timezone.
        $format   = ! empty( $format ) ? $format : self::get_default_datetime_format();
        return $standard->date_i18n( $format ); // Convert to site default datetime format and localize.
    }
}
