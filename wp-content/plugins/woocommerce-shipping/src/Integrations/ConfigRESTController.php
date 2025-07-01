<?php
namespace Automattic\WCShipping\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WCShipping\Exceptions\RESTRequestException;
use Automattic\WCShipping\LabelPurchase\View;
use Automattic\WCShipping\WCShippingRESTController;
use Exception;
use WP_Error;
use WP_REST_Server;

class ConfigRESTController extends WCShippingRESTController {

	protected $rest_base = 'config';

	/**
	 * @var View
	 */
	private $shipping_label_view;
	public function __construct( View $shipping_label_view ) {
		$this->shipping_label_view = $shipping_label_view;
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/label-purchase/(?P<order_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get' ),
					'permission_callback' => array( $this, 'ensure_rest_permission' ),
				),
			)
		);
	}

	public function get( $request ) {
		try {
			[ $order_id ] = $this->get_and_check_request_params( $request, array( 'order_id' ) );
		} catch ( RESTRequestException $error ) {
			return rest_ensure_response( $error->get_error_response() );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$message = __( 'Order not found', 'woocommerce-shipping' );
			return new WP_Error(
				'order_not_found',
				$message,
				array(
					'success' => false,
					'message' => $message,
				),
			);
		}

		try {
			$config = $this->shipping_label_view->get_meta_boxes_payload( $order, array() );
			return rest_ensure_response(
				array(
					'success' => true,
					'config'  => $config,
				),
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'error',
				$e->getMessage(),
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
			);
		}
	}
}
