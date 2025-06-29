<?php
/**
 * Author: Rymera Web Co
 *
 * @package RymeraWebCo\WPay\Classes
 */

namespace RymeraWebCo\WPay\Classes;

use RymeraWebCo\WPay\Abstracts\Admin_Page;
use RymeraWebCo\WPay\Factories\Vite_App;
use RymeraWebCo\WPay\Helpers\Helper;
use RymeraWebCo\WPay\Helpers\License;
use RymeraWebCo\WPay\Helpers\RCS;
use RymeraWebCo\WPay\Helpers\Stripe;
use RymeraWebCo\WPay\Helpers\WPay;
use RymeraWebCo\WPay\REST\Payment_Plan;
use RymeraWebCo\WPay\REST\Rymera_Stripe;
use RymeraWebCo\WPay\REST\Invoice_Lists;
use RymeraWebCo\WPay\Traits\Singleton_Trait;

/**
 * Responsible for loading the wp-admin related functionalities
 *
 * @since 1.0.0
 */
class Settings_Page extends Admin_Page {

    use Singleton_Trait;

    /**
     * Admin menu page slug.
     *
     * @since 1.0.0
     */
    const MENU_SLUG = 'wpay';

    /**
     * Singleton instance.
     *
     * @var Settings_Page
     */
    protected static $instance;

    /**
     * Enqueue admin scripts.
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_scripts() {

        global $wc_wholesale_prices;

        $admin_base_url_args = array( 'page' => self::MENU_SLUG );
        if ( defined( 'HMR_DEV' ) && HMR_DEV ) {
            $admin_base_url_args['hmr'] = 'wpay';
        }

        $action  = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $message = filter_input( INPUT_GET, 'msg', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

        $registered_wholesale_roles = $wc_wholesale_prices->wwp_wholesale_roles->getAllRegisteredWholesaleRoles();

        $wholesale_roles = array();
        if ( ! empty( $registered_wholesale_roles ) ) {
            foreach ( $registered_wholesale_roles as $registered_wholesale_role => $role_data ) {
                $wholesale_roles[] = array(
                    'value' => $registered_wholesale_role,
                    'label' => $role_data['roleName'],
                );
            }
        }

        [
            'management_url' => $management_url,
            'upgrade_url'    => $upgrade_url,
        ] = wp_parse_args(
            License::get_license_data( 'WPAY' ),
            array(
                'management_url' => null,
                'upgrade_url'    => null,
            )
        );

        $utm_args = array(
            'utm_source'   => 'wpay',
            'utm_medium'   => 'drm',
            'utm_campaign' => 'drm-notice',
        );

        $management_url = $management_url ? esc_url_raw( add_query_arg( $utm_args, $management_url ) ) : '';
        $upgrade_url    = $upgrade_url ? esc_url_raw( add_query_arg( $utm_args, $upgrade_url ) ) : '';

        $l10n = Helper::vite_app_common_l10n(
            array(
                'adminBaseUrl'              => esc_url_raw(
                    add_query_arg( $admin_base_url_args, admin_url( 'admin.php', 'relative' ) )
                ),
                'i18n'                      => require_once WPAY_PLUGIN_DIR_PATH . 'includes/I18n/settings-page.php',
                'paymentPlanApiUrl'         => esc_url_raw( rest_url( Payment_Plan::instance()->namespace . '/' . Payment_Plan::instance()->rest_base ) ),
                'usersApiUrl'               => esc_url_raw( rest_url( '/wp/v2/users' ) ),
                'accountNumber'             => WPay::get_account_number(),
                'preConfiguredPaymentPlans' => require_once WPAY_PLUGIN_DIR_PATH . 'includes/data/preconfigured-payment-plans.php',
                'currencySymbol'            => get_woocommerce_currency_symbol(),
                //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
                'errorMessage'              => 'error' === $action && ! empty( $message ) ? esc_html( base64_decode( $message ) ) : null,
                'liveAccessToken'           => WPay::get_masked_token( 'access_token', false ),
                'livePublishableKey'        => WPay::get_masked_token( 'publishable_key', false ),
                'testAccessToken'           => WPay::get_masked_token( 'access_token', true ),
                'testPublishableKey'        => WPay::get_masked_token( 'publishable_key', true ),
                'wholesaleRoles'            => $wholesale_roles,
                'licenseStatusType'         => License::get_license_status_type(),
                'managementUrl'             => $management_url,
                'upgradeUrl'                => $upgrade_url,
                'licensePageUrl'            => esc_url_raw( admin_url( 'admin.php?page=wws-license-settings&tab=wpay' ) ),
                'licenseApiUrl'             => esc_url_raw( rest_url( 'wpay/v1/license-manager/license' ) ),
                'apiMode'                   => WPay::get_api_mode(),
                'apiModeDisabled'           => defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === WP_ENVIRONMENT_TYPE,
                'enabledAdvDueDays'         => WPay::maybe_enable_advanced_payment_plan_due_days(),
                'invoicesApiUrl'            => esc_url_raw( rest_url( Invoice_Lists::instance()->namespace . '/' . Invoice_Lists::instance()->rest_base ) ),
            )
        );

        wp_add_inline_script(
            'wpay-admin-l10n',
            'window.wpayObj = lodash.merge(window.wpayObj, ' . wp_json_encode( $l10n ) .
            ', {cypressRcsNonce: (window.Cypress ? "' . wp_create_nonce( 'rcs-connect' ) . '" : null)});'
        );
        $app = new Vite_App(
            self::MENU_SLUG,
            'src/apps/admin/settings/index.ts',
            array(
                'wp-i18n',
                'wp-url',
                'wp-hooks',
                'wp-html-entities',
                'lodash',
            ),
            null
        );

        $app->enqueue();
    }

    /**
     * Stripe `return_url` handler.
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_stripe_refresh_return_url() {

        /***************************************************************************
         * Ignore if running on CLI
         ***************************************************************************
         *
         * We bail early to ignore this hook handler if we are running on CLI.
         */
        if ( Helper::is_ajax_or_cli() ) {
            return;
        }

        $definition = array(
            'page'           => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'subpage'        => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'connect_nonce'  => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'account_number' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        );

        $defaults = array(
            'page'           => '',
            'subpage'        => '',
            'connect_nonce'  => '',
            'account_number' => '',
        );

        //phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $params = wp_parse_args( filter_input_array( INPUT_GET, $definition ), $defaults );

        /***************************************************************************
         * Bail early if not on our admin page
         ***************************************************************************
         *
         * We bail early to ignore this hook handler if we are not on our admin page.
         */
        if ( empty( $params['page'] ) || self::MENU_SLUG !== $params['page'] ) {
            return;
        }

        if ( 'stripe' === $params['subpage'] && ! empty( $params['connect_nonce'] ) && ! empty( $params['account_number'] ) &&
            wp_verify_nonce( $params['connect_nonce'], 'rcs-connect' ) ) {

            $data = RCS::instance()->send_get_request( 'retrieve-credentials', array( 'account_number' => $params['account_number'] ) );
            if ( is_wp_error( $data ) ) {
                wp_safe_redirect(
                    esc_url_raw(
                        add_query_arg(
                            array(
                                'action' => 'error',
                                //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                                'msg'    => base64_encode( $data->get_error_message() ),
                            ),
                            WPay::get_plugin_admin_url()
                        )
                    )
                );
                exit;
            }

            WPay::save_credentials( $data );

            $webhook_url    = rest_url( Rymera_Stripe::instance()->namespace . '/' . Rymera_Stripe::instance()->rest_base . '/webhook' );
            $webhooks       = Stripe::instance()->get_webhooks();
            $webhook_active = array();
            if ( ! is_wp_error( $webhooks ) && isset( $webhooks['data'] ) ) {
                $webhook_active = wp_filter_object_list(
                    $webhooks['data'],
                    array(
                        'status' => 'enabled',
                        'url'    => $webhook_url,
                    )
                );
            }

            if ( empty( $webhook_active ) ) {
                $create_webhook = Stripe::instance()->create_webhook( $webhook_url, WPay::get_webhook_events() );
                if ( is_wp_error( $create_webhook ) ) {
                    wp_safe_redirect(
                        esc_url_raw(
                            add_query_arg(
                                array(
                                    'action' => 'error',
                                    //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                                    'msg'    => base64_encode( $create_webhook->get_error_message() ),
                                ),
                                WPay::get_plugin_admin_url()
                            )
                        )
                    );
                    exit;
                }
                WPay::save_webhook_secret( $create_webhook['secret'] );
            }

            wp_safe_redirect( WPay::get_plugin_admin_url( null ) );
            exit;
        }
    }

    /**
     * Run admin page hooks.
     *
     * @since 1.0.0
     * @return void
     */
    public function run() {

        add_action( 'admin_init', array( $this, 'handle_stripe_refresh_return_url' ) );
        parent::run();
    }

    /**
     * Get the admin menu priority.
     *
     * @since 1.0.2
     * @return int
     */
    protected function get_priority() {

        return 100;
    }

    /**
     * Initialize the admin page.
     *
     * @since 1.0.2
     * @return void
     */
    protected function init() {
        $this->page_title  = __( 'Payments', 'woocommerce-wholesale-payments' );
        $this->menu_title  = __( 'Payments', 'woocommerce-wholesale-payments' );
        $this->capability  = 'manage_woocommerce';
        $this->menu_slug   = self::MENU_SLUG;
        $this->template    = 'settings-page.php';
        $this->icon        = '';
        $this->position    = 4;
        $this->parent_slug = 'wholesale-suite';
    }
}
