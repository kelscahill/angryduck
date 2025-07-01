<?php

namespace Automattic\WCShipping\Carrier;

use Automattic\WCShipping\Carrier\UPSDAP\UPSDAPCarrierStrategyService;

class CarrierStrategyService {


	/**
	 * UPS DAP Carrier Service instance.
	 *
	 * @var UPSDAPCarrierStrategyService
	 */
	protected $upsdap_carrier_service;

	public function __construct(
		UPSDAPCarrierStrategyService $upsdap_carrier_service
	) {
		$this->upsdap_carrier_service = $upsdap_carrier_service;
	}

	public function get_strategies(): array {
		return array(
			'upsdap' => $this->upsdap_carrier_service->get_strategies(),
		);
	}
}
