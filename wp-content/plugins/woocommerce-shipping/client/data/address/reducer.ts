import {
	createReducer,
	getCurrentOrderShipTo,
	getFirstSelectableOriginAddress,
	getIsDestinationVerified,
	getOriginAddresses,
	getStoreOrigin,
	isOriginAddress,
} from 'utils';
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
	AddOriginAddressAction,
	AddOriginAddressFailedAction,
	AddressActions,
	DeleteOriginAddressAction,
	NormalizationAddressAction,
	NormalizationAddressFailedAction,
	ShippingAddressVerifyAction,
	ShippingAddressVerifyFailedAction,
	ShippingAddressVerifyStartAction,
	UpdateShipmentAddressAction,
	UpdateShipmentAddressFailedAction,
} from './types.d';
import { resetAddressNormalizationResponse } from './actions';
import { AddressState } from '../types';

export const getReducer = ( withDestination: boolean ) => {
	const defaultState: AddressState = {
		origin: {
			addresses: getOriginAddresses(),
			address: getFirstSelectableOriginAddress(),
			isVerified: getFirstSelectableOriginAddress()?.isVerified,
			isAddressVerificationInProgress: false,
			normalizedAddress: null,
			submittedAddress: null,
			isTrivialNormalization: null,
			addressNeedsConfirmation: false,
			formErrors: {},
		},
		storeOrigin: getStoreOrigin(),
	} as const;

	if ( withDestination ) {
		defaultState.destination = {
			address: getCurrentOrderShipTo(),
			isVerified: getIsDestinationVerified(),
			isAddressVerificationInProgress: false,
			normalizedAddress: null,
			submittedAddress: null,
			isTrivialNormalization: null,
			addressNeedsConfirmation: false,
			formErrors: {},
		};
	}

	const addressReducer = createReducer( defaultState )
		.on(
			ADDRESS_NORMALIZATION,
			(
				state,
				{
					payload: {
						address,
						normalizedAddress,
						isTrivialNormalization,
						addressType,
					},
				}: NormalizationAddressAction
			) => ( {
				...state,
				[ addressType ]: {
					...state[ addressType ],
					normalizedAddress,
					isTrivialNormalization,
					addressNeedsConfirmation: true,
					submittedAddress: address,
					formErrors: {},
					isAddressVerificationInProgress: false,
				},
			} )
		)
		.on(
			ADDRESS_NORMALIZATION_FAILED,
			( state, { payload }: NormalizationAddressFailedAction ) => {
				const normalizationErrors: Record< string, string > = {
					...( payload.errors ?? {} ),
				};
				if ( payload.errors?.general ) {
					normalizationErrors.general = payload.errors.general;
				}
				if ( payload.message ) {
					normalizationErrors.general = payload.message;
				}
				return {
					...state,
					[ payload.addressType ]: {
						...state[ payload.addressType ],
						addressNeedsConfirmation: false,
						normalizedAddress: null,
						submittedAddress: payload.address,
						formErrors: normalizationErrors,
						isAddressVerificationInProgress: false,
					},
				};
			}
		)
		.on(
			RESET_ADDRESS_NORMALIZATION,
			(
				state,
				{
					payload: { addressType },
				}: ReturnType< typeof resetAddressNormalizationResponse >
			) => ( {
				...state,
				[ addressType ]: {
					...state[ addressType ],
					addressNeedsConfirmation: false,
				},
			} )
		)
		.on(
			UPDATE_SHIPMENT_ADDRESS,
			(
				state,
				{
					payload: { address, isVerified, addressType },
				}: UpdateShipmentAddressAction
			) => {
				let addressesMerger = {};
				if ( addressType === 'origin' && isOriginAddress( address ) ) {
					const addresses = state.origin.addresses.map(
						( originAddress ) => {
							if ( originAddress.id === address.id ) {
								return address;
							}
							return originAddress;
						}
					);
					addressesMerger = { addresses };
				}

				return {
					...state,
					[ addressType ]: {
						...state[ addressType ],
						formErrors: {},
						isVerified,
						address,
						addressNeedsConfirmation: false,
						isAddressVerificationInProgress: false,
						...addressesMerger,
					},
				};
			}
		)
		.on(
			UPDATE_SHIPMENT_ADDRESS_FAILED,
			(
				state,
				{
					payload: { addressType, message },
				}: UpdateShipmentAddressFailedAction
			) => ( {
				...state,
				[ addressType ]: {
					...state[ addressType ],
					isVerified: false,
					addressNeedsConfirmation: false,
					formErrors: { general: message },
					isAddressVerificationInProgress: false,
				},
			} )
		)
		.on(
			ADD_ORIGIN_ADDRESS,
			( state, { payload: { address } }: AddOriginAddressAction ) => {
				const existingAddresses = address.defaultAddress
					? state.origin.addresses.map( ( origin ) => ( {
							...origin,
							defaultAddress: false,
					  } ) )
					: state.origin.addresses;
				return {
					...state,
					origin: {
						...state.origin,
						formErrors: {},
						isVerified: true,
						address,
						addressNeedsConfirmation: false,
						addresses: [ ...existingAddresses, address ],
					},
				};
			}
		)
		.on(
			ADD_ORIGIN_ADDRESS_FAILED,
			( state, { payload: { error } }: AddOriginAddressFailedAction ) => {
				return {
					...state,
					origin: {
						...state.origin,
						formErrors: error,
					},
				};
			}
		)
		.on(
			VERIFY_ORDER_SHIPPING_ADDRESS_START,
			(
				state,
				{ payload: { addressType } }: ShippingAddressVerifyStartAction
			) => {
				return {
					...state,
					[ addressType ]: {
						...state[ addressType ],
						isAddressVerificationInProgress: true,
					},
				};
			}
		)
		.on(
			VERIFY_ORDER_SHIPPING_ADDRESS,
			(
				state,
				{
					payload: {
						normalizedAddress,
						isTrivialNormalization,
						isVerified,
						addressType,
					},
				}: ShippingAddressVerifyAction
			) => ( {
				...state,
				[ addressType ]: {
					...state[ addressType ],
					isVerified,
					normalizedAddress,
					isTrivialNormalization,
					isAddressVerificationInProgress: false,
				},
			} )
		)
		.on(
			VERIFY_ORDER_SHIPPING_ADDRESS_FAILED,
			(
				state,
				{ payload: { addressType } }: ShippingAddressVerifyFailedAction
			) => {
				return {
					...state,
					[ addressType ]: {
						...state[ addressType ],
						isVerified: false,
						isAddressVerificationInProgress: false,
					},
				};
			}
		)
		.on(
			DELETE_ORIGIN_ADDRESS,
			(
				state,
				{ payload: { deletedId } }: DeleteOriginAddressAction
			) => {
				return {
					...state,
					origin: {
						...state.origin,
						addresses: state.origin.addresses.filter(
							( originAddress ) => originAddress.id !== deletedId
						),
					},
				};
			}
		)
		.bind< AddressActions >();

	return addressReducer;
};
