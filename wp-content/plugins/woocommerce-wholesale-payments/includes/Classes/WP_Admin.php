<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Classes
 */

namespace RymeraWebCo\WPay\Classes;

use RymeraWebCo\WPay\Abstracts\Abstract_Class;
use RymeraWebCo\WPay\Helpers\Helper;
use RymeraWebCo\WPay\Helpers\License;

/**
 * General wp-admin related functionalities and/or overrides.
 *
 * @since 1.0.0
 */
class WP_Admin extends Abstract_Class {

    /**
     * Add plugin action links.
     *
     * @param array  $raw_links Plugin action links.
     * @param string $file      Plugin file.
     *
     * @since 3.0
     * @return mixed
     */
    public function plugin_action_links( $raw_links, $file ) {

        if ( plugin_basename( WPAY_PLUGIN_FILE ) === $file ) {
            $settings_link = '<a href="admin.php?page=wholesale-settings&tab=wpay">' . __( 'Settings', 'woocommerce-wholesale-prices' ) . '</a>';
            $links         = array(
                'settings-link' => $settings_link,
            );

            if ( ! is_multisite() ) {
                if ( License::get_license_status_type( 'type' ) === 'expired' ) {
                    $license_data      = License::get_license_data( 'WPAY' );
                    $renew_license_url = add_query_arg(
                        array(
                            'utm_source'   => 'wpay',
                            'utm_medium'   => 'drm',
                            'utm_campaign' => 'wpaydrmpluginlinkrenew',
                        ),
                        $license_data['management_url']
                    );

                    $renew_license_link = '<a href="' . esc_url( $renew_license_url ) . '">' . __( 'Renew License', 'woocommerce-wholesale-payments' ) . '</a>';
                    array_unshift( $raw_links, $renew_license_link );
                } elseif ( License::get_license_status_type( 'type' ) === 'no_license' ) {
                    $license_link = '<a href="admin.php?page=wws-license-settings&tab=wpay">' . __( 'License', 'woocommerce-wholesale-payments' ) . '</a>';
                }

                if ( isset( $license_link ) ) {
                    $links['license-link'] = $license_link;
                }
            }

            $links['payment-plans'] = '<a href="admin.php?page=wpay&subpage=payment_plans">' . __( 'Payment Plans', 'woocommerce-wholesale-payments' ) . '</a>';

            $links = array_merge( $links, $raw_links );

            $raw_links = $links;
        }

        return $raw_links;
    }

    /**
     * Enqueue admin scripts.
     *
     * @param string $hook The current admin page.
     *
     * @since 1.0.0
     * @return void
     */
    public function admin_enqueue_scripts( $hook ) {

        if ( Helper::get_getting_started_notice_dismiss() !== 'yes' ) {
            $style = <<<CSS
.notice.wpay-getting-started p {
    font-size: 16px;
}
.notice.wpay-getting-started p .wws-logo {
    max-width: 245px;
}
.notice.wpay-getting-started p a {
    -webkit-border-radius: 5px;
    background-color: #F7941D;
    border-color: #A85E06;
    border-radius: 5px;
    border-style: solid;
    border-width: 1px 1px 1px 1px;
    color: #FFF;
    display: inline-block;
    font-family: "Lato", 'Lato', Helvetica, Arial, Sans-serif;
    font-size: 18px;
    font-weight: bold;
    padding: 10px 20px;
    text-decoration: none;
}
CSS;

            wp_add_inline_style( 'wpay-admin-style', $style );

            $nonce = wp_create_nonce( 'wpay_dismiss_getting_started_notice' );
            $js    = <<<JS
(function($) {
  const wpayGettingStarted = {
    init: function() {
      $(document).on('click', '.wpay-getting-started .notice-dismiss', this.dismissNotice)
    },
    dismissNotice: function() {
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action: 'wpay_dismiss_getting_started_notice',
          nonce: "$nonce"
        }
      })
    }
  }
  
  $(function () {
    wpayGettingStarted.init()
  })
})(jQuery)
JS;

            wp_enqueue_script( 'jquery' );
            wp_add_inline_script( 'jquery', $js );
        }
    }

    /**
     * Dismiss getting started notice.
     *
     * @since 1.0.0
     * @return void
     */
    public function wpay_dismiss_getting_started_notice() {

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpay_dismiss_getting_started_notice' ) ) {
            return;
        }

        Helper::update_getting_started_notice_dismiss( 'yes' );
    }

    /**
     * Display getting started notice.
     *
     * @since 1.0.0
     * @return void
     */
    public function getting_started() {

        if ( Helper::get_getting_started_notice_dismiss() === 'yes' ) {
            return;
        }

        Helper::locate_admin_template_part( 'getting-started', true );
    }

    /**
     * Run the class
     *
     * @codeCoverageIgnore
     * @since 1.0.0
     */
    public function run() {

        if ( ! is_admin() ) {
            return;
        }

        add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
        add_action( 'admin_notices', array( $this, 'getting_started' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        add_action( 'wp_ajax_wpay_dismiss_getting_started_notice', array( $this, 'wpay_dismiss_getting_started_notice' ) );
    }
}
