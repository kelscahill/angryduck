<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Classes
 */

namespace RymeraWebCo\WPay\Classes;

use RymeraWebCo\WPay\Abstracts\Abstract_Update;
use RymeraWebCo\WPay\Helpers\Helper;
use RymeraWebCo\WPay\Helpers\License;
use RymeraWebCo\WPay\Traits\Singleton_Trait;
use RymeraWebCo\WPay\Helpers\WPAY_Invoices;
use RymeraWebCo\WPay\Classes\Cron;

/**
 * Update_Manager class.
 *
 * @since 1.0.0
 */
class Update_Manager extends Abstract_Update {

    use Singleton_Trait;

    /**
     * Holds the class instance object
     *
     * @since 3.0.6
     * @var Update_Manager $instance object
     */
    protected static $instance;

    /**
     * Ping plugin for new version. Ping static file first, if indeed new version is available, trigger update data
     * request.
     *
     * @param bool $force Flag to determine whether to "forcefully" fetch the latest update data from the server.
     *
     * @access public
     * @since  1.19.4 We added new parameter $force. This will serve as a flag if we are going to "forcefully" fetch
     *         the latest update data from the server.
     *
     * @since  1.11
     */
    private function ping_for_new_version( $force = false ) {

        $license_activated = License::get_license_activated_status();

        if ( 'yes' !== $license_activated ) {

            Helper::update_plugin_update_data( null );

            return;
        }

        if ( 'yes' === Helper::get_retrieving_update_data() ) {
            return;
        }

        /***************************************************************************
         * Check for saved update data
         ***************************************************************************
         *
         * @since 1.19.4
         * Only attempt to get the existing saved update data when the operation is not forced.
         * Else, if it is forced, we ignore the existing update data if any
         * and forcefully fetch the latest update data from our server.
         */
        if ( ! $force ) {
            $update_data = Helper::get_plugin_update_data();
        } else {
            $update_data = null;
        }

        /***************************************************************************
         * Ping JSON file
         ***************************************************************************
         *
         * Even if the update data is still valid, we still go ahead and do static
         * json file ping. The reason is on WooCommerce 3.3.x , it seems WooCommerce
         * do not regenerate the download url every time you change the downloadable
         * zip file on WooCommerce store. The side effect is, the download url is
         * still valid, points to the latest zip file, but the update info could be
         * outdated coz we check that if the download url is still valid, we don't
         * check for update info, and since the download url will always be valid
         * even after subsequent release of the plugin coz WooCommerce is reusing
         * the url now then there will be a case our update info is outdated.
         * So that is why we still need to check the static json file, even if
         * update info is still valid.
         */
        $option = apply_filters(
            'wpay_plugin_new_version_ping_options',
            array(
                'timeout' => 10, // seconds coz only static json file ping.
                'headers' => array( 'Accept' => 'application/json' ),
            )
        );

        $response = wp_remote_retrieve_body( wp_remote_get( apply_filters( 'wpay_plugin_new_version_ping_url', Helper::get_static_ping_file() ), $option ) );

        if ( ! empty( $response ) ) {

            $response = json_decode( $response );

            if ( ! empty( $response ) && property_exists( $response, 'version' ) ) {

                $installed_version = Helper::get_installed_plugin_version();

                if ( ( ! $update_data && version_compare( $response->version, $installed_version, '>' ) ) ||
                    ( $update_data && version_compare( $response->version, $update_data->latest_version, '>' ) ) ) {

                    Helper::update_retrieving_update_data( 'yes' );

                    // Fetch software product update data.
                    $this->fetch_software_product_update_data( License::get_plugin_license_email(), License::get_plugin_license_key(), home_url() );

                    Helper::update_retrieving_update_data( 'no' );
                }
            }
        }
    }

    /**
     * Fetch software product update data.
     *
     * @param string $activation_email Activation email.
     * @param string $license_key      License key.
     * @param string $site_url         Site url.
     *
     * @since  1.11
     * @access public
     */
    private function fetch_software_product_update_data( $activation_email, $license_key, $site_url ) {

        $update_check_url = add_query_arg(
            array(
                'activation_email' => rawurlencode( $activation_email ?? '' ),
                'license_key'      => $license_key,
                'site_url'         => $site_url,
                'software_key'     => 'WPAY',
                'multisite'        => is_multisite() ? 1 : 0,
            ),
            apply_filters(
                'wpay_software_product_update_data_url',
                WWS_SLMW_SERVER_URL . '/wp-admin/admin-ajax.php?action=slmw_get_update_data'
            )
        );

        $option = apply_filters(
            'wpay_software_product_update_data_options',
            array(
                'timeout' => 30,
                // seconds for worst case the server is choked and takes little longer to get update data ( this is an ajax end point ).
                'headers' => array( 'Accept' => 'application/json' ),
            )
        );

        $result = wp_remote_retrieve_body( wp_remote_get( $update_check_url, $option ) );

        if ( ! empty( $result ) ) {

            $result = json_decode( $result );

            if ( ! empty( $result ) && 'success' === $result->status && ! empty( $result->software_update_data ) ) {

                Helper::update_plugin_update_data( $result->software_update_data );
            } else {

                Helper::update_plugin_update_data( null );

                if ( ! empty( $result ) && 'fail' === $result->status && isset( $result->error_key ) &&
                    in_array( $result->error_key, array( 'invalid_license', 'expired_license' ), true ) ) {

                    // Invalid license.
                    License::update_license_activated_status( 'no' );

                    // Check if license is expired.
                    if ( 'expired_license' === $result->error_key ) {
                        License::update_license_expired( $result->expiration_timestamp );
                    } else {
                        License::update_license_expired( null );
                    }
                }
            }

            do_action( 'wpay_software_product_update_data', $result, $activation_email, $license_key );

        }
    }

    /**
     * When WordPress fetch 'update_plugins' transient ( Which holds various data regarding plugins, including which
     * have updates ), we inject our plugin update data in, if any. It is saved on wpay_update_data option. It is
     * important we dont delete this option until the plugin have successfully updated. The reason is we are hooking (
     * and we should do it this way ), on transient read. So if we delete this option on first transient read, then
     * subsequent read will not include our plugin update data.
     *
     * It also checks the validity of the update url. There could be edge case where we stored the update data locally
     * as an option, then later on the store, the product was deleted or any action occurred that would deem the update
     * data invalid. So we check if update url is still valid, if not, we remove the locally stored update data.
     *
     * @since  1.0.0
     * Refactor codebase to adapt being called on set_site_transient function.
     * We don't need to check for software update data validity as its already been checked on ping_for_new_version
     * and this function is immediately called right after that.
     * @access public
     *
     * @return object|bool Filtered plugin updates data.
     */
    private function inject_plugin_update() {

        if ( 'yes' !== License::get_license_activated_status() ) {
            return false;
        }

        $software_update_data = Helper::get_plugin_update_data();

        if ( $software_update_data ) {

            $update = new \stdClass();

            $update->id                   = $software_update_data->download_id;
            $update->slug                 = 'woocommerce-wholesale-payments';
            $update->plugin               = plugin_basename( WPAY_PLUGIN_FILE );
            $update->new_version          = $software_update_data->latest_version;
            $update->version              = $software_update_data->latest_version;
            $update->url                  = WWS_SLMW_SERVER_URL;
            $update->package              = $software_update_data->download_url;
            $update->tested               = $software_update_data->tested_up_to;
            $update->{'update-supported'} = true;
            $update->update               = false;
            $update->icons                = array(
                '1x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/icon-128x128.jpg',
                '2x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/icon-256x256.jpg',
                'default' => 'https://ps.w.org/woocommerce-wholesale-prices/assets/icon-256x256.jpg',
            );

            $update->banners = array(
                '1x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/banner-772x250.jpg',
                '2x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/banner-1544x500.jpg',
                'default' => 'https://ps.w.org/woocommerce-wholesale-prices/assets/banner-1544x500.jpg',
            );

            return $update;
        }

        return false;
    }

    /**
     * Inject plugin update info to plugin update details page.
     * Note, this is only triggered when there is a new update and the "View version <new version here> details" link
     * is clicked. In short, the pure purpose for this is to provide details and info the update info popup.
     *
     * @param false|object|array $result The result object or array. Default false.
     * @param string             $action The type of information being requested from the Plugin Install API.
     * @param object             $args   Plugin API arguments.
     *
     * @since  1.11
     * @access public
     *
     * @return false|object|array Plugin update info.
     */
    public function inject_plugin_update_info( $result, $action, $args ) {

        $license_activated = License::get_license_activated_status();

        if ( 'yes' === $license_activated && 'plugin_information' === $action && isset( $args->slug ) && 'woocommerce-wholesale-payments' === $args->slug ) {

            $software_update_data = Helper::get_plugin_update_data();

            if ( $software_update_data ) {

                $update_info = new \StdClass();

                $update_info->name                 = 'WooCommerce Wholesale Payments';
                $update_info->slug                 = 'woocommerce-wholesale-payments';
                $update_info->version              = $software_update_data->latest_version;
                $update_info->tested               = $software_update_data->tested_up_to;
                $update_info->last_updated         = $software_update_data->last_updated;
                $update_info->homepage             = $software_update_data->home_page;
                $update_info->author               = sprintf( '<a href="%s" target="_blank">%s</a>', $software_update_data->author_url, $software_update_data->author );
                $update_info->download_link        = $software_update_data->download_url;
                $update_info->{'update-supported'} = true;
                $update_info->sections             = array(
                    'description'  => $software_update_data->description,
                    'installation' => $software_update_data->installation,
                    'changelog'    => $software_update_data->changelog,
                    'support'      => $software_update_data->support,
                );

                $update_info->icons = array(
                    '1x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/icon-128x128.jpg',
                    '2x'      => 'https://ps.w.org/woocommerce-wholesale-prices/assets/icon-256x256.jpg',
                    'default' => 'https://ps.w.org/woocommerce-wholesale-prices/assets/icon-256x256.jpg',
                );

                $update_info->banners = array(
                    'low'  => 'https://ps.w.org/woocommerce-wholesale-prices/assets/banner-772x250.jpg',
                    'high' => 'https://ps.w.org/woocommerce-wholesale-prices/assets/banner-1544x500.jpg',
                );

                return $update_info;
            }
        }

        return $result;
    }

    /**
     * Maybe update plugin.
     *
     * @param array|false $update      The update object. Default false.
     * @param array       $plugin_data Plugin data.
     * @param string      $plugin_file Plugin file.
     *
     * @since 1.0.2
     * @return array|false
     */
    public function maybe_update_plugin( $update, $plugin_data, $plugin_file ) {

        if ( plugin_basename( WPAY_PLUGIN_FILE ) === $plugin_file ) {
            $this->ping_for_new_version( (bool) filter_input( INPUT_GET, 'force-check', FILTER_VALIDATE_INT ) );
            $update = $this->inject_plugin_update();
        }

        return $update;
    }

    /**
     * Plugin updated hook.
     *
     * @since 1.0.5.1
     * @return void
     */
    public function plugin_updated_hook() {
        $installed_version = Helper::get_installed_plugin_version();

        // Get option to check if update has been run.
        $update_run = get_option( 'wpay_update_run', false );

        // If update has been run, return.
        if ( $update_run ) {
            return;
        }

        // Check if plugin version is different from the installed version.
        if ( version_compare( $installed_version, '1.0.5', '>=' ) ) {
            /***************************************************************************
            * Create invoice table
            ***************************************************************************
            *
            * Create invoice table on plugin update.
            */
            WPAY_Invoices::create_invoice_table();

            /***************************************************************************
            * Schedule cron job
            ***************************************************************************
            *
            * Schedule cron job on plugin update.
            */
            Cron::schedule_cron_job();

            // Update option to check if update has been run.
            update_option( 'wpay_update_run', true );
        }
    }

    /**
     * Run plugin update actions.
     *
     * @since 3.0.0.1
     * @return void
     */
    public function actions() {
        add_action( 'init', array( $this, 'plugin_updated_hook' ), 1 );
        add_filter( 'update_plugins_wholesalesuiteplugin.com', array( $this, 'maybe_update_plugin' ), 10, 3 );
        add_filter( 'plugins_api', array( $this, 'inject_plugin_update_info' ), 10, 3 );
    }
}
