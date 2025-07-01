import { Action } from 'types';
import {
	CARRIER_STRATEGY_UPSDAP_UPDATE,
	CARRIER_STRATEGY_UPSDAP_UPDATE_FAILED,
} from './action-types';

export interface CarrierStrategyUPSDAPUpdate extends Action {
	type: CARRIER_STRATEGY_UPSDAP_UPDATE;
	payload: {
		addressId: string;
		confirmed: boolean;
	};
}

export interface CarrierStrategyUPSDAPUpdateFailed<
	ET = Record< string, string >
> {
	type: CARRIER_STRATEGY_UPSDAP_UPDATE_FAILED;
	payload: ET;
}

export type CarrierStrategyActions = CarrierStrategyUPSDAPUpdate &
	CarrierStrategyUPSDAPUpdateFailed;
