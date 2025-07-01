import React from 'react';
import { __experimentalSpacer as Spacer, Flex } from '@wordpress/components';
import { OriginAddress } from 'types';
import { select } from '@wordpress/data';
import { LabelsSettingsComponent } from './labels-settings';
import { OriginAddressList } from './origin-address/list';
import { LiveRatesSettings } from './live-rates-settings';
import { ShippingSettingsContextProvider } from 'context/shipping-settings';
import { useOriginAddressState } from './hooks';
import { addressStore } from 'data/address';
import { settingsStore } from 'data/settings';

interface ShippingSettingsProps {
	storeContactInfo: Record< string, string | number >;
	originAddresses: OriginAddress[];
}

const ShippingSettings = ( {}: ShippingSettingsProps ) => {
	const addresses = select( addressStore ).getOriginAddresses();
	const enabledServices = select( settingsStore ).getEnabledServices();

	return (
		<ShippingSettingsContextProvider
			initialValue={ {
				originAddresses: {
					addresses,
					...useOriginAddressState(),
				},
			} }
		>
			<Flex direction="column" gap="2rem">
				<Spacer marginTop={ 4 } marginBottom={ 0 } />
				<LabelsSettingsComponent />
				{ enabledServices.length > 0 && <LiveRatesSettings /> }
				<OriginAddressList />
			</Flex>
		</ShippingSettingsContextProvider>
	);
};

export default ShippingSettings;
