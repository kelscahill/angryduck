import { useCallback, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { getAccountSettings, hasSelectedPaymentMethod } from 'utils';
import { getAccountSettingsPath } from 'data/routes';
import { WCShippingConfig } from 'types';

export function useAccountState() {
	const [ accountSettings, updateAccountSettings ] = useState(
		getAccountSettings()
	);

	const refreshSettings = useCallback( async () => {
		/**
		 * Fetches directly as an exception to the rule of not using apiFetch in components.
		 * This is because we are not using the account settings store in label purchase context.
		 *
		 * Converts the data structure to match the one in WCShippingConfig[ 'accountSettings' ]
		 */
		const {
			formData: purchaseSettings,
			formMeta: purchaseMeta,
			storeOptions,
			userMeta,
		} = await apiFetch< {
			formData: WCShippingConfig[ 'accountSettings' ][ 'purchaseSettings' ];
			formMeta: WCShippingConfig[ 'accountSettings' ][ 'purchaseMeta' ];
			storeOptions: WCShippingConfig[ 'accountSettings' ][ 'storeOptions' ];
			userMeta: WCShippingConfig[ 'accountSettings' ][ 'userMeta' ];
		} >( {
			path: getAccountSettingsPath(),
			method: 'GET',
		} );

		updateAccountSettings( () => ( {
			...accountSettings,
			purchaseSettings,
			purchaseMeta,
			userMeta,
			storeOptions,
		} ) );
	}, [ updateAccountSettings, accountSettings ] );

	const setAccountCompleteOrder = ( completeOrder: boolean ) => {
		accountSettings.userMeta.last_order_completed = completeOrder;
		updateAccountSettings( accountSettings );
	};

	const getAccountCompleteOrder = () => {
		return accountSettings.userMeta?.last_order_completed || false;
	};

	const canPurchase = () => {
		return hasSelectedPaymentMethod( { accountSettings } );
	};

	return {
		refreshSettings,
		accountSettings,
		canPurchase,
		setAccountCompleteOrder,
		getAccountCompleteOrder,
	};
}
