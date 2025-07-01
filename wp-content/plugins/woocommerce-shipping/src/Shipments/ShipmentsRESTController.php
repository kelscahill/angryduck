<?php

namespace Automattic\WCShipping\Shipments;

use Automattic\WCShipping\Exceptions\RESTRequestException;
use Automattic\WCShipping\WCShippingRESTController;
use WP_REST_Request;
use WP_REST_Server;

class ShipmentsRESTController extends WCShippingRESTController {

	protected $rest_base = 'shipments';

	/**
	 * @var ShipmentsService $service
	 */
	private $service;
	public function __construct( ShipmentsService $service ) {
		$this->service = $service;
	}

	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<order_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'ensure_rest_permission' ),
				),
			)
		);
	}

	public function update( WP_REST_Request $request ) {
		try {
			list( $order_id, $shipments, $shipmentIdsToUpdate ) = $this->get_and_check_request_params(
				$request,
				array(
					'order_id',
					'shipments',
					'shipmentIdsToUpdate',
				)
			);
			$this->service->update_order_shipments( $order_id, $shipments, $shipmentIdsToUpdate );
		} catch ( RESTRequestException $e ) {
			return rest_ensure_response( $e->get_error_response() );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				// Casting to object ensures the root remains an object, not an array even if the the only key is `0`;
				'data'    => wp_json_encode( (object) $this->service->get_order_shipments_data( $order_id )['shipments'] ?? new \stdClass() ),
			)
		);
	}
}
