<?php

namespace Automattic\WCShipping\Carrier\UPSDAP;

use Automattic\WCShipping\Exceptions\RESTRequestException;
use Automattic\WCShipping\WCShippingRESTController;
use WP_REST_Server;

class UPSDAPCarrierStrategyRESTController extends WCShippingRESTController {

	protected $rest_base = 'carrier-strategy/upsdap';

	public function __construct( UPSDAPCarrierStrategyService $upsdap_carrier_service ) {
		$this->upsdap_carrier_service = $upsdap_carrier_service;
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'ensure_rest_permission' ),
				),
			)
		);
	}

	public function update( $request ) {
		try {
			[
				$origin,
				$confirmed,
			] = $this->get_and_check_request_params( $request, array( 'origin', 'confirmed' ) );
		} catch ( RESTRequestException $error ) {
			return rest_ensure_response( $error->get_error_response() );
		}

		$response = $this->upsdap_carrier_service->update_strategies( $origin, array( 'tos' => $confirmed ) );

		if ( is_wp_error( $response ) ) {
			return new \WP_REST_Response( array( 'success' => false ), 500 );
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'confirmed' => (bool) $confirmed,
			)
		);
	}
}
