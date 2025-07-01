<?php

namespace Automattic\WCShipping\Carrier;

interface CarrierStrategyServiceInterface {
	public function get_strategies(): array;
	public function update_strategies( $origin, $options = array() );
}
