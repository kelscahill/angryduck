<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WPay\Helpers
 */

namespace RymeraWebCo\WPay\Helpers;

use DateTimeZone;
use Exception;
use RymeraWebCo\WPay\REST\Rymera_Stripe;
use WC_DateTime;

/**
 * Helper methods class.
 *
 * @since 1.0.0
 */
class Helper {

    /**
     * Get plugin data.
     *
     * @param string|null $key       The plugin data key.
     * @param bool        $markup    If the returned data should have HTML markup applied. Default false.
     * @param bool        $translate If the returned data should be translated. Default false.
     *
     * @since 1.0.0
     * @return string[]|string
     */
    public static function get_plugin_data( $key = null, $markup = false, $translate = false ) {

        $plugin_data = get_plugin_data( WPAY_PLUGIN_FILE, $markup, $translate );

        if ( null !== $key ) {
            return $plugin_data[ $key ] ?? '';
        }

        return $plugin_data;
    }

    /**
     * App frontend and backend common JS app localization properties.
     *
     * @param array $merge Additional data to merge.
     *
     * @since 1.0
     * @return array
     */
    public static function vite_app_common_l10n( $merge = array() ) {

        global $allowedposttags;

        $allowed_tags  = array_keys( $allowedposttags );
        $allowed_attrs = array_keys( array_merge( ...array_values( $allowedposttags ) ) );

        $allowed_tags  = apply_filters( 'wpay_kses_allowed_tags', $allowed_tags );
        $allowed_attrs = apply_filters( 'wpay_kses_allowed_attrs', $allowed_attrs );

        $defaults = array(
            'allowedTags'        => ! empty( $allowed_tags ) ? $allowed_tags : array(),
            'allowedAttrs'       => ! empty( $allowed_attrs ) ? $allowed_attrs : array(),
            'rymeraStripeApiUrl' => Rymera_Stripe::instance()->api_url,
            'wpNonce'            => wp_create_nonce( 'wp_rest' ),
            'wcNonce'            => wp_create_nonce( 'wc_store_api' ),
        );

        return wp_parse_args( $merge, $defaults );
    }

    /**
     * Loads admin template.
     *
     * @param string $name Template name relative to `templates` directory.
     * @param bool   $load Whether to load the template or not.
     * @param bool   $once Whether to use require_once or require.
     *
     * @since 1.0.0
     * @return string
     */
    public static function locate_template( $name, $load = false, $once = true ) {

        //phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        $template = WPAY_PLUGIN_DIR_PATH . 'templates/' . rtrim( $name, '.php' ) . '.php';
        if ( ! file_exists( $template ) ) {
            return '';
        }

        if ( $load ) {
            if ( $once ) {
                require_once $template;
            } else {
                require $template;
            }
        }

        //phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
        return $template;
    }

    /**
     * Loads admin template.
     *
     * @param string $name Template name relative to `templates/admin` directory.
     * @param bool   $load Whether to load the template or not.
     * @param bool   $once Whether to use require_once or require.
     *
     * @since 1.0.0
     * @return string
     */
    public static function locate_admin_template( $name, $load = false, $once = true ) {

        return self::locate_template( "admin/$name", $load, $once );
    }

    /**
     * Loads admin template part.
     *
     * @param string $name Template name relative to `templates/admin/parts` directory.
     * @param bool   $load Whether to load the template or not.
     * @param bool   $once Whether to use require_once or require.
     *
     * @since 1.0.0
     * @return string
     */
    public static function locate_admin_template_part( $name, $load = false, $once = true ) {

        return self::locate_admin_template( "parts/$name", $load, $once );
    }

    /**
     * Loads frontend template.
     *
     * @param string $name Template name relative to `templates/front` directory.
     * @param bool   $load Whether to load the template or not.
     * @param bool   $once Whether to use require_once or require.
     *
     * @since 1.0.0
     * @return string
     */
    public static function locate_front_template( $name, $load = false, $once = true ) {

        return self::locate_template( "front/$name", $load, $once );
    }

    /**
     * Loads frontend template parts.
     *
     * @param string $name Template name relative to `templates/front/parts` directory.
     * @param bool   $load Whether to load the template or not.
     * @param bool   $once Whether to use require_once or require.
     *
     * @since 1.0.0
     * @return string
     */
    public static function locate_front_template_part( $name, $load = false, $once = true ) {

        return self::locate_front_template( "parts/$name", $load, $once );
    }

    /**
     * Parse local plugin .env file.
     *
     * @since 1.0.0
     * @return array
     */
    public static function parse_env() {

        $env = array();

        if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) || 'local' !== WP_ENVIRONMENT_TYPE ) {
            return $env;
        }

        /**************************************************************************
         * Check environment variables
         ***************************************************************************
         *
         * Let's check if we can find environment variables from a `.env` file
         */
        if ( file_exists( WPAY_PLUGIN_DIR_PATH . '.env' ) ) {
            if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
                require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
            }
            $wpfsd   = new \WP_Filesystem_Direct( false );
            $dot_env = $wpfsd->get_contents( WPAY_PLUGIN_DIR_PATH . '.env' );
            if ( $dot_env ) {
                $dot_env = preg_split( '/\\r\\n|\\r|\\n/', $dot_env );
                $dot_env = is_array( $dot_env ) ? array_filter( $dot_env ) : array();
                foreach ( $dot_env as $line ) {
                    $line = explode( '=', $line );
                    if ( 2 === count( $line ) ) {
                        $env[ $line[0] ] = $line[1];
                    }
                }
            }
        }

        return $env;
    }

    /**
     * Wrapper for `site_url()` function that simply strips the trailing slash.
     *
     * @since 1.0.0
     * @return string The site URL without trailing slash.
     */
    public static function get_site_url() {

        return untrailingslashit( site_url() );
    }

    /**
     * Check if the current request is an AJAX request or running on CLI.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function is_ajax_or_cli() {

        return wp_doing_ajax() || defined( 'WP_CLI' ) || php_sapi_name() === 'cli';
    }

    /**
     * Generate a unique id.
     *
     * @param array|null $data The whole old data array.
     *
     * @since 1.0.0
     * @return string
     */
    public static function generate_id( $data = null ) {

        try {
            $id = random_bytes( 5 );
            $id = mb_substr( bin2hex( $id ), 0, 5 );
        } catch ( Exception $e ) {
            if ( empty( $data ) ) {
                $data = array( wp_generate_password() => wp_generate_password() );
            }
            $id = mb_substr( md5( wp_json_encode( $data ) ), 0, 5 );
        }

        return $id;
    }

    /**
     * Checks required plugins if they are active.
     *
     * @return array List of plugins that are not active.
     */
    public static function missing_required_plugins() {

        $i       = 0;
        $plugins = array();

        $required_plugins = array(
            'woocommerce/woocommerce.php',
            'woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php',
        );

        foreach ( $required_plugins as $plugin ) {
            if ( ! is_plugin_active( $plugin ) ) {
                $plugin_name                  = explode( '/', $plugin );
                $plugins[ $i ]['plugin-key']  = $plugin_name[0];
                $plugins[ $i ]['plugin-base'] = $plugin;
                $plugins[ $i ]['plugin-name'] = str_replace(
                    'Woocommerce',
                    'WooCommerce',
                    ucwords( str_replace( '-', ' ', $plugin_name[0] ) )
                );
            }

            ++$i;
        }

        return $plugins;
    }

    /**
     * Get plugin update data.
     *
     * @since 1.0.0
     * @return mixed
     */
    public static function get_plugin_update_data() {

        return is_multisite() ? get_site_option( 'wpay_update_data', array() ) : get_option( 'wpay_update_data', array() );
    }

    /**
     * Update plugin update data.
     *
     * @param array|null $data The update data.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function update_plugin_update_data( $data ) {

        if ( null === $data ) {
            return is_multisite() ? delete_site_option( 'wpay_update_data' ) : delete_option( 'wpay_update_data' );
        }

        return is_multisite() ? update_site_option( 'wpay_update_data', $data ) : update_option( 'wpay_update_data', $data );
    }

    /**
     * Get flag if we are fetching plugin update data.
     *
     * @since 1.0.0
     * @return false|mixed|null
     */
    public static function get_retrieving_update_data() {

        return is_multisite() ? get_site_option( 'wpay_retrieving_update_data', false ) : get_option( 'wpay_retrieving_update_data', false );
    }

    /**
     * Update flag if we are fetching plugin update data.
     *
     * @param bool|null $retrieving Whether we are fetching plugin update data.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function update_retrieving_update_data( $retrieving ) {

        if ( null === $retrieving ) {
            return is_multisite() ? delete_site_option( 'wpay_retrieving_update_data' ) : delete_option( 'wpay_retrieving_update_data' );
        }

        return is_multisite() ? update_site_option( 'wpay_retrieving_update_data', $retrieving ) : update_option( 'wpay_retrieving_update_data', $retrieving );
    }

    /**
     * Get plugin static ping file.
     *
     * @since 1.0.0
     * @return string
     */
    public static function get_static_ping_file() {

        return WWS_SLMW_SERVER_URL . '/WPAY.json';
    }

    /**
     * Get plugin installed version.
     *
     * @since 1.0.0
     * @return false|mixed|null
     */
    public static function get_installed_plugin_version() {

        return is_multisite() ? get_site_option( WPAY_INSTALLED_VERSION, '' ) : get_option( WPAY_INSTALLED_VERSION, '' );
    }

    /**
     * Update installed plugin version.
     *
     * @param string $version The installed plugin version.
     *
     * @since 1.0.0
     * @return false|mixed|null
     */
    public static function update_installed_plugin_version( $version ) {

        if ( null === $version ) {
            return is_multisite() ? delete_site_option( WPAY_INSTALLED_VERSION ) : delete_option( WPAY_INSTALLED_VERSION );
        }

        return is_multisite() ? update_site_option( WPAY_INSTALLED_VERSION, $version ) : update_option( WPAY_INSTALLED_VERSION, $version );
    }

    /**
     * Get getting started notice dismiss flag.
     *
     * @since 1.0.0
     * @return false|mixed|null
     */
    public static function get_getting_started_notice_dismiss() {

        return is_multisite() ? get_site_option( 'wpay_getting_started_notice_dismiss', 'no' ) : get_option( 'wpay_getting_started_notice_dismiss', 'no' );
    }

    /**
     * Update getting started notice dismiss flag.
     *
     * @param string $dismiss The getting started notice dismiss flag. Either 'yes' or 'no'.
     *
     * @since 1.0.0
     * @return string|null|false
     */
    public static function update_getting_started_notice_dismiss( $dismiss ) {

        if ( null === $dismiss ) {
            return is_multisite() ? delete_site_option( 'wpay_getting_started_notice_dismiss' ) : delete_option( 'wpay_getting_started_notice_dismiss' );
        }

        return is_multisite() ? update_site_option( 'wpay_getting_started_notice_dismiss', $dismiss ) : update_option( 'wpay_getting_started_notice_dismiss', $dismiss );
    }
}
