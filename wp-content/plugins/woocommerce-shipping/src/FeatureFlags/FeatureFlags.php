<?php

namespace Automattic\WCShipping\FeatureFlags;

class FeatureFlags {

	/**
	 * Features supported by the store.
	 *
	 * Please do not use this constant directly - instead, use the
	 * `wcshipping_features_supported_by_store` filter.
	 *
	 * @var string[]
	 */
	const FEATURES_SUPPORTED_BY_STORE = array( 'upsdap' );

	public function register_hooks() {
		add_filter( 'wcshipping_api_client_body', array( $this, 'decorate_api_request_body_with_feature_flags' ) );
		add_filter( 'wcshipping_features_supported_by_store', array( $this, 'get_features_supported_by_store' ) );
	}

	public function decorate_api_request_body_with_feature_flags( array $body ): array {
		$body['settings']['features_supported_by_store'] = apply_filters( 'wcshipping_features_supported_by_store', array() );

		// Pass `features_supported_by_client` as part of `settings`.
		if ( isset( $body['features_supported_by_client'] ) ) {
			$body['settings']['features_supported_by_client'] = $body['features_supported_by_client'];
			unset( $body['features_supported_by_client'] );
		}

		return $body;
	}

	/**
	 * Get features supported by the store.
	 *
	 * @return string[]
	 */
	public function get_features_supported_by_store(): array {
		return self::FEATURES_SUPPORTED_BY_STORE;
	}
}
