<?php

namespace Automattic\WCShipping\OriginAddresses;

use Automattic\WCShipping\Connect\WC_Connect_Options;

class OriginAddressService {

	public function get_origin_addresses() {
		$addresses = array_values(
			WC_Connect_Options::get_option( 'origin_addresses', array( $this->get_store_details() ) )
		);

		// Make sure a default address is selected.
		$defaultAddress = array_filter(
			$addresses,
			function ( $address ) {
				return isset( $address['default_address'] ) && $address['default_address'];
			}
		);
		if ( empty( $defaultAddress ) ) {
			$addresses[0]['default_address'] = true;
		}

		return $addresses;
	}

	public function update_origin_addresses( $address ) {
		$origin_addresses  = $this->get_origin_addresses();
		$sanitized_address = array_map(
			function ( $value ) {
				// The mapping used in wc_clean converts boolean values to 1 or 0, so we need to check for that
				return is_bool( $value ) ? $value : wc_clean( $value );
			},
			$address
		);
		// If the new address is set as default, remove default_address from all existing addresses
		if ( isset( $address['default_address'] ) && $address['default_address'] ) {
			foreach ( $origin_addresses as &$origin_address ) {
				unset( $origin_address['default_address'] );
			}
		}

		$address_exists = array_search( $address['id'], array_column( $origin_addresses, 'id' ) );

		if ( $address_exists !== false && ! empty( $address['id'] ) ) {
			$origin_addresses[ $address_exists ] = $sanitized_address;
		} else {
			$sanitized_address['id'] = ! empty( $sanitized_address['id'] ) ? $sanitized_address['id'] : uniqid();
			$origin_addresses[]      = $sanitized_address;
		}

		WC_Connect_Options::update_option( 'origin_addresses', $origin_addresses );

		return $sanitized_address;
	}

	public function delete_origin_address( $id ) {
		// get all addresses
		$origin_addresses = $this->get_origin_addresses();

		// if there's only one address, do not delete it
		if ( count( $origin_addresses ) <= 1 ) {
			return $origin_addresses;
		}

		// if an address with the same `id` field exists, delete it...
		foreach ( $origin_addresses as $index => $origin_address ) {
			if ( strval( $origin_address['id'] ) === strval( $id ) ) {
				unset( $origin_addresses[ $index ] );
				break;
			}
		}

		// save the updated addresses
		WC_Connect_Options::update_option( 'origin_addresses', $origin_addresses );
		return $origin_addresses;
	}

	/**
	 * Sync origin address with store address.
	 * This is to ensure that the origin address is always in sync with the store address.
	 *
	 * @return void
	 */
	public function sync_origin_addresses_with_woocommerce_store_address() {
		$store_address = $this->get_store_details();
		$this->update_origin_addresses( $store_address );
	}

	/**
	 * Returns the Store's address to be included in the shipping settings script parameters
	 *
	 * @return mixed
	 */
	private function get_store_details() {
		$address   = get_option( 'woocommerce_store_address', '' );
		$address_2 = get_option( 'woocommerce_store_address_2', '' );
		$city      = get_option( 'woocommerce_store_city', '' );
		$postcode  = get_option( 'woocommerce_store_postcode', '' );

		$raw_country   = get_option( 'woocommerce_default_country', '' );
		$split_country = explode( ':', $raw_country );

		$country = isset( $split_country[0] ) ? $split_country[0] : '';
		$state   = isset( $split_country[1] ) ? $split_country[1] : '';

		$store_name = get_option( 'blogname', '' );
		$email      = get_option( 'admin_email', '' );

		$store_details = array(
			'id'          => 'store_details',
			'name'        => 'Store Address',
			'company'     => $store_name,
			'address_1'   => trim( $address . ' ' . $address_2 ),
			'address_2'   => '',
			'city'        => $city,
			'state'       => $state,
			'postcode'    => $postcode,
			'country'     => $country,
			'email'       => $email,
			'phone'       => '',
			'first_name'  => '',
			'last_name'   => '',
			'is_verified' => false,
		);

		return $store_details;
	}
}
