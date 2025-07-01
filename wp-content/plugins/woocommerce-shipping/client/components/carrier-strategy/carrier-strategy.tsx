import { useState } from '@wordpress/element';
import { createDeferred } from 'utils';
import { Carrier } from 'types';
import { UPSDAPStrategy } from './upsdap-strategy';
import { useLabelPurchaseContext } from 'context/label-purchase';

const defaultStrategy = () => ( {
	canPurchase: () => Promise.resolve( true ),
	render: () => null,
} );

const strategyMap = {
	ups: defaultStrategy,
	upsdap: UPSDAPStrategy,
	usps: defaultStrategy,
	fedex: defaultStrategy,
	dhlexpress: defaultStrategy,
} as const;

export const CarrierStrategyFactory = (
	carrierName?: Carrier // It's undefined when no rate is selected
) => {
	const strategy = carrierName ? strategyMap[ carrierName ] : null;
	const [ deferred, setDeferred ] = useState< ReturnType<
		typeof createDeferred< boolean, string >
	> | null >( null );

	const [ show, setShow ] = useState( false );

	const {
		shipment: { getShipmentOrigin },
	} = useLabelPurchaseContext();

	if ( ! strategy ) {
		return defaultStrategy();
	}

	return strategy( {
		deferred,
		setDeferred,
		show,
		setShow,
		shipmentOrigin: getShipmentOrigin(),
	} );
};
