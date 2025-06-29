<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WWPP
 */

namespace RymeraWebCo\WWPP;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Cart_Checkout_Block_Integration class.
 *
 * @since 2.0.0
 */
class Cart_Checkout_Block_Integration implements IntegrationInterface {

    /**
     * Get the name of the integration.
     */
    public function get_name() {

        return 'wwpp-cart-checkout-block-integration';
    }

    /**
     * Initialize the integration.
     */
    public function initialize() {

        $app = new Vite_App(
            'wwpp-wholesale-savings',
            'src/blocks/woocommerce/wholesale-savings/index.tsx'
        );

        $app->register();
        $app->enqueue_stylesheet();
    }

    /**
     * Get script handles.
     */
    public function get_script_handles() {

        return array(
            'wwpp-wholesale-savings',
        );
    }

    /**
     * Get editor script handles.
     */
    public function get_editor_script_handles() {

        return array();
    }

    /**
     * Get script data.
     */
    public function get_script_data() {

        return array();
    }
}
