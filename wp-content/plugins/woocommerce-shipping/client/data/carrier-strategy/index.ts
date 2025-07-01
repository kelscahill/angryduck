import { register } from '@wordpress/data';
import { createStore } from './store';

let carrierStrategyStore: ReturnType< typeof createStore >;
export const registerCarrierStrategyStore = () => {
	carrierStrategyStore = carrierStrategyStore || createStore();
	register( carrierStrategyStore );
};

export { carrierStrategyStore };
