import React from 'react';
import { Label } from 'types';
import { Icon, shipping } from '@wordpress/icons';
import { LABEL_PURCHASE_STATUS } from 'data/constants';
import { ShipmentTrackingLink } from '../link/link';

interface ShipmentTrackingEventProps {
	label: Label;
}

export const ShipmentTrackingEvent = ( {
	label,
}: ShipmentTrackingEventProps ) => {
	const showIcon =
		label.status === LABEL_PURCHASE_STATUS.PURCHASED ? true : false;

	return (
		<div className="shipment-tracking__event">
			<div className="shipment-tracking__event__meta">
				{ showIcon && (
					<div className="shipment-tracking__event__meta__icon">
						<Icon icon={ shipping } size={ 30 } />
					</div>
				) }
			</div>
			<div className="shipment-tracking__event__body">
				<ShipmentTrackingLink label={ label } />
				<small>{ `(  ${ label.serviceName }  )` }</small>
			</div>
		</div>
	);
};
