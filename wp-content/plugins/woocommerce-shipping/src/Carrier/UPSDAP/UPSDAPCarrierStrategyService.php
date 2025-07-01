<?php

namespace Automattic\WCShipping\Carrier\UPSDAP;

use Automattic\WCShipping\Carrier\CarrierStrategyServiceInterface;
use Automattic\WCShipping\Connect\WC_Connect_API_Client;
use Automattic\WCShipping\Connect\WC_Connect_Options;
use Automattic\WCShipping\OriginAddresses\OriginAddressService;

class UPSDAPCarrierStrategyService implements CarrierStrategyServiceInterface {
	const UPSDAP_STRATEGIES_KEY = 'upsdap_strategies';

	/**
	 * Origin address service.
	 *
	 * @var OriginAddressService
	 */
	private $origin_address_service;

	/**
	 * API client.
	 *
	 * @var WC_Connect_API_Client
	 */
	private $api_client;

	public function __construct(
		OriginAddressService $origin_address_service,
		WC_Connect_API_Client $api_client
	) {
		$this->origin_address_service = $origin_address_service;
		$this->api_client             = $api_client;
	}

	/**
	 * Updates carrier strategies for the given origin.
	 *
	 * @param array $origin  Origin address data.
	 * @param array $options Additional options for the update.
	 * @return mixed|\WP_Error A success indicator.
	 */
	public function update_strategies( $origin, $options = array() ) {
		$current_user = wp_get_current_user();

		$data = array(
			'origin' => array_merge(
				$origin,
				array( 'email' => $current_user->user_email )
			),
		);

		if ( isset( $options['tos'] ) ) {
			$data['carriers'] = array(
				'upsdap' => array(
					'tos' => $options['tos'],
				),
			);
		}

		return $this->api_client->send_tos_acceptance_for_origin_address( $data );
	}

	public function get_strategies(): array {
		$upsdap_strategies = WC_Connect_Options::get_option( self::UPSDAP_STRATEGIES_KEY, array() );
		if ( empty( $upsdap_strategies ) ) {
			$origin_addresses  = $this->origin_address_service->get_origin_addresses();
			$upsdap_strategies = array(
				'origin_address' => array(),
			);
			foreach ( $origin_addresses as $address ) {
				$upsdap_strategies['origin_address'][ $address['id'] ] = array(
					'has_agreed_to_tos' => false,
				);
			}

			$this->update_strategies( $upsdap_strategies );
		}

		return $upsdap_strategies;
	}

	public function revoke_tos_for_address( string $address_id ): void {
		$strategies = $this->get_strategies();
		if ( empty( $strategies['origin_address'][ $address_id ] ) ) {
			return;
		}
		$strategies['origin_address'][ $address_id ]['has_agreed_to_tos'] = false;
		$this->update_strategies( $strategies );
	}
}
