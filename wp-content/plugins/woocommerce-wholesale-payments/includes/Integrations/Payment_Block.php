<?php
/**
 * Author: Rymera Web Co.
 *
 * @package RymeraWebCo\WPay\Integrations
 */

namespace RymeraWebCo\WPay\Integrations;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use RymeraWebCo\WPay\Helpers\Helper;
use RymeraWebCo\WPay\Payment_Gateway\WC_Wholesale_Payments;
use RymeraWebCo\WPay\Helpers\WPay;

/**
 * Payment_Block class.
 *
 * @since 1.0.0
 */
final class Payment_Block extends AbstractPaymentMethodType {

    /**
     * Gateway.
     *
     * @since 1.0.0
     * @var class $gateway
     */
    private $gateway;

    /**
     * Gateway Name.
     *
     * @since 1.0.0
     * @var string $name
     */
    protected $name = 'wc_wholesale_payments';

    /**
     * Initialize the class.
     *
     * @since 1.0.0
     */
    public function initialize() {
        $this->settings = get_option( 'woocommerce_wc-wpay_settings', array() );
        $this->gateway  = new WC_Wholesale_Payments();
    }

    /**
     * Check if the payment method is active.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_active() {
        return $this->gateway->is_available();
    }

    /**
     * Get the payment method script handles.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_payment_method_script_handles() {
        if ( ! is_admin() ) {
            wp_register_script(
                'wc-wpay-blocks-integration',
                plugins_url( 'static/', WPAY_PLUGIN_FILE ) . 'js/checkout/wpay-payment-method.js',
                array(
                    'wc-blocks-registry',
                    'wc-settings',
                    'wp-element',
                    'wp-html-entities',
                    'wp-i18n',
                    'wc-blocks-checkout',
                    'wp-hooks',
                ),
                Helper::get_plugin_data( 'Version' ),
                true
            );
            if ( function_exists( 'wp_set_script_translations' ) ) {
                wp_set_script_translations( 'wc-wpay-blocks-integration', 'wc-wpay', WPAY_PLUGIN_DIR_PATH . 'languages/' );
            }

            return array( 'wc-wpay-blocks-integration' );
        }

        return array();
    }

    /**
     * Get the payment method data.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_payment_method_data() {
        $total          = ( WC()->cart ) ? WC()->cart->get_total( false ) : 0;
        $payment_plans  = $this->gateway->get_applicable_payment_plans( $total );
        $test_mode_text = '';
        if ( WPay::get_api_mode() === 'test' ) {
            $test_mode_text = __( 'Payment method is in TEST MODE. No payment will be processed or any shipment fulfilled.', 'wc-woocommerce-wholesale-payments' );
        }

        $payment_plans_style = apply_filters(
            'wpay_payment_plans_block_style',
            array(
                'marginTop'   => '10px',
                'marginLeft'  => '10px',
                'paddingLeft' => '0',
            )
        );

        $payment_plan_items_style = apply_filters(
            'wpay_payment_plan_items_block_style',
            array(
                'padding'      => '0.2rem 0',
                'display'      => 'flex',
                'alignItems'   => 'center',
                'marginBottom' => '0.5rem',
            )
        );

        $payment_plan_charge_status_style = apply_filters(
            'wpay_payment_plan_charge_status_style',
            array(
                'backgroundColor' => '#E6D96A',
                'borderRadius'    => '1rem',
                'color'           => '#000',
                'fontSize'        => '14px',
                'padding'         => '0.2rem 0.5rem',
                'marginLeft'      => '10px',
            )
        );

        return array(
            'restUrl'                      => esc_url_raw( rest_url( 'wpay/v1/checkout/choose-plan' ) ),
            'nonce'                        => wp_create_nonce( 'wp_rest' ),
            'title'                        => __( 'Wholesale Payments', 'wc-woocommerce-wholesale-payments' ),
            'description'                  => __( 'Payment plans available for this order.', 'woocommerce-wholesale-payments' ),
            'autoChargeLabel'              => __( 'Auto Charge', 'wc-woocommerce-wholesale-payments' ),
            'supports'                     => array(
                'default'            => true,
                'products'           => true,
                'subscriptions'      => true,
                'pre-orders'         => true,
                'add_payment_method' => true,
                'tokenization'       => true,
                'refunds'            => true,
                'add_to_cart'        => true,
                'ajax_checkout'      => true,
            ),
            'paymentPlans'                 => $payment_plans,
            'testModeText'                 => $test_mode_text,
            'paymentPlansStyle'            => $payment_plans_style,
            'paymentPlanItemsStyle'        => $payment_plan_items_style,
            'paymentPlanChargeStatusStyle' => $payment_plan_charge_status_style,
            'selectedPlan'                 => $this->gateway->wpay_get_selected_plan(),
        );
    }
}
