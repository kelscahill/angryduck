<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Factories
 */

namespace RymeraWebCo\WPay\Factories;

use RymeraWebCo\WPay\Abstracts\Abstract_Class;
use RymeraWebCo\WPay\Helpers\Helper;

/**
 * Admin_Script class.
 *
 * @since 1.0.0
 */
class Admin_Script extends Abstract_Class {

    /**
     * Enqueue admin scripts.
     *
     * @since 1.0.0
     * @return void
     */
    public function admin_enqueue_scripts() {

        wp_register_style( 'wpay-admin-style', '', array(), Helper::get_plugin_data( 'Version' ) );
        wp_enqueue_style( 'wpay-admin-style' );
        wp_register_script( 'wpay-admin-l10n', '', array( 'lodash' ), Helper::get_plugin_data( 'Version' ), false );
        wp_enqueue_script( 'wpay-admin-l10n' );
        wp_localize_script( 'wpay-admin-l10n', 'wpayObj', array( 'pluginDirUrl' => plugins_url( '', WPAY_PLUGIN_FILE ) ) );
    }

    /**
     * Run the class.
     */
    public function run() {

        if ( ! is_admin() ) {
            return;
        }

        add_action( 'admin_init', array( $this, 'admin_enqueue_scripts' ), 1 );
    }
}
