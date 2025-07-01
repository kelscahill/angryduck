import { LabelPurchaseState } from '../../types';
import type { Label } from 'types';
import { mapKeys, mapValues } from 'lodash';
import { camelCaseKeysRecursive } from 'utils';

export const getPurchasedLabel = (
	state: LabelPurchaseState,
	shipmentId: string | number
): Label | undefined => {
	const labels = state.labels?.[ shipmentId ] ?? [];
	return labels.find( ( l ) => ! l.refund );
};
export const getPurchasedLabels = ( state: LabelPurchaseState ) =>
	mapValues(
		state.labels,
		( labels ) => labels?.find( ( l ) => ! l.refund ) ?? undefined
	);

export const getSelectedRates = ( state: LabelPurchaseState ) =>
	state.selectedRates
		? camelCaseKeysRecursive(
				mapKeys(
					state.selectedRates,
					( value, key ) => key.replace( 'shipment_', '' ) ?? key
				)
		  )
		: undefined;

export const getSelectedHazmatConfig = ( state: LabelPurchaseState ) =>
	state.selectedHazmatConfig
		? mapKeys(
				state.selectedHazmatConfig,
				( value, key ) => key.replace( 'shipment_', '' ) ?? key
		  )
		: undefined;

export const getPurchaseAPIError = (
	state: LabelPurchaseState,
	shipmentId: string | number
) => state.purchaseAPIErrors?.[ shipmentId ];

export const getRefundedLabel = (
	state: LabelPurchaseState,
	shipmentId: string | number
): Label | undefined => {
	const labels = state.labels?.[ shipmentId ] ?? [];
	return labels.find( ( l ) => l.refund );
};

export const getLabelOrigins = (
	state: LabelPurchaseState,
	shipmentId: string
) => state.selectedOrigins?.[ shipmentId ];

export const getLabelDestinations = (
	state: LabelPurchaseState,
	shipmentId: string
) => state.selectedDestinations?.[ shipmentId ];
