import { mapValues } from 'lodash';
import { camelCaseKeys, createReducer, getCarrierStrategies } from 'utils';
import { CarrierStrategyActions, CarrierStrategyUPSDAPUpdate } from './types.d';

import { CARRIER_STRATEGY_UPSDAP_UPDATE } from './action-types';
import { CarrierStrategyState } from '../types';

const defaultState: CarrierStrategyState = {
	carrierStrategies: mapValues( getCarrierStrategies(), ( value ) =>
		camelCaseKeys( value )
	),
} as const;

export const carrierStrategyReducer = createReducer( defaultState )
	.on(
		CARRIER_STRATEGY_UPSDAP_UPDATE,
		(
			state,
			{ payload: { addressId, confirmed } }: CarrierStrategyUPSDAPUpdate
		) => {
			return {
				...state,
				carrierStrategies: {
					upsdap: {
						...state.carrierStrategies.upsdap,
						originAddress: {
							...state.carrierStrategies.upsdap.originAddress,
							[ addressId ]: {
								...state.carrierStrategies.upsdap[ addressId ],
								has_agreed_to_tos: confirmed,
							},
						},
					},
				},
			};
		}
	)
	.bind< CarrierStrategyActions >();
