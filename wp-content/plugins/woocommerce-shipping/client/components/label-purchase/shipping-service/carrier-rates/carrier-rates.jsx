import { Flex } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { camelCase } from 'lodash';
import { getExtraOptionRate } from 'utils';
import { labelPurchaseStore } from 'data/label-purchase';
import { useLabelPurchaseContext } from 'context/label-purchase';
import { useCallback, useEffect } from '@wordpress/element';
import { withBoundary } from 'components/HOC';
import { RateRow } from './rate-row';
import { LABEL_RATE_TYPE } from 'data/constants';
export const CarrierRates = withBoundary( ( { rates } ) => {
	const {
		rates: {
			getSelectedRate,
			selectRate,
			preselectRateBasedOnLastSelections,
		},
		shipment: { currentShipmentId },
		essentialDetails: {
			resetFocusArea: resetEssentialDetailsFocusArea,
			setShippingServiceCompleted,
		},
	} = useLabelPurchaseContext();
	const setSelected = useCallback(
		( rate, parent ) => ( checked ) => {
			resetEssentialDetailsFocusArea();
			setShippingServiceCompleted( true );
			if ( checked ) {
				selectRate( rate, parent );
			}

			if ( ! checked && parent ) {
				selectRate( parent );
			}
		},
		[
			selectRate,
			setShippingServiceCompleted,
			resetEssentialDetailsFocusArea,
		]
	);

	useEffect( () => {
		if ( getSelectedRate() ) {
			setShippingServiceCompleted( false );
		} else {
			preselectRateBasedOnLastSelections( currentShipmentId );
		}
	}, [
		getSelectedRate,
		preselectRateBasedOnLastSelections,
		setShippingServiceCompleted,
		currentShipmentId,
	] );

	const signatureRequiredRates = useSelect( ( s ) =>
		s( labelPurchaseStore ).getRatesForShipment(
			currentShipmentId,
			LABEL_RATE_TYPE.SIGNATURE_REQUIRED
		)
	);

	const adultSignatureRequiredRates = useSelect( ( s ) =>
		s( labelPurchaseStore ).getRatesForShipment(
			currentShipmentId,
			LABEL_RATE_TYPE.ADULT_SIGNATURE_REQUIRED
		)
	);

	const carbonNeutralRates = useSelect( ( s ) =>
		s( labelPurchaseStore ).getRatesForShipment(
			currentShipmentId,
			LABEL_RATE_TYPE.CARBON_NEUTRAL
		)
	);

	const additionalHandlingRates = useSelect( ( s ) =>
		s( labelPurchaseStore ).getRatesForShipment(
			currentShipmentId,
			LABEL_RATE_TYPE.ADDITIONAL_HANDLING
		)
	);

	const saturdayDeliveryRates = useSelect( ( s ) =>
		s( labelPurchaseStore ).getRatesForShipment(
			currentShipmentId,
			LABEL_RATE_TYPE.SATURDAY_DELIVERY
		)
	);

	return (
		<Flex
			className="carrier-rates"
			justify="space-between"
			gap={ 4 }
			direction="column"
		>
			{ rates.map( ( rate ) => (
				<RateRow
					key={ rate.rateId }
					rate={ rate }
					selected={ getSelectedRate() }
					setSelected={ setSelected }
					signatureRequiredRate={ getExtraOptionRate(
						rate.serviceId,
						signatureRequiredRates?.[ rate.carrierId ] ?? [],
						rate || 0,
						camelCase( LABEL_RATE_TYPE.SIGNATURE_REQUIRED )
					) }
					adultSignatureRequiredRate={ getExtraOptionRate(
						rate.serviceId,
						adultSignatureRequiredRates?.[ rate.carrierId ] ?? [],
						rate || 0,
						camelCase( LABEL_RATE_TYPE.ADULT_SIGNATURE_REQUIRED )
					) }
					carbonNeutralRate={
						rate.carrierId === 'upsdap'
							? getExtraOptionRate(
									rate.serviceId,
									carbonNeutralRates?.[ rate.carrierId ] ??
										[],
									rate || 0,
									camelCase( LABEL_RATE_TYPE.CARBON_NEUTRAL )
							  )
							: null
					}
					additionalHandlingRate={
						rate.carrierId === 'upsdap'
							? getExtraOptionRate(
									rate.serviceId,
									additionalHandlingRates?.[
										rate.carrierId
									] ?? [],
									rate || 0,
									camelCase(
										LABEL_RATE_TYPE.ADDITIONAL_HANDLING
									)
							  )
							: null
					}
					saturdayDeliveryRate={
						rate.carrierId === 'upsdap'
							? getExtraOptionRate(
									rate.serviceId,
									saturdayDeliveryRates?.[ rate.carrierId ] ??
										[],
									rate || 0,
									camelCase(
										LABEL_RATE_TYPE.SATURDAY_DELIVERY
									)
							  )
							: null
					}
				/>
			) ) }
		</Flex>
	);
} )( 'CarrierRates' );
