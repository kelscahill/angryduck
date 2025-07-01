<?php
/**
 * Class LabelRateRESTController
 *
 * @package Automattic\WCShipping
 */

namespace Automattic\WCShipping\LabelRate;

use Automattic\WCShipping\WCShippingRESTController;
use Automattic\WCShipping\Connect\WC_Connect_Functions;
use Automattic\WCShipping\Exceptions\RESTRequestException;
use Automattic\WCShipping\Shipment\Address;
use Automattic\WCShipping\Validators;
use WC_Validation;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Class to label rate requests.
 */
class LabelRateRESTController extends WCShippingRESTController {

	/**
	 * Route
	 *
	 * @var string
	 */
	protected $rest_base = 'label/rate';

	/**
	 * Label rate service
	 *
	 * @var LabelRateService
	 */
	protected $label_rate_service;

	/**
	 * Class constructor.
	 *
	 * @param LabelRateService $label_rate_service Service that has logic for handling label rates.
	 */
	public function __construct( LabelRateService $label_rate_service ) {
		$this->label_rate_service = $label_rate_service;
	}

	/**
	 * Register API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'quote_rates' ),
					'permission_callback' => array( WC_Connect_Functions::class, 'user_can_manage_labels' ),
					'args'                => $this->get_label_rate_properties(),
				),
			)
		);
	}

	/**
	 * Define the schema for the label rate request.
	 *
	 * @return array
	 */
	private function get_label_rate_properties() {
		return array(
			'order_id'         => array(
				'required'    => true,
				'description' => __( 'Order ID for this shipping label.', 'woocommerce-shipping' ),
				'type'        => 'integer',
			),
			'origin'           => array(
				'required'          => true,
				'description'       => __( 'Ship from address', 'woocommerce-shipping' ),
				'type'              => 'object',
				'properties'        => $this->get_shipment_properties(),
				'validate_callback' => array( $this, 'validate_address' ),
				'sanitize_callback' => array( $this, 'sanitize_address' ),
			),
			'destination'      => array(
				'required'          => true,
				'description'       => __( 'Ship to address', 'woocommerce-shipping' ),
				'type'              => 'object',
				'properties'        => $this->get_shipment_properties(),
				'validate_callback' => array( $this, 'validate_address' ),
				'sanitize_callback' => array( $this, 'sanitize_address' ),
			),
			'packages'         => array(
				'required'    => true,
				'description' => __( 'The package object that describe how the shipment is packed.', 'woocommerce-shipping' ),
				'type'        => 'array',
				'items'       => array(
					'type'       => 'object',
					'required'   => true,
					'properties' => $this->get_package_properties(),
				),
			),
			'shipment_options' => array(
				'required'    => false, // Provide backward compatibility for clients ( mobile app ) not setting this field.
				'description' => __( 'Extra options for the shipment', 'woocommerce-shipping' ),
				'type'        => 'object',
				'properties'  => array(
					'label_date' => array(
						'type'        => 'string',
						'description' => __( 'ISO 8601 formatted date string for the shipping label', 'woocommerce-shipping' ),
						'format'      => 'date-time',
						'pattern'     => Validators::ISO8601_PATTERN,
					),
				),
			),
		);
	}

	/**
	 * Define the schema for the shipment object inside label rate request.
	 *
	 * @return array
	 */
	private function get_shipment_properties() {
		return array(
			'company'   => array(
				'description' => __( 'Company name.', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
			'name'      => array(
				'description' => __( 'Name of the shipper.', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'required'    => true,
			),
			'address'   => array(
				'description' => __( 'Address line', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'required'    => true,
			),
			'address_1' => array(
				'description' => __( 'Address line 1', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'required'    => true,
			),
			'address_2' => array(
				'description' => __( 'Address line 2', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
			'city'      => array(
				'description' => __( 'City name.', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
			'state'     => array(
				'description' => __( 'ISO code or name of the state, province or district.', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
			'postcode'  => array(
				'description' => __( 'Postal code.', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'required'    => true,
			),
			'country'   => array(
				'description' => __( 'ISO code of the country.', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'required'    => true,
			),
			'phone'     => array(
				'description' => __( 'Phone number.', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'required'    => true,
			),
		);
	}

	/**
	 * Define the schema for the package object inside label rate request.
	 *
	 * @return array
	 */
	private function get_package_properties() {
		return array(
			'id'                  => array(
				'description' => __( 'Package slug (ie. default_box)', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'required'    => true,
			),
			'box_id'              => array(
				'description' => __( 'Box ID (ie. small_flat_box)', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'required'    => true,
			),
			'length'              => array(
				'description'      => __( 'Length of the box. The unit is based on the store setting unit.', 'woocommerce-shipping' ),
				'type'             => 'number',
				'minimum'          => 0,
				'exclusiveMinimum' => true,
				'context'          => array( 'view', 'edit' ),
				'required'         => true,
			),
			'width'               => array(
				'description'      => __( 'Width of the box. The unit is based on the store setting unit.', 'woocommerce-shipping' ),
				'type'             => 'number',
				'minimum'          => 0,
				'exclusiveMinimum' => true,
				'context'          => array( 'view', 'edit' ),
				'required'         => true,
			),
			'height'              => array(
				'description'      => __( 'Height of the box. The unit is based on the store setting unit.', 'woocommerce-shipping' ),
				'type'             => 'number',
				'minimum'          => 0,
				'exclusiveMinimum' => true,
				'context'          => array( 'view', 'edit' ),
				'required'         => true,
			),
			'weight'              => array(
				'description'      => __( 'Weight of the box. The unit is based on the store setting unit.', 'woocommerce-shipping' ),
				'type'             => 'number',
				'minimum'          => 0,
				'exclusiveMinimum' => true,
				'context'          => array( 'view', 'edit' ),
				'required'         => true,
			),
			'is_letter'           => array(
				'description' => __( 'Is this an envelope or package?', 'woocommerce-shipping' ),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
			),
			'contents_type'       => array(
				'description' => __( 'Customs info contents type', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
			'restriction_type'    => array(
				'description' => __( 'Custom infos restriction type', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
			'non_delivery_option' => array(
				'description' => __( 'Custom infos, what to do if it can not be delivered, abandon? or return?', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
			'itn'                 => array(
				'description' => __( 'Custom infos, internal transaction number', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
			'items'               => array(
				'description' => __( 'List of products being shipped in this package', 'woocommerce-shipping' ),
				'type'        => 'array',
				'items'       => array(
					'type'       => 'object',
					'required'   => true,
					'properties' => $this->get_product_properties(),
				),
			),
		);
	}

	/**
	 * Define the schema for the product item object inside package.
	 *
	 * @return array
	 */
	private function get_product_properties() {
		return array(
			'description'      => array(
				'description' => __( 'Description of this item', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'required'    => true,
			),
			'quantity'         => array(
				'description' => __( 'Quanity of this item in the shipment', 'woocommerce-shipping' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'required'    => true,
			),
			'value'            => array(
				'description' => __( 'The total value of this item', 'woocommerce-shipping' ),
				'type'        => 'number',
				'context'     => array( 'view', 'edit' ),
			),
			'weight'           => array(
				'description' => __( 'The total weight of this item', 'woocommerce-shipping' ),
				'type'        => 'number',
				'context'     => array( 'view', 'edit' ),
			),
			'hs_tariff_number' => array(
				'description' => __( 'HS Tariff number for this item', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
			'origin_country'   => array(
				'description' => __( 'The origin country of this item', 'woocommerce-shipping' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
			'product_id'       => array(
				'description' => __( 'The product ID of this item', 'woocommerce-shipping' ),
				'type'        => 'number',
				'context'     => array( 'view', 'edit' ),
			),
		);
	}

	/**
	 * Validate the address and phone number.
	 *
	 * @param array $param Request payload.
	 *
	 * @return boolean|WP_Error
	 */
	public function validate_address( $param ) {
		$address           = new Address( $param );
		$validation_result = $address->validate();

		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		if ( ! WC_Validation::is_phone( $param['phone'] ) ) {
			return new WP_Error(
				'invalid_address',
				__( 'The provided phone number is not valid', 'woocommerce-shipping' )
			);
		}

		return true;
	}

	/**
	 * Sanitize the address.
	 *
	 * @param array $param Request payload.
	 */
	public function sanitize_address( $param ) {
		$original_param = array(
			'company' => sanitize_text_field( $param['company'] ),
			'name'    => sanitize_text_field( $param['name'] ),
			'phone'   => sanitize_text_field( $param['phone'] ),
		);

		$address = new Address( $param );

		// Overwrite original param with the sanitized values.
		$sanitized_param = array_merge( $original_param, (array) $address );

		return $sanitized_param;
	}

	/**
	 * The method that handles GET request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function quote_rates( WP_REST_Request $request ) {
		try {
			$payload = $request->get_json_params();
			if ( empty( $payload ) ) {
				throw new RESTRequestException( 'Request payload is invalid.' );
			}
		} catch ( RESTRequestException $error ) {
			return rest_ensure_response( $error->get_error_response() );
		}

		// Retrieve shipping rates.
		$response = $this->label_rate_service->get_all_rates( $payload );
		return rest_ensure_response( $response );
	}
}
