<?php
namespace Automattic\WCShipping\LabelPurchase;

use Automattic\WCShipping\Connect\WC_Connect_API_Client;
use Automattic\WCShipping\Connect\WC_Connect_Logger;
use Automattic\WCShipping\Connect\WC_Connect_Service_Settings_Store;
use Automattic\WCShipping\LabelPurchase\LabelPrintService;
use Automattic\WCShipping\WCShippingRESTController;
use WP_Error;
use WP_REST_Server;

class LabelPrintController extends WCShippingRESTController {
	protected $rest_base = 'label/print';

	/**
	 * @var WC_Connect_Service_Settings_Store
	 */
	protected $settings_store;

	/**
	 * @var WC_Connect_API_Client
	 */
	protected $api_client;

	/**
	 * @var WC_Connect_Logger
	 */
	protected $logger;

	/**
	 * @var WC_Label_Print_Service
	 */
	protected $label_print_service;

	public function __construct( WC_Connect_Service_Settings_Store $settings_store, WC_Connect_API_Client $api_client, WC_Connect_Logger $logger, LabelPrintService $label_print_service ) {
		$this->settings_store      = $settings_store;
		$this->api_client          = $api_client;
		$this->logger              = $logger;
		$this->label_print_service = $label_print_service;
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
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'print_label' ),
					'permission_callback' => array( $this, 'ensure_rest_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/packing-list/(?P<label_id>\d+)/(?P<order_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_packing_list' ),
					'permission_callback' => array( $this, 'ensure_rest_permission' ),
					'args'                => array(
						'label_id' => array(
							'type'     => 'integer',
							'required' => true,
						),
						'order_id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);
	}

	public function print_label( $request ) {
		list( $label_id, $paper_size ) = $this->get_and_check_request_params( $request, array( 'label_id_csv', 'paper_size' ) );

		if ( ! $label_id ) {
			$message = __( 'Invalid PDF request.', 'woocommerce-shipping' );
			$error   = new WP_Error(
				'invalid_pdf_request',
				$message,
				array(
					'message' => $message,
					'status'  => 400,
				)
			);
			$this->logger->log( $error, __CLASS__ );
			return $error;
		}
		$request_params               = array();
		$request_params['paper_size'] = $paper_size;
		$request_params['labels']     = array(
			array(
				'label_id' => (int) $label_id,
			),
		);

		$raw_response = $this->api_client->get_labels_print_pdf( $request_params );
		if ( is_wp_error( $raw_response ) ) {
			$this->logger->log( $raw_response, __CLASS__ );
			return $raw_response;
		}

		return array(
			'mimeType'   => $raw_response['headers']['content-type'],
			'b64Content' => base64_encode( $raw_response['body'] ),
			'success'    => true,
		);
	}

	/**
	 * Generate packing list for a specific label.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error REST response or error.
	 */
	public function get_packing_list( \WP_REST_Request $request ) {
		try {
			list( $order_id, $label_id ) = $this->get_and_check_request_params( $request, array( 'order_id', 'label_id' ) );
		} catch ( \RESTRequestException $error ) {
			return rest_ensure_response( $error->get_error_response() );
		}

		return rest_ensure_response( $this->label_print_service->get_packing_list( $order_id, $label_id ) );
	}
}
