<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Helpers
 */

namespace RymeraWebCo\WPay\Helpers;

use DateInterval;
use DateTimeZone;
use Exception;
use WC_DateTime;
use WP_Error;

/**
 * Datetime class.
 *
 * @since 1.0.0
 */
class Datetime {

    /**
     * Get default datetime format for display.
     *
     * @since  1.0.0
     *
     * @return string Datetime format.
     */
    public static function get_default_datetime_format() {

        return sprintf( '%s %s', get_option( 'date_format', 'F j, Y' ), get_option( 'time_format', 'g:i a' ) );
    }

    /**
     * Get datetime with site timezone.
     *
     * @param string $datetime Datetime string.
     * @param string $interval Datetime interval.
     *
     * @since  1.0.0
     *
     * @return WP_Error|WC_DateTime Datetime object or WP_Error.
     */
    public static function get_datetime_with_site_timezone( $datetime, $interval = '' ) {

        try {
            $datetime = new WC_DateTime( $datetime, new DateTimeZone( 'UTC' ) );
            $datetime->setTimezone( new DateTimeZone( wc_timezone_string() ) );
            if ( ! empty( $interval ) ) {
                $interval = DateInterval::createFromDateString( $interval );
                $datetime->add( $interval ); // Add interval if needed.
            }
        } catch ( Exception $e ) {
            return new WP_Error( 'invalid_datetime', $e->getMessage() );
        }

        return $datetime;
    }

    /**
     * Convert datetime to site standard format.
     * 1. Datetime must use site timezone.
     * 2. Datetime must use site default datetime format.
     * 3. Datetime must be localize using i18n.
     *
     * @param string $datetime Datetime string.
     * @param string $format   Datetime format.
     * @param string $interval Datetime interval.
     *
     * @since 3.0.4
     *
     * @return string
     */
    public static function convert_datetime_to_site_standard_format( $datetime, $format = '', $interval = '' ) {

        $standard = self::get_datetime_with_site_timezone( $datetime, $interval ); // Convert to site timezone.
        $format   = ! empty( $format ) ? $format : self::get_default_datetime_format();

        return $standard->date_i18n( $format ); // Convert to site default datetime format and localize.
    }
}
