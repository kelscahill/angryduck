<?php
/**
 * CheckoutController class.
 *
 * Controller class for checkout-related hooks.
 *
 * @package Automattic/WCShipping
 */

namespace Automattic\WCShipping\Checkout;

use Automattic\WCShipping\Connect\WC_Connect_Logger;
use Automattic\WCShipping\Connect\WC_Connect_Service_Settings_Store;
use Automattic\WCShipping\LabelPurchase\AddressNormalizationService;
use Automattic\WCShipping\Utils;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Class CheckoutController
 */
class CheckoutController {

	/**
	 * Checkout service.
	 *
	 * @var CheckoutService
	 */
	private CheckoutService $checkout_service;

	/**
	 * Notifier instance.
	 *
	 * @var CheckoutNotifier
	 */
	private CheckoutNotifier $notifier;

	/**
	 * The settings store.
	 *
	 * @var WC_Connect_Service_Settings_Store
	 */
	private WC_Connect_Service_Settings_Store $settings_store;

	/**
	 * Address normalization service.
	 *
	 * @var AddressNormalizationService
	 */
	private AddressNormalizationService $address_normalization_service;

	/**
	 * CheckoutController constructor.
	 *
	 * @param WC_Connect_Logger                 $wc_connect_logger The WC_Connect_Logger instance.
	 * @param CheckoutService                   $checkout_service Checkout service.
	 * @param WC_Connect_Service_Settings_Store $settings_store The settings store.
	 * @param AddressNormalizationService       $address_normalization_service Address normalization service.
	 */
	public function __construct(
		WC_Connect_Logger $wc_connect_logger,
		CheckoutService $checkout_service,
		WC_Connect_Service_Settings_Store $settings_store,
		AddressNormalizationService $address_normalization_service
	) {
		$this->checkout_service              = $checkout_service;
		$this->notifier                      = new CheckoutNotifier( $wc_connect_logger->is_debug_enabled() );
		$this->settings_store                = $settings_store;
		$this->address_normalization_service = $address_normalization_service;

		add_action( 'wp_enqueue_scripts', array( $this, 'load_assets' ) );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'maybe_display_address_validation_notices' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'maybe_set_destination_normalized_order_meta' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'maybe_set_destination_normalized_order_meta' ) );
		add_filter( 'woocommerce_shipping_packages', array( $this, 'maybe_add_address_validation_notices' ) );
	}

	/**
	 * Load assets.
	 */
	public function load_assets() {
		if ( ! CheckoutService::is_address_validation_enabled() || ! CheckoutService::is_checkout_page() ) {
			return;
		}

		wp_enqueue_style(
			'wcshipping-checkout',
			Utils::get_enqueue_base_url() . 'woocommerce-shipping-checkout-address-validation.css',
			array(),
			Utils::get_file_version( WCSHIPPING_PLUGIN_DIST_DIR . 'woocommerce-shipping-checkout-address-validation.css' )
		);

		$handle = 'wcshipping-checkout';

		wp_register_script(
			$handle,
			WCSHIPPING_JAVASCRIPT_URL . 'checkout.js',
			array( 'wp-i18n' ),
			Utils::get_file_version( WCSHIPPING_JAVASCRIPT_DIR . 'checkout.js' ),
			true
		);

		wp_localize_script(
			$handle,
			'wcShippingSettings',
			array_merge(
				Utils::get_settings_object(),
				array(
					'checkout' => CheckoutService::get_checkout_script_data(),
				)
			)
		);

		wp_enqueue_script( $handle );
	}

	/**
	 * Maybe display address validation notices.
	 */
	public function maybe_display_address_validation_notices() {
		if ( ! CheckoutService::is_address_validation_enabled() || ! CheckoutService::is_checkout_page() ) {
			return;
		}

		$this->notifier->print_notices();
		$this->notifier->clear_notices();
	}

	/**
	 * Maybe set destination normalized order meta.
	 *
	 * @param int|WC_Order $order_id_or_object The order ID or WC_Order instance depending on the context.
	 */
	public function maybe_set_destination_normalized_order_meta( $order_id_or_object ) {
		if ( ! CheckoutService::is_address_validation_enabled() ) {
			return;
		}

		if ( ! $this->checkout_service->get_destination_normalized_session_value() ) {
			return;
		}

		$order = wc_get_order( $order_id_or_object );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$this->address_normalization_service->set_is_destination_address_normalized( $order->get_id(), true );
	}

	/**
	 * If the right conditions are met, add address validation notices for entered shipping address.
	 *
	 * @param array $packages Shipping packages.
	 *
	 * @return array
	 */
	public function maybe_add_address_validation_notices( array $packages ): array {
		if (
			CheckoutService::is_address_validation_enabled()
			&& CheckoutService::is_classic_checkout()
		) {
			$this->add_address_validation_notices();
		}

		return $packages;
	}


	/**
	 * Add address validation notices for entered shipping address.
	 */
	private function add_address_validation_notices() {
		static $has_run = false;

		if ( $has_run ) {
			return;
		}

		$has_run = true;

		// Validate the shipping address.
		$response = $this->checkout_service->validate_shipping_address();
		if ( ! $response['success'] || empty( $response['notices'] ) ) {
			return;
		}

		foreach ( $response['notices'] as $notice ) {
			$this->notifier->info( $notice->get_message(), $notice->get_data() ?? array(), 'address-validation' );
		}
	}
}
