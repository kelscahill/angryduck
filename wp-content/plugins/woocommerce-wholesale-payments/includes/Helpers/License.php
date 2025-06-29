<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Helpers
 */

namespace RymeraWebCo\WPay\Helpers;

use DateTimeZone;
use Exception;
use WC_DateTime;

/**
 * License helper class.
 *
 * @since 1.0.0
 */
class License {

    /**
     * Get plugin license email.
     *
     * @since 1.0.0
     * @return false|mixed|null
     */
    public static function get_plugin_license_email() {

        return is_multisite() ? get_site_option( 'wpay_license_email', null ) : get_option( 'wpay_license_email', null );
    }

    /**
     * Update plugin license email.
     *
     * @param string $email The license email.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function update_plugin_license_email( $email ) {

        if ( null === $email ) {
            return is_multisite() ? delete_site_option( 'wpay_license_email' ) : delete_option( 'wpay_license_email' );
        }

        return is_multisite() ? update_site_option( 'wpay_license_email', $email ) : update_option( 'wpay_license_email', $email );
    }

    /**
     * Get plugin license key.
     *
     * @param bool $masked If the returned license key should be masked.
     *
     * @since 1.0.0
     * @return false|mixed|null
     */
    public static function get_plugin_license_key( $masked = false ) {

        $license_key = is_multisite() ? get_site_option( 'wpay_license_key', null ) : get_option( 'wpay_license_key', null );
        if ( $masked && ! empty( $license_key ) ) {
            $license_key = substr( $license_key, 0, 4 ) . str_repeat( '*', strlen( $license_key ) - 8 ) . substr( $license_key, -4 );
        }

        return $license_key;
    }

    /**
     * Check if the license key is masked.
     *
     * @param string $key The license key.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function is_license_key_masked( $key ) {

        return str_contains( $key, '*' );
    }

    /**
     * Update plugin license key.
     *
     * @param string $key The license key.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function update_plugin_license_key( $key ) {

        if ( null === $key ) {
            return is_multisite() ? delete_site_option( 'wpay_license_key' ) : delete_option( 'wpay_license_key' );
        }

        return is_multisite() ? update_site_option( 'wpay_license_key', $key ) : update_option( 'wpay_license_key', $key );
    }

    /**
     * Get plugin license expiration date timestamp.
     *
     * @since 1.0.0
     * @return false|mixed|null
     */
    public static function get_license_expiration_date() {

        return is_multisite() ? get_site_option( 'wpay_license_expired', null ) : get_option( 'wpay_license_expired', null );
    }

    /**
     * Update plugin license expiration date timestamp.
     *
     * @param string|int|null $timestamp The license expiration date timestamp.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function update_license_expired( $timestamp ) {

        if ( null === $timestamp ) {
            return is_multisite() ? delete_site_option( 'wpay_license_expired' ) : delete_option( 'wpay_license_expired' );
        }

        return is_multisite() ? update_site_option( 'wpay_license_expired', $timestamp ) : update_option( 'wpay_license_expired', $timestamp );
    }

    /**
     * Update plugin license activation status.
     *
     * @param string $status The license activation status.
     *
     * @return bool
     */
    public static function update_license_activated_status( $status ) {

        if ( null === $status ) {
            return is_multisite() ? delete_site_option( 'wpay_license_activated' ) : delete_option( 'wpay_license_activated' );
        }

        return is_multisite() ? update_site_option( 'wpay_license_activated', $status ) : update_option( 'wpay_license_activated', $status );
    }

    /**
     * Get plugin license activation status.
     *
     * @since 1.0.0
     * @return false|mixed|null
     */
    public static function get_license_activated_status() {

        return is_multisite() ? get_site_option( 'wpay_license_activated', 'no' ) : get_option( 'wpay_license_activated', 'no' );
    }

    /**
     * Update plugin license notice dismissed status.
     *
     * @param string $dismissed Whether the license notice is dismissed. Either 'yes' or 'no'.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function update_license_notice_dismissed( $dismissed ) {

        if ( null === $dismissed ) {
            return is_multisite() ? delete_site_option( 'wpay_license_notice_dismissed' ) : delete_option( 'wpay_license_notice_dismissed' );
        }

        return is_multisite() ? update_site_option( 'wpay_license_notice_dismissed', $dismissed ) : update_option( 'wpay_license_notice_dismissed', $dismissed );
    }

    /**
     * Get plugin license notice dismissed status.
     *
     * @since 1.0.0
     * @return false|mixed|null
     */
    public static function get_license_notice_dismissed() {

        return is_multisite() ? get_site_option( 'wpay_license_notice_dismissed', 'no' ) : get_option( 'wpay_license_notice_dismissed', 'no' );
    }

    /**
     * Get plugin last license check timestamp.
     *
     * @param int|string $timestamp The last license check timestamp.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function update_last_license_check( $timestamp = '' ) {

        if ( null === $timestamp ) {
            return is_multisite() ? delete_site_option( 'wpay_license_last_check' ) : delete_option( 'wpay_license_last_check' );
        }

        if ( ! is_numeric( $timestamp ) ) {
            $timestamp = time();
        }

        return is_multisite() ? update_site_option( 'wpay_license_last_check', $timestamp ) : update_option( 'wpay_license_last_check', $timestamp );
    }

    /**
     * Get plugin last license check timestamp.
     *
     * @since 1.0.0
     * @return false|mixed|null
     */
    public static function get_last_license_check() {

        return is_multisite() ? get_site_option( 'wpay_license_last_check', 0 ) : get_option( 'wpay_license_last_check', 0 );
    }

    /**
     * Get plugin license data.
     *
     * @param string|null $software_key The software key.
     *
     * @since 1.0.0
     * @return false|null|array{
     *     status: string,
     *     error_msg: string,
     *     license_status: string,
     *     expiration_timestamp: string,
     *     subscription_status: string,
     *     max_activation: numeric,
     *     max_staging_activation: numeric,
     *     activated_sites_count: numeric,
     *     activated_staging_sites_count: numeric,
     *     management_url: string,
     *     upgrade_url: string,
     * }
     */
    public static function get_license_data( $software_key = null ) {

        $license_data = is_multisite() ? get_site_option( 'wws_license_data', array() ) : get_option( 'wws_license_data', array() );

        if ( $software_key ) {
            return $license_data[ $software_key ] ?? array();
        }

        return $license_data;
    }

    /**
     * Update plugin license data.
     *
     * @param array|null $data The license data.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function update_license_data( $data ) {

        if ( null === $data ) {
            return is_multisite() ? delete_site_option( 'wws_license_data' ) : delete_option( 'wws_license_data' );
        }

        return is_multisite() ? update_site_option( 'wws_license_data', $data ) : update_option( 'wws_license_data', $data );
    }

    /**
     * Check plugin license status.
     *
     * @param null|string $which Either 'type' or 'interval'.
     *
     * @since 1.0.0
     * @return array{
     *     type: string,
     *     interval: string
     * } | string
     */
    public static function get_license_status_type( $which = null ) {

        $logger       = wc_get_logger();
        $license_data = self::get_license_data( 'WPAY' );

        $license_status = ( empty( self::get_plugin_license_key() ) || empty( self::get_plugin_license_email() ) )
            ? array(
                'type'     => 'no_license',
                'interval' => '0',
            ) : array(
                'type'     => 'active',
                'interval' => '0',
            );

        $last_check_date_time = gmdate( 'Y-m-d H:i:s', (int) self::get_last_license_check() );
        try {
            $now        = new WC_DateTime( 'now', new DateTimeZone( 'UTC' ) );
            $last_check = new WC_DateTime( $last_check_date_time, new DateTimeZone( 'UTC' ) );
        } catch ( Exception $e ) {
            $logger->error( $e->getMessage(), array( 'source' => 'woocommerce-wholesale-payments' ) );

            return $license_status;
        }
        if ( empty( self::get_plugin_license_key() ) || empty( self::get_plugin_license_email() ) ) { // no license.
            $days_after_check = $last_check->diff( $now )->days;
            $license_status   = array(
                'type' => 'no_license',
            );

            if ( $days_after_check < 7 ) {
                $license_status['interval'] = 'lt_7';
            } elseif ( $days_after_check < 14 ) {
                $license_status['interval'] = 'lt_14';
            } else {
                $license_status['interval'] = 'gte_14';
            }
        } elseif ( ! empty( $license_data['license_status'] ) && ! empty( $license_data['subscription_status'] ) &&
            'disabled' === $license_data['license_status'] ) { // disabled license.
            $days_after_check = $last_check->diff( $now )->days;
            $license_status   = array(
                'type' => "disabled-{$license_data['subscription_status']}",
            );
            if ( $days_after_check < 7 ) {
                $license_status['interval'] = 'lt_7';
            } elseif ( $days_after_check < 14 ) {
                $license_status['interval'] = 'lt_14';
            } else {
                $license_status['interval'] = 'gte_14';
            }
        } elseif ( ! empty( $license_data['expiration_timestamp'] ) ) {
            try {
                $license_expiration_date = new WC_DateTime( $license_data['expiration_timestamp'], new DateTimeZone( 'UTC' ) );
            } catch ( Exception $e ) {
                $logger->error( $e->getMessage(), array( 'source' => 'woocommerce-wholesale-payments' ) );

                return $license_status;
            }
            $expiry_days_diff = $license_expiration_date->diff( $now )->days;

            if ( ! empty( $license_data['license_status'] ) && 'expired' === $license_data['license_status'] ) { // expired.
                $license_status = array(
                    'type' => 'expired',
                );
                if ( $expiry_days_diff < 14 ) {
                    $license_status['interval'] = 'lt_14';
                } else {
                    $license_status['interval'] = 'gte_14';
                }
            } elseif ( isset( $license_data['subscription_status'] ) && 'pending-cancel' === $license_data['subscription_status'] &&
                $expiry_days_diff <= 14 ) { // pre-expiry.
                $license_status = array(
                    'type'     => 'pre_expiry',
                    'interval' => "$expiry_days_diff",
                );
            }
        }

        if ( ! empty( $which ) ) {
            return $license_status[ $which ] ?? $license_status['type'];
        }

        return $license_status;
    }
}
