<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Classes
 */

namespace RymeraWebCo\WPay\Classes;

use DateTimeZone;
use Exception;
use RymeraWebCo\WPay\Abstracts\Abstract_Class;
use RymeraWebCo\WPay\Factories\Vite_App;
use RymeraWebCo\WPay\Helpers\Helper;
use RymeraWebCo\WPay\Helpers\License;
use RymeraWebCo\WPay\Traits\Singleton_Trait;
use WC_DateTime;

/**
 * License_Manager class.
 *
 * @since 1.0.0
 */
class License_Manager extends Abstract_Class {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @since 3.0.6
     * @var License_Manager $instance object
     */
    protected static $instance;

    /**
     * Render license settings tab markup.
     *
     * @since 1.0.0
     * @return void
     */
    public function license_settings_tab() {

        $_tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

        $wpay_license_settings_url = add_query_arg(
            array(
                'page' => 'wws-license-settings',
                'tab'  => 'wpay',
            ),
            admin_url( 'admin.php' )
        );

        printf(
            '<a href="%1$s" class="nav-tab%3$s">%2$s</a>',
            esc_url( $wpay_license_settings_url ),
            esc_html__( 'Wholesale Payments', 'woocommerce-wholesale-lead-capture' ),
            ( 'wpay' === $_tab ) ? ' nav-tab-active' : ''
        );
    }

    /**
     * Render license settings tab content markup.
     *
     * @since 1.0.0
     * @return void
     */
    public function license_settings_page() {

        Helper::locate_admin_template_part( 'license/license-form.php', true );
    }

    /**
     * Check whether to show license activation notice.
     *
     * @since 1.0.0
     * @return bool
     */
    private function show_license_notice() {

        global $pagenow;

        $skip_admin_pages = array(
            'update-core.php',
            'update.php',
        );

        return ( ! in_array( $pagenow, $skip_admin_pages, true ) ) &&
            'wws-license-settings' !== filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) &&
            License::get_license_notice_dismissed() !== 'yes' && 'active' !== License::get_license_status_type( 'type' );
    }

    /**
     * Enqueue scripts and styles.
     *
     * @param string $hook_suffix The current admin page.
     *
     * @since 1.0.0
     * @return void
     */
    public function admin_enqueue_scripts( $hook_suffix ) {

        $l10n    = array(
            'licenseApiUrl' => esc_url_raw( rest_url( 'wpay/v1/license-manager/license' ) ),
            'licenseEmail'  => License::get_plugin_license_email(),
            'licenseKey'    => License::get_plugin_license_key( true ),
            'i18n'          => array(
                'licenseEmail' => __( 'License Email', 'woocommerce-wholesale-payments' ),
                'licenseKey'   => __( 'License Key', 'woocommerce-wholesale-payments' ),
                'saveChanges'  => __( 'Save Changes', 'woocommerce-wholesale-payments' ),
                'notice'       => array(
                    'error'   => __( 'Error', 'woocommerce-wholesale-payments' ),
                    'success' => __( 'Success', 'woocommerce-wholesale-payments' ),
                ),
            ),
        );
        $enqueue = false;
        if ( 'wws-license-settings' === filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) &&
            'wpay' === filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) {
            $enqueue         = true;
            $l10n['appType'] = 'table';
        } elseif ( $this->show_license_notice() ) {
            $enqueue      = true;
            $license_data = License::get_license_data( 'WPAY' );

            $utm_args = array(
                'utm_source'   => 'wpay',
                'utm_medium'   => 'drm',
                'utm_campaign' => 'drm-notice',
            );

            $l10n['appType']         = 'notice';
            $l10n['wwsLoginUrl']     = ! empty( $license_data['management_url'] ) ? esc_url( add_query_arg( $utm_args, $license_data['management_url'] ) ) : null;
            $l10n['wwsUpgradeUrl']   = ! empty( $license_data['upgrade_url'] ) ? esc_url( add_query_arg( $utm_args, $license_data['upgrade_url'] ) ) : null;
            $l10n['wpayLandingPage'] = esc_url_raw( add_query_arg( $utm_args, 'https://wholesalesuiteplugin.com/wholesale-payments/' ) );
            $l10n['licenseUrl']      = esc_url_raw(
                add_query_arg(
                    array(
                        'page' => 'wws-license-settings',
                        'tab'  => 'wpay',
                    ),
                    admin_url( 'admin.php' )
                )
            );

            // get the notice type.
            $l10n['noticeType'] = License::get_license_status_type();

            $l10n['i18n']['notice'] = wp_parse_args(
                include WPAY_PLUGIN_DIR_PATH . 'includes/I18n/license-notice.php',
                $l10n['i18n']['notice']
            );

            if ( isset( $license_data['expiration_timestamp'] ) ) {
                try {
                    $l10n['licenseExpiryDate'] = $license_data['expiration_timestamp']
                        ? ( new WC_DateTime( $license_data['expiration_timestamp'], new DateTimeZone( 'UTC' ) ) )->format( 'jS F Y' )
                        : null;
                } catch ( Exception $e ) {
                    $l10n['licenseExpiryDate'] = gmdate( 'jS F Y', strtotime( $license_data['expiration_timestamp'] ) );
                }
            }

            $l10n['i18n']['saveChanges'] = __( 'Activate Key', 'woocommerce-wholesale-payments' );
        }

        if ( $enqueue ) {
            wp_add_inline_script( 'wpay-admin-l10n', 'window.wpayObj = lodash.merge(window.wpayObj, ' . wp_json_encode( Helper::vite_app_common_l10n( $l10n ) ) . ');' );
            $app = new Vite_App(
                'wpay-license-settings',
                'src/apps/admin/license/index.ts',
                array( 'wp-i18n', 'lodash' ),
                null
            );
            $app->enqueue();
        }
    }

    /**
     * Insert license activation notice HTML markup.
     *
     * @return void
     */
    public function license_activation_notice() {

        global $hook_suffix;

        if ( ! $this->show_license_notice() ) {
            return;
        }

        $is_wholesale_admin_page = str_contains( $hook_suffix, 'wholesale_page_' ) || str_contains( $hook_suffix, 'wholesale-suite' );
        printf(
            '<div class="notice notice-info%s"><div id="wpay-license-notice"></div><p style="margin: 0 auto; padding: 0; height: 0;">&nbsp;</p></div>',
            ! $is_wholesale_admin_page ? ' is-dismissible' : ''
        );
    }

    /**
     * Update license data.
     *
     * @param object $response The response object.
     * @param string $context  The context, either: 'check', 'activation', or 'update_data'.
     *
     * @return object
     */
    public function update_license_data( $response, $context = 'check' ) {

        if ( empty( $response ) ) {
            return (object) array(
                'status'    => 'fail',
                'error_msg' => __( 'Failed to activate license. Failed to connect to license server. Please contact plugin support.', 'woocommerce-wholesale-payments' ),
            );
        }

        if ( 'success' === $response->status && property_exists( $response, 'license_status' ) && 'active' === $response->license_status ) {
            License::update_license_expired( null );
            License::update_license_activated_status( 'yes' );
            if ( 'update_data' === $context ) {
                Helper::update_plugin_update_data( $response->software_update_data );
            }
            License::update_license_notice_dismissed( 'yes' );
        } else {
            License::update_license_activated_status( 'no' );
            if ( 'update_data' === $context && ! empty( $response->software_update_data ) ) {
                Helper::update_plugin_update_data( null );
            }

            if ( property_exists( $response, 'license_status' ) &&
                'expired' === $response->license_status && property_exists( $response, 'expiration_timestamp' ) ) {
                License::update_license_expired( $response->expiration_timestamp );
            } else {
                License::update_license_expired( null );
            }

            if ( 'update_data' !== $context ) {
                $this->maybe_clear_local_update_data();
            }
        }

        $wws_license_data = License::get_license_data();
        if ( property_exists( $response, 'license_status' ) &&
            in_array( $response->license_status, array( 'active', 'disabled', 'expired' ), true ) ) {
            $response = array_map(
                function ( $value ) {

                    if ( is_object( $value ) ) {
                        return $value;
                    }

                    return sanitize_text_field( $value );
                },
                (array) $response
            );

            // Update WWS license data.
            $wws_license_data = array_merge(
                $wws_license_data,
                array( 'WPAY' => $response )
            );
        } else {
            // Remove WWS license data.
            unset( $wws_license_data['WPAY'] );
        }

        License::update_license_data( $wws_license_data );

        return (object) $response;
    }

    /**
     * Maybe clear local update date.
     *
     * @since 1.0.0
     * @return void
     */
    private function maybe_clear_local_update_data() {

        $wp_site_transient = get_site_transient( 'update_plugins' );
        if ( $wp_site_transient ) {
            $wpay_plugin_basename = plugin_basename( WPAY_PLUGIN_FILE );

            if ( isset( $wp_site_transient->checked ) &&
                is_array( $wp_site_transient->checked ) &&
                array_key_exists( $wpay_plugin_basename, $wp_site_transient->checked ) ) {
                unset( $wp_site_transient->checked[ $wpay_plugin_basename ] );
            }

            if ( isset( $wp_site_transient->response ) &&
                is_array( $wp_site_transient->response ) &&
                array_key_exists( $wpay_plugin_basename, $wp_site_transient->response ) ) {
                unset( $wp_site_transient->response[ $wpay_plugin_basename ] );
            }

            set_site_transient( 'update_plugins', $wp_site_transient );

            wp_update_plugins();
        }
    }

    /**
     * Checks the license data status.
     *
     * @since 1.0.0
     * @return object[]
     */
    public function license_check() {

        $check_url = add_query_arg(
            array(
                'activation_email' => rawurlencode( License::get_plugin_license_email() ?? '' ),
                'license_key'      => License::get_plugin_license_key(),
                'site_url'         => home_url(),
                'software_key'     => 'WPAY',
                'multisite'        => is_multisite() ? 1 : 0,
            ),
            apply_filters( 'wpay_license_check_url', WWS_SLMW_SERVER_URL . '/wp-json/slmw/v1/license/activate' )
        );

        $args = apply_filters(
            'wpay_license_check_option',
            array(
                'timeout' => 10, // Seconds.
                'headers' => array( 'Accept' => 'application/json' ),
            )
        );

        $response = json_decode( wp_remote_retrieve_body( wp_remote_get( $check_url, $args ) ) );

        $license_data = array(
            'WPAY' => $this->update_license_data( $response ),
        );

        if ( 'success' === $response->status && property_exists( $response, 'license_status' ) ) {
            License::update_last_license_check();
        }

        $license_status = License::get_license_status_type();
        if ( 'active' !== $license_status['type'] ) {
            License::update_license_notice_dismissed( 'no' );
        }

        do_action( 'wpay_after_check_license', $license_data, License::get_plugin_license_email(), License::get_plugin_license_key() );

        return $license_data;
    }

    /**
     * Run the class
     *
     * @codeCoverageIgnore
     * @since 1.0.0
     */
    public function run() {

        if ( is_admin() ) {
            add_action( 'wws_action_license_settings_tab', array( $this, 'license_settings_tab' ), 20 );

            if ( is_multisite() ) {//phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
                // @todo: Add multisite support.
            } else {
                add_action( 'wws_license_check', array( $this, 'license_check' ) );
                add_action( 'wws_action_license_settings_wpay', array( $this, 'license_settings_page' ) );
                add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
                add_action( 'admin_notices', array( $this, 'license_activation_notice' ) );
            }
        }
    }
}
