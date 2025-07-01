import { register } from '@wordpress/data';
import { createStore } from './store';

let addressStore: ReturnType< typeof createStore >;
export const registerAddressStore = ( withDestination: boolean ) => {
	addressStore = addressStore || createStore( withDestination );
	register( addressStore );
};

export { addressStore };
