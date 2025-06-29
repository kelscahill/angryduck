<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WWOF\Classes
 */

namespace RymeraWebCo\WWOF\Classes;

use RymeraWebCo\WWOF\Abstracts\Abstract_Class;
use RymeraWebCo\WWOF\CLI\Cli;
use RymeraWebCo\WWOF\Factories\Vite_App;
use RymeraWebCo\WWOF\Helpers\WWOF;
use RymeraWebCo\WWOF\Helpers\WWP;
use RymeraWebCo\WWOF\REST\Admin_Notices as Admin_Notices_REST;
use RymeraWebCo\WWOF\Classes\License_Manager;

/**
 * General wp-admin related functionalities and/or overrides.
 *
 * @since 3.0
 */
class WP_Admin extends Abstract_Class {

    /**
     * Holds boolean value for showing the getting started notice.
     *
     * @since 3.0
     * @var bool Whether to show getting started admin notice.
     */
    protected $show_getting_started_notice = false;

    /**
     * Enqueue admin scripts.
     *
     * @param string $hook_suffix The current admin page.
     *
     * @since 3.0
     * @return void
     */
    public function admin_enqueue_scripts( $hook_suffix = '' ) {

        $screen = get_current_screen();

        $hook_suffixes = array(
            'wholesale_page_wws-license-settings',
            'settings_page_wws-license-settings',
        );

        /***************************************************************************
         * Check specific pages
         ***************************************************************************
         *
         * We display the getting started notice on specific pages:
         * - WWS license page
         * - Products pages
         * - WooCommerce pages ( wc, products, analytics )
         * - Plugins page
         */
        if ( in_array( $hook_suffix, $hook_suffixes, true ) ||
            str_contains( $hook_suffix, 'woocommerce_page' ) ||
            'product' === $screen->post_type ||
            in_array( $screen->base, array( 'woocommerce', 'plugins' ), true ) ) {

            $this->show_getting_started_notice = true;

            $l10n = array(
                'adminNoticesUrl' => Admin_Notices_REST::instance()->api_url,
                'nonce'           => wp_create_nonce( 'wp_rest' ),
            );

            $script = new Vite_App(
                'wwof-getting-started',
                'src/vanilla/admin/notices/getting-started.ts',
                array( 'wp-i18n' ),
                $l10n
            );

            $script->enqueue();
        }
    }

    /**
     * Render getting started admin notice on specific admin pages.
     *
     * @since 3.0
     * @return void
     */
    public function getting_started_notice() {

        require_once WWOF::locate_admin_template_part( 'notices/getting-started.php' );
    }

    /**
     * Add plugin action links.
     *
     * @param array  $links Plugin action links.
     * @param string $file  Plugin file.
     *
     * @since 3.0
     * @return mixed
     */
    public function plugin_action_links( $links, $file ) {

        if ( plugin_basename( WWOF_PLUGIN_FILE ) === $file ) {
            if ( ! is_multisite() ) {
                if ( License_Manager::instance()->is_license_status( 'expired' ) ) {
                    $license_data      = License_Manager::instance()->get_wws_license_data( 'WWOF' );
                    $renew_license_url = add_query_arg(
                        array(
                            'utm_source'   => 'wwof',
                            'utm_medium'   => 'drm',
                            'utm_campaign' => 'wwofdrmpluginlinkrenew',
                        ),
                        $license_data['management_url']
                    );

                    $renew_license_link = '<a href="' . esc_url( $renew_license_url ) . '">' . __( 'Renew License', 'woocommerce-wholesale-order-form' ) . '</a>';
                    array_unshift( $links, $renew_license_link );
                }

                $license_link = '<a href="options-general.php?page=wws-license-settings&tab=wwof">' . __( 'License', 'woocommerce-wholesale-order-form' ) . '</a>';

                if ( WWP::is_v2() ) {
                    $license_link = '<a href="admin.php?page=wws-license-settings&tab=wwof">' . __( 'License', 'woocommerce-wholesale-order-form' ) . '</a>';
                }
                array_unshift( $links, $license_link );

            }

            $getting_started          = '<a href="https://wholesalesuiteplugin.com/kb/woocommerce-wholesale-order-form-getting-started-guide/?utm_source=wwof&utm_medium=kb&utm_campaign=WWOFGettingStartedGuide" target="_blank">' . __( 'Getting Started', 'woocommerce-wholesale-order-form' ) . '</a>';
            $links['getting_started'] = $getting_started;
        }

        return $links;
    }

    /**
     * Run actions and filters for the admin pages only.
     */
    public function run() {

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            try {
                \WP_CLI::add_command( Cli::COMMAND, Cli::class );
            } catch ( \Exception $exception ) {
                _doing_it_wrong( __METHOD__, esc_html( $exception->getMessage() ), esc_attr( WWOF::get_current_plugin_version() ) );
            }
        }

        if ( ! is_admin() ) {
            return;
        }

        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        add_action( 'admin_notices', array( $this, 'getting_started_notice' ) );
        add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
    }
}
