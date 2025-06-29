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
 * Frontend_Script class.
 *
 * @since 1.0.2
 */
class Frontend_Script extends Abstract_Class {

    /**
     * Enqueue admin scripts.
     *
     * @since 1.0.2
     * @return void
     */
    public function frontend_enqueue_scripts() {
        wp_register_script(
            'wpay-frontend-scripts',
            plugins_url( 'static/', WPAY_PLUGIN_FILE ) . 'js/wpay-scripts.js',
            array(
                'jquery',
            ),
            Helper::get_plugin_data( 'Version' ),
            true
        );
        wp_enqueue_script( 'wpay-frontend-scripts' );
    }

    /**
     * Run the class.
     */
    public function run() {
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_scripts' ), 11 );
    }
}
