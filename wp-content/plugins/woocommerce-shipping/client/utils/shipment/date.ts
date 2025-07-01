import { isInTheFuture } from '@wordpress/date';
import { mapValues, mapKeys, isObject } from 'lodash';
import { camelCaseKeys } from '../common';
import { getConfig, getAccountSettings } from '../config';
import { Label } from 'types';
import { getDateTS, isDateValid } from 'utils';

export const getShipmentDates = () => {
	const shipmentDates =
		getConfig().shippingLabelData.storedData.shipment_dates;

	if ( isObject( shipmentDates ) ) {
		return mapKeys( mapValues( shipmentDates, camelCaseKeys ), ( _, key ) =>
			key.replace( 'shipment_', '' )
		);
	}
	return {};
};

/**
 * Retrieves or calculates the default dates for a shipment.
 *
 * This function determines the shipping date and estimated delivery date for a shipment based on:
 * 1. Previously stored dates for this specific shipment
 * 2. The creation date of an active purchased label
 * 3. User's last used shipping date from account settings (if enabled)
 * 4. Today's date as a fallback
 *
 * The function prioritizes existing data over calculated values to maintain consistency.
 * So if the shipment has a stored shipping date, it will be used.
 * If the shipment does not have a stored shipping date, the label's created date will be used.
 * If the user has enabled the "Remember last used shipping date" option in the account settings,
 * the last used shipping date will be used.
 * Otherwise, today's date will be used.
 *
 * @param shipmentId           - The ID of the shipment
 * @param activePurchasedLabel - The active purchased label
 * @return An object containing the shipping date and optional estimated delivery date
 */
export const getShipmentDefaultDates = (
	shipmentId: string,
	activePurchasedLabel?: Label
): { shippingDate: Date; estimatedDeliveryDate?: Date } => {
	const shipmentDates = getShipmentDates();
	const accountSettings = getAccountSettings();
	const shippingDate = shipmentDates[ shipmentId ]?.shippingDate;
	const estimatedDeliveryDate =
		shipmentDates[ shipmentId ]?.estimatedDeliveryDate;

	// As we were not persisting the shipping date before, we use the label's created date which is used as label_date by our provider
	const labelCreatedDate = activePurchasedLabel?.createdDate
		? new Date( activePurchasedLabel.createdDate ).toISOString()
		: undefined;

	const initialShippingDate = () => {
		// Only calculate lastShippingDate if shippingDate or labelCreatedDate are not provided
		if ( shippingDate || labelCreatedDate ) {
			return getDateTS( shippingDate ?? labelCreatedDate );
		}

		const today = getDateTS( null, true );

		if ( ! isInTheFuture( accountSettings.userMeta.last_shipping_date ) ) {
			return today;
		}

		// Get the last shipping date from the account settings or use null to yield today
		const selectedShippingDate = getDateTS(
			accountSettings.purchaseSettings.remember_last_used_shipping_date &&
				isDateValid( accountSettings.userMeta.last_shipping_date )
				? accountSettings.userMeta.last_shipping_date
				: null
		);

		return selectedShippingDate;
	};

	return {
		shippingDate: initialShippingDate(),
		// Not setting estimatedDeliveryDate if it's not defined
		estimatedDeliveryDate: estimatedDeliveryDate
			? getDateTS( estimatedDeliveryDate )
			: undefined,
	};
};
