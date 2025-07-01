import { createStore } from './store';
import { register } from '@wordpress/data';

let settingsStore: ReturnType< typeof createStore >;
export const registerSettingsStore = () => {
	settingsStore = settingsStore || createStore();
	register( settingsStore );
};

export { settingsStore };
