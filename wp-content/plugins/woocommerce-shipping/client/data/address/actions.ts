import { apiFetch } from '@wordpress/data-controls';
import { __ } from '@wordpress/i18n';
import {
	ADD_ORIGIN_ADDRESS,
	ADD_ORIGIN_ADDRESS_FAILED,
	ADDRESS_NORMALIZATION,
	ADDRESS_NORMALIZATION_FAILED,
	DELETE_ORIGIN_ADDRESS,
	RESET_ADDRESS_NORMALIZATION,
	UPDATE_SHIPMENT_ADDRESS,
	UPDATE_SHIPMENT_ADDRESS_FAILED,
	VERIFY_ORDER_SHIPPING_ADDRESS,
	VERIFY_ORDER_SHIPPING_ADDRESS_FAILED,
	VERIFY_ORDER_SHIPPING_ADDRESS_START,
} from './action-types';
import {
	getAddressNormalizationPath,
	getDeleteOriginAddressPath,
	getUpdateDestinationPath,
	getUpdateOriginPath,
	getVerifyOrderShippingAddressPath,
} from 'data/routes';
import {
	camelCaseKeys,
	composeAddress,
	composeName,
	snakeCaseKeys,
} from 'utils';
import { ADDRESS_TYPES } from 'data/constants';
import {
	AddressTypes,
	Destination,
	LocationResponse,
	OriginAddress,
} from 'types';
import {
	AddOriginAddressAction,
	AddOriginAddressFailedAction,
	DeleteOriginAddressAction,
	NormalizationAddressAction,
	NormalizationAddressFailedAction,
	ShippingAddressVerifyAction,
	ShippingAddressVerifyFailedAction,
	ShippingAddressVerifyStartAction,
	UpdateShipmentAddressAction,
	UpdateShipmentAddressFailedAction,
} from './types.d';
import { dispatch } from '@wordpress/data';
import { carrierStrategyStore } from '../carrier-strategy';

export function* verifyOrderShippingAddress( {
	orderId,
}: {
	orderId: string;
} ): Generator<
	ReturnType< typeof apiFetch > | ShippingAddressVerifyStartAction,
	ShippingAddressVerifyAction | ShippingAddressVerifyFailedAction,
	{
		success: boolean;
		normalizedAddress: LocationResponse;
		isTrivialNormalization: boolean;
		isVerified: boolean;
	}
> {
	yield {
		type: VERIFY_ORDER_SHIPPING_ADDRESS_START,
		payload: {
			addressType: 'destination',
		},
	};
	try {
		const result = yield apiFetch( {
			path: getVerifyOrderShippingAddressPath( orderId ),
		} );

		return {
			type: VERIFY_ORDER_SHIPPING_ADDRESS,
			payload: {
				...result,
				addressType: 'destination',
			},
		};
	} catch {
		return {
			type: VERIFY_ORDER_SHIPPING_ADDRESS_FAILED,
			payload: {
				addressType: 'destination',
			},
		};
	}
}

export function* normalizeAddress(
	{
		address,
	}: {
		address: Destination | OriginAddress;
	},
	addressType: 'origin' | 'destination'
): Generator<
	ReturnType< typeof apiFetch > | ShippingAddressVerifyStartAction,
	NormalizationAddressAction | NormalizationAddressFailedAction,
	{
		success: boolean;
		errors?: Record< string, string >;
		isTrivialNormalization: boolean;
		address: LocationResponse;
		normalizedAddress: LocationResponse;
	}
> {
	yield {
		type: VERIFY_ORDER_SHIPPING_ADDRESS_START,
		payload: {
			addressType,
		},
	};
	try {
		const {
			address: submittedAddress,
			normalizedAddress,
			success,
			errors,
			...rest
		} = yield apiFetch( {
			path: getAddressNormalizationPath(),
			method: 'POST',
			data: { address: snakeCaseKeys( address ) },
		} );

		if ( success ) {
			return {
				type: ADDRESS_NORMALIZATION,
				payload: {
					...rest,
					normalizedAddress: camelCaseKeys( normalizedAddress ),
					address: camelCaseKeys( submittedAddress ),
					addressType,
				},
			};
		}
		return {
			type: ADDRESS_NORMALIZATION_FAILED,
			payload: {
				errors,
				address,
				addressType,
			},
		};
	} catch ( error ) {
		return {
			type: ADDRESS_NORMALIZATION_FAILED,
			payload: {
				errors:
					( error as Record< string, Record< string, string > > )
						?.errors ?? {},
				message: ( error as Record< string, string > )?.message ?? '',
				address,
				addressType,
			},
		};
	}
}

export function resetAddressNormalizationResponse( addressType: AddressTypes ) {
	return {
		type: RESET_ADDRESS_NORMALIZATION,
		payload: { addressType } as {
			addressType: AddressTypes;
		},
	};
}

export function* updateShipmentAddress(
	{
		orderId,
		address,
		isVerified,
	}: {
		orderId: string;
		address: Destination | OriginAddress;
		isVerified: boolean;
	},
	type: AddressTypes
): Generator<
	ReturnType< typeof apiFetch > | ShippingAddressVerifyStartAction,
	UpdateShipmentAddressAction | UpdateShipmentAddressFailedAction,
	{
		success: boolean;
		address: LocationResponse;
		isVerified: boolean;
		message?: string;
	}
> {
	yield {
		type: VERIFY_ORDER_SHIPPING_ADDRESS_START,
		payload: {
			addressType: type,
		},
	};

	try {
		const snakeCaseAddress = snakeCaseKeys<
			Destination | OriginAddress,
			LocationResponse
		>( address );
		const requestData = {
			address: {
				...snakeCaseAddress,
				address: composeAddress( snakeCaseAddress ),
				name: composeName( snakeCaseAddress ),
			},
			isVerified,
		};

		if ( type === ADDRESS_TYPES.ORIGIN ) {
			// we only attempt to save the origin address if it's verified by the merchant
			requestData.address.is_verified = true;
		}

		const {
			success,
			address: resultAddress,
			...rest
		} = yield apiFetch( {
			path:
				type === ADDRESS_TYPES.ORIGIN
					? getUpdateOriginPath()
					: getUpdateDestinationPath( orderId ),
			method: 'POST',
			data: requestData,
		} );
		if ( success ) {
			dispatch( carrierStrategyStore ).updateUPSDAPStrategySuccess( {
				addressId: resultAddress.id,
				confirmed: false,
			} );
			return {
				type: UPDATE_SHIPMENT_ADDRESS,
				payload: {
					...rest,
					address: {
						...camelCaseKeys( resultAddress ),
						name: composeName( resultAddress ),
						address: composeAddress( resultAddress ),
					},
					addressType: type,
				},
			};
		}
		return {
			type: UPDATE_SHIPMENT_ADDRESS_FAILED,
			payload: {
				message: __(
					'Failed to update shipment address',
					'woocommerce-shipping'
				),
				addressType: type,
			},
		};
	} catch ( error ) {
		return {
			type: UPDATE_SHIPMENT_ADDRESS_FAILED,
			payload: {
				message: ( error as Record< string, string > )?.message ?? '',
				addressType: type,
			},
		};
	}
}

export function* deleteOriginAddress( addressId: string ): Generator<
	ReturnType< typeof apiFetch >,
	DeleteOriginAddressAction,
	{
		success: boolean;
		addresses: LocationResponse[];
		deleted_id: string;
	}
> {
	const { deleted_id: deletedId } = yield apiFetch( {
		path: getDeleteOriginAddressPath( addressId ),
		method: 'DELETE',
	} );

	return {
		type: DELETE_ORIGIN_ADDRESS,
		payload: {
			deletedId,
		},
	};
}

export function* addOriginAddress( address: OriginAddress ): Generator<
	ReturnType< typeof apiFetch >,
	AddOriginAddressAction | AddOriginAddressFailedAction,
	{
		success: boolean;
		address: LocationResponse;
		isVerified: boolean;
		message?: string;
	}
> {
	// we only attempt to save the origin address if it's verified by the merchant, but this parameter is needed by the API
	const isVerified = true;
	try {
		const snakeCaseAddress = snakeCaseKeys<
			Destination | OriginAddress,
			LocationResponse
		>( address );
		const requestData = {
			address: {
				...snakeCaseAddress,
				address: composeAddress( snakeCaseAddress ),
				name: composeName( snakeCaseAddress ),
			},
			isVerified,
		};

		requestData.address.is_verified = isVerified;

		const {
			success,
			address: resultAddress,
			...rest
		} = yield apiFetch( {
			path: getUpdateOriginPath(),
			method: 'POST',
			data: requestData,
		} );
		if ( success ) {
			return {
				type: ADD_ORIGIN_ADDRESS,
				payload: {
					...rest,
					address: {
						...camelCaseKeys( resultAddress ),
						name: composeName( resultAddress ),
						address: composeAddress( resultAddress ),
					},
				},
			};
		}
		return {
			type: ADD_ORIGIN_ADDRESS_FAILED,
			payload: {
				error: {
					general: __(
						'Failed to add origin address',
						'woocommerce-shipping'
					),
				},
			},
		};
	} catch ( error ) {
		return {
			type: ADD_ORIGIN_ADDRESS_FAILED,
			payload: {
				error: {
					general:
						( error as Record< string, string > )?.message ?? '',
				},
			},
		};
	}
}
