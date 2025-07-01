<?php
/**
 * Class LabelRateService
 *
 * @package Automattic\WCShipping
 */

namespace Automattic\WCShipping\LabelRate;

use Automattic\WCShipping\Connect\WC_Connect_API_Client;
use Automattic\WCShipping\Connect\WC_Connect_Logger;
use Automattic\WCShipping\Connect\WC_Connect_Service_Settings_Store;
use WP_Error;
use stdClass;

/**
 * Handles all label rate logics.
 */
class LabelRateService {
	/**
	 * API client
	 *
	 * @var WC_Connect_API_Client $api_client
	 */
	private $api_client;

	/**
	 * WC connect logger.
	 *
	 * @var WC_Connect_Logger $logger
	 */
	private $logger;

	/**
	 * Settings store.
	 *
	 * @var WC_Connect_Service_Settings_Store $settings_store
	 */
	protected $settings_store;


	/**
	 * Prefix to add in package name for making requests with multiple rates.
	 */
	public const SPECIAL_RATE_PREFIX = '_wcshipping_rate_type_';

	/**
	 * Array of extra options to collect rates for.
	 *
	 * @var array $extra_rates
	 */
	private const EXTRA_RATES = array(
		'signature_required'       => array(
			'signature' => 'yes',
		),
		'adult_signature_required' => array(
			'signature' => 'adult',
		),
	);

	private const UPSDAP_EXTRA_RATES = array(
		'carbon_neutral'      => true,
		'additional_handling' => true,
		'saturday_delivery'   => true,
	);

	/**
	 * Class constructor.
	 *
	 * @param WC_Connect_API_Client             $api_client API client.
	 * @param WC_Connect_Logger                 $logger Logger.
	 * @param WC_Connect_Service_Settings_Store $settings_store Server settings store instance.
	 */
	public function __construct( WC_Connect_API_Client $api_client, WC_Connect_Logger $logger, WC_Connect_Service_Settings_Store $settings_store ) {
		$this->api_client     = $api_client;
		$this->logger         = $logger;
		$this->settings_store = $settings_store;
	}

	/**
	 * Get standard rates along with rates for special options
	 * that are defined in self::EXTRA_RATES.
	 *
	 * @param array $payload Request payload.
	 * @return WP_Error|stdClass
	 */
	public function get_all_rates( $payload ) {
		// Find and add payment method to payload.
		$payload['payment_method_id'] = $this->settings_store->get_selected_payment_method_id();

		// Add tax identifiers to the payload.
		$payload['tax_identifiers'] = array();
		foreach ( $this->settings_store->get_tax_identifiers() as $tax_id_type => $tax_id ) {
			if ( empty( $tax_id ) ) {
				continue;
			}

			$payload['tax_identifiers'][] = array(
				'tax_id_type'     => strtoupper( $tax_id_type ),
				'tax_id'          => $tax_id,
				'issuing_country' => strtoupper( wc_get_base_location()['country'] ),
				'entity'          => 'SENDER',
			);
		}

		// Update the customs information on all this order's products and line items.
		// Note: this function pass $payload by reference, $payload may get modified after this is called.
		$this->update_product_and_payload_customs_information( $payload );

		// Get all the package ID from the payload.
		$original_package_ids = $this->get_package_ids_from_payload( $payload );

		$payload['packages'] = $this->get_request_payload_packages( $payload['packages'] );

		$response = $this->request_rates( $payload );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( property_exists( $response, 'rates' ) ) {
			return $this->merge_extra_rates( $response->rates, $original_package_ids );
		}
		return new stdClass();
	}

	/**
	 * Go through the packages from the payload and return a list of IDs.
	 *
	 * @param array $payload Request payload.
	 */
	public function get_package_ids_from_payload( $payload ) {
		if ( empty( $payload['packages'] ) ) {
			return array();
		}

		return array_column( $payload['packages'], 'id' );
	}

	/**
	 * Go through the extra rates and append it to the list of packages.
	 *
	 * @param array $payload_packages Request payload.
	 * @return array
	 */
	public function get_packages_with_signature_required_options( $payload_packages ) {
		$packages_requiring_signature = array();

		// Add extra package requests with special options set.
		foreach ( self::EXTRA_RATES as $rate_name => $rate_option ) {
			foreach ( $rate_option as $option_name => $option_value ) {
				foreach ( $payload_packages as $package ) {
					$new_package                 = $package;
					$new_package[ $option_name ] = $option_value;

					$new_package['id']             .= self::SPECIAL_RATE_PREFIX . $rate_name;
					$packages_requiring_signature[] = $new_package;
				}
			}
		}
		return $packages_requiring_signature;
	}

	/**
	 * Merge default rates together with "signature required" and
	 * "adult signature required" rates.
	 *
	 * The get_all_rates requests extra rate options and upsdap rate options as separate
	 * packages. This function groups these separate packages
	 * under the original the package name for easier parsing
	 * on the frontend.
	 *
	 * @param stdClass $rates Rate response for server.
	 * @param array    $original_package_ids Package IDs.
	 *
	 * @return stdClass Rates
	 */
	public function merge_extra_rates( $rates, $original_package_ids ) {
		/**
		 * Using stdClass to avoid unnecessary array allocations.
		 * Using an array can result in `0` used as key be removed when doing a JSON encoding which
		 * will yield an array and no object.
		 */
		$parsed_rates = new stdClass();

		foreach ( $original_package_ids as $name ) {
			// Add a 'default' entry for the rate with no special options.
			$parsed_rates->$name = (object) array(
				'default' => $rates->{ $name },
			);

			// Get package for each extra rate to group them under the original package name.
			foreach ( self::EXTRA_RATES as $extra_rate_name => $option ) {
				$extra_rate_package_name = $name . self::SPECIAL_RATE_PREFIX . $extra_rate_name;
				if ( isset( $rates->{ $extra_rate_package_name } ) ) {
					$parsed_rates->$name->$extra_rate_name = $rates->{ $extra_rate_package_name };
				}
			}

			// Get package for each UPSDAP extra rate to group them under the original package name.
			foreach ( self::UPSDAP_EXTRA_RATES as $extra_rate_name => $option ) {
				$extra_rate_package_name = $name . self::SPECIAL_RATE_PREFIX . $extra_rate_name;
				if ( isset( $rates->{ $extra_rate_package_name } ) ) {
					$parsed_rates->$name->$extra_rate_name = $rates->{ $extra_rate_package_name };
				}
			}
		}
		return $parsed_rates;
	}

	/**
	 * Make a rate request through our connect server client.
	 *
	 * @param object $payload Request payload.
	 * @return WP_Error|stdClass
	 */
	public function request_rates( $payload ) {
		$payload = $this->normalize_api_rate_request( $payload );

		$response = $this->api_client->get_label_rates( $payload );

		if ( is_wp_error( $response ) ) {
			$error = new WP_Error(
				$response->get_error_code(),
				$response->get_error_message(),
				array( 'message' => $response->get_error_message() )
			);
			$this->logger->log( $error, __CLASS__ );
			return $error;
		}
		return $response;
	}

	/**
	 * International shipping requires custom forms. The frontend provides a `contents_type` settings to indicate this.
	 * This function update product meta `wcshipping_customs_info` if there are custom form info, it also updates
	 * the "value" to "total value".
	 *
	 * @param array $payload Request payload. This is a reference, any changes to payload will affect the caller.
	 */
	public function update_product_and_payload_customs_information( &$payload ) {
		// Update the customs information on all this order's products.
		$updated_product_ids = array();
		foreach ( $payload['packages'] as &$package ) {
			if ( ! isset( $package['contents_type'] ) ) {
				/**
				 * If at least 1 package has no customs form, then this whole shipment is domestic.
				 * This is because we don't support multiple addresses yet. In this case, we don't
				 * need to process any of the international shipping logic below this point.
				 */
				break;
			}
			foreach ( $package['items'] as &$item ) {
				if ( ! in_array( $item['product_id'], $updated_product_ids, true ) ) {
					$product = wc_get_product( $item['product_id'] );
					if ( ! $product ) {
						continue;
					}
					$product->update_meta_data(
						'wcshipping_customs_info',
						array(
							'description'      => $item['description'],
							'hs_tariff_number' => $item['hs_tariff_number'],
							'origin_country'   => $item['origin_country'],
						)
					);
					$updated_product_ids[] = $item['product_id'];
					$product->save();
				}

				/**
				 * React app is passing the item "value", "weight" as the individual value and weight. The
				 * connect server expects "value" to represent "total value", "weight" to represent "total weight".
				 * The connect server uses "value" and "weight" with this definition https://docs.easypost.com/docs/customs-items#customsitems-object.
				 *
				 * This function updates the "value" and "weight" in all items within all packages to "total value" and "total weight" respectively.
				 */
				$item['value']  = $item['value'] * $item['quantity'];
				$item['weight'] = $item['weight'] * $item['quantity'];
			}
		}
	}

	/**
	 * Remove unnecessary parameters before passing it to the connect server.
	 *
	 * @param array $payload Request payload.
	 * @return array
	 */
	public function normalize_api_rate_request( $payload ) {
		unset( $payload['order_id'] );

		// The server requires address line 1 to be "address" instead of "address_1".
		if ( empty( $payload['origin']['address'] ) ) {
			$payload['origin']['address'] = $payload['origin']['address_1'];
		}

		if ( empty( $payload['destination']['address'] ) ) {
			$payload['destination']['address'] = $payload['destination']['address_1'];
		}
		unset( $payload['origin']['address_1'] );
		unset( $payload['destination']['address_1'] );

		// We only require either the origin name or company name to be defined, but if both
		// are defined, then we should consider "name" as something that's used internally and
		// should not be shown on shipping labels.
		if ( ! empty( $payload['origin']['name'] ) && ! empty( $payload['origin']['company'] ) ) {
			$payload['origin']['name'] = '';
		}

		// Rename country_code, state_code to "country" and "state".
		$payload['origin']['country']      = $payload['origin']['country_code'];
		$payload['origin']['state']        = $payload['origin']['state_code'];
		$payload['destination']['country'] = $payload['destination']['country_code'];
		$payload['destination']['state']   = $payload['destination']['state_code'];
		unset( $payload['origin']['country_code'] );
		unset( $payload['origin']['state_code'] );
		unset( $payload['destination']['country_code'] );
		unset( $payload['destination']['state_code'] );

		return $payload;
	}

	/**
	 * Get packages with UPSDAP extra rate options.
	 * This function will return an array of packages with UPSDAP extra rate options set.
	 * These packages will be used to request UPSDAP rates.
	 *
	 * @param $payload_packages
	 *
	 * @return array
	 */
	public function get_packages_with_extra_options_for_upsdap( $payload_packages ) {
		$packages_with_extra_options = array();

		// Add extra package requests with special options set.
		foreach ( self::UPSDAP_EXTRA_RATES as $option_name => $option_value ) {
			foreach ( $payload_packages as $package ) {
				$new_package                 = $package;
				$new_package[ $option_name ] = $option_value;

				// connect-server will only run these packages against UPSDAP carrier.
				$new_package['carrier_ids']    = array( 'upsdap' );
				$new_package['id']            .= self::SPECIAL_RATE_PREFIX . $option_name;
				$packages_with_extra_options[] = $new_package;
			}
		}
		return $packages_with_extra_options;
	}

	/**
	 * Get request payload packages.
	 * The function will merge the original packages with packages with extra rate options and UPSDAP extra rate options.
	 * These packages will be used to request rates.
	 *
	 * @param $packages
	 */
	public function get_request_payload_packages( $packages ): array {
		return array_merge(
			$packages,
			$this->get_packages_with_signature_required_options( $packages ),
			$this->get_packages_with_extra_options_for_upsdap( $packages )
		);
	}
}
