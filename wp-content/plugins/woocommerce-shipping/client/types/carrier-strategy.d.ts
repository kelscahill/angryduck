import { JSX } from '@wordpress/element';
import { OriginAddress } from './origin-address';

export interface CarrierStrategy {
	canPurchase: () => Promise< boolean >;
	render: () => JSX.Element | null;
}

export interface CarrierStrategyProps {
	deferred: ReturnType< typeof createDeferred< boolean, string > > | null;
	setDeferred: (
		deferred: ReturnType< typeof createDeferred< boolean, string > >
	) => void;
	show: boolean;
	setShow: ( show: boolean ) => void;
	shipmentOrigin: OriginAddress;
}
