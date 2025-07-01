import { createReduxStore } from '@wordpress/data';
import { controls as wpControls } from '@wordpress/data-controls';
import { carrierStrategyReducer } from './reducer';
import * as selectors from './selectors';
import * as actions from './actions';
import { CARRIER_STRATEGY_STORE_NAME } from 'data/constants';

const resolvers = {};

export const createStore = () =>
	createReduxStore( CARRIER_STRATEGY_STORE_NAME, {
		reducer: carrierStrategyReducer,
		actions,
		selectors,
		controls: wpControls,
		resolvers,
	} );
