<?php
/**
 * Class LabelPurchaseService
 *
 * @package Automattic\WCShipping
 */

namespace Automattic\WCShipping\LabelPurchase;

use Automattic\WCShipping\Connect\WC_Connect_Service_Settings_Store;
use Automattic\WCShipping\Connect\WC_Connect_API_Client;
use Automattic\WCShipping\Connect\WC_Connect_Logger;
use Automattic\WCShipping\Connect\WC_Connect_Utils;
use Automattic\WCShipping\Promo\PromoService;
use Automattic\WCShipping\Utils;
use WP_Error;

/**
 * Class to handle label purchase requests.
 */
class LabelPurchaseService {

	/**
	 * Connect Server settings store.
	 *
	 * @var WC_Connect_Service_Settings_Store
	 */
	private $settings_store;

	/**
	 * Connect Server API client.
	 *
	 * @var WC_Connect_API_Client
	 */
	private $api_client;

	/**
	 * Connect Label Service.
	 *
	 * @var View
	 */
	private $connect_label_service;

	/**
	 * Logger utility.
	 *
	 * @var WC_Connect_Logger
	 */
	private $logger;

	/**
	 * Promo service.
	 *
	 * @var PromoService
	 */
	private $promo_service;

	/**
	 * Selected rates key used to store selected rates in order meta.
	 *
	 * @var string
	 */
	const SELECTED_RATES_KEY = '_wcshipping_selected_rates';
	/**
	 * Selected hazmat key used to store selected hazmat in order meta.
	 *
	 * @var string
	 */
	const SELECTED_HAZMAT_KEY = '_wcshipping_selected_hazmat';

	/**
	 * Selected hazmat key used to store selected hazmat in order meta.
	 *
	 * @var string
	 */
	const SELECTED_ORIGIN_KEY = '_wcshipping_selected_origin';

	/**
	 * Selected hazmat key used to store selected hazmat in order meta.
	 *
	 * @var string
	 */
	const SELECTED_DESTINATION_KEY = '_wcshipping_selected_destination';

	/**
	 * Key used to store customs information in order meta.
	 *
	 * @var string
	 */
	const CUSTOMS_INFORMATION = '_wcshipping_customs_information';

	/**
	 * Key used to store order shipments in order meta.
	 *
	 * @var string
	 */
	const ORDER_SHIPMENTS = '_wcshipping-shipments';


	/**
	 * Key used to store shipment dates in order meta.
	 *
	 * @var string
	 */
	const SHIPMENT_DATES = '_wcshipping_shipment_dates';

	/**
	 * Class constructor.
	 *
	 * @param WC_Connect_Service_Settings_Store $settings_store        Server settings store instance.
	 * @param WC_Connect_API_Client             $api_client            Server API client instance.
	 * @param View                              $connect_label_service Connect Label Service instance.
	 * @param WC_Connect_Logger                 $logger                Server API client instance.
	 * @param PromoService                      $promo_service         Promo service instance.
	 */
	public function __construct(
		WC_Connect_Service_Settings_Store $settings_store,
		WC_Connect_API_Client $api_client,
		View $connect_label_service,
		WC_Connect_Logger $logger,
		PromoService $promo_service
	) {
		$this->settings_store        = $settings_store;
		$this->api_client            = $api_client;
		$this->connect_label_service = $connect_label_service;
		$this->logger                = $logger;
		$this->promo_service         = $promo_service;
	}

	/**
	 * Get labels for order.
	 *
	 * @param int $order_id WC Order ID.
	 * @return array REST response body.
	 */
	public function get_labels( $order_id ) {
		$response = $this->connect_label_service->get_label_payload( $order_id );
		if ( ! $response ) {
			$message = __( 'Order not found', 'woocommerce-shipping' );
			return new WP_Error(
				401,
				$message,
				array(
					'success' => false,
					'message' => $message,
				),
			);
		}

		return array(
			'success' => true,
			'labels'  => $response['currentOrderLabels'],
		);
	}

	/**
	 * Purchase labels for order.
	 *
	 * @param array $origin      Origin address.
	 * @param array $destination Destination address.
	 * @param array $packages   Packages to purchase labels for.
	 * @param int   $order_id    WC Order ID.
	 * @param array $selected_rate Selected rate. { rate: array, parent?: array }
	 * @param array $selected_rate_options Selected rate options.
	 * @param array $hazmat Selected HAZMAT category and if shipment includes HAZMAT.
	 * @param array $customs Customs form information.
	 * @param array $user_meta User meta array.
	 * @param array $features_supported_by_client Features supported by client.
	 * @param array $shipment_options Extra options.
	 * @return array|WP_Error REST response body.
	 */
	public function purchase_labels(
		$origin,
		$destination,
		$packages,
		$order_id,
		$selected_rate,
		$selected_rate_options,
		$hazmat,
		$customs,
		$user_meta = array(),
		$features_supported_by_client = array(),
		$shipment_options = array()
	) {
		$settings         = $this->settings_store->get_account_settings();
		$service_names    = array_column( $packages, 'service_name' );
		$request_packages = $this->prepare_packages_for_purchase( $packages, $user_meta );

		if ( ! empty( $user_meta ) ) {
			$this->update_user_meta( $user_meta );
		}

		$origin_address_id = 'UNKNOWN_ORIGIN_ID';
		// Assuming only verified addresses are being used to purchase labels.
		$is_origin_address_verified = true;
		// Todo: To be updated via  woocommerce-shipping/issues/859
		if ( isset( $origin['id'] ) ) {
			$origin_address_id = $origin['id'];
			unset( $origin['id'] );
		}

		if ( isset( $origin['is_verified'] ) ) {
			$is_origin_address_verified = $origin['is_verified'];
			unset( $origin['is_verified'] );
		}

		// Extract label_date from shipment_options, default to null if not present
		$label_date = isset( $shipment_options['label_date'] ) ? $shipment_options['label_date'] : null;

		$label_response = $this->api_client->send_shipping_label_request(
			array(
				'async'                        => true,
				'email_receipt'                => $settings['email_receipts'] ?? false,
				'origin'                       => $origin,
				'destination'                  => $destination,
				'payment_method_id'            => $this->settings_store->get_selected_payment_method_id(),
				'order_id'                     => $order_id,
				'packages'                     => $request_packages,
				'features_supported_by_client' => $features_supported_by_client ?? array(),
				'shipment_options'             => array(
					'label_date' => $label_date,
				),
			)
		);

		if ( is_wp_error( $label_response ) ) {
			$error_data            = (array) $label_response->get_error_data();
			$error_data['success'] = false;
			$error_data['message'] = $label_response->get_error_message();

			$error = new WP_Error(
				$label_response->get_error_code(),
				$label_response->get_error_message(),
				$error_data
			);
			$this->logger->log( $error, __CLASS__ );
			return $error;
		}

		$purchased_labels_meta = $this->get_labels_meta_from_response( $label_response, $request_packages, $service_names, $order_id );

		if ( is_wp_error( $purchased_labels_meta ) ) {
			$this->logger->log( $purchased_labels_meta, __CLASS__ );
			return $purchased_labels_meta;
		}

		$this->settings_store->add_labels_to_order( $order_id, $purchased_labels_meta );

		/**
		 * $hazmat looks like this:
		 * [
		 *   'shipment_0' => [
		 *     'category' => 'SOMECATEGORY'
		 *     'is_hazmat' => 'true'
		 *   ]
		 * ]
		 * so we can get the shipment key by getting the first key of the array
		 *
		 * @var string
		 */
		$shipment_key = array_keys( $hazmat )[0];

		$keyed_selected_rate = array(
			$shipment_key => array(
				'rate'             => array_merge(
					(array) $label_response->rates[0],
					array(
						'type' => $selected_rate['rate']['type'] ?? '',
					)
				),
				'parent'           => isset( $selected_rate['parent'] ) ? (array) $selected_rate['parent'] : null,
				'shipment_options' => $selected_rate_options,
			),
		);

		$origin      = array(
			$shipment_key => array_merge(
				$origin,
				array(
					'id'          => $origin_address_id,
					'is_verified' => $is_origin_address_verified,
				),
			),
		);
		$destination = array(
			$shipment_key => $destination,
		);

		$selected_meta = $this->store_selected_meta(
			$order_id,
			array(
				self::SELECTED_RATES_KEY       => $keyed_selected_rate,
				self::SELECTED_HAZMAT_KEY      => $hazmat,
				self::SELECTED_ORIGIN_KEY      => $origin,
				self::SELECTED_DESTINATION_KEY => $destination,
				self::CUSTOMS_INFORMATION      => $customs,
				self::SHIPMENT_DATES           => array(
					$shipment_key => array(
						'shipping_date'           => $label_date,
						'estimated_delivery_date' => null, // Coming soon
					),
				),
			),
		);

		return array(
			'labels'               => $purchased_labels_meta,
			'selected_rates'       => $selected_meta[ self::SELECTED_RATES_KEY ],
			'selected_hazmat'      => $selected_meta[ self::SELECTED_HAZMAT_KEY ],
			'selected_origin'      => $selected_meta[ self::SELECTED_ORIGIN_KEY ],
			'selected_destination' => $selected_meta[ self::SELECTED_DESTINATION_KEY ],
			'customs_information'  => $selected_meta[ self::CUSTOMS_INFORMATION ],
			'shipment_dates'       => $selected_meta[ self::SHIPMENT_DATES ],
			'success'              => true,
		);
	}

	/**
	 * Returns meta object for purchased labels to store with order.
	 *
	 * @param object $response      Purchase shipping label response from Connect Server.
	 * @param array  $packages     Packages for purchase label request body.
	 * @param array  $service_names List of service names for packages.
	 * @param int    $order_id      WooCommerce order ID.
	 * @return array|WP_Error Meta for purchased labels.
	 */
	private function get_labels_meta_from_response( $response, $packages, $service_names, $order_id ) {
		$label_ids             = array();
		$purchased_labels_meta = array();
		$package_lookup        = $this->settings_store->get_package_lookup();
		foreach ( $response->labels as $index => $label_data ) {
			if ( isset( $label_data->error ) ) {
				$error = new WP_Error(
					$label_data->error->code,
					$label_data->error->message,
					array(
						'success' => false,
						'message' => $label_data->error->message,
					)
				);
				return $error;
			}

			/*
			 * Aknowledge the error returned on label level.
			 * In this case, error is a string and a property of the individual label object.
			 *
			 * Example:
			 * $label_data->label->error = "Rate not found";
			 */
			if ( isset( $label_data->label->error ) ) {
				$error = new WP_Error(
					'purchase_error',
					$label_data->label->error,
					array(
						'success' => false,
						'message' => $label_data->label->error,
					)
				);
				return $error;
			}

			$label_ids[] = $label_data->label->label_id;

			$label_meta = array(
				'label_id'               => $label_data->label->label_id,
				'tracking'               => $label_data->label->tracking_id,
				'refundable_amount'      => $label_data->label->refundable_amount,
				'created'                => $label_data->label->created,
				'carrier_id'             => $label_data->label->carrier_id,
				'service_name'           => $service_names[ $index ],
				'status'                 => $label_data->label->status,
				'commercial_invoice_url' => $label_data->label->commercial_invoice_url ?? '',
				'is_commercial_invoice_submitted_electronically' => $label_data->label->is_commercial_invoice_submitted_electronically ?? '',
			);

			$package = $packages[ $index ];
			$box_id  = $package['box_id'];
			if ( 'custom_box' === $box_id ) {
				$label_meta['package_name'] = __( 'Individual packaging', 'woocommerce-shipping' );
			} elseif ( isset( $package_lookup[ $box_id ] ) ) {
				$label_meta['package_name'] = $package_lookup[ $box_id ]['name'];
			} else {
				$label_meta['package_name'] = __( 'Unknown package', 'woocommerce-shipping' );
			}

			$label_meta['is_letter'] = isset( $package['is_letter'] ) ? $package['is_letter'] : false;

			$product_names = array();
			$product_ids   = array();
			foreach ( $package['products'] as $product_id ) {
				$product       = \wc_get_product( $product_id );
				$product_ids[] = $product_id;

				if ( $product ) {
					$product_names[] = $product->get_title();
				} else {
					$order           = \wc_get_order( $order_id );
					$product_names[] = WC_Connect_Utils::get_product_name_from_order( $product_id, $order );
				}
			}

			$label_meta['product_names'] = $product_names;
			$label_meta['product_ids']   = $product_ids;
			$label_meta['id']            = $package['id']; // internal shipment id.

			array_unshift( $purchased_labels_meta, $label_meta );
		}
		return $purchased_labels_meta;
	}

	/**
	 * Prepares packages request for Connect Server.
	 *
	 * @param array $packages Packages from purchase request.
	 * @return array Prepared packages request payload.
	 */
	private function prepare_packages_for_purchase( $packages ) {
		$last_box_id     = '';
		$last_service_id = '';
		$last_carrier_id = '';
		foreach ( $packages as $index => $package ) {
			unset( $package['service_name'] );
			$packages[ $index ] = $package;

			if ( empty( $last_box_id ) && ! empty( $package['box_id'] ) ) {
				$last_box_id = $package['box_id'];
			}

			if ( empty( $last_service_id ) && ! empty( $package['service_id'] ) ) {
				$last_service_id = $package['service_id'];
			}

			if ( empty( $last_carrier_id ) && ! empty( $package['carrier_id'] ) ) {
				$last_carrier_id = $package['carrier_id'];
			}
		}

		// Store most recently used box/service/carrier.
		if ( ! empty( $last_box_id ) ) {
			update_user_meta( get_current_user_id(), 'wcshipping_last_box_id', $last_box_id );
		}

		if ( ! empty( $last_service_id ) && '' !== $last_service_id ) {
			update_user_meta( get_current_user_id(), 'wcshipping_last_service_id', $last_service_id );
		}

		if ( ! empty( $last_carrier_id ) && '' !== $last_carrier_id ) {
			update_user_meta( get_current_user_id(), 'wcshipping_last_carrier_id', $last_carrier_id );
		}

		return $packages;
	}

	/**
	 * Store user meta.
	 *
	 * @param array $user_meta User meta array.
	 */
	public function update_user_meta( $user_meta ) {
		if ( empty( $user_meta ) ) {
			return;
		}
		foreach ( $user_meta as $key => $value ) {
			update_user_meta( get_current_user_id(), 'wcshipping_' . $key, $value );
		}
	}

	public function get_status( $label_id ) {
		return $this->api_client->get_label_status( $label_id );
	}

	public function update_order_label( int $order_id, $label_data ) {
		// Due to the async nature of the purchase process, we need to do the promotion decrement here, to only do it after the status changes to PURCHASED.

		if ( isset( $label_data->promo_id ) ) {
			$this->promo_service->maybe_decrement_promotion_remaining( $order_id, $label_data );
		}

		return $this->settings_store->update_label_order_meta_data( $order_id, $label_data );
	}

	/**
	 *
	 * @param $order_id int
	 * @param $selected_meta [
	 *    'selected_rate' => [],
	 *   'hazmat' => []
	 *   'origin' => []
	 *   'destination' => []
	 * ]
	 *
	 * @return array
	 */
	private function store_selected_meta( $order_id, $selected_meta ): array {
		$order = \wc_get_order( $order_id );
		foreach ( $selected_meta as $key => $value ) {
			$selected_state = $order->get_meta( $key );
			$selected_state = array_merge( empty( $selected_state ) ? array() : $selected_state, $value );
			$order->update_meta_data( $key, $selected_state );
		}
		$order->save();

		return $selected_meta;
	}

	/**
	 * @return object|WP_Error
	 */
	public function refund_label( int $order_id, int $label_id ) {
		$response = $this->api_client->send_shipping_label_refund_request( $label_id );

		if ( isset( $response->error ) ) {
			$response = new WP_Error(
				property_exists( $response->error, 'code' ) ? $response->error->code : 'refund_error',
				property_exists( $response->error, 'message' ) ? $response->error->message : ''
			);
		}

		if ( is_wp_error( $response ) ) {
			$this->logger->log( $response, __CLASS__ );
			return $response;
		}

		$label_refund = (object) array(
			'label_id' => (int) $response->label->id,
			'refund'   => $response->refund,
		);

		$this->settings_store->update_label_order_meta_data( $order_id, $label_refund );

		return $response;
	}

	/**
	 * Get shipments destinations.
	 *
	 * @param int $order_id Order ID.
	 * @return array Array of destinations by shipment id.
	 */
	public function get_shipments_destinations( int $order_id ) {
		$order = \wc_get_order( $order_id );
		return $order->get_meta( self::SELECTED_DESTINATION_KEY );
	}

	/**
	 * Get shipments origins.
	 *
	 * @param int $order_id Order ID.
	 * @return array Array of origins by shipment id.
	 */
	public function get_shipments_origins( int $order_id ) {
		$order = \wc_get_order( $order_id );
		return $order->get_meta( self::SELECTED_ORIGIN_KEY );
	}

	/**
	 * Build a shipment from order items.
	 *
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	private function build_shipment_from_order_items( $order ) {
		$order_products = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();

			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			if ( ! $product->needs_shipping() ) {
				continue;
			}

			$product_meta = array();

			$customs_info = Utils::get_product_customs_data( $product );
			if ( $customs_info ) {
				$product_meta['customs_info'] = $customs_info;
			}

			$line_item = array(
				'id'           => $item_id,
				'subtotal'     => wc_format_decimal( $order->get_line_subtotal( $item, false, false ) ),
				'subtotal_tax' => wc_format_decimal( $item->get_subtotal_tax() ),
				'total'        => wc_format_decimal( $order->get_line_total( $item, false, false ) ),
				'total_tax'    => wc_format_decimal( $item->get_total_tax() ),
				'price'        => wc_format_decimal( $order->get_item_total( $item, false, false ) ),
				'quantity'     => $item->get_quantity(),
				'tax_class'    => $item->get_tax_class(),
				'name'         => $item->get_name(),
				'product_id'   => $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id(),
				'sku'          => is_object( $product ) ? $product->get_sku() : null,
				'meta'         => (object) $product_meta,
				'image'        => wp_get_attachment_url( $product->get_image_id() ) ?: wc_placeholder_img_src(),
				'weight'       => $product->get_weight(),
				'dimensions'   => array(
					'length' => $product->get_length(),
					'width'  => $product->get_width(),
					'height' => $product->get_height(),
				),
				'variation'    => array_values( $item->get_all_formatted_meta_data() ),
			);

			$order_products[] = $line_item;
		}

		return $order_products;
	}

	/**
	 * Get shipments from order, build it from order items if only 1 shipment is present.
	 *
	 * @param int $order_id Order ID.
	 * @return array Array of shipments.
	 */
	public function get_shipments( int $order_id ) {
		$order     = \wc_get_order( $order_id );
		$shipments = $order->get_meta( self::ORDER_SHIPMENTS );
		// Single shipment orders does not have shipments meta set, so we build it from the order items
		if ( empty( $shipments ) ) {
			$shipments    = array();
			$shipments[0] = $this->build_shipment_from_order_items( $order );
		}
		return $shipments;
	}
}
