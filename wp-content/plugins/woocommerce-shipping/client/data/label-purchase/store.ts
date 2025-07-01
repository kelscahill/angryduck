import { createReduxStore } from '@wordpress/data';
import { controls as wpControls } from '@wordpress/data-controls';
import { labelPurchaseReducer } from './reducer';
import * as selectors from './selectors';
import * as actions from './actions';
import * as carrierStrategyActions from '../carrier-strategy/actions';
import * as carrierStrategySelectors from '../carrier-strategy/selectors';
import { packagesActions, packagesSelectors } from './packages';
import { labelActions, labelSelectors } from './label';
import { LABEL_PURCHASE_STORE_NAME } from 'data/constants';

export const labelPurchaseConfig = {
	reducer: labelPurchaseReducer,
	selectors,
	actions,
};
const storeSelectors = {
	...labelPurchaseConfig.selectors,
	...packagesSelectors,
	...labelSelectors,
	...carrierStrategySelectors,
};
const storeActions = {
	...labelPurchaseConfig.actions,
	...packagesActions,
	...labelActions,
	...carrierStrategyActions,
};
const resolvers = {};

export const createStore = () =>
	createReduxStore( LABEL_PURCHASE_STORE_NAME, {
		reducer: labelPurchaseConfig.reducer,
		actions: storeActions,
		selectors: storeSelectors,
		controls: wpControls,
		resolvers,
	} );
