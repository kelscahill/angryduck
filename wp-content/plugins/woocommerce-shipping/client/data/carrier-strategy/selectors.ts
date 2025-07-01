import { camelCaseKeys } from 'utils';
import { CarrierStrategyState } from '../types';

export const getUPSDAPCarrierStrategy = ( state: CarrierStrategyState ) =>
	state.carrierStrategies.upsdap;

export const getUPSDAPCarrierStrategyForAddressId = (
	state: CarrierStrategyState,
	addressId: string
) => {
	const { originAddress } = getUPSDAPCarrierStrategy( state );
	return camelCaseKeys( originAddress[ addressId ] );
};
