import { apiFetch } from '@wordpress/data-controls';
import { getCarrierStrategyPath } from '../routes';
import {
	CARRIER_STRATEGY_UPSDAP_UPDATE,
	CARRIER_STRATEGY_UPSDAP_UPDATE_FAILED,
} from './action-types';
import { UPSDAPStrategyResponse } from 'types';
import {
	CarrierStrategyUPSDAPUpdate,
	CarrierStrategyUPSDAPUpdateFailed,
} from './types.d';
import { camelCaseKeys } from 'utils';

export function* updateUPSDAPStrategy( {
	addressId,
	confirmed,
}: {
	addressId: string;
	confirmed: boolean;
} ): Generator<
	ReturnType< typeof apiFetch >,
	CarrierStrategyUPSDAPUpdate | CarrierStrategyUPSDAPUpdateFailed,
	{
		success: boolean;
		strategies: UPSDAPStrategyResponse;
	}
> {
	try {
		const { strategies } = yield apiFetch( {
			path: getCarrierStrategyPath( 'upsdap' ),
			method: 'PUT',
			data: { addressId, confirmed },
		} );

		return {
			type: CARRIER_STRATEGY_UPSDAP_UPDATE,
			payload: {
				strategies: camelCaseKeys( strategies ),
			},
		};
	} catch ( error ) {
		return {
			type: CARRIER_STRATEGY_UPSDAP_UPDATE_FAILED,
			payload: { error: ( error as Error ).message },
		};
	}
}

export function updateUPSDAPStrategySuccess( {
	addressId,
	confirmed,
}: {
	addressId: string;
	confirmed: boolean;
} ): CarrierStrategyUPSDAPUpdate {
	return {
		type: CARRIER_STRATEGY_UPSDAP_UPDATE,
		payload: {
			addressId,
			confirmed,
		},
	};
}
